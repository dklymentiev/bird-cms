<?php

declare(strict_types=1);

namespace App\Admin;

use App\Support\HtmlCache;

final class SettingsController extends Controller
{
    /**
     * Whitelist of fields the General tab is allowed to write to
     * config/app.php. Everything else (APP_KEY, ADMIN_PASSWORD_HASH,
     * ADMIN_ALLOWED_IPS, path overrides) stays in .env and is rejected
     * by saveGeneral() even if it sneaks past the form.
     *
     * Order matters for the rewriter -- it walks this list when emitting
     * the file.
     *
     * Public so the REST API's SiteConfigController can reuse the
     * same whitelist without duplicating it -- a single source of
     * truth across the admin UI and the public API keeps the
     * "never expose APP_KEY / admin_password_hash" invariant in one
     * place.
     *
     * @var list<string>
     */
    public const GENERAL_ALLOWED_FIELDS = [
        'site_name',
        'site_description',
        'site_url',
        'active_theme',
        'timezone',
        'language',
    ];

    public function index(): void
    {
        $this->requireAuth();

        $tab = (string)($this->get('tab') ?? 'general');
        if (!in_array($tab, ['general', 'site', 'appearance', 'security', 'email', 'about'], true)) {
            $tab = 'general';
        }

        $this->render('settings/index', [
            'tab'        => $tab,
            'general'    => $this->generalSettings(),
            'site'       => $this->siteSettings(),
            'appearance' => $this->appearanceSettings(),
            'security'   => $this->securitySettings(),
            'email'      => $this->emailSettings(),
            'about'      => $this->aboutSettings(),
            'csrf'       => $this->generateCsrf(),
            'flash'      => $this->getFlash(),
        ]);
    }

    /**
     * Render the Settings page on the General tab. Convenience entry point
     * so /admin/settings/general renders the right tab without the operator
     * having to remember the ?tab=general query string.
     */
    public function general(): void
    {
        $this->requireAuth();

        $this->render('settings/index', [
            'tab'        => 'general',
            'general'    => $this->generalSettings(),
            'site'       => $this->siteSettings(),
            'appearance' => $this->appearanceSettings(),
            'security'   => $this->securitySettings(),
            'email'      => $this->emailSettings(),
            'about'      => $this->aboutSettings(),
            'csrf'       => $this->generateCsrf(),
            'flash'      => $this->getFlash(),
        ]);
    }

    /**
     * Persist General-tab form submission to config/app.php.
     *
     * Whitelist-driven: only the keys in GENERAL_ALLOWED_FIELDS are read
     * from the POST body. APP_KEY, admin credentials, IP allowlists, and
     * path overrides are NEVER accepted here -- those live in .env and
     * editing them through a web form is the cause of self-lockout and
     * leaked-HMAC incidents we explicitly designed against.
     *
     * Atomic temp+rename write; validation up-front, so a bad timezone
     * or a path-traversal theme name never reaches the rewriter.
     */
    public function saveGeneral(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/settings?tab=general');
            return;
        }

        try {
            $values = $this->collectGeneralPost();
            $this->validateGeneralFields($values);

            $configPath = $this->configAppPath();
            $oldSiteUrl = (string)config('site_url', '');

            $this->writeConfigApp($configPath, $values);

            if ($oldSiteUrl !== '' && $oldSiteUrl !== $values['site_url']) {
                $this->flash(
                    'warning',
                    'Site URL changed. This invalidates HMAC preview tokens; existing draft preview links will return 403.'
                );
            }

            // General-tab fields drive header/footer/nav text on every
            // page (site_name, site_description, site_url, default_meta_*).
            // A targeted invalidation would have to enumerate every
            // cached URL; the only sound default is to throw the whole
            // HTML cache away and let the next request repopulate.
            HtmlCache::flushAll();

            $this->flash('success', 'General settings saved.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash('error', 'Failed to write config/app.php: ' . $e->getMessage());
        }

        $this->redirect('/admin/settings?tab=general');
    }

