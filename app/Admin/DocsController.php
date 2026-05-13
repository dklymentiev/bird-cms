<?php

declare(strict_types=1);

namespace App\Admin;

use App\Support\Markdown;

/**
 * Read-only documentation viewer (planner #1844).
 *
 * Renders project markdown docs inside the admin panel so operators don't
 * have to git clone the repo or browse GitLab. Two-column layout: grouped
 * file tree on the left, rendered markdown body on the right.
 *
 * Hard constraint: this controller MUST NOT write anything to disk and MUST
 * NOT serve files outside docs/ (the README.md at the repo root is the one
 * exception, whitelisted explicitly). Path traversal is the only meaningful
 * threat surface here -- every resolved path is checked against
 * realpath() of the docs root before it's returned to the renderer.
 */
final class DocsController extends Controller
{
    /**
     * Grouped doc tree. Keys are sidebar section labels, values are arrays
     * of relative paths (from project root). The viewer renders each group
     * as a collapsible <details> block. Files missing from disk are dropped
     * silently so the sidebar always reflects what's actually shippable.
     *
     * @var array<string, list<string>>
     */
    public const GROUPS = [
        'Getting started' => [
            'README.md',
            'docs/install.md',
            'docs/structure.md',
            'docs/usage.md',
            'CHANGELOG.md',
        ],
        'Customization' => [
            'docs/branding.md',
            'docs/theming.md',
        ],
        'Reference' => [
            'docs/api.md',
            'docs/troubleshooting.md',
            'mcp/README.md',
        ],
        'Recipes' => [
            'docs/recipes/ai-content-workflow.md',
            'docs/recipes/small-business-cafe.md',
            'docs/recipes/personal-blog-import.md',
            'docs/recipes/hugo-migration.md',
            'docs/recipes/add-content-type.md',
            'docs/recipes/integrate-statio.md',
        ],
    ];

    /**
     * Files outside docs/ that are still allowed in the viewer. Each entry
     * is a project-root-relative path. The viewer's safeResolve checks
     * resolved path against this list before accepting anything outside
     * docs/. Keep small -- these are the only "external" files the link
     * rewriter and sidebar can reach.
     */
    public const ROOT_ALLOWED = [
        'README.md',
        'CHANGELOG.md',
        'mcp/README.md',
    ];

    private string $projectRoot;

    public function __construct()
    {
        parent::__construct();
        // Mirrors the other admin controllers: dirname(__DIR__, 2) lands at
        // the project root (where bootstrap.php lives). Tests construct paths
        // independently via BIRD_PROJECT_ROOT, so we don't need to wire this
        // through a fixture.
        $this->projectRoot = self::resolveProjectRoot();
    }

    /**
     * Resolve the project root deterministically:
     *   - Production: dirname(__DIR__, 2) (app/Admin/DocsController.php -> project)
     *   - Tests: same path resolves to the same dir; BIRD_PROJECT_ROOT is also
     *     defined but only used when tests call the static helpers directly.
     */
    private static function resolveProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Default landing: auto-generated index built from GROUPS. Lists every
     * shipped doc as a clickable card, grouped by section. Replaces the
     * old in-page sidebar -- there's no nested nav anywhere now.
     */
    public function index(): void
    {
        $this->requireAuth();

        $sections = [];
        foreach (self::GROUPS as $group => $paths) {
            $items = [];
            foreach ($paths as $relPath) {
                $absolute = self::safeResolve($relPath, $this->projectRoot);
                if ($absolute === null || !is_file($absolute)) {
                    continue;
                }
                $items[] = [
                    'path'  => $relPath,
                    'title' => self::resolveTitle($absolute, $relPath),
                    'lede'  => self::leadingParagraph($absolute),
                ];
            }
            if (!empty($items)) {
                $sections[$group] = $items;
            }
        }

        $this->render('docs/welcome', [
            'pageTitle'    => 'Docs',
            'currentTitle' => 'Docs',
            'currentPath'  => $_SERVER['REQUEST_URI'] ?? '/admin/docs',
            'currentDoc'   => '',
            'sections'     => $sections,
        ]);
    }

