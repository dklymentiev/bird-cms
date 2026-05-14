<?php

declare(strict_types=1);

/**
 * Bird CMS Doctor — site integrity validator.
 *
 * Walks a site directory and reports whether the install is structurally
 * consistent. Designed to be run before destructive operations (mv, rm,
 * mass content migration) and nightly via cron to catch silent drift.
 *
 * Usage:
 *   php scripts/doctor.php [--quick|--deep] [--json] [<site-path>]
 *
 * Modes (all are read-only; nothing is modified):
 *   --quick   skeleton + .env + config parse                     ~1s
 *   default   quick + theme + frontmatter parse + categories     ~10s
 *   --deep    default + HTTP smoke on site_url + /admin/         ~60s
 *
 * Auto-discovers <site-path> when invoked from inside a site root or
 * via the site's engine symlink (versions/X.Y.Z/scripts/doctor.php).
 *
 * Exit codes:
 *   0   everything passed
 *   1   warnings only (site is up but should be fixed)
 *   2   critical errors (site broken — do NOT push changes)
 */

/**
 * Schema version of the checks this doctor performs. Bump when adding,
 * removing, or changing semantics of a check. Sites running an older
 * engine will run an older doctor — read the version from the report
 * to know which spec was applied.
 */
const DOCTOR_VERSION = '1.0';

$mode = 'medium';
$jsonOut = false;
$siteArg = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--quick') { $mode = 'quick'; continue; }
    if ($arg === '--deep')  { $mode = 'deep';  continue; }
    if ($arg === '--json')  { $jsonOut = true; continue; }
    if ($arg === '-h' || $arg === '--help') {
        echo "Usage: doctor.php [--quick|--deep] [--json] [<site-path>]\n";
        exit(0);
    }
    if (str_starts_with($arg, '-')) { fwrite(STDERR, "unknown flag: $arg\n"); exit(2); }
    $siteArg = rtrim($arg, '/');
}

// ---------- Auto-discover site root --------------------------------------
$site = $siteArg;
if ($site === null) {
    // Look in cwd first
    $cwd = getcwd();
    if ($cwd !== false && is_file($cwd . '/config/app.php')) {
        $site = $cwd;
    } else {
        // Walk up from the script: scripts/doctor.php lives at
        // engine/scripts/, and engine -> versions/X.Y.Z, so site root
        // is three levels up.
        $candidate = realpath(__DIR__ . '/../../..');
        if ($candidate !== false && is_file($candidate . '/config/app.php')) {
            $site = $candidate;
        }
    }
}
if ($site === null || !is_dir($site)) {
    fwrite(STDERR, "doctor: cannot resolve site root. Pass it explicitly:\n");
    fwrite(STDERR, "  php doctor.php /server/sites/<site>\n");
    exit(2);
}

// ---------- Report accumulator -------------------------------------------
$results = [];
$passed  = 0;
$warned  = 0;
$failed  = 0;

function ok(string $section, string $name): void {
    global $results, $passed, $jsonOut;
    $results[] = ['section' => $section, 'status' => 'ok', 'name' => $name];
    $passed++;
    if (!$jsonOut) echo "  [OK]   $name\n";
}
function warn(string $section, string $name, string $detail = ''): void {
    global $results, $warned, $jsonOut;
    $results[] = ['section' => $section, 'status' => 'warn', 'name' => $name, 'detail' => $detail];
    $warned++;
    if (!$jsonOut) echo "  [WARN] $name" . ($detail ? " — $detail" : '') . "\n";
}
function fail(string $section, string $name, string $detail = ''): void {
    global $results, $failed, $jsonOut;
    $results[] = ['section' => $section, 'status' => 'fail', 'name' => $name, 'detail' => $detail];
    $failed++;
    if (!$jsonOut) echo "  [FAIL] $name" . ($detail ? " — $detail" : '') . "\n";
}
function check(bool $cond, string $section, string $name, string $detail = ''): void {
    if ($cond) ok($section, $name); else fail($section, $name, $detail);
}

// Probe engine symlink early so the header can show the engine version.
$engineTarget = is_link($site . '/engine') ? realpath($site . '/engine') : false;

$engineVersion = 'unknown';
$verFile = ($engineTarget !== false ? $engineTarget : $site . '/engine') . '/VERSION';
if (is_file($verFile)) {
    $engineVersion = trim((string) file_get_contents($verFile)) ?: 'unknown';
}


/**
 * Minimal .env loader — populates $_ENV and getenv() so config/app.php's
 * `$env('KEY')` lookups resolve correctly. Handles `KEY=value`,
 * `KEY="quoted value"`, `KEY='single quoted'`, and skips comments/blank lines.
 * Does NOT support variable interpolation or multi-line values.
 */
