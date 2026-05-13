<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Admin\DocsController;
use App\Support\Markdown;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for the admin docs viewer (planner #1844).
 *
 * We exercise:
 *   - The static path resolver (safeResolve) against the real project
 *     docs/ tree -- traversal attempts, README exception, and a known-
 *     good doc.
 *   - The link-rewriter (rewriteLinks) for the four cases the view
 *     contract guarantees: internal .md, external http(s), images under
 *     docs/screenshots/, and anchor-only links.
 *   - The view template rendered directly via output buffer, the same
 *     way DashboardControllerTest exercises its view -- we don't bring
 *     up Auth/sessions/IP just to render a template.
 *
 * Skipped: the controller's instance methods (index/show/asset) call
 * requireAuth() + render() which depend on session + theme bootstrap.
 * The behavior they wrap (path resolution, link rewriting, view markup)
 * is covered through the helpers and view above.
 */
final class DocsControllerTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = BIRD_PROJECT_ROOT;
    }

    public function testIndexRendersReadmeByDefault(): void
    {
        // The default route opens README.md. Render the view with the same
        // payload the controller would supply.
        $readmePath = $this->projectRoot . '/README.md';
        self::assertFileExists($readmePath);

        $markdown = (string) file_get_contents($readmePath);
        $html = DocsController::rewriteLinks(Markdown::toHtml($markdown), 'README.md');

        $rendered = $this->renderView([
            'pageTitle'    => 'Docs - Bird CMS',
            'currentTitle' => 'Bird CMS',
            'currentDoc'   => 'README.md',
            'docHtml'      => $html,
            'tree'         => DocsController::buildTreeFor($this->projectRoot,'README.md'),
        ]);

        // README's first h1 is "Bird CMS" -- the rendered prose should
        // contain it. The doc tree itself now lives in the main admin
        // sidebar (partials/sidebar.php), not in this view body.
        self::assertStringContainsString('<h1>Bird CMS</h1>', $rendered);
        self::assertStringContainsString('<article class="prose">', $rendered);
    }

    public function testShowRendersRequestedFile(): void
    {
        // Mirrors the /admin/docs/docs%2Fstructure.md flow: take the
        // urlencoded path, decode, resolve, render.
        $encoded = 'docs%2Fstructure.md';
        $relPath = rawurldecode($encoded);
        self::assertSame('docs/structure.md', $relPath);

        $absolute = DocsController::safeResolve($relPath, $this->projectRoot);
        self::assertNotNull($absolute, 'docs/structure.md must resolve');
        self::assertFileExists($absolute);

        $html = DocsController::rewriteLinks(
            Markdown::toHtml((string) file_get_contents($absolute)),
            $relPath
        );
        $rendered = $this->renderView([
            'pageTitle'    => 'Docs - Structure',
            'currentTitle' => 'Structure',
            'currentDoc'   => $relPath,
            'docHtml'      => $html,
            'tree'         => DocsController::buildTreeFor($this->projectRoot,$relPath),
        ]);

        // structure.md starts with "# Structure" -- the rendered article
        // should contain that heading.
        self::assertStringContainsString('Structure', $rendered);
        self::assertStringContainsString('<article class="prose">', $rendered);
    }

    public function testInternalMarkdownLinkRewritten(): void
    {
        $sourceHtml = '<p>See <a href="structure.md">Architecture</a> for details.</p>';
        $rewritten  = DocsController::rewriteLinks($sourceHtml, 'docs/install.md');

        // The relative link resolves from the dir of the current doc (docs/)
        // and becomes /admin/docs/<urlencoded>.
        self::assertStringContainsString(
            'href="/admin/docs/docs%2Fstructure.md"',
            $rewritten
        );
    }

    public function testInternalMarkdownLinkRewrittenFromNestedDoc(): void
    {
        // Link from docs/recipes/foo.md -> "../structure.md" must
        // resolve up one level into docs/.
        $sourceHtml = '<a href="../structure.md">arch</a>';
        $rewritten  = DocsController::rewriteLinks($sourceHtml, 'docs/recipes/foo.md');
        self::assertStringContainsString(
            'href="/admin/docs/docs%2Fstructure.md"',
            $rewritten
        );
    }

    public function testExternalLinksKeepHrefAndGetNoopener(): void
    {
        $sourceHtml = '<p>Visit <a href="https://example.com/page">example</a>.</p>';
        $rewritten  = DocsController::rewriteLinks($sourceHtml, 'README.md');

        self::assertStringContainsString('href="https://example.com/page"', $rewritten);
        self::assertStringContainsString('target="_blank"', $rewritten);
        self::assertStringContainsString('rel="noopener"', $rewritten);
    }

    public function testMailtoLinksGetNoopener(): void
    {
        // mailto: counts as external -- same rewrite rule applies.
        $sourceHtml = '<a href="mailto:dev@example.com">email</a>';
        $rewritten  = DocsController::rewriteLinks($sourceHtml, 'README.md');
        self::assertStringContainsString('href="mailto:dev@example.com"', $rewritten);
        self::assertStringContainsString('target="_blank"', $rewritten);
    }

    public function testImageReferencesRewrittenToAssetRoute(): void
    {
        $sourceHtml = '<img src="screenshots/preview.jpg" alt="x" />';
        $rewritten  = DocsController::rewriteLinks($sourceHtml, 'docs/install.md');
        self::assertStringContainsString(
            'src="/admin/docs/asset/docs%2Fscreenshots%2Fpreview.jpg"',
            $rewritten
        );
    }

    public function testPathTraversalRejected(): void
    {
        // Various flavors of "escape upward".
        self::assertNull(DocsController::safeResolve('../etc/passwd', $this->projectRoot));
        self::assertNull(DocsController::safeResolve('docs/../../etc/passwd', $this->projectRoot));
        self::assertNull(DocsController::safeResolve('../../../../../etc/passwd', $this->projectRoot));
        self::assertNull(DocsController::safeResolve('/etc/passwd', $this->projectRoot));
        // Backslashes (Windows-style traversal) rejected outright.
        self::assertNull(DocsController::safeResolve('..\\windows\\system32', $this->projectRoot));
        // NUL byte injection rejected.
        self::assertNull(DocsController::safeResolve("docs/structure.md\0.jpg", $this->projectRoot));
        // Empty.
        self::assertNull(DocsController::safeResolve('', $this->projectRoot));
    }

    public function testNonDocsPathRejected(): void
    {
        // The ROOT_ALLOWED whitelist covers README/CHANGELOG/mcp README only;
        // arbitrary top-level files are still rejected.
        self::assertNull(DocsController::safeResolve('composer.json', $this->projectRoot));
        self::assertNull(DocsController::safeResolve('bootstrap.php', $this->projectRoot));
        self::assertNull(DocsController::safeResolve('.env', $this->projectRoot));
        self::assertNull(DocsController::safeResolve('app/Admin/Controller.php', $this->projectRoot));
    }

    public function testRootAllowedFilesResolve(): void
    {
        // README.md, CHANGELOG.md, mcp/README.md are documented exceptions
        // referenced from the project README -- they MUST resolve so the
        // sidebar's links don't 404.
        foreach (['README.md', 'CHANGELOG.md', 'mcp/README.md'] as $allowed) {
            $resolved = DocsController::safeResolve($allowed, $this->projectRoot);
            self::assertNotNull(
                $resolved,
                "{$allowed} must resolve via ROOT_ALLOWED whitelist"
            );
        }
    }

    public function testReadmeExceptionAllowed(): void
    {
        $resolved = DocsController::safeResolve('README.md', $this->projectRoot);
        self::assertNotNull($resolved, 'README.md must resolve as the documented exception');
        self::assertStringEndsWith('README.md', $resolved);
    }

    public function testKnownDocResolves(): void
    {
        $resolved = DocsController::safeResolve('docs/structure.md', $this->projectRoot);
        self::assertNotNull($resolved);
        self::assertFileExists($resolved);
    }

    public function testNonExistentFileReturnsNull(): void
    {
        self::assertNull(DocsController::safeResolve('docs/does-not-exist.md', $this->projectRoot));
        self::assertNull(DocsController::safeResolve('docs/nope/deeper/missing.md', $this->projectRoot));
    }

    public function testResolveTitleUsesFirstHeading(): void
    {
        // structure.md starts with "# Structure".
        $absolute = $this->projectRoot . '/docs/structure.md';
        $title = DocsController::resolveTitle($absolute, 'docs/structure.md');
        self::assertStringContainsString('Structure', $title);
    }

    public function testResolveTitlePrettifiesFilenameWhenNoHeading(): void
    {
        // Use a non-existent path -- resolveTitle falls back to filename
        // prettification when the file isn't readable.
        $title = DocsController::resolveTitle(
            $this->projectRoot . '/docs/__missing__/small-business-cafe.md',
            'docs/__missing__/small-business-cafe.md'
        );
        self::assertSame('Small business cafe', $title);

        // Numeric prefix gets stripped.
        $title2 = DocsController::resolveTitle(
            $this->projectRoot . '/docs/__missing__/01-vision.md',
            'docs/__missing__/01-vision.md'
        );
        self::assertSame('Vision', $title2);
    }

    public function testBuildTreeSkipsMissingFiles(): void
    {
        $tree = DocsController::buildTreeFor($this->projectRoot,'README.md');
        self::assertArrayHasKey('Getting started', $tree);
        // Every returned entry must point at a file that actually exists.
        foreach ($tree as $group => $items) {
            foreach ($items as $item) {
                $resolved = DocsController::safeResolve($item['path'], $this->projectRoot);
                self::assertNotNull(
                    $resolved,
                    "Group {$group} item {$item['path']} must resolve to a real file"
                );
                self::assertFileExists($resolved);
            }
        }
    }

    public function testAssetPathRejectsTraversal(): void
    {
        // The asset route uses the same safeResolve path. Make sure none of
        // the traversal flavors leak through.
        self::assertNull(DocsController::safeResolve('../etc/passwd', $this->projectRoot));
        self::assertNull(DocsController::safeResolve('docs/../bootstrap.php', $this->projectRoot));
    }

    public function testAnchorOnlyLinksKept(): void
    {
        // [foo](#section) -- pure anchor links must stay anchor-only, not
        // get rewritten into /admin/docs/<encoded>.
        $sourceHtml = '<a href="#section">jump</a>';
        $rewritten  = DocsController::rewriteLinks($sourceHtml, 'docs/structure.md');
        self::assertStringContainsString('href="#section"', $rewritten);
        self::assertStringNotContainsString('/admin/docs/', $rewritten);
    }

    public function testAnchorFragmentPreservedOnRewrittenLink(): void
    {
        // [arch heading](structure.md#components) keeps the #components
        // fragment after rewriting.
        $sourceHtml = '<a href="structure.md#components">arch</a>';
        $rewritten  = DocsController::rewriteLinks($sourceHtml, 'docs/install.md');
        self::assertStringContainsString(
            'href="/admin/docs/docs%2Fstructure.md#components"',
            $rewritten
        );
    }

    /**
     * Render the docs view template against the supplied data, returning
     * the captured HTML. Mirrors Controller::render() minus the layout wrap
     * (which depends on auth + theme bootstrap we don't want to bring up).
     *
     * @param array<string, mixed> $data
     */
    private function renderView(array $data): string
    {
        $viewPath = BIRD_PROJECT_ROOT . '/themes/admin/views/docs/index.php';
        self::assertFileExists($viewPath);

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        return (string) ob_get_clean();
    }
}
