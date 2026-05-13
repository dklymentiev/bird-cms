<?php

declare(strict_types=1);

namespace Tests\Parity;

use App\Content\ArticleRepository;
use App\Content\PageRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * Round-trip parity between MCP and admin.
 *
 * This is the drift-prevention test class. The MCP server (mcp/server.php)
 * and the admin controllers (app/Admin/{Article,Pages}Controller -- which
 * delegate writes to App\Content\{Article,Page}Repository) write to the
 * same content/ tree via two different code paths. If their on-disk
 * formats diverge, the symptom is "I wrote this from Claude Desktop and
 * the admin can't read it" -- exactly the regression these tests catch.
 *
 * Each test does the cycle:
 *   1. Write through surface A
 *   2. Read through surface B
 *   3. Assert title + body + meta survived
 *
 * Both directions are covered for both content types (articles + pages).
 *
 * The MCP handlers are loaded the same way McpServerTest does it: set
 * BIRD_SITE_DIR to a per-class temp dir, `require_once` mcp/server.php,
 * which makes the global tool_* handlers callable in-process.
 */
final class McpAdminParityTest extends TestCase
{
    private static string $siteRoot;
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        self::$siteRoot = TempContent::make('parity');
        mkdir(self::$siteRoot . '/content/articles', 0755, true);
        mkdir(self::$siteRoot . '/content/pages', 0755, true);

        putenv('BIRD_SITE_DIR=' . self::$siteRoot);
        $_ENV['BIRD_SITE_DIR'] = self::$siteRoot;

        require_once BIRD_PROJECT_ROOT . '/mcp/server.php';

        // mcp/server.php is require_once'd; if another test class loaded
        // it first, the top-level $siteRoot/$articlesDir/... locked to
        // that class's temp tree. Rebind the globals here so this class's
        // tool_* calls write into our own per-class temp dir.
        $GLOBALS['siteRoot']    = self::$siteRoot;
        $GLOBALS['articlesDir'] = self::$siteRoot . '/content/articles';
        $GLOBALS['pagesDir']    = self::$siteRoot . '/content/pages';
        $GLOBALS['contentDir']  = self::$siteRoot . '/content';

        self::$loaded = true;
    }

    public static function tearDownAfterClass(): void
    {
        TempContent::cleanup();
    }

    protected function setUp(): void
    {
        TestConfig::reset();
        TestConfig::set('site_url', 'http://localhost');
    }

    public function testArticleSavedViaMcpReadableViaController(): void
    {
        // Write through the MCP path.
        tool_write_article([
            'category' => 'blog',
            'slug' => 'mcp-to-admin',
            'frontmatter' => [
                'title' => 'MCP to Admin',
                'description' => 'Created from Claude Desktop',
                'date' => '2026-05-10',
                'type' => 'insight',
                'status' => 'published',
                'tags' => ['parity', 'drift'],
                'primary' => 'kw',
            ],
            'body' => '# Body from MCP',
        ]);

        // Read through the admin/repository path.
        $repo = new ArticleRepository(self::$siteRoot . '/content/articles');
        $article = $repo->find('blog', 'mcp-to-admin');

        self::assertNotNull($article, 'Admin must be able to find an article MCP just wrote');
        self::assertSame('mcp-to-admin', $article['slug']);
        self::assertSame('MCP to Admin', $article['title']);
        self::assertSame('Created from Claude Desktop', $article['description']);
        self::assertSame('# Body from MCP', $article['content']);
    }

    public function testArticleSavedViaControllerReadableViaMcp(): void
    {
        // Write through the admin/repository path.
        $repo = new ArticleRepository(self::$siteRoot . '/content/articles');
        $repo->save('blog', 'admin-to-mcp', [
            'title' => 'Admin to MCP',
            'description' => 'Created in /admin/articles',
            'date' => '2026-05-10',
            'type' => 'insight',
            'status' => 'published',
            'tags' => ['parity'],
            'primary' => 'kw',
        ], '# Body from admin');

        // Read through the MCP path.
        $read = tool_read_article(['category' => 'blog', 'slug' => 'admin-to-mcp']);

        self::assertSame('blog', $read['category']);
        self::assertSame('admin-to-mcp', $read['slug']);
        self::assertSame('# Body from admin', $read['body']);
        self::assertSame('Admin to MCP', $read['frontmatter']['title']);
    }

    public function testPageSavedViaMcpReadableViaController(): void
    {
        tool_write_page([
            'slug' => 'mcp-page',
            'frontmatter' => [
                'title' => 'MCP Page',
                'description' => 'Page from MCP',
                'status' => 'published',
            ],
            'body' => 'page body via mcp',
        ]);

        $repo = new PageRepository(self::$siteRoot . '/content/pages');
        $page = $repo->find('mcp-page');

        self::assertNotNull($page);
        self::assertSame('mcp-page', $page['slug']);
        self::assertSame('MCP Page', $page['title']);
        self::assertSame('page body via mcp', $page['content']);
    }

    public function testPageSavedViaControllerReadableViaMcp(): void
    {
        $repo = new PageRepository(self::$siteRoot . '/content/pages');
        $repo->save('admin-page', [
            'title' => 'Admin Page',
            'description' => 'Page from /admin/pages',
            'status' => 'published',
        ], 'page body via admin');

        $read = tool_read_page(['slug' => 'admin-page']);

        self::assertSame('admin-page', $read['slug']);
        self::assertSame('page body via admin', $read['body']);
        self::assertSame('Admin Page', $read['frontmatter']['title']);
    }

    public function testRepositorySaveReadableViaBoth(): void
    {
        // The triple-check: save through the repository (the surface both
        // sides converge on), then read through both top-level entry points
        // and assert the data is identical from both vantage points. If
        // either entry point projects the on-disk record differently, this
        // is the canary.
        $repo = new ArticleRepository(self::$siteRoot . '/content/articles');
        $repo->save('blog', 'three-way', [
            'title' => 'Three Way',
            'description' => 'Read via both surfaces',
            'date' => '2026-05-10',
            'type' => 'insight',
            'status' => 'published',
            'tags' => ['canary'],
            'primary' => 'three-way',
        ], 'three-way body');

        $viaAdmin = $repo->find('blog', 'three-way');
        $viaMcp = tool_read_article(['category' => 'blog', 'slug' => 'three-way']);

        self::assertNotNull($viaAdmin);
        self::assertSame($viaAdmin['title'], $viaMcp['frontmatter']['title']);
        self::assertSame($viaAdmin['content'], $viaMcp['body']);
        self::assertSame($viaAdmin['slug'], $viaMcp['slug']);
        // category exposed by admin == category echoed by MCP
        self::assertSame($viaAdmin['category'], $viaMcp['category']);
    }
}