function loadDotenv(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $k)) continue;
        if ((str_starts_with($v, '"') && str_ends_with($v, '"'))
            || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        $_ENV[$k] = $v;
        putenv("$k=$v");
    }
}

if (!$jsonOut) {
    echo "Bird CMS Doctor v" . DOCTOR_VERSION . " — mode=$mode\n";
    echo "Site:           $site\n";
    echo "Engine version: $engineVersion\n\n";
}

// ====================== Section 1: Skeleton ==============================
$sec = '1. skeleton';
if (!$jsonOut) echo "[1] Skeleton\n";
check(is_dir($site . '/config'),         $sec, 'config/ dir');
check(is_dir($site . '/public'),         $sec, 'public/ dir');
check(is_dir($site . '/content'),        $sec, 'content/ dir');
check(is_dir($site . '/storage'),        $sec, 'storage/ dir');
check(is_file($site . '/.env'),          $sec, '.env file');
check(is_link($site . '/engine'),        $sec, 'engine symlink');
// $engineTarget already probed above
check($engineTarget !== false,           $sec, 'engine symlink resolves',
    $engineTarget === false ? readlink($site . '/engine') . ' missing' : '');
// Entrypoints: symlink-to-engine is the modern install pattern; a real
// copy is drift (won't auto-update with engine bumps) but not breakage.
foreach (['index.php' => true, 'admin' => false, 'api' => false] as $entry => $isFile) {
    $path = $site . '/public/' . $entry;
    if (is_link($path)) {
        ok($sec, "public/$entry symlink");
    } elseif (($isFile && is_file($path)) || (!$isFile && is_dir($path))) {
        warn($sec, "public/$entry is a real " . ($isFile ? 'file' : 'dir'),
            'drift — engine updates do NOT propagate; relink to ../engine/public/' . $entry);
    } else {
        fail($sec, "public/$entry missing");
    }
}
if (!$jsonOut) echo "\n";

// ====================== Section 2: Config parse ==========================
$sec = '2. config';
if (!$jsonOut) echo "[2] Config parse\n";
$appPhp = $site . '/config/app.php';
$config = null;

if (!is_file($appPhp)) {
    fail($sec, 'config/app.php exists', $appPhp . ' missing');
} else {
    // Define the constants config/app.php expects from bootstrap.
    if (!defined('SITE_ROOT'))         define('SITE_ROOT', $site);
    if (!defined('SITE_CONTENT_PATH')) define('SITE_CONTENT_PATH', $site . '/content');
    if (!defined('SITE_STORAGE_PATH')) define('SITE_STORAGE_PATH', $site . '/storage');
    if ($engineTarget && !defined('ENGINE_ROOT'))         define('ENGINE_ROOT', $engineTarget);
    if ($engineTarget && !defined('ENGINE_THEMES_PATH'))  define('ENGINE_THEMES_PATH', $engineTarget . '/themes');

    // Load site .env so $env(...) lookups in config/app.php resolve.
    loadDotenv($site . '/.env');

    try {
        $config = include $appPhp;
        check(is_array($config), $sec, 'config/app.php returns array');
        check(isset($config['site_name']),    $sec, 'config.site_name set');
        check(isset($config['site_url']),     $sec, 'config.site_url set');
        check(isset($config['active_theme']), $sec, 'config.active_theme set');
        check(isset($config['themes_path']),  $sec, 'config.themes_path set');
    } catch (Throwable $e) {
        fail($sec, 'config/app.php parses', $e->getMessage());
    }
}
if (!$jsonOut) echo "\n";

if ($mode === 'quick') {
    require __DIR__ . '/doctor-summary.inc.php';
    exit($failed > 0 ? 2 : ($warned > 0 ? 1 : 0));
}

// ====================== Section 3: Theme =================================
$sec = '3. theme';
if (!$jsonOut) echo "[3] Theme\n";
if (is_array($config) && isset($config['active_theme'], $config['themes_path'])) {
    $themeDir = rtrim($config['themes_path'], '/') . '/' . $config['active_theme'];
    check(is_dir($themeDir), $sec, "active theme '{$config['active_theme']}' exists",
        is_dir($themeDir) ? '' : "expected $themeDir");
    check(is_file($themeDir . '/layouts/base.php'), $sec, 'theme layouts/base.php');
    check(is_dir($themeDir . '/views'),             $sec, 'theme views/ dir');
} else {
    warn($sec, 'theme check skipped', 'config not parsed');
}
if (!$jsonOut) echo "\n";

