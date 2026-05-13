<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Per-URL overrides stored in storage/url-meta.json.
 *
 * Same JSON the URL Inventory editor in /admin/pages writes:
 *
 *   {
 *     "/services/window-cleaning": {
 *       "in_sitemap": true,
 *       "noindex":    false,
 *       "priority":   "0.7",
 *       "changefreq": "weekly",
 *       "template":   "service-deluxe"
 *     }
 *   }
 *
 * The renderer in public/index.php consults templateFor() to pick a
 * per-URL view template; the sitemap generator reads the rest. Loading
 * is cached per request so the JSON file is only read once even when
 * multiple call sites query it (router + sitemap + canonical resolver).
 */
final class UrlMeta
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** Reset cache. Tests + the admin save path call this after writing. */
    public static function reset(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = self::path();
        if (!is_file($path)) {
            return self::$cache = [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return self::$cache = [];
        }

        $decoded = json_decode($raw, true);
        return self::$cache = is_array($decoded) ? $decoded : [];
    }

    /**
     * Per-URL template override or null when none set.
     *
     * Empty strings in the JSON are treated as null so a cleared
     * dropdown in the admin UI doesn't accidentally pin every render to
     * an empty template name.
     */
    public static function templateFor(string $urlPath): ?string
    {
        $row = self::all()[$urlPath] ?? null;
        if (!is_array($row)) {
            return null;
        }
        $template = $row['template'] ?? null;
        if (!is_string($template) || $template === '') {
            return null;
        }
        return $template;
    }

    private static function path(): string
    {
        $root = defined('SITE_ROOT') ? SITE_ROOT : getcwd();
        return $root . '/storage/url-meta.json';
    }
}
