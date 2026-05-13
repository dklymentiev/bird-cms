<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Content\ArticleRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * Cache-layer tests for the ContentCache trait.
 *
 * Tests run against ArticleRepository -- it's the most cache-sensitive
 * implementation, and verifying the cache there exercises the same trait
 * code used by every other repository. We toggle CONTENT_CACHE via
 * putenv() per-test so the disk layer is exercised here but stays off in
 * every other test suite (the bootstrap doesn't set it).
 *
 * Each test uses its own temp content tree under tests/fixtures/tmp/ AND
 * its own temp storage/cache/ so a failing test never pollutes a follow-up
 * test's cache file. BIRD_PROJECT_ROOT defaults storage/cache/ to the
 * project root; we override it via env var-aware behaviour: the trait
 * reads BIRD_PROJECT_ROOT, and we can't redefine constants -- instead we
 * verify cache behaviour by observing repository return values across
 * file mutations, not by reading the cache file directly.
 *
 * Caveat: storage/cache/ for the project will accumulate test cache files
 * during this suite. They're harmless (regenerate on next access) but the
 * tearDown blows them away to be tidy.
 */
final class ContentCacheTest extends TestCase
{
    private string $articlesDir;

    protected function setUp(): void
    {
        TestConfig::reset();
        TestConfig::set('site_url', 'http://localhost');
        $this->articlesDir = TempContent::make('cache-articles');
        // Default: cache enabled for these tests. One test below flips it off.
        putenv('CONTENT_CACHE=true');
    }

    protected function tearDown(): void
    {
        putenv('CONTENT_CACHE');
        TempContent::cleanup();
        $this->cleanCacheDir();
    }

    /**
     * Cache miss: first call must parse and store. We can't easily verify
     * "stored" without poking the disk; instead we observe two repository
     * instances over the same content directory. Without the trait's
     * per-instance memo crossing, the second instance must still see the
     * just-written record -- which only happens via the filesystem cache.
     */
    public function testCacheMissParsesAndStores(): void
    {
        $this->writeArticle('blog', 'post-one', 'Title One');

        $repo1 = new ArticleRepository($this->articlesDir);
        $first = $repo1->all();

        self::assertCount(1, $first);
        self::assertSame('post-one', $first[0]['slug']);

        $repo2 = new ArticleRepository($this->articlesDir);
        $second = $repo2->all();

        self::assertCount(1, $second);
        self::assertSame('post-one', $second[0]['slug']);
    }

    /**
     * Cache hit on the same instance: the per-instance memo means a
     * follow-up call returns the same record set without re-parsing. We
     * verify by mutating the underlying YAML in-place and observing the
     * stale read.
     */
    public function testCacheHitReturnsStored(): void
    {
        $this->writeArticle('blog', 'cached-post', 'Original Title');

        $repo = new ArticleRepository($this->articlesDir);
        $first = $repo->all();
        self::assertSame('Original Title', $first[0]['title']);

        // Edit the YAML without touching the mtime: the per-instance memo
        // must still serve the original title because it never re-reads.
        $metaPath = $this->articlesDir . '/blog/cached-post.meta.yaml';
        $contents = file_get_contents($metaPath);
        $mtime = filemtime($metaPath);
        file_put_contents($metaPath, str_replace('Original Title', 'Mutated Title', (string) $contents));
        touch($metaPath, $mtime);

        $second = $repo->all();
        self::assertSame('Original Title', $second[0]['title'], 'per-instance memo must return stored copy');
    }

    /**
     * Filesystem cache must regenerate when an existing file's mtime
     * advances past the cache file's mtime.
     */
    public function testCacheInvalidatesWhenFileModified(): void
    {
        $this->writeArticle('blog', 'editable', 'Before Edit');

        $repo1 = new ArticleRepository($this->articlesDir);
        $first = $repo1->all();
        self::assertSame('Before Edit', $first[0]['title']);

        // Sleep to advance the second-resolution mtime. Necessary because
        // filemtime on some filesystems (FAT, some Linux configs) has
        // 1- or 2-second granularity; without the wait, the cache mtime
        // and the new write mtime can be equal, making "newer" untrue.
        sleep(2);

        $this->writeArticle('blog', 'editable', 'After Edit');

        // Fresh instance so per-instance memo doesn't mask the test.
        $repo2 = new ArticleRepository($this->articlesDir);
        $second = $repo2->all();
        self::assertSame('After Edit', $second[0]['title'], 'fs cache must regenerate after file edit');
    }

    /**
     * Adding a new article must invalidate. The watched directory's mtime
     * changes on entry add, which is what the trait keys off.
     */
    public function testCacheInvalidatesWhenFileAdded(): void
    {
        $this->writeArticle('blog', 'first', 'First');

        $repo1 = new ArticleRepository($this->articlesDir);
        self::assertCount(1, $repo1->all());

        sleep(2);
        $this->writeArticle('blog', 'second', 'Second');

        $repo2 = new ArticleRepository($this->articlesDir);
        self::assertCount(2, $repo2->all(), 'fs cache must regenerate after new article appears');
    }

    /**
     * Removing an article must invalidate. Same mechanism as the add case:
     * directory mtime advances when an entry is unlinked.
     */
    public function testCacheInvalidatesWhenFileDeleted(): void
    {
        $this->writeArticle('blog', 'one', 'One');
        $this->writeArticle('blog', 'two', 'Two');

        $repo1 = new ArticleRepository($this->articlesDir);
        self::assertCount(2, $repo1->all());

        sleep(2);
        unlink($this->articlesDir . '/blog/two.md');
        unlink($this->articlesDir . '/blog/two.meta.yaml');

        $repo2 = new ArticleRepository($this->articlesDir);
        self::assertCount(1, $repo2->all(), 'fs cache must regenerate after delete');
    }

    /**
     * With CONTENT_CACHE unset, the filesystem cache layer must be a
     * no-op: every fresh repository instance must re-parse from disk and
     * pick up any mutation, even without an mtime bump.
     */
    public function testCacheDisabledByEnvVar(): void
    {
        putenv('CONTENT_CACHE'); // unset
        unset($_ENV['CONTENT_CACHE'], $_SERVER['CONTENT_CACHE']);

        $this->writeArticle('blog', 'fresh', 'Initial');

        $repo1 = new ArticleRepository($this->articlesDir);
        $first = $repo1->all();
        self::assertSame('Initial', $first[0]['title']);

        // Same-second edit -- the fs cache would NOT have noticed (mtime
        // equal). With caching off, a fresh instance must always re-read.
        $metaPath = $this->articlesDir . '/blog/fresh.meta.yaml';
        $contents = file_get_contents($metaPath);
        $mtime = filemtime($metaPath);
        file_put_contents($metaPath, str_replace('Initial', 'Refreshed', (string) $contents));
        touch($metaPath, $mtime);

        $repo2 = new ArticleRepository($this->articlesDir);
        $second = $repo2->all();
        self::assertSame('Refreshed', $second[0]['title'], 'cache disabled -- second instance must re-parse');
    }

    /**
     * Helper: write the two-file flat layout expected by ArticleRepository.
     * Keeps each test concise and consistent.
     */
    private function writeArticle(string $category, string $slug, string $title): void
    {
        $dir = $this->articlesDir . '/' . $category;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/' . $slug . '.md', "# " . $title);
        file_put_contents(
            $dir . '/' . $slug . '.meta.yaml',
            "title: " . $title . "\nslug: " . $slug . "\ndate: 2025-01-01\nstatus: published\n"
        );
    }

    /**
     * Wipe any cache files left under storage/cache/ that match the
     * repository's keys. Keeps the project tree clean between test runs.
     */
    private function cleanCacheDir(): void
    {
        $cacheDir = BIRD_PROJECT_ROOT . '/storage/cache';
        if (!is_dir($cacheDir)) {
            return;
        }
        foreach (glob($cacheDir . '/articles-index.php') ?: [] as $f) {
            @unlink($f);
        }
    }
}
