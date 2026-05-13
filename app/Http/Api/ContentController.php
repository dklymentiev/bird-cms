<?php

declare(strict_types=1);

namespace App\Http\Api;

use App\Content\ArticleRepository;
use App\Content\AreaRepository;
use App\Content\PageRepository;
use App\Content\ProjectRepository;
use App\Content\ServiceRepository;
use App\Support\Config;
use App\Support\EditLog;

/**
 * /api/v1/content/* -- CRUD across every registered content type.
 *
 * The public API surface mirrors the MCP tool set: list / read / write
 * / delete operate on the same underlying repositories as the admin
 * URL Inventory editor and the MCP server. There is intentionally one
 * code path through which content lands on disk -- the repositories
 * own atomicity, cache invalidation, and validation.
 *
 * Supported types:
 *   articles, pages, services, areas, projects
 *
 * Articles accept an optional category in the URL (and require it on
 * write) so the public API mirrors the on-disk shape. Services accept
 * the same; the path segment is the `type` subfolder (residential /
 * commercial). The route table in public/api/v1/index.php is what
 * actually peels the category off, so this controller just receives
 * a $category argument.
 */
final class ContentController
{
    /**
     * Allowed `type` URL segment -> repository class.
     *
     * Anything not in this map is rejected with 404 unsupported_type
     * so a typo / removed type doesn't accidentally load something
     * else.
     */
    private const TYPE_REPOSITORIES = [
        'articles' => ArticleRepository::class,
        'pages'    => PageRepository::class,
        'services' => ServiceRepository::class,
        'areas'    => AreaRepository::class,
        'projects' => ProjectRepository::class,
    ];

    /**
     * Types that require a category/type segment in the URL on write.
     * Read-by-slug is also category-aware for these; the route table
     * provides the category to show() when present.
     *
     * @var list<string>
     */
    private const CATEGORY_TYPES = ['articles', 'services'];

    /**
     * GET /api/v1/content/<type>
     *
     * Returns a flat list of every published item. Drafts are excluded
     * just like the public site; the read-only API surface should not
     * leak unpublished work even to an authenticated caller.
     */
    public function index(string $type): void
    {
        $repo = $this->repository($type);
        if ($repo === null) {
            Response::error('unsupported_type', 'Unknown content type: ' . $type, 404);
        }

        $items = $repo->all();
        // Strip rendered HTML and the absolute filesystem path: callers
        // only need shape, not rendered output (re-renderable from
        // markdown) or an internal disk path.
        $items = array_map([$this, 'sanitiseRecord'], $items);

        Response::json([
            'type'  => $type,
            'items' => array_values($items),
            'total' => count($items),
        ]);
    }

    /**
     * GET /api/v1/content/<type>/<slug>
     * GET /api/v1/content/articles/<category>/<slug>
     */
    public function show(string $type, string $slug, ?string $category = null): void
    {
        if (!$this->isValidSlug($slug)) {
            Response::error('invalid_slug', 'Slug must match [a-z0-9-]+.', 400);
        }
        if ($category !== null && !$this->isValidSlug($category)) {
            Response::error('invalid_category', 'Category must match [a-z0-9-]+.', 400);
        }

        $repo = $this->repository($type);
        if ($repo === null) {
            Response::error('unsupported_type', 'Unknown content type: ' . $type, 404);
        }

        $record = $this->find($type, $repo, $slug, $category);
        if ($record === null) {
            Response::error('not_found', 'No such item.', 404);
        }

        Response::json($this->sanitiseRecord($record));
    }

