<?php

declare(strict_types=1);

namespace App\Support;

final class Config
{
    private static array $items = [];

    public static function boot(array $config): void
    {
        self::$items = $config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function all(): array
    {
        return self::$items;
    }

    /**
     * Load a config file as array. Site-first, engine-fallback (Koval-style whole-file override).
     * Cached per-name. Throws if neither site nor engine has the file.
     */
    public static function load(string $name): array
    {
        static $cache = [];
        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $cache[$name] = require self::readPath($name);
        return $cache[$name];
    }

    /**
     * Path resolved for reading: site override if it exists, else engine default.
     * Throws if neither exists.
     */
    public static function readPath(string $name): string
    {
        self::assertBootstrapLoaded();

        $sitePath = SITE_CONFIG_PATH . '/' . $name . '.php';
        if (file_exists($sitePath)) {
            return $sitePath;
        }

        $enginePath = ENGINE_ROOT . '/config/' . $name . '.php';
        if (file_exists($enginePath)) {
            return $enginePath;
        }

        throw new \RuntimeException(sprintf(
            "Config '%s' not found. Looked in site (%s) and engine (%s).",
            $name,
            $sitePath,
            $enginePath
        ));
    }

    /**
     * Path for writing — ALWAYS site path (engine is read-only).
     * First write of a config effectively converts engine-default into site-override.
     * Creates SITE_CONFIG_PATH directory if missing.
     */
    public static function writePath(string $name): string
    {
        self::assertBootstrapLoaded();

        if (!is_dir(SITE_CONFIG_PATH)) {
            if (!mkdir(SITE_CONFIG_PATH, 0755, true) && !is_dir(SITE_CONFIG_PATH)) {
                throw new \RuntimeException(sprintf(
                    'Failed to create site config dir: %s',
                    SITE_CONFIG_PATH
                ));
            }
        }

        return SITE_CONFIG_PATH . '/' . $name . '.php';
    }

    private static function assertBootstrapLoaded(): void
    {
        if (!defined('SITE_CONFIG_PATH') || !defined('ENGINE_ROOT')) {
            throw new \RuntimeException(
                'Config requires bootstrap.php to be loaded (SITE_CONFIG_PATH and ENGINE_ROOT must be defined)'
            );
        }
    }
}
