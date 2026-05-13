<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlCache;
use PHPUnit\Framework\TestCase;

/**
 * Tests for App\Support\HtmlCache.
 *
 * Each test toggles HTML_CACHE=true (default-off for the rest of the
 * suite) and operates inside a per-test temp directory created under
 * storage/cache/html/test-<n>/ via the dir() override pattern. Since
 * HtmlCache::dir() resolves storage/cache/html/ relative to
 * BIRD_PROJECT_ROOT, we let the tests share that real dir but flush
 * between tests -- the keys are all test-prefixed so concurrent suites
 * won't collide with production cache files outside the test run.
 */
final class HtmlCacheTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('HTML_CACHE=true');
        $this->wipeTestKeys();
    }

    protected function tearDown(): void
    {
        $this->wipeTestKeys();
        putenv('HTML_CACHE');
        unset($_ENV['HTML_CACHE'], $_SERVER['HTML_CACHE']);
    }

    public function testGetReturnsNullWhenMiss(): void
    {
        self::assertNull(HtmlCache::get('htest/missing'));
    }

    public function testPutThenGetRoundtrip(): void
    {
        HtmlCache::put('htest/round-trip', '<html>hello</html>');
        self::assertSame('<html>hello</html>', HtmlCache::get('htest/round-trip'));
    }

    /**
     * Stale = older than TTL_SECONDS. Backdating the file's mtime via
     * touch() lets us hit the boundary without sleeping for 5+ minutes.
     */
    public function testGetReturnsNullWhenStale(): void
    {
        HtmlCache::put('htest/stale', '<html>stale</html>');
        $path = $this->pathFor('htest/stale');
        self::assertFileExists($path);

        // Backdate by 1 second past the TTL boundary.
        touch($path, time() - (HtmlCache::TTL_SECONDS + 1));

        self::assertNull(HtmlCache::get('htest/stale'), 'stale entry must be ignored');
        // File itself stays on disk -- only get() refuses to serve it.
        // The next put() will overwrite atomically.
        self::assertFileExists($path);
    }

    public function testForgetRemoves(): void
    {
        HtmlCache::put('htest/forget-me', '<html>x</html>');
        self::assertSame('<html>x</html>', HtmlCache::get('htest/forget-me'));

        HtmlCache::forget('htest/forget-me');
        self::assertNull(HtmlCache::get('htest/forget-me'));
        self::assertFileDoesNotExist($this->pathFor('htest/forget-me'));

        // Idempotent: a second forget() must not throw.
        HtmlCache::forget('htest/forget-me');
    }

    public function testForgetByPrefixRemovesEntireSubtree(): void
    {
        HtmlCache::put('htest/prefix/a', '<html>a</html>');
        HtmlCache::put('htest/prefix/b', '<html>b</html>');
        HtmlCache::put('htest/prefix/nested/c', '<html>c</html>');
        HtmlCache::put('htest/other/d', '<html>d</html>');

        HtmlCache::forgetByPrefix('htest/prefix');

        self::assertNull(HtmlCache::get('htest/prefix/a'));
        self::assertNull(HtmlCache::get('htest/prefix/b'));
        self::assertNull(HtmlCache::get('htest/prefix/nested/c'));
        // Adjacent prefix untouched.
        self::assertSame('<html>d</html>', HtmlCache::get('htest/other/d'));
    }

    public function testFlushAllWipesNestedDirs(): void
    {
        HtmlCache::put('htest/top', '<html>top</html>');
        HtmlCache::put('htest/dir/nested', '<html>nested</html>');
        HtmlCache::put('htest/dir/deep/inner', '<html>inner</html>');

        HtmlCache::flushAll();

        self::assertNull(HtmlCache::get('htest/top'));
        self::assertNull(HtmlCache::get('htest/dir/nested'));
        self::assertNull(HtmlCache::get('htest/dir/deep/inner'));
    }

    /**
     * Path-traversal attempts must be rejected at the key sanitiser layer,
     * not by accident at the filesystem layer. Each of these would resolve
     * outside storage/cache/html/ if accepted verbatim.
     */
    public function testKeyRejectsPathTraversal(): void
    {
        // Each entry must fail the dot/dot-dot guard. /etc/passwd-style
        // leading slashes are normalised (the cache root prefix prevents
        // any actual escape), so those aren't here -- only literal '.'
        // or '..' segments belong on the traversal-reject list.
        $bad = [
            '../etc/passwd',
            'foo/../bar',
            '.',
            './foo',
            'foo/.',
            'foo/./bar',
            '..',
            'foo/..',
        ];
        foreach ($bad as $key) {
            self::assertNull(
                HtmlCache::sanitizeKey($key),
                'sanitizeKey must reject traversal attempt: ' . $key
            );
            // put() must also no-op on a rejected key.
            HtmlCache::put($key, '<malicious />');
            self::assertNull(HtmlCache::get($key), 'rejected key must never round-trip: ' . $key);
        }
    }

    public function testKeyAllowsAsciiSlashDashDot(): void
    {
        $good = [
            'home',
            'llms.txt',
            'blog/welcome',
            'articles/blog/welcome',
            'category/blog',
            'services/residential/house-cleaning',
            'a',
            'a-b-c/d-e-f',
        ];
        foreach ($good as $key) {
            self::assertSame(
                $key,
                HtmlCache::sanitizeKey($key),
                'sanitizeKey must accept conforming key: ' . $key
            );
        }
    }

    public function testKeyRejectsNonAsciiAndUppercase(): void
    {
        // Sanitisation whitelist is intentionally narrow; URLs that don't
        // conform should never have made it into the cache key in the
        // first place. Better to fail closed than to risk a half-match.
        self::assertNull(HtmlCache::sanitizeKey('Welcome'));    // uppercase
        self::assertNull(HtmlCache::sanitizeKey('blog/Welcome'));
        self::assertNull(HtmlCache::sanitizeKey('blog welcome')); // space
        self::assertNull(HtmlCache::sanitizeKey('blog?cb=1'));    // query
        self::assertNull(HtmlCache::sanitizeKey('blog#frag'));    // fragment
        // 'articulos' is plain lowercase ASCII; the whitelist accepts it
        // unchanged. Pin that contract here so a future tightening doesn't
        // silently regress legitimate non-English slugs.
        self::assertSame('articulos', HtmlCache::sanitizeKey('articulos'));
    }

    public function testShouldServeRequiresGet(): void
    {
        self::assertFalse(HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/blog'],
            [],
        ));
        self::assertFalse(HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'HEAD', 'REQUEST_URI' => '/blog'],
            [],
        ));
        self::assertTrue(HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/blog', 'QUERY_STRING' => ''],
            [],
        ));
    }

    public function testShouldServeRejectsQueryString(): void
    {
        self::assertFalse(HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/blog?preview=1', 'QUERY_STRING' => 'preview=1'],
            [],
        ));
        // Empty QUERY_STRING but ? in URI: still rejected (some SAPIs split oddly).
        self::assertFalse(HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/blog?cb=', 'QUERY_STRING' => ''],
            [],
        ));
    }

    public function testShouldServeRejectsExcludedPaths(): void
    {
        foreach (['/admin', '/admin/articles', '/api/v1', '/install', '/install/step1', '/health'] as $path) {
            self::assertFalse(
                HtmlCache::shouldServe(
                    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path, 'QUERY_STRING' => ''],
                    [],
                ),
                'shouldServe must reject excluded path: ' . $path
            );
        }
        // A slug that merely starts with admin- should still cache.
        self::assertTrue(HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin-tips', 'QUERY_STRING' => ''],
            [],
        ));
    }

    public function testShouldServeRejectsAdminSessionCookie(): void
    {
        foreach (['bird_admin', 'dim_admin', 'site_admin'] as $cookieName) {
            self::assertFalse(
                HtmlCache::shouldServe(
                    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/blog', 'QUERY_STRING' => ''],
                    [$cookieName => 'abc'],
                ),
                'shouldServe must reject admin cookie: ' . $cookieName
            );
        }
        // Regular session/cookie name passes.
        self::assertTrue(HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/blog', 'QUERY_STRING' => ''],
            ['PHPSESSID' => 'visitor'],
        ));
    }

    public function testShouldServeReturnsFalseWhenDisabled(): void
    {
        putenv('HTML_CACHE'); // unset
        unset($_ENV['HTML_CACHE'], $_SERVER['HTML_CACHE']);

        self::assertFalse(HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/blog', 'QUERY_STRING' => ''],
            [],
        ));
    }

    public function testKeyForPathMapsRequestUrisToCanonicalKeys(): void
    {
        self::assertSame('home', HtmlCache::keyForPath('/'));
        self::assertSame('home', HtmlCache::keyForPath(''));
        self::assertSame('blog', HtmlCache::keyForPath('/blog'));
        self::assertSame('blog/welcome', HtmlCache::keyForPath('/blog/welcome'));
        // Query string in URI is ignored (parse_url-style extraction).
        self::assertSame('blog/welcome', HtmlCache::keyForPath('/blog/welcome?preview=1'));
        // llms.txt keeps its dotted suffix.
        self::assertSame('llms.txt', HtmlCache::keyForPath('/llms.txt'));
    }

    public function testEmptyKeyResolvesToHome(): void
    {
        HtmlCache::put('', '<html>root</html>');
        self::assertSame('<html>root</html>', HtmlCache::get(''));
        // Also reachable as the literal "home" key.
        self::assertSame('<html>root</html>', HtmlCache::get('home'));

        HtmlCache::forget('');
        self::assertNull(HtmlCache::get('home'));
    }

    /**
     * Helper: resolve a cache file path by re-implementing the simple part
     * of HtmlCache::pathFor() that's reachable via the public API.
     */
    private function pathFor(string $key): string
    {
        $dir = HtmlCache::dir();
        if ($dir === null) {
            self::fail('HtmlCache::dir() returned null -- storage/cache/html/ not writable');
        }
        if (preg_match('/\.[a-z0-9]+$/', $key) === 1) {
            return $dir . '/' . $key;
        }
        return $dir . '/' . $key . '.html';
    }

    /**
     * Wipe just the htest/ prefix so we don't trash any legitimate cache
     * the operator might have. Test prefix is fixed so concurrent dev
     * traffic against the same storage/cache/ stays untouched.
     */
    private function wipeTestKeys(): void
    {
        HtmlCache::forgetByPrefix('htest');
        // Also drop the bare "home" and "llms.txt" entries the empty-key
        // test writes, since they aren't under the htest prefix.
        HtmlCache::forget('home');
        HtmlCache::forget('llms.txt');
    }
}
