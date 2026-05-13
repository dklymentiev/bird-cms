<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Full HTML response cache for stable-URL frontend pages.
 *
 * Stage 2 of the perf work (stage 1 was {@see \App\Content\ContentCache}
 * for meta-array memoization). This cache stores the complete rendered
 * HTML body for a request under storage/cache/html/<key>.html so a repeat
 * GET can echo the file verbatim and skip the entire render pipeline
 * (repository load, markdown render, theme include, etc.).
 *
 * Trade-offs vs ContentCache:
 *   - Larger savings per hit (no PHP work at all besides the read), but
 *     a coarser invalidation surface: any write to any article touches
 *     the homepage, the affected category index, llms.txt, and the
 *     article's own URL. Repositories call forget() for each.
 *   - Cache files are plain HTML, not PHP, so opcache doesn't help here;
 *     PHP's file-system caching layer (statcache) does the heavy lifting.
 *   - A TTL safety net (300s) caps the worst-case staleness even when an
 *     invalidation path forgets to call forget(). Treats the cache as an
 *     optimization, not a source of truth.
 *
 * Opt-in via HTML_CACHE=true env var. Default off: a forgotten forget()
 * inside the trait would silently serve stale HTML for sites that haven't
 * profiled the trade-off. Operators flip it on once they have a baseline.
 *
 * Atomic writes mirror {@see \App\Content\AtomicMarkdownWrite::atomicWrite()}:
 * temp file in the same directory + rename(2). Same pattern used everywhere
 * else in the engine so a crashed PHP process can't leave a half-written
 * HTML body on disk.
 *
 * Skip conditions enforced by {@see HtmlCache::shouldServe()}:
 *   - non-GET methods (cached HTML never reflects POST state)
 *   - any query string (?preview=, ?cb=, ?page= -- treats them all as
 *     uncacheable; per-page would need a key-shape decision)
 *   - admin / api / install / health paths (auth-scoped or stateful)
 *   - request carries an admin session cookie (the admin should never
 *     see cached anonymous HTML in case of a misconfigured proxy)
 */
final class HtmlCache
{
    /**
     * Maximum age for any cached entry, in seconds. Even if invalidation
     * fires correctly, a stuck cache file is bounded by this number.
     * Chosen as a balance: long enough to amortize render cost across
     * burst traffic, short enough that operator-visible staleness windows
     * stay under "go grab a coffee" range.
     */
    public const TTL_SECONDS = 300;

    /**
     * Resolve storage/cache/html/ relative to the project root.
     *
     * Returns null if the directory can't be created or is not writable;
     * callers (get/put/forget) bail to no-op behaviour in that case so a
     * cache failure never breaks the actual request.
     */
    public static function dir(): ?string
    {
        $root = defined('BIRD_PROJECT_ROOT')
            ? BIRD_PROJECT_ROOT
            : (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 2));

