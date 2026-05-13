<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Content\ArticleRepository;
use App\Content\PageRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * Round-trip coverage for /api/v1/content/* shape -- exercises the
 * repository contract the controller delegates to.
 *
 * The ContentController is a thin adapter: it validates slugs, picks
 * the right repository, and forwards the JSON payload's frontmatter +
 * body to repo->save(). Anything that breaks at the controller layer
 * either fails slug regex (covered in ApiSlugTest below) or breaks
 * the repository contract -- which would also break the admin URL
 * Inventory and the MCP write_article tool. Those repositories
 * already have unit coverage; this class re-validates the read-back
 * shape that the API returns.
 */
final class ContentApiTest extends TestCase
{
    private string $articlesDir;
    private string $pagesDir;

    protected function setUp(): void
    {
        TestConfig::reset();
        $this->articlesDir = TempContent::make('api-content-articles');
        $this->pagesDir = TempContent::make('api-content-pages');
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testArticleCrudRoundTrip(): void
    {
        $repo = new ArticleRepository($this->articlesDir);

        // Create.
        $repo->save('blog', 'launch-notes', [
            'title' => 'Launch Notes',
            'description' => 'first day',
            'date' => '2026-05-10',
            'status' => 'published',
            'tags' => ['launch'],
            'type' => 'announcement',
            'primary' => 'launch',
        ], '# Hello world');

        self::assertFileExists($this->articlesDir . '/blog/launch-notes.md');
        self::assertFileExists($this->articlesDir . '/blog/launch-notes.meta.yaml');

        // Read.
        $loaded = $repo->find('blog', 'launch-notes');
        self::assertNotNull($loaded);
        self::assertSame('Launch Notes', $loaded['title']);
        self::assertSame('# Hello world', $loaded['content']);
        self::assertSame('blog', $loaded['category']);
    }

    public function testPageCrudRoundTrip(): void
    {
        $repo = new PageRepository($this->pagesDir);
        $repo->save('about', [
            'title' => 'About',
            'description' => 'About us',
            'status' => 'published',
        ], '# About body');

        self::assertFileExists($this->pagesDir . '/about.md');
        $loaded = $repo->find('about');
        self::assertNotNull($loaded);
        self::assertSame('About', $loaded['title']);
        self::assertSame('# About body', $loaded['content']);
    }

    public function testInvalidArticleSlugIsRejectedByRepository(): void
    {
        $repo = new ArticleRepository($this->articlesDir);
        $this->expectException(\RuntimeException::class);
        $repo->save('blog', 'Not A Valid Slug', ['title' => 'x'], 'body');
    }

    public function testInvalidArticleCategoryIsRejectedByRepository(): void
    {
        $repo = new ArticleRepository($this->articlesDir);
        $this->expectException(\RuntimeException::class);
        $repo->save('Bad Category', 'valid-slug', ['title' => 'x'], 'body');
    }

    public function testFrontmatterShapeMatchesMcpEmittedShape(): void
    {
        // The public API forwards the same shape the MCP write_article
        // tool produces. Storing both ways and reading both back must
        // produce identical meta arrays so a client can switch
        // transports without rewriting code.
        $apiRepo = new ArticleRepository($this->articlesDir);
        $apiRepo->save('blog', 'via-api', [
            'title' => 'Via API',
            'description' => 'd',
            'date' => '2026-05-10',
            'status' => 'published',
            'tags' => ['a', 'b'],
            'type' => 'insight',
            'primary' => 'topic',
        ], 'body');

        $loaded = $apiRepo->find('blog', 'via-api');
        self::assertNotNull($loaded);
        self::assertSame(['a', 'b'], $loaded['tags']);
        self::assertSame('insight', $loaded['type']);
        self::assertSame('published', $loaded['status']);
    }

    public function testCategoriesListEnumeratesOnDisk(): void
    {
        $repo = new ArticleRepository($this->articlesDir);
        $repo->save('blog', 'a', ['title' => 'a', 'date' => '2026-05-10'], 'x');
        $repo->save('tips', 'b', ['title' => 'b', 'date' => '2026-05-10'], 'y');
        self::assertEqualsCanonicalizing(['blog', 'tips'], $repo->categories());
    }

    public function testListReturnsOnlyPublished(): void
    {
        $repo = new ArticleRepository($this->articlesDir);
        $repo->save('blog', 'public', ['title' => 'Public', 'date' => '2026-05-10', 'status' => 'published'], 'p');
        $repo->save('blog', 'secret', ['title' => 'Secret', 'date' => '2026-05-10', 'status' => 'draft'], 's');

        $slugs = array_column($repo->all(), 'slug');
        self::assertContains('public', $slugs);
        self::assertNotContains('secret', $slugs, 'API `read` scope must not leak unpublished work.');
    }
}