    /**
     * Collect General-tab form values, applying the whitelist. Anything
     * the operator (or an attacker) POSTed outside the whitelist is
     * silently dropped before validation -- a single layer is enough,
     * but we keep the collection + validation steps separate so a future
     * field addition only touches GENERAL_ALLOWED_FIELDS.
     *
     * @return array<string,string>
     */
    private function collectGeneralPost(): array
    {
        $out = [];
        foreach (self::GENERAL_ALLOWED_FIELDS as $key) {
            $raw = $this->post($key, '');
            if (!is_string($raw)) {
                $raw = '';
            }
            $out[$key] = trim($raw);
        }
        return $out;
    }

    /**
     * Validate the whitelisted general fields. Throws InvalidArgument on
     * the first failure -- saveGeneral() catches it and flashes the
     * message back to the form. Public for reuse by the public REST
     * API's SiteConfigController, which applies the identical whitelist
     * and validation rules.
     *
     * @param array<string,string> $values
     */
    public function validateGeneralFields(array $values): void
    {
        if ($values['site_name'] === '' || mb_strlen($values['site_name']) > 100) {
            throw new \InvalidArgumentException('Site name is required (1-100 chars).');
        }
        if (mb_strlen($values['site_description']) > 280) {
            throw new \InvalidArgumentException('Site description must be 280 chars or fewer.');
        }
        if (filter_var($values['site_url'], FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Site URL must be a valid URL (e.g. https://example.com).');
        }
        if (!in_array($values['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException('Unknown timezone: ' . $values['timezone']);
        }
        if (!$this->isValidTheme($values['active_theme'])) {
            throw new \InvalidArgumentException('Unknown or non-selectable theme: ' . $values['active_theme']);
        }
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $values['language'])) {
            throw new \InvalidArgumentException('Language must be a 2-5 char locale code (e.g. "en" or "en-US").');
        }
    }

    /**
     * Confirm the submitted theme slug names a real folder under
     * themes/ AND isn't a control-plane theme (admin/install). This is
     * the path-traversal gate: $value goes nowhere near the filesystem
     * unless it round-trips through discoverThemes() first.
     */
    private function isValidTheme(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $valid = array_column($this->discoverThemes(), 'slug');
        return in_array($value, $valid, true);
    }

    /**
     * Rewrite config/app.php with the whitelisted general values.
     *
     * The shipped template uses the `$env('KEY') ?? 'fallback'` pattern.
     * We update the fallback for each whitelisted field -- so the
     * resulting file still honours .env overrides at runtime, but the
     * baked-in default matches what the operator just typed.
     *
     * Atomic write: temp file + rename(2) in the same directory. Public
     * so the public REST API's SiteConfigController writes through the
     * exact same code path; the whitelist + rewriter live in one place.
     *
     * @param array<string,string> $values
     */
    public function writeConfigApp(string $path, array $values): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException('config/app.php missing at ' . $path);
        }
        $content = (string)file_get_contents($path);

        // .env-key the field corresponds to in the config template.
        // SITE_LANGUAGE is the wizard-appended key; the rest match the
        // template verbatim.
        $envKeys = [
            'site_name'        => 'SITE_NAME',
            'site_description' => 'SITE_DESCRIPTION',
            'site_url'         => 'SITE_URL',
            'timezone'         => 'TIMEZONE',
            'active_theme'     => 'ACTIVE_THEME',
            'language'         => 'LANGUAGE',
        ];

        foreach (self::GENERAL_ALLOWED_FIELDS as $key) {
            if (!isset($envKeys[$key])) {
                continue;
            }
            $envKey = $envKeys[$key];
            $escaped = $this->phpStringLiteral($values[$key]);

            // Match: 'key' => $env('ENV_KEY') ?? '...something...',
            // The fallback may be single-quoted or double-quoted; only
            // the closing quote class matters for the capture.
            $pattern = '/(\'' . preg_quote($key, '/') . '\'\s*=>\s*\$env\(\'' . preg_quote($envKey, '/')
                     . '\'\)\s*\?\?\s*)([\'"])((?:\\\\.|(?!\2).)*)(\2)/u';

            if (preg_match($pattern, $content)) {
                $content = (string)preg_replace($pattern, '$1' . $escaped, $content, 1);
                continue;
            }

            // Field is whitelisted but config template doesn't have an
            // $env() fallback line for it (e.g. 'language' isn't in the
            // shipped template). Inject it after 'active_theme' line.
            $anchor = '/(\'active_theme\'\s*=>\s*\$env\(\'ACTIVE_THEME\'\)\s*\?\?\s*[\'"][^\'"]*[\'"],)/u';
            $injection = "$1\n    '" . $key . "'        => \$env('" . $envKey . "')         ?? " . $escaped . ',';
            if (preg_match($anchor, $content)) {
                $content = (string)preg_replace($anchor, $injection, $content, 1);
            }
        }

        $this->atomicWriteFile($path, $content);
    }