        $dir = $root . '/storage/cache/html';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return null;
            }
        }
        if (!is_writable($dir)) {
            return null;
        }
        return $dir;
    }

    /**
     * HTML_CACHE env-var gate. Default OFF.
     */
    public static function enabled(): bool
    {
        $flag = getenv('HTML_CACHE');
        if ($flag === false || $flag === '') {
            $flag = $_ENV['HTML_CACHE'] ?? $_SERVER['HTML_CACHE'] ?? '';
        }
        return is_string($flag) && strtolower(trim($flag)) === 'true';
    }

    /**
     * Decide whether the current request is eligible for the cache.
     *
     * Anything that changes per-request (query string, session, POST body)
     * is rejected here so the cache only ever holds the anonymous,
     * canonical render of a URL. The dispatcher checks this once and
     * threads the result into both get() and put().
     *
     * @param array<string, mixed> $server  $_SERVER-shaped
     * @param array<string, mixed> $cookies $_COOKIE-shaped
     */
    public static function shouldServe(array $server, array $cookies): bool
    {
        if (!self::enabled()) {
            return false;
        }

        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET') {
            return false;
        }

        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');
        $queryString = (string) ($server['QUERY_STRING'] ?? '');
        if ($queryString !== '') {
            return false;
        }
        // Belt-and-suspenders: REQUEST_URI may contain ? even when
        // QUERY_STRING wasn't populated (some SAPIs do this).
        if (str_contains($requestUri, '?')) {
            return false;
        }

        $path = (string) parse_url($requestUri, PHP_URL_PATH);
        $path = '/' . ltrim($path, '/');

        // Auth-scoped or stateful paths are never cached. Match the exact
        // segment, not a substring, so a slug starting with "admin-" still
        // caches normally.
        $excludedFirstSegments = ['admin', 'api', 'install', 'health'];
        foreach ($excludedFirstSegments as $segment) {
            if ($path === '/' . $segment || str_starts_with($path, '/' . $segment . '/')) {
                return false;
            }
        }

        // Admin session cookie present: don't serve cached anonymous HTML
        // to a logged-in admin. Cookie name defaults to dim_admin (from
        // config/admin.php) but we also accept the historical bird_admin
        // and any cookie whose name ends with `_admin` -- safer to over-skip
        // than to leak admin context out of a misconfigured cache.
        foreach (array_keys($cookies) as $cookieName) {
            $lower = strtolower((string) $cookieName);
            if ($lower === 'bird_admin'
                || $lower === 'dim_admin'
                || str_ends_with($lower, '_admin')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read a cached HTML body. Returns null on miss, stale entry, or any
     * IO error; callers fall through to live render.
     *
     * Stale = older than TTL_SECONDS. The check is on filemtime, not on
     * any embedded timestamp inside the HTML, so a touch(2) of the cache
     * file is enough to refresh it without a regenerate.
     */
    public static function get(string $cacheKey): ?string
    {
        $dir = self::dir();
        if ($dir === null) {
            return null;
        }
        $path = self::pathFor($dir, $cacheKey);
        if ($path === null || !is_file($path)) {
            return null;
        }
        $mtime = @filemtime($path);
        if ($mtime === false) {
            return null;
        }
        if ((time() - $mtime) > self::TTL_SECONDS) {
            return null;
        }
        $body = @file_get_contents($path);
        if ($body === false) {
            return null;
        }
        return $body;
    }

    /**
     * Write a cached HTML body atomically. Silent on IO failure: a cache
     * write that crashes must never blow up the live response.
     */
    public static function put(string $cacheKey, string $html): void
    {
        $dir = self::dir();
        if ($dir === null) {
            return;
        }
        $path = self::pathFor($dir, $cacheKey);
        if ($path === null) {
            return;
        }
        $parent = dirname($path);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
            return;
        }
        $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $html, LOCK_EX) === false) {
            return;
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
        }
    }

    /**
     * Delete one cache entry. Idempotent; missing files are not an error.
     */
    public static function forget(string $cacheKey): void
    {
        $dir = self::dir();
        if ($dir === null) {
            return;
        }
        $path = self::pathFor($dir, $cacheKey);
        if ($path === null) {
            return;
        }
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Recursively delete every entry under storage/cache/html/<prefix>/.
     *
     * Used by repository invalidation hooks: a save() to an article in
     * category `blog` calls forgetByPrefix('articles/blog') so every
     * cached variant under that category is dropped. Prefix is sanitized
     * the same way as cache keys so the call can't escape the cache root.
     */
    public static function forgetByPrefix(string $prefix): void
    {
        $dir = self::dir();
        if ($dir === null) {
            return;
        }
        $safePrefix = self::sanitizeKey($prefix);
        if ($safePrefix === null) {
            return;
        }
        $target = $dir . '/' . $safePrefix;
        if (!is_dir($target)) {
            return;
        }
        self::removeTree($target);
    }

    /**
     * Wipe the entire cache. Called by Settings save: header/footer/nav
     * config touches every page, so the only sound default is to throw
     * everything away.
     */
    public static function flushAll(): void
    {
        $dir = self::dir();
        if ($dir === null) {
            return;
        }
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir . '/' . $item;
            if (is_dir($full)) {
                self::removeTree($full);
            } elseif (is_file($full)) {
                @unlink($full);
            }
        }
    }

    /**
     * Map a request path (or repository-supplied key) to a safe absolute
     * cache-file path. Returns null on any key that escapes the cache
     * root, contains illegal characters, or normalizes to empty.
     *
     * Sanitization rules:
     *   - Strip leading slash, .html suffix, and trailing slashes.
     *   - Allow only [a-z0-9/-]; any other character is rejected (returns
     *     null) so a malformed key never half-matches a real entry.
     *   - Reject "..", absolute paths, and "." segments to block traversal.
     *   - Empty key resolves to "home.html" -- consistent with the
     *     dispatcher mapping `/` to that file.
     */
    private static function pathFor(string $dir, string $cacheKey): ?string
    {
        $safe = self::sanitizeKey($cacheKey);
        if ($safe === null) {
            return null;
        }
        if ($safe === '') {
            return $dir . '/home.html';
        }
        // Already-suffixed keys (e.g. "home.html", "llms.txt") pass through;
        // bare keys ("articles/blog/welcome") get a default .html suffix.
        if (preg_match('/\.[a-z0-9]+$/', $safe) === 1) {
            return $dir . '/' . $safe;
        }
        return $dir . '/' . $safe . '.html';
    }

    /**
     * Sanitize a cache key. Returns null when the input contains characters
     * that don't belong in a cache path. The whitelist is deliberately
     * narrow: lowercase ASCII, digits, slash, dash, dot. Underscores are
     * not in the URL grammar Bird CMS uses, and excluding them keeps the
     * filename rules a strict subset of the URL rules.
     *
     * Public for tests; not exposed as part of the runtime contract.
     */
    public static function sanitizeKey(string $key): ?string
    {
        $key = trim($key);
        // Strip leading slash so /foo and foo map to the same entry.
        $key = ltrim($key, '/');
        // Trailing slash means "directory index"; collapse to the bare key.
        $key = rtrim($key, '/');

        if ($key === '') {
            return '';
        }

        // Path-traversal guard. Reject the literal `..` and any segment
        // equal to `..` or `.`; we don't want "foo/.." to resolve up.
        if (str_contains($key, '..')) {
            return null;
        }
        foreach (explode('/', $key) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        // Whitelist enforcement. Lowercase ASCII alphanumerics, slash,
        // dash, dot are allowed -- the dot supports llms.txt and the .html
        // suffix on already-suffixed keys.
        if (preg_match('/^[a-z0-9.\/-]+$/', $key) !== 1) {
            return null;
        }

        return $key;
    }

    /**
     * Recursive delete helper. Stays private; HtmlCache only ever recurses
     * inside its own storage/cache/html/ subtree.
     */
    private static function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }
            return;
        }
        $items = @scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::removeTree($path . '/' . $item);
        }
        @rmdir($path);
    }

    /**
     * Derive the canonical cache key for a request path. Public so
     * controllers can pass a single source of truth into get()/put().
     *
     * Examples:
     *   `/`                       -> 'home'
     *   `/llms.txt`               -> 'llms.txt'
     *   `/blog/welcome`           -> 'blog/welcome'
     *   `/articles/blog/welcome`  -> 'articles/blog/welcome'
     */
    public static function keyForPath(string $path): string
    {
        $path = trim($path);
        $path = (string) parse_url($path, PHP_URL_PATH);
        $path = '/' . ltrim($path, '/');
        if ($path === '/' || $path === '') {
            return 'home';
        }
        return ltrim($path, '/');
    }
}
