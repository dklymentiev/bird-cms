<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Admin\SettingsController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * Whitelist coverage for /api/v1/site-config.
 *
 * The public API endpoint reuses SettingsController::
 * GENERAL_ALLOWED_FIELDS + validateGeneralFields + writeConfigApp
 * verbatim (via ReflectionClass::newInstanceWithoutConstructor to
 * bypass the admin IP gate). Anything outside the whitelist is
 * silently dropped before validation, so a malicious PUT body trying
 * to set app_key never reaches the rewriter.
 *
 * The whitelist content itself is the security boundary; that's
 * what this class asserts.
 */
final class SiteConfigApiTest extends TestCase
{
    private string $tmpDir;
    private string $configPath;
    private static bool $themesPrimed = false;

    protected function setUp(): void
    {
        TestConfig::reset();
        TestConfig::set('site_url', 'https://old.example.com');
        TestConfig::set('site_name', 'Old');
        TestConfig::set('site_description', '');
        TestConfig::set('active_theme', 'tailwind');
        TestConfig::set('timezone', 'UTC');
        TestConfig::set('language', 'en');

        $this->tmpDir = TempContent::make('api-siteconfig');
        $this->configPath = $this->tmpDir . '/app.php';
        file_put_contents($this->configPath, <<<'PHP'
<?php
$env = static fn(string $k) => $_ENV[$k] ?? null;
return [
    'site_name'        => $env('SITE_NAME')         ?? 'Old',
    'site_description' => $env('SITE_DESCRIPTION')  ?? '',
    'site_url'         => $env('SITE_URL')          ?? 'https://old.example.com',
    'active_theme'     => $env('ACTIVE_THEME')      ?? 'tailwind',
    'timezone'         => $env('TIMEZONE')          ?? 'UTC',
];
PHP);

        if (!self::$themesPrimed) {
            $themesDir = sys_get_temp_dir() . '/bird-cms-api-themes-' . bin2hex(random_bytes(4));
            mkdir($themesDir . '/tailwind', 0755, true);
            if (!defined('ENGINE_THEMES_PATH')) {
                define('ENGINE_THEMES_PATH', $themesDir);
            }
            self::$themesPrimed = true;
        }
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testWhitelistContainsExactlySafeFields(): void
    {
        // If somebody adds APP_KEY / ADMIN_PASSWORD_HASH / ADMIN_ALLOWED_IPS
        // to GENERAL_ALLOWED_FIELDS, this test fails loudly. That's the whole
        // point of the whitelist.
        self::assertSame([
            'site_name',
            'site_description',
            'site_url',
            'active_theme',
            'timezone',
            'language',
        ], SettingsController::GENERAL_ALLOWED_FIELDS);

        foreach (SettingsController::GENERAL_ALLOWED_FIELDS as $field) {
            self::assertNotContains($field, ['app_key', 'admin_password_hash', 'admin_allowed_ips']);
        }
    }

    public function testRewriterSilentlyIgnoresFieldsOutsideWhitelist(): void
    {
        $controller = (new ReflectionClass(SettingsController::class))->newInstanceWithoutConstructor();

        // Try to sneak app_key through. The rewriter only walks
        // GENERAL_ALLOWED_FIELDS, so this entry is dropped before any
        // file write happens.
        $values = [
            'site_name'        => 'New Name',
            'site_description' => 'New',
            'site_url'         => 'https://new.example.com',
            'active_theme'     => 'tailwind',
            'timezone'         => 'UTC',
            'language'         => 'en',
            'app_key'          => 'malicious-injected-key',
            'admin_password_hash' => '$2y$10$attackerControlledHash',
        ];

        $controller->writeConfigApp($this->configPath, $values);

        $rewritten = (string) file_get_contents($this->configPath);
        self::assertStringContainsString("'New Name'", $rewritten);
        self::assertStringNotContainsString('malicious-injected-key', $rewritten);
        self::assertStringNotContainsString('attackerControlledHash', $rewritten);
        self::assertStringNotContainsString('app_key', $rewritten);
    }

    public function testValidatorRejectsInvalidUrl(): void
    {
        $controller = (new ReflectionClass(SettingsController::class))->newInstanceWithoutConstructor();
        $this->expectException(\InvalidArgumentException::class);
        $controller->validateGeneralFields([
            'site_name'        => 'ok',
            'site_description' => '',
            'site_url'         => 'not-a-url',
            'active_theme'     => 'tailwind',
            'timezone'         => 'UTC',
            'language'         => 'en',
        ]);
    }

    public function testValidatorRejectsUnknownTheme(): void
    {
        $controller = (new ReflectionClass(SettingsController::class))->newInstanceWithoutConstructor();
        $this->expectException(\InvalidArgumentException::class);
        $controller->validateGeneralFields([
            'site_name'        => 'ok',
            'site_description' => '',
            'site_url'         => 'https://example.com',
            'active_theme'     => '../../etc/passwd',
            'timezone'         => 'UTC',
            'language'         => 'en',
        ]);
    }
}