    /**
     * POST /api/v1/content/<type>/<slug>
     * POST /api/v1/content/articles/<category>/<slug>
     *
     * Create-or-update. Body: JSON with {frontmatter: {...}, body: "..."}.
     * The 'frontmatter' map mirrors what the MCP write_article tool
     * accepts; this controller forwards it verbatim to the repository
     * after slug/category validation, so the on-disk shape stays
     * identical to MCP writes.
     */
    public function upsert(string $type, string $slug, ?string $category = null): void
    {
        if (!$this->isValidSlug($slug)) {
            Response::error('invalid_slug', 'Slug must match [a-z0-9-]+.', 400);
        }
        if (in_array($type, self::CATEGORY_TYPES, true)) {
            if ($category === null || !$this->isValidSlug($category)) {
                Response::error(
                    'invalid_category',
                    'Type "' . $type . '" requires a category segment.',
                    400
                );
            }
        }

        $repo = $this->repository($type);
        if ($repo === null) {
            Response::error('unsupported_type', 'Unknown content type: ' . $type, 404);
        }

        $payload = Request::json();
        $frontmatter = $payload['frontmatter'] ?? null;
        $body = $payload['body'] ?? '';

        if (!is_array($frontmatter)) {
            Response::error('invalid_body', 'Request body must include a "frontmatter" object.', 400);
        }
        if (!is_string($body)) {
            Response::error('invalid_body', '"body" must be a string.', 400);
        }

        // Tag the downstream repository save() as source=api so the
        // dashboard's Recent edits card distinguishes API writes from
        // admin clicks and MCP-tool calls.
        EditLog::$context = 'api';

        try {
            $this->save($type, $repo, $slug, $category, $frontmatter, $body);
        } catch (\InvalidArgumentException $e) {
            Response::error('invalid_input', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error('write_failed', 'Failed to persist content: ' . $e->getMessage(), 500);
        }

        Response::json([
            'ok'       => true,
            'type'     => $type,
            'slug'     => $slug,
            'category' => $category,
        ], 200);
    }

    /**
     * DELETE /api/v1/content/<type>/<slug>
     * DELETE /api/v1/content/articles/<category>/<slug>
     *
     * Removes the on-disk .md and .meta.yaml pair (or the bundle's
     * index.md + meta.yaml). Idempotent: a missing file returns 200
     * with `deleted: []` so retries don't fail.
     */
    public function destroy(string $type, string $slug, ?string $category = null): void
    {
        if (!$this->isValidSlug($slug)) {
            Response::error('invalid_slug', 'Slug must match [a-z0-9-]+.', 400);
        }
        if (in_array($type, self::CATEGORY_TYPES, true)) {
            if ($category === null || !$this->isValidSlug($category)) {
                Response::error(
                    'invalid_category',
                    'Type "' . $type . '" requires a category segment.',
                    400
                );
            }
        }

        $sourcePath = $this->sourcePath($type);
        if ($sourcePath === null) {
            Response::error('unsupported_type', 'Unknown content type: ' . $type, 404);
        }

        $deleted = [];
        foreach ($this->candidatePaths($type, $sourcePath, $slug, $category) as $path) {
            if (is_file($path) && @unlink($path)) {
                $deleted[] = $this->relativise($path);
            }
        }
        // Bundle directory: if its index.md + meta.yaml are both gone,
        // the empty parent dir is removed too so a re-create starts clean.
        $bundleDir = $this->bundleDirectory($type, $sourcePath, $slug, $category);
        if ($bundleDir !== null && is_dir($bundleDir) && $this->isEmptyDir($bundleDir)) {
            @rmdir($bundleDir);
        }

        // Audit trail: log the delete with the same URL shape the
        // repositories use on save, so the dashboard's Recent edits card
        // shows admin and API deletes consistently. Skipped when nothing
        // was actually removed -- a 200-with-no-op should not pollute
        // the log with phantom rows.
        if (!empty($deleted)) {
            EditLog::record(
                'api',
                'delete',
                $this->deleteUrl($type, $slug, $category),
                $type === 'articles' ? 'article'
                    : ($type === 'pages' ? 'page'
                        : ($type === 'services' ? 'service'
                            : ($type === 'areas' ? 'area' : 'project'))),
                $slug
            );
        }

        Response::json([
            'ok'      => true,
            'deleted' => $deleted,
        ]);
    }

    /**
     * URL shape used for delete audit rows. Matches what each repository
     * passes to EditLog::record() on save so the dashboard renders the
     * same canonical link whether a row was added by a save or a delete.
     */
    private function deleteUrl(string $type, string $slug, ?string $category): string
    {
        switch ($type) {
            case 'articles':
                return '/' . (string) $category . '/' . $slug;
            case 'services':
                return '/' . (string) $category . '/' . $slug;
            case 'projects':
                return '/projects/' . $slug;
            case 'areas':
                return '/areas/' . $slug;
            case 'pages':
            default:
                return '/' . $slug;
        }
    }

    /**
     * Resolve a type to a fresh repository instance bound to the
     * configured source path. Returns null when the type isn't
     * registered.
     */
    private function repository(string $type): ?object
    {
        if (!isset(self::TYPE_REPOSITORIES[$type])) {
            return null;
        }
        $source = $this->sourcePath($type);
        if ($source === null) {
            return null;
        }
        $class = self::TYPE_REPOSITORIES[$type];
        return new $class($source);
    }

    private function sourcePath(string $type): ?string
    {
        try {
            $config = Config::load('content');
        } catch (\Throwable) {
            return null;
        }
        $sub = $config['types'][$type]['source'] ?? ('content/' . $type);
        $root = defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 3);
        return $root . '/' . ltrim((string) $sub, '/');
    }

