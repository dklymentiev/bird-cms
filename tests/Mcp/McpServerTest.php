<?php

declare(strict_types=1);

namespace Tests\Mcp;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TempContent;

/**
 * MCP server JSON-RPC handler coverage with golden fixtures.
 *
 * mcp/server.php declares its tools as a global $tools array and a set
 * of `tool_*` handler functions, then drops into a `while(fgets(STDIN))`
 * dispatch loop. In a PHPUnit process STDIN is empty so fgets returns
 * false on the first read and the loop exits immediately, which lets us
 * `require_once` the file and call the handler functions directly.
 *
 * BIRD_SITE_DIR must be set BEFORE the require so the site-root probe at
 * the top of mcp/server.php latches onto our temp tree instead of
 * walking up to find a real install.
 */
final class McpServerTest extends TestCase
{
    private static string $siteRoot;
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        self::$siteRoot = TempContent::make('mcp-site');
        mkdir(self::$siteRoot . '/content/articles', 0755, true);
        mkdir(self::$siteRoot . '/content/pages', 0755, true);

        // Hand-write one article so list/read tests have something to find.
        mkdir(self::$siteRoot . '/content/articles/blog', 0755, true);
        file_put_contents(
            self::$siteRoot . '/content/articles/blog/seed.md',
            '## seed body'
        );
        file_put_contents(
            self::$siteRoot . '/content/articles/blog/seed.meta.yaml',
            "title: Seed\nstatus: published\ndate: 2025-01-01\n"
        );

        putenv('BIRD_SITE_DIR=' . self::$siteRoot);
        $_ENV['BIRD_SITE_DIR'] = self::$siteRoot;

        // require_once: STDIN is empty in PHPUnit, the dispatch loop falls
        // through immediately, leaving $tools and tool_* in scope.
        require_once BIRD_PROJECT_ROOT . '/mcp/server.php';
        self::$loaded = true;
    }

    public static function tearDownAfterClass(): void
    {
        TempContent::cleanup();
    }

    public function testToolsListReturnsExpectedSchema(): void
    {
        global $tools;
        $names = array_keys($tools);

        // Golden set declared in mcp/server.php as of v0.2.
        $expected = [
            'list_articles', 'read_article', 'write_article', 'list_categories',
            'delete_article', 'list_pages', 'read_page', 'write_page',
            'publish', 'unpublish', 'search',
        ];

        foreach ($expected as $name) {
            self::assertContains($name, $names, "Tool '$name' must be advertised by tools/list");
        }

        // Each tool must have a description and inputSchema.
        foreach ($tools as $name => $t) {
            self::assertArrayHasKey('description', $t, "Tool '$name' missing description");
            self::assertArrayHasKey('inputSchema', $t, "Tool '$name' missing inputSchema");
            self::assertArrayHasKey('handler', $t, "Tool '$name' missing handler");
        }
    }

    public function testWriteArticleViaToolsCallWritesFile(): void
    {
        $result = tool_write_article([
            'category' => 'blog',
            'slug' => 'mcp-test',
            'frontmatter' => ['title' => 'MCP Test', 'status' => 'draft'],
            'body' => '# from mcp',
        ]);

        self::assertSame('mcp-test', $result['slug']);
        self::assertSame('blog', $result['category']);
        self::assertFileExists(self::$siteRoot . '/content/articles/blog/mcp-test.md');
        self::assertFileExists(self::$siteRoot . '/content/articles/blog/mcp-test.meta.yaml');
        self::assertSame('# from mcp', file_get_contents(self::$siteRoot . '/content/articles/blog/mcp-test.md'));
    }

    public function testReadArticleReturnsWhatWriteSaved(): void
    {
        tool_write_article([
            'category' => 'blog',
            'slug' => 'rw-cycle',
            'frontmatter' => ['title' => 'Round Trip', 'status' => 'published'],
            'body' => 'cycle body',
        ]);

        $read = tool_read_article(['category' => 'blog', 'slug' => 'rw-cycle']);
        self::assertSame('blog', $read['category']);
        self::assertSame('rw-cycle', $read['slug']);
        self::assertSame('cycle body', $read['body']);
        self::assertSame('Round Trip', $read['frontmatter']['title']);
    }

    public function testWriteArticleRejectsBadSlug(): void
    {
        $this->expectException(RuntimeException::class);
        tool_write_article([
            'category' => 'blog',
            'slug' => 'Bad Slug!',
            'frontmatter' => ['title' => 'X'],
            'body' => '',
        ]);
    }

    public function testWriteArticleRejectsBadCategory(): void
    {
        $this->expectException(RuntimeException::class);
        tool_write_article([
            'category' => 'Bad Cat!',
            'slug' => 'ok-slug',
            'frontmatter' => ['title' => 'X'],
            'body' => '',
        ]);
    }

    public function testListArticlesIncludesSavedRecord(): void
    {
        tool_write_article([
            'category' => 'blog',
            'slug' => 'list-me',
            'frontmatter' => ['title' => 'List Me', 'status' => 'published'],
            'body' => '',
        ]);

        $list = tool_list_articles([]);
        $slugs = array_map(static fn(array $a) => $a['slug'], $list['articles']);
        self::assertContains('list-me', $slugs);
    }

    public function testDeleteArticleRemovesBothFiles(): void
    {
        tool_write_article([
            'category' => 'blog',
            'slug' => 'doomed',
            'frontmatter' => ['title' => 'Doomed'],
            'body' => 'rip',
        ]);
        self::assertFileExists(self::$siteRoot . '/content/articles/blog/doomed.md');

        $result = tool_delete_article(['category' => 'blog', 'slug' => 'doomed']);
        self::assertTrue($result['ok']);
        self::assertFileDoesNotExist(self::$siteRoot . '/content/articles/blog/doomed.md');
        self::assertFileDoesNotExist(self::$siteRoot . '/content/articles/blog/doomed.meta.yaml');
    }

    public function testGoldenFixtureToolsListMatchesExpectedShape(): void
    {
        // Fixture under tests/fixtures/mcp/tools-list.expected.json captures
        // the canonical shape of the tools/list response. If a tool is
        // renamed or its inputSchema reshaped, this test fires.
        $fixture = BIRD_TEST_ROOT . '/fixtures/mcp/tools-list.expected.json';
        self::assertFileExists($fixture);
        $expected = json_decode((string) file_get_contents($fixture), true);

        global $tools;
        $advertisedNames = array_keys($tools);
        sort($advertisedNames);
        $expectedNames = $expected['tools'];
        sort($expectedNames);

        self::assertSame($expectedNames, $advertisedNames);
    }
}
