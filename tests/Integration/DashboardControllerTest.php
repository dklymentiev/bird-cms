<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Admin\DashboardController;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestConfig;

/**
 * Integration coverage for the dashboard's static helpers + supporting
 * cards (Site info, Quick links).
 *
 * The Drafts / Scheduled / Recent edits cards introduced in v2 are
 * covered in DashboardV2Test; this file keeps the rc.9-era assertions
 * that still apply: the static formatters (relativeTime, articleStatus)
 * and the supporting Site info / Quick links cards that survived the
 * rewrite unchanged.
 *
 * Same boundary as before: we render the view template directly through
 * an output buffer with seeded data, the way Controller::render() would
 * once the data is ready. The HTTP / Auth / session chain stays out so
 * tests don't depend on the running environment.
 */
final class DashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        TestConfig::reset();
        TestConfig::set('site_url', 'https://example.test');
        TestConfig::set('active_theme', 'tailwind');
    }

    public function testSiteInfoCardShowsConfiguredValues(): void
    {
        $html = $this->renderDashboard([
            'drafts' => [],
            'scheduled' => [],
            'recentEdits' => [],
            'lastContentUpdate' => null,
            'flash' => [],
        ]);

        // Site URL is rendered as a clickable, new-tab-safe link.
        self::assertMatchesRegularExpression(
            '#<a [^>]*href="https://example\.test"[^>]*target="_blank"[^>]*rel="noopener"#',
            $html,
            'site_url must render as <a target="_blank" rel="noopener" href=...>'
        );
        self::assertStringContainsString('https://example.test', $html);
        self::assertStringContainsString('tailwind', $html);
    }

    public function testQuickLinksContainExpectedRoutes(): void
    {
        $html = $this->renderDashboard([
            'drafts' => [],
            'scheduled' => [],
            'recentEdits' => [],
            'lastContentUpdate' => null,
            'flash' => [],
        ]);

        // All four quick-link hrefs are present. Using strpos rather than
        // a single regex so a missing one fails with a clear name.
        self::assertStringContainsString('href="/admin/articles/new"', $html);
        self::assertStringContainsString('href="/admin/pages"', $html);
        self::assertStringContainsString('href="/admin/media"', $html);
        self::assertStringContainsString('href="/admin/settings/general"', $html);
    }

    public function testRelativeTimeBoundaries(): void
    {
        // The view passes lastContentUpdate through DashboardController::
        // relativeTime(); the rest of the dashboard depends on its output
        // shape. Lock the boundaries so a future refactor doesn't silently
        // ship "0 days ago" or similar.
        $now = 1_700_000_000;
        self::assertSame('never', DashboardController::relativeTime(null, $now));
        self::assertSame('just now', DashboardController::relativeTime($now - 30, $now));
        self::assertSame('5 min ago', DashboardController::relativeTime($now - 300, $now));
        self::assertSame('2 hours ago', DashboardController::relativeTime($now - 7200, $now));
        self::assertSame('yesterday', DashboardController::relativeTime($now - 86400, $now));
        self::assertSame('3 days ago', DashboardController::relativeTime($now - 86400 * 3, $now));
    }

    public function testArticleStatusDetectsScheduledFromFutureDate(): void
    {
        // status field missing or "published" but date is in the future ->
        // treat as scheduled. Mirrors ArticleRepository::isPublished's
        // contract; the dashboard surfaces it as a distinct badge.
        $future = date('Y-m-d', time() + 86400 * 7);
        $past   = date('Y-m-d', time() - 86400 * 7);

        self::assertSame('scheduled', DashboardController::articleStatus([
            'date' => $future, 'status' => 'published',
        ]));
        self::assertSame('published', DashboardController::articleStatus([
            'date' => $past, 'status' => 'published',
        ]));
        self::assertSame('draft', DashboardController::articleStatus([
            'date' => $past, 'status' => 'draft',
        ]));
        self::assertSame('scheduled', DashboardController::articleStatus([
            'date' => $past, 'status' => 'scheduled',
        ]));
    }

    /**
     * Render the dashboard view template against the supplied $data,
     * returning the captured HTML. Mirrors Controller::render() minus the
     * layout wrap (which is gated to auth + theme bootstrap we don't want
     * to bring up here).
     *
     * @param array<string, mixed> $data
     */
    private function renderDashboard(array $data): string
    {
        $viewPath = BIRD_PROJECT_ROOT . '/themes/admin/views/dashboard.php';
        self::assertFileExists($viewPath);

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        return (string) ob_get_clean();
    }

}