    /**
     * Per-type lookup wrapper. Each repository has its own find()
     * signature; mirrors what App\Admin\PagesController::loadRecord
     * does, but stays independent so the public API can evolve its
     * own validation rules without touching admin.
     */
    private function find(string $type, object $repo, string $slug, ?string $category): ?array
    {
        switch ($type) {
            case 'articles':
                return $repo->find((string) $category, $slug);
            case 'services':
                return $repo->find((string) $category, $slug);
            case 'pages':
            case 'projects':
            case 'areas':
                return $repo->find($slug);
        }
        return null;
    }

    /**
     * @param array<string, mixed> $frontmatter
     */
    private function save(string $type, object $repo, string $slug, ?string $category, array $frontmatter, string $body): void
    {
        switch ($type) {
            case 'articles':
                $repo->save((string) $category, $slug, $frontmatter, $body);
                return;
            case 'services':
                $repo->save((string) $category, $slug, $frontmatter, $body);
                return;
            case 'areas':
                $repo->save($slug, $frontmatter, $body);
                return;
            case 'pages':
            case 'projects':
                $repo->save($slug, $frontmatter, $body);
                return;
        }
        throw new \InvalidArgumentException('Unsupported type: ' . $type);
    }

    /**
     * Enumerate on-disk paths that destroy() should try to unlink.
     * Covers both the flat (slug.md + slug.meta.yaml) and bundle
     * (slug/index.md + slug/meta.yaml) layouts so a delete always
     * cleans the layout the repository actually wrote.
     *
     * @return list<string>
     */
    private function candidatePaths(string $type, string $sourcePath, string $slug, ?string $category): array
    {
        switch ($type) {
            case 'articles':
            case 'services':
                $dir = $sourcePath . '/' . (string) $category;
                return [
                    $dir . '/' . $slug . '.md',
                    $dir . '/' . $slug . '.meta.yaml',
                    $dir . '/' . $slug . '/index.md',
                    $dir . '/' . $slug . '/meta.yaml',
                ];
            case 'pages':
                return [
                    $sourcePath . '/' . $slug . '.md',
                    $sourcePath . '/' . $slug . '.meta.yaml',
                    $sourcePath . '/' . $slug . '/index.md',
                    $sourcePath . '/' . $slug . '/meta.yaml',
                ];
            case 'projects':
                return [
                    $sourcePath . '/' . $slug . '.md',
                    $sourcePath . '/' . $slug . '/index.md',
                ];
            case 'areas':
                return [$sourcePath . '/' . $slug . '.yaml'];
        }
        return [];
    }

    private function bundleDirectory(string $type, string $sourcePath, string $slug, ?string $category): ?string
    {
        switch ($type) {
            case 'articles':
            case 'services':
                return $sourcePath . '/' . (string) $category . '/' . $slug;
            case 'pages':
            case 'projects':
                return $sourcePath . '/' . $slug;
        }
        return null;
    }

    private function isEmptyDir(string $dir): bool
    {
        $items = @scandir($dir) ?: [];
        foreach ($items as $i) {
            if ($i !== '.' && $i !== '..') {
                return false;
            }
        }
        return true;
    }

    private function relativise(string $path): string
    {
        $root = defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 3);
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root));
        }
        return $path;
    }

    /**
     * Trim internal-only fields off a repository record before it
     * goes out the wire:
     *   - html: rebuildable from `content`; bloats the payload.
     *   - path / bundle_path: server filesystem details.
     */
    private function sanitiseRecord(array $record): array
    {
        unset($record['html'], $record['path'], $record['bundle_path']);
        return $record;
    }

    private function isValidSlug(string $s): bool
    {
        return $s !== '' && preg_match('/^[a-z0-9-]+$/', $s) === 1;
    }
}