    /**
     * Emit a PHP single-quoted string literal for the given value.
     * Safe for any UTF-8 input; escapes backslash and single-quote per
     * PHP's literal grammar.
     */
    private function phpStringLiteral(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    /**
     * Atomic temp+rename write. Mirrors the pattern in ConfigWriter and
     * AtomicMarkdownWrite -- we duplicate it here rather than pulling
     * in a content-namespaced trait because settings live outside the
     * content tree and shouldn't depend on App\Content\.
     */
    private function atomicWriteFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            throw new \RuntimeException('Config directory missing: ' . $dir);
        }
        $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Write failed: ' . $tmp);
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Rename failed: ' . $tmp . ' -> ' . $path);
        }
    }

    /**
     * Resolve config/app.php on disk. Overridable in tests via the
     * SITE_CONFIG_PATH constant the bootstrap chain already defines.
     */
    private function configAppPath(): string
    {
        if (defined('SITE_CONFIG_PATH')) {
            return SITE_CONFIG_PATH . '/app.php';
        }
        return SITE_ROOT . '/config/app.php';
    }

    /**
     * Data for the General-tab form: current values pulled from config()
     * plus the option lists the form selects need (themes, timezones).
     *
     * @return array{
     *   values:array<string,string>,
     *   themes:list<array{slug:string,name:string}>,
     *   timezones:list<string>
     * }
     */
    private function generalSettings(): array
    {
        return [
            'values' => [
                'site_name'        => (string)config('site_name', ''),
                'site_description' => (string)config('site_description', ''),
                'site_url'         => (string)config('site_url', ''),
                'active_theme'     => (string)config('active_theme', 'tailwind'),
                'timezone'         => (string)config('timezone', 'UTC'),
                'language'         => (string)config('language', 'en'),
            ],
            'themes'    => array_map(
                static fn(array $t): array => ['slug' => $t['slug'], 'name' => $t['name']],
                $this->discoverThemes()
            ),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ];
    }

    public function setTheme(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/settings?tab=appearance');
            return;
        }

        $theme = (string)$this->post('theme', '');
        $themes = $this->discoverThemes();
        $valid = array_column($themes, 'slug');
        if (!in_array($theme, $valid, true)) {
            $this->flash('error', 'Unknown theme: ' . $theme);
            $this->redirect('/admin/settings?tab=appearance');
            return;
        }

        try {
            $this->updateEnv('ACTIVE_THEME', $theme);
            $this->flash('success', 'Theme switched to ' . $theme . '. Reload the public site to see changes.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Failed to write .env: ' . $e->getMessage());
        }

        $this->redirect('/admin/settings?tab=appearance');
    }

    /**
     * @return list<array{label:string,value:string,source:string,mono?:bool}>
     */
    private function siteSettings(): array
    {
        return [
            ['label' => 'Site name',        'value' => (string)config('site_name', ''),        'source' => 'config/app.php: site_name'],
            ['label' => 'Site URL',         'value' => (string)config('site_url', ''),         'source' => 'config/app.php: site_url'],
            ['label' => 'Description',      'value' => (string)config('site_description', '— not set —'), 'source' => 'config/app.php: site_description'],
            ['label' => 'Tagline',          'value' => (string)config('site_tagline', '— not set —'),     'source' => 'config/app.php: site_tagline'],
            ['label' => 'Timezone',         'value' => (string)config('timezone', 'UTC'),      'source' => 'config/app.php: timezone'],
            ['label' => 'Active theme',     'value' => (string)config('active_theme', 'tailwind'), 'source' => 'config/app.php: active_theme', 'mono' => true],
            ['label' => 'Language',         'value' => (string)config('language', 'en'),       'source' => 'config/app.php: language'],
            ['label' => 'Content path',     'value' => (string)config('content_dir', ''),      'source' => 'config/app.php: content_dir', 'mono' => true],
        ];
    }

    /**
     * @return list<array{label:string,value:string,source:string,mono?:bool,danger?:bool}>
     */
    private function securitySettings(): array
    {
        $env = $this->envSnapshot();

        return [
            ['label' => 'Admin allowed IPs',     'value' => $env['ADMIN_ALLOWED_IPS'] ?? '— missing —',
                'source' => '.env: ADMIN_ALLOWED_IPS', 'mono' => true,
                'danger' => empty($env['ADMIN_ALLOWED_IPS'])],
            ['label' => 'Trusted proxies',       'value' => $env['TRUSTED_PROXIES'] ?? '127.0.0.1,::1',
                'source' => '.env: TRUSTED_PROXIES', 'mono' => true],
            ['label' => 'Debug mode',            'value' => $this->boolLabel($env['DEBUG'] ?? 'false'),
                'source' => '.env: DEBUG',
                'danger' => $this->isTruthy($env['DEBUG'] ?? 'false')],
            ['label' => 'APP_KEY',               'value' => $this->maskSecret($env['APP_KEY'] ?? ''),
                'source' => '.env: APP_KEY', 'mono' => true],
            ['label' => 'Admin username',        'value' => $env['ADMIN_USERNAME'] ?? '— missing —',
                'source' => '.env: ADMIN_USERNAME', 'mono' => true],
            ['label' => 'Admin password hash',   'value' => $this->maskHash($env['ADMIN_PASSWORD_HASH'] ?? ''),
                'source' => '.env: ADMIN_PASSWORD_HASH', 'mono' => true],
            ['label' => 'Auto-update enabled',   'value' => $this->boolLabel($env['ENABLE_AUTO_UPDATE'] ?? 'false'),
                'source' => '.env: ENABLE_AUTO_UPDATE'],
        ];
    }

    /**
     * @return list<array{label:string,value:string,source:string,mono?:bool}>
     */
    private function emailSettings(): array
    {
        $env = $this->envSnapshot();
        $configured = !empty($env['SMTP_HOST'] ?? '');

        return [
            ['label' => 'Configured', 'value' => $configured ? 'Yes' : 'No', 'source' => '.env: SMTP_HOST presence'],
            ['label' => 'SMTP host',  'value' => $env['SMTP_HOST']     ?? '— not set —', 'source' => '.env: SMTP_HOST', 'mono' => true],
            ['label' => 'SMTP port',  'value' => $env['SMTP_PORT']     ?? '— not set —', 'source' => '.env: SMTP_PORT'],
            ['label' => 'SMTP user',  'value' => $env['SMTP_USER']     ?? '— not set —', 'source' => '.env: SMTP_USER', 'mono' => true],
            ['label' => 'SMTP password', 'value' => $this->maskSecret($env['SMTP_PASSWORD'] ?? ''), 'source' => '.env: SMTP_PASSWORD'],
            ['label' => 'From address',  'value' => $env['MAIL_FROM']  ?? '— not set —', 'source' => '.env: MAIL_FROM', 'mono' => true],
            ['label' => 'From name',     'value' => $env['MAIL_FROM_NAME'] ?? '— not set —', 'source' => '.env: MAIL_FROM_NAME'],
        ];
    }

    /**
     * @return list<array{label:string,value:string,mono?:bool}>
     */
    private function aboutSettings(): array
    {
        $versionFile = SITE_ROOT . '/VERSION';
        $version = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : 'unknown';

        $engineRoot = defined('ENGINE_ROOT') ? ENGINE_ROOT : SITE_ROOT;
        $engineLink = SITE_ROOT . '/engine';
        $engineTarget = is_link($engineLink) ? readlink($engineLink) : '(not a versioned layout)';

        return [
            ['label' => 'Bird CMS version', 'value' => $version,                                'mono' => true],
            ['label' => 'PHP version',      'value' => PHP_VERSION,                             'mono' => true],
            ['label' => 'Server',           'value' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'],
            ['label' => 'Site root',        'value' => SITE_ROOT,                               'mono' => true],
            ['label' => 'Engine root',      'value' => $engineRoot,                             'mono' => true],
            ['label' => 'Engine symlink',   'value' => $engineTarget,                           'mono' => true],
            ['label' => 'Storage path',     'value' => SITE_STORAGE_PATH,                       'mono' => true],
            ['label' => 'Content path',     'value' => SITE_CONTENT_PATH,                       'mono' => true],
        ];
    }

    /**
     * @return array<string,string>
     */
    private function envSnapshot(): array
    {
        // PHP-FPM and some SAPI configurations expose non-scalar values via
        // $_ENV (HTTP_*, PATH_INFO arrays, etc.). strval() on those throws
        // "Array to string conversion" warnings -- pre-filter to scalars.
        $scalars = array_filter($_ENV, 'is_scalar');
        return array_filter(
            array_map('strval', $scalars),
            static fn(string $k): bool => $k !== '',
            ARRAY_FILTER_USE_KEY
        );
    }

    private function maskSecret(string $value): string
    {
        if ($value === '') {
            return '— not set —';
        }
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }
        return str_repeat('•', $len - 4) . substr($value, -4);
    }

    private function maskHash(string $hash): string
    {
        if ($hash === '') {
            return '— not set —';
        }
        // bcrypt hashes are 60 chars; show algo prefix + length only.
        $prefix = substr($hash, 0, 4);
        return $prefix . str_repeat('•', 16) . ' (' . strlen($hash) . ' chars)';
    }

    private function isTruthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function boolLabel(string $value): string
    {
        return $this->isTruthy($value) ? 'Enabled' : 'Disabled';
    }

    /**
     * @return array{themes:list<array{slug:string,name:string,description:string,active:bool}>, active:string}
     */
    private function appearanceSettings(): array
    {
        $themes = $this->discoverThemes();
        $active = (string)config('active_theme', 'tailwind');
        foreach ($themes as &$t) {
            $t['active'] = ($t['slug'] === $active);
        }
        unset($t);
        return ['themes' => $themes, 'active' => $active];
    }

    /**
     * Scan ENGINE_THEMES_PATH for frontend themes. Skips control-plane
     * themes (admin / install) which are not user-selectable.
     *
     * @return list<array{slug:string,name:string,description:string}>
     */
    private function discoverThemes(): array
    {
        $themesPath = defined('ENGINE_THEMES_PATH') ? ENGINE_THEMES_PATH : (SITE_ROOT . '/themes');
        $excluded = ['admin', 'install'];
        $out = [];

        if (!is_dir($themesPath)) {
            return $out;
        }

        foreach (scandir($themesPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $excluded, true)) {
                continue;
            }
            $dir = $themesPath . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            $meta = $this->readThemeMeta($dir);
            $out[] = [
                'slug'        => $entry,
                'name'        => $meta['name']        ?? ucfirst($entry),
                'description' => $meta['description'] ?? '',
            ];
        }

        return $out;
    }

    /**
     * @return array{name?:string, description?:string}
     */
    private function readThemeMeta(string $themeDir): array
    {
        $jsonFile = $themeDir . '/theme.json';
        if (!is_file($jsonFile)) {
            return [];
        }
        $raw = (string)file_get_contents($jsonFile);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Update or insert a single KEY=VALUE line in .env atomically.
     * Other lines (including comments) are preserved verbatim.
     */
    private function updateEnv(string $key, string $value): void
    {
        $path = SITE_ROOT . '/.env';
        if (!is_file($path)) {
            throw new \RuntimeException('.env missing at ' . $path);
        }
        $content = (string)file_get_contents($path);

        $line = $key . '=' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $line, $content, 1);
        } else {
            if ($content !== '' && substr($content, -1) !== "\n") {
                $content .= "\n";
            }
            $content .= $line . "\n";
        }

        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Write failed: ' . $path);
        }
        @chmod($tmp, 0600);
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Rename failed: ' . $tmp . ' -> ' . $path);
        }
    }
}