// ====================== Section 4: Frontmatter ===========================
$sec = '4. frontmatter';
if (!$jsonOut) echo "[4] Frontmatter parse on every .md and .meta.yaml\n";
$fmPath = ($engineTarget ?: $site . '/engine') . '/app/Content/FrontMatter.php';
if (!is_file($fmPath)) {
    fail($sec, 'FrontMatter.php available in engine', $fmPath . ' missing');
} else {
    require_once $fmPath;
    $mdFiles = [];
    $yamlFiles = [];
    if (is_dir($site . '/content')) {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $site . '/content',
            FilesystemIterator::SKIP_DOTS
        ));
        foreach ($rii as $f) {
            if (!$f->isFile()) continue;
            $ext = $f->getExtension();
            if ($ext === 'md')   $mdFiles[]   = $f->getPathname();
            if ($ext === 'yaml') $yamlFiles[] = $f->getPathname();
        }
    }
    $okCount = 0;
    $failures = [];
    foreach ($mdFiles as $p) {
        try {
            \App\Content\FrontMatter::parseWithBody((string) file_get_contents($p));
            $okCount++;
        } catch (Throwable $e) {
            $failures[] = str_replace($site . '/', '', $p) . ': ' . $e->getMessage();
        }
    }
    foreach ($yamlFiles as $p) {
        try {
            \App\Content\FrontMatter::parse((string) file_get_contents($p));
            $okCount++;
        } catch (Throwable $e) {
            $failures[] = str_replace($site . '/', '', $p) . ': ' . $e->getMessage();
        }
    }
    $total = count($mdFiles) + count($yamlFiles);
    if (count($failures) === 0) {
        ok($sec, "$total content files parse cleanly");
    } else {
        $sample = implode(' | ', array_slice($failures, 0, 3));
        $extra  = count($failures) > 3 ? ' (+' . (count($failures) - 3) . ' more)' : '';
        fail($sec, "$okCount/$total parse cleanly", "failures: $sample$extra");
    }
}
if (!$jsonOut) echo "\n";

// ====================== Section 5: Categories ============================
$sec = '5. categories';
if (!$jsonOut) echo "[5] Categories consistency\n";
$catFile = $site . '/config/categories.php';
if (!is_file($catFile)) {
    warn($sec, 'config/categories.php missing', 'OK for landing-only sites');
} else {
    $categories = include $catFile;
    $articleDirs = [];
    if (is_dir($site . '/content/articles')) {
        foreach (scandir($site . '/content/articles') as $d) {
            if ($d[0] !== '.' && is_dir($site . '/content/articles/' . $d)) {
                $articleDirs[] = $d;
            }
        }
    }
    if (!is_array($categories)) {
        fail($sec, 'config/categories.php returns array');
    } else {
        ok($sec, count($categories) . ' categories registered, ' . count($articleDirs) . ' dirs in content/articles/');
        foreach (array_keys($categories) as $slug) {
            if (!in_array($slug, $articleDirs, true)) {
                warn($sec, "registered category has no dir", "category '$slug' missing content/articles/$slug/");
            }
        }
        foreach ($articleDirs as $d) {
            if (!isset($categories[$d])) {
                warn($sec, 'orphan article dir', "content/articles/$d/ not in config");
            }
        }
    }
}
if (!$jsonOut) echo "\n";

if ($mode === 'medium') {
    require __DIR__ . '/doctor-summary.inc.php';
    exit($failed > 0 ? 2 : ($warned > 0 ? 1 : 0));
}

// ====================== Section 6: HTTP smoke (deep) =====================
$sec = '6. http-smoke';
if (!$jsonOut) echo "[6] HTTP smoke (deep)\n";
if (is_array($config) && isset($config['site_url'])) {
    $base = rtrim($config['site_url'], '/');
    foreach (['/', '/admin/'] as $path) {
        $h = curl_init($base . $path);
        curl_setopt_array($h, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'bird-cms-doctor/1.0',
            CURLOPT_NOBODY         => true,
        ]);
        curl_exec($h);
        $code = (int) curl_getinfo($h, CURLINFO_HTTP_CODE);
        curl_close($h);

        // 2xx, 3xx ok. /admin/ may return 404 by design when caller IP is
        // not whitelisted — treat as warn, not fail.
        if ($code >= 200 && $code < 400) {
            ok($sec, "GET $base$path → $code");
        } elseif ($path === '/admin/' && $code === 404) {
            warn($sec, "GET $base$path → 404", 'expected when caller IP not in ADMIN_ALLOWED_IPS');
        } elseif ($code === 0) {
            fail($sec, "GET $base$path", 'no response (DNS/TLS/network)');
        } else {
            fail($sec, "GET $base$path → $code");
        }
    }
} else {
    warn($sec, 'http smoke skipped', 'site_url not set');
}
if (!$jsonOut) echo "\n";

require __DIR__ . '/doctor-summary.inc.php';
exit($failed > 0 ? 2 : ($warned > 0 ? 1 : 0));
