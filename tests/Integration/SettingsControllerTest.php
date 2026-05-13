<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Admin\SettingsController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * Integration coverage for App\Admin\SettingsController -- General tab.
 *
 * The Settings General tab lets the operator edit site identity from
 * the admin without SSH'ing into config/app.php. Two security-critical
 * properties have to hold at the file-system boundary the controller
 * actually writes through:
 *
 *   1) the whitelist must reject sensitive .env keys (APP_KEY,
 *      admin_password_hash, admin_allowed_ips); a leak here would
 *      reintroduce the HMAC incident the whole design exists to
 *      avoid.
 *   2) the theme selector must not accept arbitrary user input as a
 *      folder name (path traversal); only physical folders under
 *      themes/ that aren't admin/ or install/ are valid.
 *
 * Both invariants are exercised below alongside the happy-path
 * rewrite, the validators, and the round-trip of values through
 * config/app.php on disk.
 */
final class SettingsControllerTest extends TestCase
{
    private string $tmpDir;
    private string $configPath;
    private static bool $themesPrimed = false;
    private static string $themesDir = '';

    protected function setUp(): void
    {
        TestConfig::reset();

        $this->tmpDir    = TempContent::make('settings-int');
        $this->configPath = $this->tmpDir . '/app.php';

        // Seed a representative config/app.php on disk so the rewriter
        // has the same $env('KEY') ?? 'fallback' pattern that the
        // shipped template emits. The test then asserts the fallback
        // string gets swapped to the operator-supplied value.
        file_put_contents(
            $this->configPath,
            $this->shippedConfigTemplate()
        );

        // ENGINE_THEMES_PATH is a process-wide constant. Another test class
        // (notably the API SiteConfigApiTest) may have define()'d it first
        // to its own temp dir, in which case our define() below is a no-op.
        // To stay robust across class ordering, always (re)create the four
        // expected theme dirs inside whatever path is active — they're
        // idempotent (mkdir guarded by is_dir) and cheap.
        if (!self::$themesPrimed) {
            self::$themesDir = sys_get_temp_dir() . '/bird-cms-themes-' . bin2hex(random_bytes(4));
            if (!defined('ENGINE_THEMES_PATH')) {
                define('ENGINE_THEMES_PATH', self::$themesDir);
            }
            self::$themesPrimed = true;
        }
        $activeThemes = ENGINE_THEMES_PATH;
        foreach (['tailwind', 'minimal', 'admin', 'install'] as $sub) {
            $path = $activeThemes . '/' . $sub;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // Pre-seed the test config helper so generalSettings() returns
        // sensible defaults when a test inspects the rendered values.
        TestConfig::set('site_name', 'Old Name');
        TestConfig::set('site_description', 'old desc');
        TestConfig::set('site_url', 'https://old.example.com');
        TestConfig::set('active_theme', 'tailwind');
        TestConfig::set('timezone', 'UTC');
        TestConfig::set('language', 'en');
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testGeneralRendersWithCurrentValues(): void
    {
        $controller = $this->makeController();
        $r = new ReflectionMethod($controller, 'generalSettings');
        $r->setAccessible(true);
        $payload = $r->invoke($controller);

        self::assertIsArray($payload);
        self::assertArrayHasKey('values', $payload);
        self::assertArrayHasKey('themes', $payload);
        self::assertArrayHasKey('timezones', $payload);

        // values: pulled from config() shim
        self::assertSame('Old Name', $payload['values']['site_name']);
        self::assertSame('https://old.example.com', $payload['values']['site_url']);
        self::assertSame('tailwind', $payload['values']['active_theme']);

        // themes: tailwind + minimal, admin/install excluded
        $slugs = array_column($payload['themes'], 'slug');
        sort($slugs);
        self::assertSame(['minimal', 'tailwind'], $slugs);

        // timezones: real DateTimeZone catalog, contains UTC + a region
        self::assertContains('UTC', $payload['timezones']);
        self::assertContains('America/Chicago', $payload['timezones']);
    }

    public function testSaveGeneralValidatesUrlAndTimezone(): void
    {
        $controller = $this->makeController();
        $validate = new ReflectionMethod($controller, 'validateGeneralFields');
        $validate->setAccessible(true);

        $base = [
            'site_name'        => 'My Site',
            'site_description' => '',
            'site_url'         => 'https://example.com',
            'active_theme'     => 'tailwind',
            'timezone'         => 'UTC',
            'language'         => 'en',
        ];

        // Happy path passes.
        $validate->invoke($controller, $base);

        // Bad URL.
        $bad = $base;
        $bad['site_url'] = 'not-a-url';
        try {
            $validate->invoke($controller, $bad);
            self::fail('Expected InvalidArgumentException for bad URL');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Site URL', $e->getMessage());
        }

        // Unknown timezone.
        $bad = $base;
        $bad['timezone'] = 'Mars/Olympus';
        try {
            $validate->invoke($controller, $bad);
            self::fail('Expected InvalidArgumentException for unknown timezone');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('timezone', $e->getMessage());
        }

        // Empty site name.
        $bad = $base;
        $bad['site_name'] = '';
        try {
            $validate->invoke($controller, $bad);
            self::fail('Expected InvalidArgumentException for empty site name');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Site name', $e->getMessage());
        }

        // Over-long site_description.
        $bad = $base;
        $bad['site_description'] = str_repeat('x', 281);
        try {
            $validate->invoke($controller, $bad);
            self::fail('Expected InvalidArgumentException for too-long description');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('280', $e->getMessage());
        }

        // Bad language code.
        $bad = $base;
        $bad['language'] = 'english';
        try {
            $validate->invoke($controller, $bad);
            self::fail('Expected InvalidArgumentException for bad language');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Language', $e->getMessage());
        }
    }

    public function testSaveGeneralWritesAtomicallyToConfig(): void
    {
        $controller = $this->makeController();
        $write = new ReflectionMethod($controller, 'writeConfigApp');
        $write->setAccessible(true);

        $values = [
            'site_name'        => 'Edited Name',
            'site_description' => 'New description',
            'site_url'         => 'https://new.example.com',
            'active_theme'     => 'minimal',
            'timezone'         => 'America/Chicago',
            'language'         => 'fr',
        ];

        $write->invoke($controller, $this->configPath, $values);

        $written = (string)file_get_contents($this->configPath);

        // Each whitelisted field should appear as the new fallback in
        // the $env('KEY') ?? '...' line. The .env override is left
        // alone so the runtime contract is unchanged.
        self::assertStringContainsString("'site_name'        => \$env('SITE_NAME')        ?? 'Edited Name'", $written);
        self::assertStringContainsString("'site_description' => \$env('SITE_DESCRIPTION') ?? 'New description'", $written);
        self::assertStringContainsString("'site_url'         => \$env('SITE_URL')         ?? 'https://new.example.com'", $written);
        self::assertStringContainsString("'active_theme'     => \$env('ACTIVE_THEME')     ?? 'minimal'", $written);
        self::assertStringContainsString("'timezone'         => \$env('TIMEZONE')         ?? 'America/Chicago'", $written);

        // No leftover temp file in the config directory -- rename(2)
        // completed successfully.
        $leftover = glob($this->configPath . '.tmp.*');
        self::assertSame([], $leftover);
    }

    public function testSaveGeneralRejectsAppKeyField(): void
    {
        // The whitelist gate is collectGeneralPost(). Even if an
        // attacker POSTs app_key / admin_password_hash / etc. the
        // collector must not return them in its output array. This
        // is the load-bearing security check that keeps HMAC and
        // bcrypt material out of config/app.php.
        $controller = $this->makeController();

        $_POST = [
            'site_name'             => 'Legit',
            'site_description'      => '',
            'site_url'              => 'https://example.com',
            'active_theme'          => 'tailwind',
            'timezone'              => 'UTC',
            'language'              => 'en',
            // Attacker payload:
            'app_key'               => 'attacker-controlled-key',
            'admin_password_hash'   => '$2y$10$pwnpwnpwnpwnpwnpwnpwn',
            'admin_allowed_ips'     => '0.0.0.0/0',
            'content_dir'           => '/etc/passwd',
        ];

        $collect = new ReflectionMethod($controller, 'collectGeneralPost');
        $collect->setAccessible(true);
        $values = $collect->invoke($controller);

        $allowed = ['site_name', 'site_description', 'site_url', 'active_theme', 'timezone', 'language'];
        self::assertSame($allowed, array_keys($values));
        self::assertArrayNotHasKey('app_key', $values);
        self::assertArrayNotHasKey('admin_password_hash', $values);
        self::assertArrayNotHasKey('admin_allowed_ips', $values);
        self::assertArrayNotHasKey('content_dir', $values);

        // And the rewriter must not surface those keys in the file
        // even when the inputs map carries them by accident.
        $tampered = array_merge($values, [
            'app_key'             => 'attacker',
            'admin_password_hash' => '$2y$10$pwn',
        ]);
        $write = new ReflectionMethod($controller, 'writeConfigApp');
        $write->setAccessible(true);
        $write->invoke($controller, $this->configPath, $tampered);

        $written = (string)file_get_contents($this->configPath);
        self::assertStringNotContainsString('attacker-controlled-key', $written);
        self::assertStringNotContainsString('attacker', $written);
        self::assertStringNotContainsString('$2y$10$pwn', $written);
        self::assertStringNotContainsString('admin_password_hash', $written);

        $_POST = [];
    }

    public function testSaveGeneralRejectsThemeOutsideThemesDir(): void
    {
        // Path-traversal gate: the theme slug from the form must
        // round-trip through discoverThemes() before it's accepted.
        // Anything else -- ../etc, absolute paths, dot-slashes,
        // control-plane theme names -- is rejected.
        $controller = $this->makeController();
        $isValid = new ReflectionMethod($controller, 'isValidTheme');
        $isValid->setAccessible(true);

        // Real, selectable themes: OK
        self::assertTrue($isValid->invoke($controller, 'tailwind'));
        self::assertTrue($isValid->invoke($controller, 'minimal'));

        // Control-plane themes: excluded from discoverThemes()
        self::assertFalse($isValid->invoke($controller, 'admin'));
        self::assertFalse($isValid->invoke($controller, 'install'));

        // Path traversal attempts:
        self::assertFalse($isValid->invoke($controller, '../etc/passwd'));
        self::assertFalse($isValid->invoke($controller, '..\\..\\Windows\\System32'));
        self::assertFalse($isValid->invoke($controller, '/etc/passwd'));
        self::assertFalse($isValid->invoke($controller, 'tailwind/../admin'));
        self::assertFalse($isValid->invoke($controller, ''));
        self::assertFalse($isValid->invoke($controller, 'nonexistent-theme'));

        // And the full validator rejects path-traversal themes with a
        // clear error message.
        $validate = new ReflectionMethod($controller, 'validateGeneralFields');
        $validate->setAccessible(true);
        try {
            $validate->invoke($controller, [
                'site_name'        => 'Site',
                'site_description' => '',
                'site_url'         => 'https://example.com',
                'active_theme'     => '../admin',
                'timezone'         => 'UTC',
                'language'         => 'en',
            ]);
            self::fail('Expected InvalidArgumentException for path-traversal theme');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('theme', strtolower($e->getMessage()));
        }
    }

    /**
     * Construct a SettingsController without booting the full admin
     * stack. We bypass the parent Controller::__construct (which
     * loads admin config and runs the IP allowlist check) via
     * ReflectionClass::newInstanceWithoutConstructor, then wire the
     * minimum properties the methods under test actually read.
     */
    private function makeController(): SettingsController
    {
        $r = new ReflectionClass(SettingsController::class);
        $controller = $r->newInstanceWithoutConstructor();

        // Override configAppPath() so writes hit the temp file rather
        // than the real config/app.php on disk. We do this by adding
        // a SITE_CONFIG_PATH constant pointing at tmpDir -- the
        // controller honours it when defined.
        if (!defined('SITE_CONFIG_PATH')) {
            define('SITE_CONFIG_PATH', $this->tmpDir);
        }

        return $controller;
    }

    /**
     * Minimal copy of templates/config-app.php.example. The rewriter
     * targets the `$env('KEY') ?? 'fallback'` pattern; this fixture
     * exercises that exactly.
     */
    private function shippedConfigTemplate(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

$env = static fn(string $k): ?string => ($_ENV[$k] ?? getenv($k)) ?: null;

return [
    'site_name'        => $env('SITE_NAME')        ?? 'My Site',
    'site_url'         => $env('SITE_URL')         ?? 'http://localhost:8080',
    'site_description' => $env('SITE_DESCRIPTION') ?? '',
    'timezone'         => $env('TIMEZONE')         ?? 'UTC',
    'active_theme'     => $env('ACTIVE_THEME')     ?? 'tailwind',
];
PHP;
    }
}
