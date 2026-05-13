<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Admin\DashboardController;
use App\Content\ArticleRepository;
use App\Content\PageRepository;
use App\Support\EditLog;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * Integration coverage for the dashboard v2 layout (planner #1843).
 *
 * Same boundary as DashboardControllerTest -- we drive the data the
 * controller would assemble (drafts / scheduled / recent edits), then
 * render the view template directly through an output buffer. The
 * HTTP / auth / session chain is excluded by design; what matters is
 * "given this data, what HTML lands on disk".
 */
final class DashboardV2Test extends TestCase
{
    private string $articlesDir;
    private string $pagesDir;
    private ArticleRepository $articles;
    private PageRepository $pages;
    private string $editLogPath;

    protected function setUp(): void
    {
        TestConfig::reset();
        TestConfig::set('site_url', 'https://example.test');
        TestConfig::set('active_theme', 'tailwind');

        $this->articlesDir = TempContent::make('dashv2-articles');
        $this->pagesDir = TempContent::make('dashv2-pages');
        $this->articles = new ArticleRepository($this->articlesDir);
        $this->pages = new PageRepository($this->pagesDir);

        $tmpRoot = BIRD_TEST_ROOT . '/fixtures/tmp';
        $this->editLogPath = $tmpRoot . '/dashv2-edits-' . bin2hex(random_bytes(4)) . '.sqlite';
        EditLog::useDatabase($this->editLogPath);
        EditLog::$context = null;
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
        EditLog::useDatabase(null);
        EditLog::$context = null;
        if (is_file($this->editLogPath)) {
            @unlink($this->editLogPath);
        }
    }

    public function testDraftsCardListsDraftStatusOnly(): void
    {
        $this->seedArticle('blog', 'finished-post', [
            'title' => 'Finished Post',
            'status' => 'published',
            'date' => '2026-05-01',
        ]);
        $this->seedArticle('blog', 'rough-cut', [
            'title' => 'Rough Cut',
            'status' => 'draft',
            'date' => '2026-05-05',
        ]);
        $this->seedArticle('news', 'in-progress', [
            'title' => 'Work in Progress',
            'status' => 'draft',
            'date' => '2026-05-09',
        ]);

        $articles = $this->articles->all(true);
        $drafts = DashboardController::buildDrafts($articles, [], 10);

        // Two draft rows, zero non-drafts.
        self::assertCount(2, $drafts);
        $titles = array_column($drafts, 'title');
        self::assertContains('Rough Cut', $titles);
        self::assertContains('Work in Progress', $titles);
        self::assertNotContains('Finished Post', $titles);

        $html = $this->renderDashboard([
            'drafts' => $drafts,
            'scheduled' => [],
            'recentEdits' => [],
            'lastContentUpdate' => null,
        ]);

        self::assertStringContainsString('Drafts', $html);
        self::assertStringContainsString('Rough Cut', $html);
        self::assertStringContainsString('Work in Progress', $html);
        self::assertStringNotContainsString('Finished Post', $html);
        // Edit link shape is what the operator clicks; if it ever drifts
        // (e.g. /admin/edit?slug=...) the dashboard goes from helpful to
        // broken in one shot.
        self::assertStringContainsString('/admin/articles/blog/rough-cut/edit', $html);
        self::assertStringContainsString('/admin/articles/news/in-progress/edit', $html);
    }

    public function testDraftsCardHiddenWhenNoDrafts(): void
    {
        // Only published content -- the Drafts card must not render at
        // all. We assert on the heading rather than a generic substring
        // so a future re-skin can rename text without breaking the test
        // in a misleading way.
        $this->seedArticle('blog', 'all-shipped', [
            'title' => 'All Shipped',
            'status' => 'published',
            'date' => '2026-04-01',
        ]);

        $articles = $this->articles->all(true);
        $drafts = DashboardController::buildDrafts($articles, [], 10);
        self::assertSame([], $drafts);

        $html = $this->renderDashboard([
            'drafts' => $drafts,
            'scheduled' => [],
            'recentEdits' => [],
            'lastContentUpdate' => null,
        ]);

        self::assertStringNotContainsString(
            '>Drafts<',
            $html,
            'Drafts <h2> must not render when the list is empty'
        );
    }

    public function testScheduledCardListsFutureItems(): void
    {
        $future = date('Y-m-d', time() + 86400 * 7);
        $past   = date('Y-m-d', time() - 86400 * 7);

        $this->seedArticle('blog', 'past-post', [
            'title' => 'Past Post',
            'status' => 'published',
            'date' => $past,
        ]);
        $this->seedArticle('blog', 'future-launch', [
            'title' => 'Future Launch',
            'status' => 'scheduled',
            'date' => $past,
            'publish_at' => $future,
        ]);

        $articles = $this->articles->all(true);
        $scheduled = DashboardController::buildScheduled($articles, 10);

        self::assertCount(1, $scheduled);
        self::assertSame('Future Launch', $scheduled[0]['title']);

        $html = $this->renderDashboard([
            'drafts' => [],
            'scheduled' => $scheduled,
            'recentEdits' => [],
            'lastContentUpdate' => null,
        ]);

        self::assertStringContainsString('Scheduled', $html);
        self::assertStringContainsString('Future Launch', $html);
        self::assertStringNotContainsString('Past Post', $html);
        // Heuristic "publishes" copy is part of the contract -- operators
        // scan for that phrasing.
        self::assertStringContainsString('publishes', $html);
    }

    public function testScheduledCardHiddenWhenEmpty(): void
    {
        $past = date('Y-m-d', time() - 86400 * 7);
        $this->seedArticle('blog', 'already-out', [
            'title' => 'Already Out',
            'status' => 'published',
            'date' => $past,
        ]);

        $articles = $this->articles->all(true);
        $scheduled = DashboardController::buildScheduled($articles, 10);
        self::assertSame([], $scheduled);

        $html = $this->renderDashboard([
            'drafts' => [],
            'scheduled' => $scheduled,
            'recentEdits' => [],
            'lastContentUpdate' => null,
        ]);

        self::assertStringNotContainsString(
            '>Scheduled<',
            $html,
            'Scheduled <h2> must not render when no items are scheduled'
        );
    }

    public function testRecentEditsRendersWithSource(): void
    {
        // Tag the next save as admin so the EditLog row carries that
        // source. The repo wiring is the same code the production admin
        // request hits.
        EditLog::$context = 'admin';
        $this->seedArticle('blog', 'admin-saved', [
            'title' => 'Admin Saved',
            'status' => 'published',
            'date' => '2026-05-09',
        ]);

        // Then a direct MCP-source row, to exercise the colour-coded
        // pill the view renders per source.
        EditLog::record('mcp', 'save', '/blog/mcp-saved', 'article', 'mcp-saved');

        $recent = EditLog::recent(5);
        self::assertGreaterThanOrEqual(2, count($recent));

        $html = $this->renderDashboard([
            'drafts' => [],
            'scheduled' => [],
            'recentEdits' => $recent,
            'lastContentUpdate' => null,
        ]);

        self::assertStringContainsString('Recent edits', $html);
        // Source pills must appear with their distinct CSS classes. The
        // dashboard rewrite (3727155) replaced the Tailwind bg-emerald-100
        // / bg-violet-100 utilities with brand-token pill classes.
        self::assertStringContainsString('bird-pill-success', $html, 'admin source pill missing');
        self::assertStringContainsString('bird-pill-violet', $html, 'mcp source pill missing');
        // The MCP target URL is the most-recent row -- it should also
        // render as the canonical link shape.
        self::assertStringContainsString('/blog/mcp-saved', $html);
    }

    public function testRecentEditsEmptyHidesCard(): void
    {
        // No prior writes; the log is empty.
        self::assertSame([], EditLog::recent());

        $html = $this->renderDashboard([
            'drafts' => [],
            'scheduled' => [],
            'recentEdits' => [],
            'lastContentUpdate' => null,
        ]);

        self::assertStringNotContainsString(
            '>Recent edits<',
            $html,
            'Recent edits <h2> must not render when the log has no rows'
        );
    }

    public function testSupportingCardsStayVisibleWhenTopCardsHidden(): void
    {
        // Even when all three top cards are empty (fresh install), the
        // dashboard must still surface Site info + Quick links so a
        // first-time operator has somewhere to click.
        $html = $this->renderDashboard([
            'drafts' => [],
            'scheduled' => [],
            'recentEdits' => [],
            'lastContentUpdate' => null,
        ]);

        self::assertStringContainsString('Site URL', $html);
        self::assertStringContainsString('Quick links', $html);
        self::assertStringContainsString('href="/admin/articles/new"', $html);
        self::assertStringContainsString('href="/admin/settings/general"', $html);
    }

    /**
     * Render the dashboard view directly through an output buffer.
     * Mirrors Controller::render() minus the layout wrap. Same pattern
     * as DashboardControllerTest::renderDashboard().
     *
     * @param array<string, mixed> $data
     */
    private function renderDashboard(array $data): string
    {
        $viewPath = BIRD_PROJECT_ROOT . '/themes/admin/views/dashboard.php';
        self::assertFileExists($viewPath);

        // The view also reads $flash; integration tests don't surface flash
        // messages, but the variable has to be defined to satisfy strict
        // PHP error settings.
        $data = $data + ['flash' => []];
        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function seedArticle(string $category, string $slug, array $meta): void
    {
        $meta = $meta + [
            'description' => '',
            'type' => 'insight',
            'tags' => [],
            'primary' => 'kw',
        ];
        $this->articles->save($category, $slug, $meta, '# Body');
    }
}
