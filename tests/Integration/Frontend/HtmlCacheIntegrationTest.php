<?php

declare(strict_types=1);

namespace Tests\Integration\Frontend;

use App\Content\ArticleRepository;
use App\Http\Frontend\LlmsTxtController;
use App\Support\HtmlCache;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * End-to-end integration coverage for HtmlCache wired through a real
 * frontend controller (LlmsTxtController -- chosen because it doesn't
 * need a ThemeManager, has stable output, and is one of the five
 * cacheable routes in the dispatcher).
 *
 * The dispatcher's withCache() helper isn't directly callable from tests
 * (private method on a fully-wired Dispatcher), so we replicate its
 * shape here: shouldServe gate, get-or-render, put on miss. This keeps
 * the test focused on the cache contract rather than the dispatcher's
 * routing table.
 *
 * Covers:
 *   - first GET renders + persists the body on disk
 *   - second GET serves the cached copy without invoking the controller
 *   - query strings bypass both get and put
 *   - admin cookie bypasses both
 *   - article save() invalidates the cache file for the article URL
 *   - settings flushAll() wipes the whole cache tree (including subdirs)
 */
final class HtmlCacheIntegrationTest extends TestCase
{
    private string $articlesDir;
    private ArticleRepository $articles;

    protected function setUp(): void
    {
        TestConfig::reset();
        TestConfig::set('site_url', 'http://example.test');
        TestConfig::set('site_name', 'Example');
        TestConfig::set('site_description', 'Tagline');

        $this->articlesDir = TempContent::make('htmlcache-int');
        $this->articles = new ArticleRepository($this->articlesDir);

        putenv('HTML_CACHE=true');

        // Wipe any cache left from previous runs so we start from a
        // known-empty state per test.
        HtmlCache::flushAll();
    }

    protected function tearDown(): void
    {
        HtmlCache::flushAll();
        TempContent::cleanup();
        putenv('HTML_CACHE');
        unset($_ENV['HTML_CACHE'], $_SERVER['HTML_CACHE']);
    }

    public function testFirstHitPersistsCacheFile(): void
    {
        $this->seedArticle('blog', 'first', 'First Title');

        $key = 'llms.txt';
        $eligible = HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/llms.txt', 'QUERY_STRING' => ''],
            []
        );
        self::assertTrue($eligible, 'precondition: /llms.txt must be cache-eligible');

        $body = $this->renderWithCache($key, $eligible);
        self::assertStringContainsString('Example', $body);

