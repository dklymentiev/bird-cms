<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Two-tier cache for content repositories.
 *
 * Tier 1: per-instance memoization. Repository::all(), getCategoryArticles(),
 * byType() etc. already cached results in a `private array $cache` field; this
 * trait consolidates that pattern so every repository memoizes the same way.
 *
 * Tier 2: filesystem cache. An opcache-friendly PHP-array file under
 * storage/cache/<key>.php holds the previously-parsed list. The trait
 * compares the cache file's mtime against the youngest mtime in the watched
 * content directories; if anything on disk is newer, the cache is stale and
 * the caller regenerates.
 *
 * Both tiers are opt-in for the filesystem half: the per-instance memo is
 * always active, the disk layer only activates when CONTENT_CACHE=true.
 * Default-off keeps the cache invisible to tests and to anyone who hasn't
 * deliberately enabled it. Cache is also skipped if storage/cache/ is not
 * writable (graceful degrade -- the engine still works, just slower).
 *
 * mtime invalidation, not TTL: content rarely changes on a Bird CMS site,
 * and when it does, the filesystem already carries an authoritative
 * "changed at" stamp. A TTL would either be too short (defeats the cache
 * on quiet sites) or too long (serves stale content after a publish).
 *
 * Writes are atomic: temp file in the same directory, rename(2) into place.
 * Mirrors AtomicMarkdownWrite so a crashed PHP process can't leave a
 * half-written cache.
 */
trait ContentCache
{
    /** @var array<string, mixed> */
    private array $contentMemo = [];

    /**
     * Per-instance memoized fetch.
     *
     * @template T
     * @param string   $key       Memo key (free-form, repository chooses scheme)
     * @param callable $loader    Returns the value to cache on miss
     * @return mixed              Whatever $loader returned (memoized on hit)
     */
    protected function memo(string $key, callable $loader): mixed
    {
        if (array_key_exists($key, $this->contentMemo)) {
            return $this->contentMemo[$key];
        }
        return $this->contentMemo[$key] = $loader();
    }

    /**
     * Drop one or all memo entries.
     *
     * Repositories call this from save()/delete() so a follow-up read in the
     * same request reflects the write. Pass null to flush every entry.
     */
    protected function memoForget(?string $key = null): void
    {
        if ($key === null) {
            $this->contentMemo = [];
            return;
        }
        unset($this->contentMemo[$key]);
    }

    /**
     * Filesystem-cache wrapper around a loader callback.
     *
     * Behaviour:
     *   - If CONTENT_CACHE is not "true" or storage/cache/ is not writable,
     *     calls $loader() directly and returns its result (no caching).
     *   - Otherwise, checks storage/cache/<cacheKey>.php. If it exists AND
     *     its mtime is >= the youngest watched file's mtime, the cached
     *     array is returned without invoking $loader.
     *   - Cache miss / stale: calls $loader(), atomically writes the result
     *     to storage/cache/<cacheKey>.php (as `<?php return [...];`), and
     *     returns it.
     *
     * Cache files use `var_export()` so opcache can compile them; the cost
     * of a hit is then equivalent to reading an already-compiled PHP file.
     *
     * @param string        $cacheKey   Stable identifier (e.g. "articles-index")
     * @param list<string>  $watchPaths Files OR directories whose mtimes invalidate the cache
     * @param callable():array $loader  Returns the fresh array on miss
     * @return array
     */
    protected function fsCache(string $cacheKey, array $watchPaths, callable $loader): array
    {
        if (!$this->fsCacheEnabled()) {
            return $loader();
        }

        $cacheDir = $this->fsCacheDir();
        if ($cacheDir === null) {
            return $loader();
        }

        $cacheFile = $cacheDir . '/' . $this->fsCacheFilename($cacheKey);
        $cacheMtime = @filemtime($cacheFile);

        if ($cacheMtime !== false && $this->fsCacheFresh($cacheMtime, $watchPaths)) {
            $loaded = @include $cacheFile;
            if (is_array($loaded)) {
                return $loaded;
            }
            // Corrupt cache file: fall through and regenerate.
        }

        $data = $loader();
        $this->fsCacheWrite($cacheFile, $data);
        return $data;
    }

