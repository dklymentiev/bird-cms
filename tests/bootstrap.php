<?php
/**
 * PHPUnit bootstrap for Bird CMS.
 *
 * Goals:
 *   - Load engine app/ classes without booting a full site (no .env, no
 *     APP_KEY guard, no config/app.php walk-up). Tests construct repositories
 *     directly with explicit paths.
 *   - Provide a tiny PSR-4 autoloader for App\ and Tests\ so we don't depend
 *     on `composer dump-autoload` having been run.
 *   - Define a tmp content root each test class can use; tests are
 *     responsible for cleanup via TestCase::tearDown().
 *   - Ship a minimal `config()` shim. The engine's repositories call the
 *     global `config()` helper for `site_url` and `seo.pillar_patterns`;
 *     tests need that without dragging in the bootstrap chain.
 */

declare(strict_types=1);

define('BIRD_TEST_ROOT', __DIR__);
define('BIRD_PROJECT_ROOT', dirname(__DIR__));

if (!is_dir(BIRD_TEST_ROOT . '/fixtures/tmp')) {
    mkdir(BIRD_TEST_ROOT . '/fixtures/tmp', 0755, true);
}

// Minimal PSR-4 autoload so repositories load without composer dump.
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\'   => BIRD_PROJECT_ROOT . '/app/',
        'Tests\\' => BIRD_TEST_ROOT . '/',
    ];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $rel = substr($class, strlen($prefix));
            $file = $base . str_replace('\\', '/', $rel) . '.php';
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// Engine's `config()` helper lives in app/Support/helpers.php and depends
// on Config::boot(). Tests call repositories directly; provide a tiny shim
// so `config('site_url')` and `config('seo.pillar_patterns', [])` return
// something sensible without booting the real engine.
if (!function_exists('config')) {
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        $store = \Tests\Support\TestConfig::all();
        $segments = explode('.', $key);
        $value = $store;
        foreach ($segments as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }
        return $value;
    }
}
