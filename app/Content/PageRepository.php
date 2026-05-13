<?php

declare(strict_types=1);

namespace App\Content;

use App\Support\EditLog;
use App\Support\HtmlCache;
use App\Support\Markdown;

final class PageRepository implements ContentRepositoryInterface
{
    use AtomicMarkdownWrite;
    use ContentCache;

    public function __construct(private readonly string $pagesDir)
    {
    }

    /**
     * Persist a page from the URL Inventory editor.
     *
     * Mirrors {@see ArticleRepository::save()}: writes into the existing
     * layout (flat .md + .meta.yaml at the top of pagesDir, or
     * <slug>/index.md + <slug>/meta.yaml bundle) and falls back to flat
     * for newly created pages -- including the homepage / category-index
     * fall-through pages introduced in Step 5.
     *
     * @param array<string, mixed> $meta YAML keys (title, description,
     *                                   status, etc. -- whatever the page wants)
     */
    public function save(string $slug, array $meta, string $body): void
    {
        $this->assertValidSlug($slug, 'slug');

        $bundleIndex = $this->pagesDir . '/' . $slug . '/index.md';
        $bundleMeta  = $this->pagesDir . '/' . $slug . '/meta.yaml';
        $flatBody    = $this->pagesDir . '/' . $slug . '.md';
        $flatMeta    = $this->pagesDir . '/' . $slug . '.meta.yaml';

        if (file_exists($bundleIndex)) {
            $bodyPath = $bundleIndex;
            $metaPath = $bundleMeta;
        } else {
            $bodyPath = $flatBody;
            $metaPath = $flatMeta;
        }

        $this->atomicWrite($bodyPath, $body);
        $this->atomicWrite($metaPath, FrontMatter::encode($meta) . "\n");

        // Pages don't have a global list memo (find() reads single files),
        // but all() and the disk cache aggregate everything; both need to
        // drop the just-edited page out of their stored copies.
        $this->memoForget();
        $this->fsCacheForget('pages-index');

        // HTML cache invalidation. A page save can affect:
        //   - the page's own URL (/<slug>)
        //   - the homepage when the page is the category-index fall-through
        //     (Step 5 of the URL Inventory work uses content/pages/<cat>.md
        //     as the category intro) or when 'home' is the saved slug
        //   - llms.txt only when the page is the homepage marker (page
        //     listings don't appear in llms.txt today, but it's cheap)
        // We can't reliably tell which case applies without re-reading
        // config; the conservative move is to forget all three. Each is an
        // idempotent unlink.
        HtmlCache::forget($slug);
        HtmlCache::forget('home');
        HtmlCache::forget('llms.txt');

        // Audit trail for the admin "Recent edits" card. EditLog::$context
        // is set by Admin\Controller / API ContentController so the source
        // attribution lands automatically; MCP handlers pass 'mcp' directly.
        EditLog::record(
            EditLog::$context ?? 'unknown',
            'save',
            '/' . $slug,
            'page',
            $slug
        );
    }

    public function findByParams(array $params): ?array
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return null;
        }
        return $this->find($slug);
    }

    public function find(string $slug): ?array
    {
        if (!$this->isValidSlug($slug)) {
            return null;
        }

        // Flat layout: <slug>.md + <slug>.meta.yaml at the top of pagesDir.
        $flatMd   = $this->pagesDir . '/' . $slug . '.md';
        $flatMeta = $this->pagesDir . '/' . $slug . '.meta.yaml';
        if (file_exists($flatMd) && file_exists($flatMeta)) {
            return $this->loadPage($slug, $flatMd, $flatMeta);
        }

        // Bundle layout: <slug>/index.md + <slug>/meta.yaml. Mirrors what
        // ArticleRepository does for bundle articles. Lets a page own its
        // own assets in a sibling subdirectory without breaking the flat
        // pages that already exist.
        $bundleMd   = $this->pagesDir . '/' . $slug . '/index.md';
        $bundleMeta = $this->pagesDir . '/' . $slug . '/meta.yaml';
        if (file_exists($bundleMd) && file_exists($bundleMeta)) {
            return $this->loadPage($slug, $bundleMd, $bundleMeta);
        }

        return null;
    }

    /**
     * Read a single page from the given markdown + meta paths.
     *
     * Both files are required; PageRepository never invents metadata
     * or bodies, so a missing pair returns null at the call sites above.
     *
     * @return array{slug:string,title:string,description:string,meta:array,content:string,html:string}
     */
    private function loadPage(string $slug, string $mdPath, string $metaPath): array
    {
        $metaContents = file_get_contents($metaPath);
        $meta = $metaContents !== false ? FrontMatter::parse($metaContents) : [];

        $contents = file_get_contents($mdPath);
        $body = $contents !== false ? trim($contents) : '';

        return [
            'slug' => $slug,
            'title' => $meta['title'] ?? ucfirst($slug),
            'description' => $meta['description'] ?? '',
            'meta' => $meta,
            'content' => $body,
            'html' => Markdown::toHtml($body),
        ];
    }

    /**
     * Get all pages
     */
    public function all(): array
    {
        return $this->memo('all', function () {
            return $this->fsCache(
                'pages-index',
                [$this->pagesDir],
                fn() => $this->scanPages()
            );
        });
    }

    /**
     * Raw scan of pages/. Walks flat layout (slug.md) first, then bundle
     * layout (slug/index.md) so flat wins when both exist for the same
     * slug. Stays separate so the cache wraps a pure function.
     */
    private function scanPages(): array
    {
        if (!is_dir($this->pagesDir)) {
            return [];
        }

        $pages = [];
        $seen  = [];

        // Flat layout: every <slug>.md at the top level.
        foreach (glob($this->pagesDir . '/*.md') as $file) {
            $slug = basename($file, '.md');
            if (isset($seen[$slug])) {
                continue;
            }
            $page = $this->find($slug);
            if ($page !== null) {
                $pages[] = $page;
                $seen[$slug] = true;
            }
        }

        // Bundle layout: every <slug>/index.md sibling. Flat takes priority
        // when both exist for the same slug; the lookup order in find()
        // already guarantees that.
        foreach (glob($this->pagesDir . '/*/index.md') as $indexPath) {
            $slug = basename(dirname($indexPath));
            if (isset($seen[$slug])) {
                continue;
            }
            $page = $this->find($slug);
            if ($page !== null) {
                $pages[] = $page;
                $seen[$slug] = true;
            }
        }

        return $pages;
    }

    private function isValidSlug(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[a-z0-9-]+$/', $slug) === 1;
    }
}