        // Cache file appeared on disk.
        $dir = HtmlCache::dir();
        self::assertNotNull($dir);
        self::assertFileExists($dir . '/llms.txt');
    }

    public function testRepeatHitServesFromDiskWithoutReRendering(): void
    {
        $this->seedArticle('blog', 'first', 'First Title');

        $key = 'llms.txt';
        $eligible = true;

        // First call populates the cache.
        $first = $this->renderWithCache($key, $eligible);
        $path = (string) HtmlCache::dir() . '/llms.txt';
        $mtimeAfterFirst = filemtime($path);

        // Replace the underlying article so a re-render would change the
        // output. The cached copy must still serve the old bytes because
        // the cache wasn't invalidated.
        $this->seedArticle('blog', 'second', 'Second Title');

        // Wait long enough that any re-render would advance mtime.
        usleep(50000);

        $second = $this->renderWithCacheNoRender($key, $eligible);
        self::assertSame($first, $second, 'cache hit must serve the persisted bytes verbatim');
        self::assertSame(
            $mtimeAfterFirst,
            filemtime($path),
            'cache hit must not rewrite the file'
        );
        // The second-render bytes must NOT contain the new article -- the
        // test would have caught it on the live render path.
        self::assertStringNotContainsString('Second Title', $second);
    }

    public function testQueryStringBypassesGetAndPut(): void
    {
        $this->seedArticle('blog', 'first', 'First');

        $eligible = HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/llms.txt?cb=1', 'QUERY_STRING' => 'cb=1'],
            []
        );
        self::assertFalse($eligible, 'query string must bypass cache');

        // Render without cache wrap -- no file should land on disk.
        ob_start();
        (new LlmsTxtController($this->articles, ''))->handle();
        ob_end_clean();

        $path = (string) HtmlCache::dir() . '/llms.txt';
        self::assertFileDoesNotExist($path, 'no put() may run when shouldServe returned false');
    }

    public function testAdminCookieBypassesCache(): void
    {
        $this->seedArticle('blog', 'first', 'First');

        $eligible = HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/llms.txt', 'QUERY_STRING' => ''],
            ['bird_admin' => 'session-id-here']
        );
        self::assertFalse($eligible, 'admin session cookie must bypass cache');

        // Same for the configured dim_admin name.
        $eligible2 = HtmlCache::shouldServe(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/llms.txt', 'QUERY_STRING' => ''],
            ['dim_admin' => 'whatever']
        );
        self::assertFalse($eligible2);
    }

    public function testRepositorySaveInvalidatesArticleCache(): void
    {
        // Pre-populate cache with the article URL key.
        $articleKey = 'blog/welcome';
        HtmlCache::put($articleKey, '<html>stale</html>');
        self::assertSame('<html>stale</html>', HtmlCache::get($articleKey));

        $this->articles->save('blog', 'welcome', [
            'title' => 'Welcome',
            'description' => 'desc',
            'date' => '2025-01-01',
            'type' => 'insight',
            'status' => 'published',
            'tags' => [],
            'primary' => 'kw',
        ], '# body');

        // The save() hook must have forgotten the article URL.
        self::assertNull(
            HtmlCache::get($articleKey),
            'article save must invalidate the cached article URL'
        );
        // Homepage and llms.txt are also forgotten by the same hook.
        // We can't assert "was forgotten" without first writing them, so
        // pre-populate and re-test.
        HtmlCache::put('home', '<html>old home</html>');
        HtmlCache::put('llms.txt', 'old llms');
        HtmlCache::put('blog', '<html>old cat</html>');

        $this->articles->save('blog', 'welcome', [
            'title' => 'Welcome 2',
            'description' => 'desc',
            'date' => '2025-01-01',
            'type' => 'insight',
            'status' => 'published',
            'tags' => [],
            'primary' => 'kw',
        ], '# body');

        self::assertNull(HtmlCache::get('home'),     'home must be invalidated on article save');
        self::assertNull(HtmlCache::get('llms.txt'), 'llms.txt must be invalidated on article save');
        self::assertNull(HtmlCache::get('blog'),     'category index must be invalidated on article save');
    }

    public function testFlushAllWipesCacheTree(): void
    {
        HtmlCache::put('home', '<html>home</html>');
        HtmlCache::put('blog/welcome', '<html>a</html>');
        HtmlCache::put('articles/blog/welcome', '<html>b</html>');
        HtmlCache::put('llms.txt', 'txt');

        HtmlCache::flushAll();

        self::assertNull(HtmlCache::get('home'));
        self::assertNull(HtmlCache::get('blog/welcome'));
        self::assertNull(HtmlCache::get('articles/blog/welcome'));
        self::assertNull(HtmlCache::get('llms.txt'));
    }

    /**
     * Replicate Dispatcher::withCache() in shape -- get-or-render, then
     * put on miss. The dispatcher's helper is private so we re-implement
     * here; the actual implementation is small enough that this stays
     * faithful to production.
     */
    private function renderWithCache(string $key, bool $eligible): string
    {
        if (!$eligible) {
            ob_start();
            (new LlmsTxtController($this->articles, ''))->handle();
            return (string) ob_get_clean();
        }
        $cached = HtmlCache::get($key);
        if ($cached !== null) {
            return $cached;
        }
        ob_start();
        (new LlmsTxtController($this->articles, ''))->handle();
        $captured = (string) ob_get_clean();
        HtmlCache::put($key, $captured);
        return $captured;
    }

    /**
     * Read-only variant: only invokes get(). Used to verify a hit doesn't
     * re-render or rewrite the file.
     */
    private function renderWithCacheNoRender(string $key, bool $eligible): string
    {
        if (!$eligible) {
            self::fail('renderWithCacheNoRender requires eligible=true');
        }
        $cached = HtmlCache::get($key);
        self::assertNotNull($cached, 'expected cache hit');
        return $cached;
    }

    /**
     * Helper: write the two-file flat layout ArticleRepository expects.
     */
    private function seedArticle(string $category, string $slug, string $title): void
    {
        $dir = $this->articlesDir . '/' . $category;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/' . $slug . '.md', "# " . $title);
        file_put_contents(
            $dir . '/' . $slug . '.meta.yaml',
            "title: " . $title . "\nslug: " . $slug . "\ndate: 2025-01-01\nstatus: published\ntype: insight\n"
        );
    }
}
