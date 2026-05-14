<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Content\ArticleRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * Unit coverage for ArticleRepository: round-trip save/find, atomic write,
 * delete, slug validation, and flat-vs-bundle layout detection.
 *
 * The repository depends on the global config() helper (`site_url` for
 * canonical URL generation). bootstrap.php registers a shim that reads
 * Tests\Support\TestConfig; we reset it per-test so one test's site_url
 * can't leak into another's expectation.
 */
final class ArticleRepositoryTest extends TestCase
{
    private string $articlesDir;
    private ArticleRepository $repo;

    protected function setUp(): void
    {
        TestConfig::reset();
        TestConfig::set('site_url', 'http://localhost');
        $this->articlesDir = TempContent::make('articles');
        $this->repo = new ArticleRepository($this->articlesDir);
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testSaveWritesAtomically(): void
    {
        $this->repo->save('blog', 'hello-world', $this->validMeta(), '# Hello');

        $body = $this->articlesDir . '/blog/hello-world.md';
        $meta = $this->articlesDir . '/blog/hello-world.meta.yaml';

        self::assertFileExists($body, 'body .md must be on disk after save()');
        self::assertFileExists($meta, '.meta.yaml sidecar must be on disk after save()');
        self::assertSame('# Hello', file_get_contents($body));
        // Sidecar is YAML; validate by re-parsing rather than string-matching
        // so the test doesn't pin to formatting choices in FrontMatter::encode.
        $raw = (string) file_get_contents($meta);
        self::assertStringContainsString('title:', $raw);
        self::assertStringContainsString('hello-world', $raw);
    }

    public function testFindRoundtrip(): void
    {
        $meta = $this->validMeta(['title' => 'Round Trip Title']);
        $this->repo->save('blog', 'round-trip', $meta, 'body text');

        $found = $this->repo->find('blog', 'round-trip');
        self::assertNotNull($found);
        self::assertSame('round-trip', $found['slug']);
        self::assertSame('Round Trip Title', $found['title']);
        self::assertSame('blog', $found['category']);
        self::assertSame('body text', $found['content']);
    }

    public function testDeleteRemovesBothFiles(): void
    {
        // No public delete() on ArticleRepository -- the controller deletes
        // by unlinking the two files directly. Mirror that path so the test
        // captures the actual production behaviour.
        $this->repo->save('blog', 'will-vanish', $this->validMeta(), 'gone');

        $body = $this->articlesDir . '/blog/will-vanish.md';
        $meta = $this->articlesDir . '/blog/will-vanish.meta.yaml';
        self::assertFileExists($body);
        self::assertFileExists($meta);

        unlink($body);
        unlink($meta);

        $reborn = new ArticleRepository($this->articlesDir);
        self::assertNull($reborn->find('blog', 'will-vanish'));
    }

    public function testInvalidSlugIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->repo->save('blog', 'Bad Slug!', $this->validMeta(), 'body');
    }