    /**
     * Render a specific doc, identified by urlencoded relative path.
     */
    public function show(string $encodedPath): void
    {
        $this->requireAuth();
        $relPath = rawurldecode($encodedPath);
        $this->renderDoc($relPath);
    }

    /**
     * Serve a binary asset (image, pdf) referenced from a doc. Only paths
     * under docs/ are accepted; anything else (.., absolute, outside the
     * docs subtree) returns 404 with no body leakage.
     */
    public function asset(string $encodedPath): void
    {
        $this->requireAuth();
        $relPath = rawurldecode($encodedPath);

        // Assets are limited to docs/ -- README.md is markdown only, not a
        // binary host, so the README exception does NOT extend here.
        if (!self::isAllowedAssetPath($relPath)) {
            $this->notFound();
            return;
        }

        $absolute = self::safeResolve($relPath, $this->projectRoot);
        if ($absolute === null || !is_file($absolute)) {
            $this->notFound();
            return;
        }

        $mime = self::mimeFromExtension($absolute);
        if ($mime === null) {
            // Reject unknown extensions outright. We never want to serve
            // arbitrary file types from inside the repo.
            $this->notFound();
            return;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($absolute));
        header('X-Content-Type-Options: nosniff');
        readfile($absolute);
    }

    /**
     * Build a flat tree of {group => [{path, title, active}]} for the
     * sidebar. Missing files are skipped so the rendered tree never shows
     * a dead link.
     *
     * Static so tests can call it without booting the controller (which
     * drags in Auth + Config + sessions). Production code path goes through
     * the instance shim below.
     *
     * @return array<string, list<array{path: string, title: string, active: bool}>>
     */
    public static function buildTreeFor(string $projectRoot, string $currentRelPath): array
    {
        $tree = [];
        foreach (self::GROUPS as $group => $paths) {
            $items = [];
            foreach ($paths as $relPath) {
                $absolute = self::safeResolve($relPath, $projectRoot);
                if ($absolute === null || !is_file($absolute)) {
                    continue;
                }
                $items[] = [
                    'path'   => $relPath,
                    'title'  => self::resolveTitle($absolute, $relPath),
                    'active' => $relPath === $currentRelPath,
                ];
            }
            if (!empty($items)) {
                $tree[$group] = $items;
            }
        }
        return $tree;
    }

    /**
     * Instance shim used by the view; defers to the static implementation.
     *
     * @return array<string, list<array{path: string, title: string, active: bool}>>
     */
    public function buildTree(string $currentRelPath): array
    {
        return self::buildTreeFor($this->projectRoot, $currentRelPath);
    }

    /**
     * Render a single doc. Looks up the file, reads it, runs it through
     * Markdown::toHtml, rewrites internal links/images, hands off to the
     * view. 404 if the path doesn't resolve under the project root.
     */
    private function renderDoc(string $relPath): void
    {
        $absolute = self::safeResolve($relPath, $this->projectRoot);
        if ($absolute === null || !is_file($absolute) || !str_ends_with($absolute, '.md')) {
            $this->notFound();
            return;
        }

        $markdown = (string) file_get_contents($absolute);
        $title    = self::resolveTitle($absolute, $relPath);
        $html     = Markdown::toHtml($markdown);
        $html     = self::rewriteLinks($html, $relPath);

        $this->render('docs/index', [
            'pageTitle'    => 'Docs - ' . $title,
            'currentTitle' => $title,
            'currentPath'  => $_SERVER['REQUEST_URI'] ?? '/admin/docs',
            'currentDoc'   => $relPath,
            'docHtml'      => $html,
            'tree'         => $this->buildTree($relPath),
        ]);
    }