    /**
     * Drop a specific filesystem cache file. Called from save() paths so a
     * concurrent reader on the same request can't pick up a stale entry
     * before the mtime check would have caught it.
     */
    protected function fsCacheForget(string $cacheKey): void
    {
        $cacheDir = $this->fsCacheDir();
        if ($cacheDir === null) {
            return;
        }
        $cacheFile = $cacheDir . '/' . $this->fsCacheFilename($cacheKey);
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    /**
     * CONTENT_CACHE env var gate. Default OFF so tests, CI, and unconfigured
     * sites get the legacy behaviour.
     */
    protected function fsCacheEnabled(): bool
    {
        $flag = getenv('CONTENT_CACHE');
        if ($flag === false || $flag === '') {
            // Also check $_ENV / $_SERVER -- PHP-FPM sometimes only populates those.
            $flag = $_ENV['CONTENT_CACHE'] ?? $_SERVER['CONTENT_CACHE'] ?? '';
        }
        return is_string($flag) && strtolower(trim($flag)) === 'true';
    }

    /**
     * Resolve storage/cache/ relative to the project root.
     *
     * Returns null if the directory cannot be created or is not writable;
     * callers fall back to no-cache behaviour in that case rather than
     * throwing -- a slow render beats a broken render.
     */
    protected function fsCacheDir(): ?string
    {
        $root = defined('BIRD_PROJECT_ROOT')
            ? BIRD_PROJECT_ROOT
            : dirname(__DIR__, 2);

        $dir = $root . '/storage/cache';
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
     * Map a cache key to a safe filename. Repositories pass keys like
     * "articles-index" or "services-residential"; we strip anything that
     * isn't alphanumeric, dash, or underscore to keep the filename a safe
     * leaf inside storage/cache/.
     */
    private function fsCacheFilename(string $cacheKey): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $cacheKey) ?? 'cache';
        return $safe . '.php';
    }

    /**
     * True when no watched path has been touched since the cache was written.
     *
     * Walks each watch path: if it's a file, use its mtime; if a directory,
     * use the youngest mtime among its direct .md / .yaml / .meta.yaml
     * descendants (recursive one level for bundle layouts). This is cheap
     * enough on hundreds of files that doing it on every request is still
     * a win over re-parsing YAML + rendering markdown.
     *
     * @param int          $cacheMtime
     * @param list<string> $watchPaths
     */
    private function fsCacheFresh(int $cacheMtime, array $watchPaths): bool
    {
        foreach ($watchPaths as $path) {
            $youngest = $this->fsCacheYoungestMtime($path);
            if ($youngest > $cacheMtime) {
                return false;
            }
        }
        return true;
    }

    /**
     * Youngest mtime under $path, including $path itself.
     *
     * For directories we glob `*` (catches added/removed entries via the
     * parent's mtime) plus `*` + `*\/*` for nested bundles, looking at the
     * mtime of every matched entry. Glob is cheaper than recursive
     * directory iterators and matches the patterns the repositories
     * themselves already use to find content.
     */
    private function fsCacheYoungestMtime(string $path): int
    {
        $pathMtime = @filemtime($path);
        $youngest = $pathMtime !== false ? $pathMtime : 0;

        if (!is_dir($path)) {
            return $youngest;
        }

        // Directory mtime changes when entries are added/removed; that
        // already invalidates the cache for new and deleted files. For
        // in-place edits we still need to scan the entries themselves.
        $patterns = [
            $path . '/*',                  // top-level files + subdirs
            $path . '/*/*',                // bundle index.md / meta.yaml
            $path . '/*/*/*',              // category/slug/* inside bundles
        ];

        foreach ($patterns as $pattern) {
            $matches = @glob($pattern) ?: [];
            foreach ($matches as $entry) {
                $entryMtime = @filemtime($entry);
                if ($entryMtime !== false && $entryMtime > $youngest) {
                    $youngest = $entryMtime;
                }
            }
        }

        return $youngest;
    }

    /**
     * Atomic write of the cache file as `<?php return [...];`.
     *
     * Uses var_export so the file is plain PHP and opcache will compile
     * it on first include. Mirrors AtomicMarkdownWrite::atomicWrite() so
     * a crash mid-write can't leave a half-serialized array on disk.
     *
     * @param string $path
     * @param array  $data
     */
    private function fsCacheWrite(string $path, array $data): void
    {
        $payload = "<?php\n\nreturn " . var_export($data, true) . ";\n";

        $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return; // Cache write failures are silent: the engine still works.
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
        }
    }
}