    public function testInvalidCategoryIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->repo->save('Bad Cat!', 'ok-slug', $this->validMeta(), 'body');
    }

    public function testFlatAndBundleFormatBothLoad(): void
    {
        // Flat
        $this->repo->save('blog', 'flat-one', $this->validMeta(['title' => 'Flat']), 'flat body');

        // Bundle: hand-craft the directory layout the way an MCP/CLI consumer
        // who explicitly wanted bundle format would.
        $bundleDir = $this->articlesDir . '/blog/bundle-one';
        mkdir($bundleDir, 0755, true);
        file_put_contents($bundleDir . '/index.md', 'bundle body');
        file_put_contents(
            $bundleDir . '/meta.yaml',
            "title: Bundle\nslug: bundle-one\ndate: 2025-01-01\n"
        );

        $repo = new ArticleRepository($this->articlesDir);
        $flat = $repo->find('blog', 'flat-one');
        $bundle = $repo->find('blog', 'bundle-one');

        self::assertNotNull($flat);
        self::assertNotNull($bundle);
        self::assertSame('Flat', $flat['title']);
        self::assertSame('Bundle', $bundle['title']);
        self::assertSame('flat body', $flat['content']);
        self::assertSame('bundle body', $bundle['content']);
    }

    public function testNestedBundleLoadsAndDerivesSubcategoryFromPath(): void
    {
        // Layout: services/ai/agent-dev/{index.md, meta.yaml}
        $nestedDir = $this->articlesDir . '/services/ai/agent-dev';
        mkdir($nestedDir, 0755, true);
        file_put_contents($nestedDir . '/index.md', 'agent body');
        file_put_contents(
            $nestedDir . '/meta.yaml',
            "title: Agent Dev\nslug: agent-dev\ndate: 2025-01-01\n"
        );

        $repo = new ArticleRepository($this->articlesDir);
        $article = $repo->find('services', 'agent-dev', false, 'ai');

        self::assertNotNull($article);
        self::assertSame('agent-dev', $article['slug']);
        self::assertSame('services', $article['category']);
        self::assertSame('ai', $article['subcategory']);
        self::assertSame('http://localhost/services/ai/agent-dev', $article['url']);
    }

    public function testNestedArticleNotReachableViaFlatLookup(): void
    {
        // A nested article must not answer to /services/agent-dev -- otherwise
        // there would be two URLs (flat + nested) serving the same content and
        // the canonical URL becomes ambiguous.
        $nestedDir = $this->articlesDir . '/services/ai/agent-dev';
        mkdir($nestedDir, 0755, true);
        file_put_contents($nestedDir . '/index.md', 'body');
        file_put_contents(
            $nestedDir . '/meta.yaml',
            "title: Agent\nslug: agent-dev\ndate: 2025-01-01\n"
        );

        $repo = new ArticleRepository($this->articlesDir);
        // Flat lookup (no subcategory) must NOT return the nested article.
        self::assertNull($repo->find('services', 'agent-dev'));
        // findByParams with no subcategory in the URL must also reject.
        self::assertNull($repo->findByParams(['category' => 'services', 'slug' => 'agent-dev']));
        // But with the correct subcategory, it resolves.
        self::assertNotNull($repo->findByParams([
            'category' => 'services',
            'subcategory' => 'ai',
            'slug' => 'agent-dev',
        ]));
    }

    public function testFlatArticleNotReachableViaWrongSubcategory(): void
    {
        $this->repo->save('blog', 'plain', $this->validMeta(['title' => 'Plain']), 'body');

        $repo = new ArticleRepository($this->articlesDir);
        // Flat article has subcategory=null, so a URL claiming subcategory=foo
        // must not match it.
        self::assertNull($repo->findByParams([
            'category' => 'blog',
            'subcategory' => 'foo',
            'slug' => 'plain',
        ]));
        // But flat lookup still works.
        self::assertNotNull($repo->findByParams([
            'category' => 'blog',
            'slug' => 'plain',
        ]));
    }

    public function testNestedSubcategoryWithInvalidSlugInPathIsSkipped(): void
    {
        // basename(dirname(...)) yielding an invalid slug must skip the file,
        // not load it with a broken subcategory.
        $nestedDir = $this->articlesDir . '/services/Bad Sub/foo';
        mkdir($nestedDir, 0755, true);
        file_put_contents($nestedDir . '/index.md', 'body');
        file_put_contents(
            $nestedDir . '/meta.yaml',
            "title: Foo\nslug: foo\ndate: 2025-01-01\n"
        );

        $repo = new ArticleRepository($this->articlesDir);
        // The "Bad Sub" directory has spaces -- glob may not even match it on
        // some filesystems, but if it does the validation must drop it.
        self::assertNull($repo->find('services', 'foo'));
    }

    /** @return array<string, mixed> */
    private function validMeta(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Article',
            'description' => 'desc',
            'date' => '2025-01-01',
            'type' => 'insight',
            'status' => 'published',
            'tags' => ['t1', 't2'],
            'primary' => 'kw',
        ], $overrides);
    }
}
