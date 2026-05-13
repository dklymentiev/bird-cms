<?php

declare(strict_types=1);

/**
 * Bird CMS install wizard entry.
 *
 * Bootstrap-free by design: it runs while .env, config/app.php, and the
 * autoloader still don't exist. Loads the four App\Install classes by hand
 * and dispatches to Wizard::handle() based on the URL.
 *
 * public/index.php and public/admin/index.php redirect here while
 * storage/installed.lock is missing; once the wizard writes the lock,
 * this entry refuses every subsequent request.
 */

$siteRoot = dirname(__DIR__);
$installLock = $siteRoot . '/storage/installed.lock';

// Defense in depth: never re-run the wizard once installed. The /install/success
// view is the one exception — it's the very first thing rendered AFTER we write
// the lock, so blocking it here would leave the user looking at /admin without
// any confirmation that the wizard finished.
$lockSkipPaths = ['/install/success'];
$activeUri = parse_url($_SERVER['REQUEST_URI'] ?? '/install', PHP_URL_PATH) ?: '/install';
if (file_exists($installLock) && !in_array($activeUri, $lockSkipPaths, true)) {
    header('Location: /admin', true, 302);
    return;
}

// Minimal autoload for the App\Install namespace.
foreach ([
    '/app/Install/SystemCheck.php',
    '/app/Install/ConfigWriter.php',
    '/app/Install/Seeder.php',
    '/app/Install/Wizard.php',
] as $path) {
    require_once $siteRoot . $path;
}

use App\Install\Wizard;
use App\Install\ConfigWriter;
use App\Install\Seeder;

// Security headers (mirror what bootstrap.php sets after install).
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');

// Resolve the action from the URL. nginx routes everything that isn't a
// real file under /install through public/index.php, which then includes
// this script. The CSS asset lives at public/install/assets/install.css
// and is served directly by nginx (so it never reaches PHP).
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/install', PHP_URL_PATH) ?: '/install';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$action = match (true) {
    $uri === '/install' || $uri === '/install/'                                => 'check',
    $uri === '/install/identity' && $method === 'GET'                          => 'identity',
    $uri === '/install/identity' && $method === 'POST'                         => 'identity_post',
    $uri === '/install/finish'   && $method === 'GET'                          => 'finish',
    $uri === '/install/finish'   && $method === 'POST'                         => 'finish_post',
    $uri === '/install/success'                                                => 'success',
    default                                                                    => null,
};

if ($action === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Wizard route not found: " . $uri . "\nStart at /install\n";
    return;
}

$request = $method === 'POST' ? $_POST : $_GET;

try {
    $wizard = new Wizard(
        $siteRoot,
        new ConfigWriter($siteRoot),
        new Seeder($siteRoot, $siteRoot . '/examples/seed')
    );
    $wizard->handle($action, $request);
} catch (\Throwable $e) {
    error_log('[install] uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Install error</title>'
       . '<body style="background:#0a2520;color:#f8f6f3;font:16px system-ui;padding:40px;">'
       . '<h1>Install error</h1>'
       . '<p>The wizard hit an unexpected error. Check PHP error_log for details.</p>'
       . '<pre style="background:#0d3d36;padding:14px;border-radius:6px;overflow:auto;">'
       . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
       . '</pre>';
}