    /**
     * Resolve `$relPath` to an absolute filesystem path inside the project,
     * or return null if the path escapes the allowed roots (project_root +
     * docs/, plus README.md at the project root).
     *
     * Defensive: realpath() is run BOTH on the candidate AND on the docs/
     * root and README, then compared with str_starts_with. This catches
     * symlink redirection and `..` collapsing in one pass.
     */
    public static function safeResolve(string $relPath, string $projectRoot): ?string
    {
        $relPath = (string) $relPath;
        if ($relPath === '') {
            return null;
        }
        // No NUL bytes; no Windows-style backslashes (force forward slashes
        // before validation so traversal detection is uniform).
        if (str_contains($relPath, "\0") || str_contains($relPath, "\\")) {
            return null;
        }
        // No absolute paths -- relPath is always relative to projectRoot.
        if (str_starts_with($relPath, '/')) {
            return null;
        }
        // No `..` segments at all. Cheaper than canonicalising and matches
        // the threat model: docs never need to reference parent directories.
        foreach (explode('/', $relPath) as $segment) {
            if ($segment === '..' || $segment === '.') {
                return null;
            }
        }

        $docsRootReal  = realpath($projectRoot . '/docs');
        $candidate     = $projectRoot . '/' . $relPath;
        $candidateReal = realpath($candidate);

        if ($candidateReal === false) {
            return null;
        }

        // A small set of root-level docs (README, CHANGELOG, mcp/README) sit
        // outside docs/. Each is whitelisted by relative path, then checked
        // via realpath equality so symlinks can't redirect the lookup.
        foreach (self::ROOT_ALLOWED as $allowed) {
            $allowedReal = realpath($projectRoot . '/' . $allowed);
            if ($allowedReal !== false && $candidateReal === $allowedReal) {
                return $candidateReal;
            }
        }

        // Everything else must resolve inside docs/.
        if ($docsRootReal === false) {
            return null;
        }
        $docsRootRealNorm = rtrim($docsRootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($candidateReal, $docsRootRealNorm)) {
            return null;
        }
        return $candidateReal;
    }

