<?php

declare(strict_types=1);

namespace App\Content;

use App\Support\EditLog;
use App\Support\HtmlCache;

/**
 * Area Repository
 *
 * Manages service areas stored as YAML files in content/areas/
 * Supports parent/child relationships for sub-areas (e.g., Toronto -> North York)
 */
final class AreaRepository implements ContentRepositoryInterface
{
    use AtomicMarkdownWrite;
    use ContentCache;

    public function __construct(private readonly string $areasDir)
    {
    }

    /**
     * Persist an area from the URL Inventory editor.
     *
     * Areas are pure YAML (no markdown body); the editor's body field is
     * stored in the meta as `body_md` only if the operator entered one,
     * which keeps the on-disk shape compatible with existing area files
     * that don't carry one. Subarea relationships (parent) are preserved
     * verbatim from the meta payload.
     *
     * @param array<string, mixed> $meta
     */
    public function save(string $slug, array $meta, string $body = ''): void
    {
        $this->assertValidSlug($slug, 'slug');

        $meta['slug'] = $slug;
        if ($body !== '') {
            $meta['body_md'] = $body;
        }

        $this->atomicWrite(
            $this->areasDir . '/' . $slug . '.yaml',
            FrontMatter::encode($meta) . "\n"
        );

        $this->memoForget();
        $this->fsCacheForget('areas-index');

        // HTML cache invalidation. Areas surface at /areas/<slug>,
        // /<slug>, or /areas/<parent>/<slug> depending on
        // config/content.php; conservatively forget all of them plus the
        // index and homepage. Subareas pass their parent in meta; clear
        // the parent's cached page too so a subarea add re-renders the
        // parent's subarea list.
        $parent = isset($meta['parent']) ? (string) $meta['parent'] : '';
        HtmlCache::forget('areas/' . $slug);
        HtmlCache::forget($slug);
        if ($parent !== '') {
            HtmlCache::forget('areas/' . $parent . '/' . $slug);
            HtmlCache::forget('areas/' . $parent);
            HtmlCache::forget($parent);
        }
        HtmlCache::forget('areas');
        HtmlCache::forget('home');

        // Audit trail for the admin "Recent edits" card. Subareas land at
        // /areas/<parent>/<slug>; top-level at /areas/<slug>.
        $url = $parent !== ''
            ? '/areas/' . $parent . '/' . $slug
            : '/areas/' . $slug;
        EditLog::record(
            EditLog::$context ?? 'unknown',
            'save',
            $url,
            'area',
            $slug
        );
    }

    /**
     * Find an area by slug
     */
    public function find(string $slug): ?array
    {
        $areas = $this->loadAll();
        return $areas[$slug] ?? null;
    }

    /**
     * Find a sub-area by parent and slug
     */
    public function findSubarea(string $parentSlug, string $slug): ?array
    {
        $parent = $this->find($parentSlug);
        if ($parent === null) {
            return null;
        }

        $subareas = $parent['subareas_data'] ?? [];
        return $subareas[$slug] ?? null;
    }

    /**
     * Get all areas (top-level + subareas) as a flat list.
     *
     * Subareas appear as their own records and carry a 'parent' field, which
     * lets ContentRouter match them against the 'subarea_url' pattern.
     */
    public function all(): array
    {
        $loaded = $this->loadAll();
        $flat = [];

        foreach ($loaded as $area) {
            $flat[] = $area;
            foreach (($area['subareas_data'] ?? []) as $subarea) {
                $flat[] = $subarea;
            }
        }

        return $flat;
    }

    /**
     * Get all top-level areas only (those without a parent), preserving the
     * pre-RFC `all()` semantic for callers that only want the top tier.
     *
     * @return array<string, array>
     */
    public function topLevel(): array
    {
        $areas = $this->loadAll();
        return array_filter($areas, static fn($area) => empty($area['parent']));
    }

    public function findByParams(array $params): ?array
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return null;
        }

        $parent = (string) ($params['parent'] ?? '');
        if ($parent !== '') {
            return $this->findSubarea($parent, $slug);
        }

        return $this->find($slug);
    }

    /**
     * Load and cache all areas from files
     */
    private function loadAll(): array
    {
        return $this->memo('all', function () {
            return $this->fsCache(
                'areas-index',
                [$this->areasDir],
                fn() => $this->scanAreas()
            );
        });
    }

    /**
     * Raw scan of areas/*.yaml. Stays separate from loadAll() so the
     * cache layer wraps a pure function.
     */
    private function scanAreas(): array
    {
        if (!is_dir($this->areasDir)) {
            return [];
        }

        $areas = [];
        $subareas = [];

        foreach (glob($this->areasDir . '/*.yaml') as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = FrontMatter::parse($content);
            if (empty($data['slug'])) {
                $data['slug'] = basename($file, '.yaml');
            }

            $slug = $data['slug'];

            // Check if this is a sub-area
            if (!empty($data['parent'])) {
                $subareas[$data['parent']][$slug] = $data;
            } else {
                $areas[$slug] = $data;
            }
        }

        // Attach sub-areas to their parents
        foreach ($subareas as $parentSlug => $children) {
            if (isset($areas[$parentSlug])) {
                $areas[$parentSlug]['subareas_data'] = $children;
            }
        }

        return $areas;
    }

    /**
     * Get all sub-areas for a parent area
     */
    public function subareasOf(string $parentSlug): array
    {
        $parent = $this->find($parentSlug);
        if ($parent === null) {
            return [];
        }

        return $parent['subareas_data'] ?? [];
    }
}
