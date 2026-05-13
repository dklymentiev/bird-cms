<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Tiny config holder used only inside the test suite.
 *
 * Real engine code calls the global `config()` helper for `site_url` and a
 * handful of other keys. tests/bootstrap.php registers a `config()` shim
 * that reads from this store so tests can swap values per-test (via set())
 * without booting App\Support\Config::boot() on every test class.
 */
final class TestConfig
{
    /** @var array<string, mixed> */
    private static array $store = [
        'site_url' => 'http://localhost',
        'categories' => [],
        'seo' => ['pillar_patterns' => []],
    ];

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$store;
        foreach ($segments as $i => $seg) {
            if ($i === count($segments) - 1) {
                $ref[$seg] = $value;
                return;
            }
            if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                $ref[$seg] = [];
            }
            $ref = &$ref[$seg];
        }
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$store;
    }

    public static function reset(): void
    {
        self::$store = [
            'site_url' => 'http://localhost',
            'categories' => [],
            'seo' => ['pillar_patterns' => []],
        ];
    }
}