    /**
     * First non-blank paragraph of a markdown file, stripped of markdown,
     * for use as a one-line summary on the index page. Blockquotes (lines
     * starting with `>`) are skipped -- they're "why this matters" prefaces
     * and don't summarise the doc itself.
     */
    public static function leadingParagraph(string $absolute, int $maxChars = 200): string
    {
        if (!is_file($absolute)) {
            return '';
        }
        $handle = @fopen($absolute, 'r');
        if ($handle === false) {
            return '';
        }
        $sawHeading  = false;
        $inCodeFence = false;
        $paragraph   = '';
        $i = 0;
        while ($i++ < 200 && ($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            // Code fences toggle skip-mode either way.
            if (str_starts_with($trimmed, '```')) {
                $inCodeFence = !$inCodeFence;
                continue;
            }
            if ($inCodeFence) {
                continue;
            }
            if ($trimmed === '') {
                if ($sawHeading && $paragraph !== '') {
                    break;
                }
                continue;
            }
            if (!$sawHeading) {
                if (str_starts_with($trimmed, '#')) {
                    $sawHeading = true;
                }
                continue;
            }
            // Skip non-prose infrastructure:
            //  - sub-headings (more `#` lines)
            //  - table rows
            //  - bullet + numbered list items (we want a flowing paragraph)
            //  - badge soup (lines that are entirely markdown image refs)
            // Blockquotes are kept -- some READMEs use them as the tagline.
            if (
                str_starts_with($trimmed, '#') ||
                str_starts_with($trimmed, '|') ||
                str_starts_with($trimmed, '- ') ||
                str_starts_with($trimmed, '* ') ||
                preg_match('/^\d+\.\s/', $trimmed) ||
                self::looksLikeBadgeLine($trimmed)
            ) {
                continue;
            }
            // Strip the leading "> " from blockquote lines so the lede reads
            // as plain prose.
            if (str_starts_with($trimmed, '>')) {
                $trimmed = ltrim(substr($trimmed, 1));
            }
            $paragraph .= ($paragraph === '' ? '' : ' ') . $trimmed;
        }
        fclose($handle);
        // Strip very lightweight markdown so the lede reads as plain text.
        $paragraph = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $paragraph) ?? $paragraph;
        $paragraph = preg_replace('/`([^`]+)`/', '$1', $paragraph) ?? $paragraph;
        $paragraph = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $paragraph) ?? $paragraph;
        $paragraph = preg_replace('/\*\*([^*]+)\*\*/', '$1', $paragraph) ?? $paragraph;
        $paragraph = preg_replace('/\*([^*]+)\*/', '$1', $paragraph) ?? $paragraph;
        $paragraph = preg_replace('/\s+/', ' ', $paragraph) ?? $paragraph;
        $paragraph = trim($paragraph);
        if (mb_strlen($paragraph) > $maxChars) {
            $paragraph = mb_substr($paragraph, 0, $maxChars - 1) . '…';
        }
        return $paragraph;
    }

    /**
     * Heuristic: a line is "badge soup" if at least half of its visible
     * tokens are markdown image refs. Catches READMEs that lead with a
     * row of shield.io badges.
     */
    private static function looksLikeBadgeLine(string $line): bool
    {
        // Count image refs anywhere on the line (covers single-badge lines
        // like `[![Version](url)](VERSION)` -- READMEs put one per line).
        $badges = preg_match_all('/!\[[^\]]*\]\([^)]*\)/', $line);
        if ($badges < 1) {
            return false;
        }
        // Strip linked badges (`[![alt](url)](target)`), bare images
        // (`![alt](url)`), and any leftover link wrappers, then see if
        // anything substantive remains. Order matters -- linked badges
        // first so the outer `[](target)` doesn't get eaten before its
        // image is stripped.
        $stripped = preg_replace('/\[!\[[^\]]*\]\([^)]*\)\]\([^)]*\)/', '', $line) ?? $line;
        $stripped = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\[[^\]]*\]\([^)]*\)/', '', $stripped) ?? $stripped;
        $stripped = trim($stripped);
        return mb_strlen($stripped) < 8;
    }

    /**
     * Title resolution rule:
     *   1. First `# Heading` in the file, if any.
     *   2. Else basename -> prettified ("small-business-cafe.md" -> "Small
     *      business cafe").
     */
    public static function resolveTitle(string $absolute, string $relPath): string
    {
        if (is_file($absolute)) {
            // We don't need to read the whole file just to grab the heading;
            // stream the first ~32 lines. Docs that put the heading further
            // down don't deserve a special-case.
            $handle = @fopen($absolute, 'r');
            if ($handle !== false) {
                $i = 0;
                while ($i++ < 64 && ($line = fgets($handle)) !== false) {
                    if (preg_match('/^#\s+(.+?)\s*$/', $line, $m)) {
                        fclose($handle);
                        return trim($m[1]);
                    }
                }
                fclose($handle);
            }
        }
        $base = basename($relPath, '.md');
        // Drop leading "NN-" sort prefixes ("01-vision" -> "vision").
        $base = preg_replace('/^\d+[-_]/', '', $base) ?? $base;
        $base = str_replace(['-', '_'], ' ', $base);
        return ucfirst($base);
    }

    /**
     * Rewrite internal markdown links/images to admin URLs:
     *   - Relative .md links become /admin/docs/<urlencoded>
     *   - docs/screenshots/foo.jpg image refs become /admin/docs/asset/...
     *   - http://, https://, mailto: links are left intact but gain
     *     target="_blank" rel="noopener" so external nav doesn't blow away
     *     the admin tab.
     *
     * `$currentRelPath` is the doc whose HTML we're rewriting -- relative
     * links resolve against its directory.
     */
    public static function rewriteLinks(string $html, string $currentRelPath): string
    {
        $currentDir = dirname($currentRelPath);
        if ($currentDir === '.' || $currentDir === '') {
            $currentDir = '';
        }

        // <a href="...">: split into external vs internal.
        $html = preg_replace_callback(
            '/<a\s+href="([^"]+)"/i',
            static function (array $m) use ($currentDir): string {
                $href = $m[1];
                if (self::isExternalUrl($href)) {
                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" target="_blank" rel="noopener"';
                }
                // Strip anchor fragment for resolution, reattach at the end.
                $fragment = '';
                $hashAt = strpos($href, '#');
                if ($hashAt !== false) {
                    $fragment = substr($href, $hashAt);
                    $href = substr($href, 0, $hashAt);
                }
                if ($href === '') {
                    // Pure anchor link -- keep as-is (jumps within the same page).
                    return '<a href="' . htmlspecialchars($fragment, ENT_QUOTES) . '"';
                }
                $resolved = self::resolveRelative($currentDir, $href);
                if ($resolved === null) {
                    return $m[0];
                }
                // Markdown links to .md files become viewer URLs; everything
                // else under docs/ is treated as an asset.
                if (str_ends_with($resolved, '.md')) {
                    $url = '/admin/docs/' . rawurlencode($resolved) . $fragment;
                } else {
                    $url = '/admin/docs/asset/' . rawurlencode($resolved) . $fragment;
                }
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '"';
            },
            $html
        ) ?? $html;

        // <img src="...">: relative paths become asset URLs. External image
        // URLs are left intact (badges, etc.).
        $html = preg_replace_callback(
            '/<img\s+src="([^"]+)"/i',
            static function (array $m) use ($currentDir): string {
                $src = $m[1];
                if (self::isExternalUrl($src)) {
                    return $m[0];
                }
                $resolved = self::resolveRelative($currentDir, $src);
                if ($resolved === null) {
                    return $m[0];
                }
                $url = '/admin/docs/asset/' . rawurlencode($resolved);
                return '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '"';
            },
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * Combine a base dir (the dir of the current doc) with a relative href,
     * normalize the `..` segments, and return the path or null if the href
     * tries to escape upward beyond the project root.
     */
    private static function resolveRelative(string $baseDir, string $href): ?string
    {
        // Already-absolute paths starting with "/" -- treat as absolute
        // relative-to-project. Strip leading slash and run through validation.
        if (str_starts_with($href, '/')) {
            $href = ltrim($href, '/');
            $parts = explode('/', $href);
        } else {
            $combined = ($baseDir === '' ? '' : $baseDir . '/') . $href;
            $parts = explode('/', $combined);
        }
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (empty($stack)) {
                    return null; // escapes the root
                }
                array_pop($stack);
                continue;
            }
            $stack[] = $part;
        }
        if (empty($stack)) {
            return null;
        }
        return implode('/', $stack);
    }

    private static function isExternalUrl(string $url): bool
    {
        return (bool) preg_match('#^(?:https?:|mailto:|tel:|//)#i', $url);
    }

    /**
     * Whitelist of file extensions the asset route is allowed to serve.
     * Everything else 404s.
     */
    private static function mimeFromExtension(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'pdf'         => 'application/pdf',
            default       => null,
        };
    }

    /**
     * Belt-and-braces check before the realpath round: the asset route
     * accepts paths under docs/ ONLY. README.md and other top-level files
     * are not assets.
     */
    private static function isAllowedAssetPath(string $relPath): bool
    {
        if ($relPath === '' || str_contains($relPath, "\0") || str_contains($relPath, "\\")) {
            return false;
        }
        if (str_starts_with($relPath, '/')) {
            return false;
        }
        foreach (explode('/', $relPath) as $segment) {
            if ($segment === '..' || $segment === '.') {
                return false;
            }
        }
        return str_starts_with($relPath, 'docs/');
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 - not found";
    }
}
