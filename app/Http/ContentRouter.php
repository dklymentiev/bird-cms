<?php

declare(strict_types=1);

namespace App\Http;

use App\Content\ContentRepositoryInterface;

/**
 * Content Router
 *
 * Routes URLs to content items based on declarative content type configuration.
 * Single source of truth for routing and sitemap generation.
 *
 * Routing and sitemap generation are pattern-driven: there is no per-type
 * code path. Repositories implementing ContentRepositoryInterface plug in via
 * config/content.php and the router treats them uniformly. Each type can
 * declare multiple URL patterns (`url`, `subarea_url`, ...) and the router
 * picks the first whose placeholders are all satisfied by a given item.
 */
class ContentRouter
{
    private array $types;
    private array $priority;
    private array $repositories = [];

    public function __construct(array $config)
    {
        $this->types = $config['types'] ?? [];
        $this->priority = $config['priority'] ?? array_keys($this->types);
    }

    /**
     * Match a URI to a content item
     *
     * @return array|null ['type' => string, 'item' => array, 'config' => array, 'params' => array]
     */
    public function match(string $uri): ?array
    {
        $uri = '/' . trim($uri, '/');

        foreach ($this->priority as $typeName) {
            if (!isset($this->types[$typeName])) {
                continue;
            }

            $config = $this->types[$typeName];
            $result = $this->matchType($uri, $typeName, $config);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Match URI against a specific content type
     */
    private function matchType(string $uri, string $typeName, array $config): ?array
    {
        // Check index URL first (e.g., /projects, /areas)
        if (isset($config['index_url'])) {
            $indexPattern = $this->urlToRegex($config['index_url']);
            if (preg_match($indexPattern, $uri, $matches)) {
                return [
                    'type' => $typeName,
                    'item' => null,
                    'config' => $config,
                    'params' => $this->extractParams($config['index_url'], $matches),
                    'is_index' => true,
                ];
            }
        }

        // Check subarea URL if exists (e.g., /areas/toronto/north-york)
        if (isset($config['subarea_url'])) {
            $pattern = $this->urlToRegex($config['subarea_url']);
            if (preg_match($pattern, $uri, $matches)) {
                $params = $this->extractParams($config['subarea_url'], $matches);
                $repository = $this->getRepository($config);
                $item = $this->findItem($repository, $typeName, $params);

                if ($item !== null) {
                    return [
                        'type' => $typeName,
                        'item' => $item,
                        'config' => $config,
                        'params' => $params,
                        'is_subarea' => true,
                    ];
                }
            }
        }

        // Check main URL pattern
        $pattern = $this->urlToRegex($config['url']);
        if (preg_match($pattern, $uri, $matches)) {
            $params = $this->extractParams($config['url'], $matches);
            $repository = $this->getRepository($config);
            $item = $this->findItem($repository, $typeName, $params);

            if ($item !== null) {
                return [
                    'type' => $typeName,
                    'item' => $item,
                    'config' => $config,
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    /**
     * Convert URL pattern to regex.
     *
     * - `{name}` is required: matches one path segment, captured.
     * - `{name?}` is optional: the placeholder AND its preceding slash
     *   become a single optional group. The captured value is null when
     *   the segment is absent.
     *
     * Examples:
     *   /{category}/{slug}              -> /^\/([a-z0-9-]+)\/([a-z0-9-]+)\/?$/
     *   /{category}/{subcategory?}/{slug} matches both /blog/foo and
     *   /blog/resources/foo (subcategory captured as null in the first case)
     */
    private function urlToRegex(string $pattern): string
    {
        // Use '#' as PCRE delimiter so literal '/' in URL patterns doesn't
        // need escaping (and a missed escape doesn't blow up at runtime).
        $regex = preg_replace_callback(
            '#(/?)\{([a-z_]+)(\??)\}#',
            function (array $m): string {
                [$_, $slash, $name, $optional] = $m;
                $cap = '([a-z0-9-]+)';
                if ($optional === '?') {
                    return '(?:' . $slash . $cap . ')?';
                }
                return $slash . $cap;
            },
            $pattern
        );
        return '#^' . $regex . '/?$#';
    }

    /**
     * Extract named parameters from matches.
     *
     * The `?` suffix is stripped from the param name so callers (repositories,
     * URL renderers) see a stable key. Optional segments that didn't match
     * yield a null value at that key.
     */
    private function extractParams(string $pattern, array $matches): array
    {
        preg_match_all('/\{([a-z_]+)(\??)\}/', $pattern, $paramNames);
        $params = [];

        foreach ($paramNames[1] as $i => $name) {
            $value = $matches[$i + 1] ?? null;
            $params[$name] = ($value === '' ? null : $value);
        }

        return $params;
    }

    /**
     * Get or create repository instance
     */
    private function getRepository(array $config): object
    {
        $class = $config['repository'];

        if (!isset($this->repositories[$class])) {
            $sourcePath = $this->resolveSourcePath($config['source']);
            $this->repositories[$class] = new $class($sourcePath);
        }

        return $this->repositories[$class];
    }

    /**
     * Resolve source path relative to site root
     */
    private function resolveSourcePath(string $source): string
    {
        if (defined('SITE_ROOT')) {
            return SITE_ROOT . '/' . $source;
        }

        return getcwd() . '/' . $source;
    }

    /**
     * Find item using repository.
     *
     * Repositories implementing ContentRepositoryInterface dispatch through
     * findByParams(), which lets each repository interpret the URL parameter
     * map without the router having to know the per-type calling convention.
     */
    private function findItem(object $repository, string $typeName, array $params): ?array
    {
        if ($repository instanceof ContentRepositoryInterface) {
            return $repository->findByParams($params);
        }

        // Legacy fallback for repositories not yet implementing the interface.
        if (!method_exists($repository, 'find')) {
            return null;
        }
        $slug = $params['slug'] ?? '';
        if (isset($params['category'])) {
            return $repository->find($params['category'], $slug);
        }
        if (isset($params['type'])) {
            return $repository->find($params['type'], $slug);
        }
        if (isset($params['parent']) && method_exists($repository, 'findSubarea')) {
            return $repository->findSubarea($params['parent'], $slug);
        }
        return $repository->find($slug);
    }

    /**
     * Get all URLs for sitemap generation.
     *
     * For each registered content type, emits:
     *   - a static index URL (if `index_url` has no placeholders)
     *   - one URL per repository item, using the most specific URL pattern
     *     (e.g. `subarea_url` before `url`) whose placeholders are satisfied
     *     by that item's fields.
     *
     * @return array<int, array{loc: string, priority: string, changefreq: string, lastmod: string, type: string}>
     */
    public function allUrls(string $baseUrl): array
    {
        $urls = [];
        $baseUrl = rtrim($baseUrl, '/');

        foreach ($this->types as $typeName => $config) {
            $repository = $this->getRepository($config);

            // Static index URL (no placeholders)
            if (isset($config['index_url']) && !str_contains($config['index_url'], '{')) {
                $urls[] = [
                    'loc' => $baseUrl . $config['index_url'],
                    'priority' => $config['sitemap']['priority'] ?? '0.5',
                    'changefreq' => $config['sitemap']['changefreq'] ?? 'weekly',
                    'lastmod' => date('Y-m-d'),
                    'type' => $typeName,
                ];
            }

            $patterns = $this->urlPatternsFor($config);
            if (empty($patterns)) {
                continue;
            }

            foreach ($this->getAllItems($repository) as $item) {
                $url = $this->renderFirstMatchingPattern($patterns, $item);
                if ($url === null) {
                    continue;
                }
                $urls[] = [
                    'loc' => $baseUrl . $url,
                    'priority' => $item['sitemap_priority']
                        ?? $config['sitemap']['priority']
                        ?? '0.5',
                    'changefreq' => $item['sitemap_changefreq']
                        ?? $config['sitemap']['changefreq']
                        ?? 'weekly',
                    'lastmod' => $item['lastmod']
                        ?? $item['date']
                        ?? $item['updated']
                        ?? date('Y-m-d'),
                    'type' => $typeName,
                ];
            }
        }

        return $urls;
    }

    /**
     * Get all items from repository as a flat list.
     *
     * Repositories implementing ContentRepositoryInterface guarantee a flat
     * shape. The legacy fallback returns whatever ->all() yields.
     */
    private function getAllItems(object $repository): array
    {
        if ($repository instanceof ContentRepositoryInterface) {
            return $repository->all();
        }
        if (method_exists($repository, 'all')) {
            return $repository->all();
        }
        return [];
    }

    /**
     * URL patterns for a content type, ordered most-specific first.
     *
     * Subarea/nested patterns are tried before the main pattern so that
     * items carrying their parent (e.g. an area subarea with `parent` set)
     * render under the more specific URL.
     *
     * @return array<int, string>
     */
    private function urlPatternsFor(array $config): array
    {
        $patterns = [];
        if (isset($config['subarea_url'])) {
            $patterns[] = $config['subarea_url'];
        }
        if (isset($config['url'])) {
            $patterns[] = $config['url'];
        }
        return $patterns;
    }

    /**
     * Try each pattern in order, returning the first that fully renders
     * against the given item (all placeholders resolved to non-empty values).
     */
    private function renderFirstMatchingPattern(array $patterns, array $item): ?string
    {
        foreach ($patterns as $pattern) {
            $url = $this->renderPattern($pattern, $item);
            if ($url !== null) {
                return $url;
            }
        }
        return null;
    }

    /**
     * Render a URL pattern against an item.
     *
     * Required placeholder (`{name}`) missing -> returns null (try next pattern).
     * Optional placeholder (`{name?}`) missing -> the preceding slash + the
     *   placeholder are removed cleanly, leaving a valid shorter URL.
     */
    private function renderPattern(string $pattern, array $item): ?string
    {
        if (!preg_match_all('/(\/?)\{([a-z_]+)(\??)\}/', $pattern, $matches, PREG_SET_ORDER)) {
            return $pattern;
        }

        $url = $pattern;
        foreach ($matches as $m) {
            [$whole, $slash, $name, $optional] = $m;
            $value = $item[$name] ?? null;
            $missing = ($value === null || $value === '');

            if ($missing) {
                if ($optional === '?') {
                    // Drop the slash AND the placeholder so /a/{x?}/b becomes /a/b
                    $url = str_replace($whole, '', $url);
                    continue;
                }
                return null;
            }

            $url = str_replace($whole, $slash . (string) $value, $url);
        }
        return $url;
    }

    /**
     * Reverse-lookup: turn a URL path back into a ContentDescriptor that
     * tells the URL Inventory editor where the underlying content lives.
     *
     * Lookup order:
     *   1. The static URLs the engine renders programmatically: '/' and
     *      every article category index. Body editing of these is opt-in
     *      via a content/pages/<slug>.md fall-through (Step 5); the
     *      descriptor's editableBody flag stays false until that file
     *      exists.
     *   2. ContentRouter::match() — same logic that dispatches frontend
     *      requests, so any URL the public site will resolve also
     *      resolves here. The matched type's repository class is reused
     *      for the editor write path.
     *
     * Returns null when no content type and no static fall-through claims
     * the URL. Callers (PagesController::editContent) should treat this
     * as "URL not editable through the inventory yet".
     */
    public function resolve(string $urlPath): ?ContentDescriptor
    {
        $urlPath = '/' . ltrim($urlPath, '/');

        // Homepage. Always present in the inventory; body comes from the
        // optional content/pages/home.md fall-through.
        if ($urlPath === '/') {
            return new ContentDescriptor(
                source: 'static',
                slug: 'home',
                category: null,
                repositoryClass: \App\Content\PageRepository::class,
                editableBody: $this->staticOverrideExists('home'),
                editableTemplate: true,
            );
        }

        $match = $this->match($urlPath);
        if ($match !== null && !empty($match['item'])) {
            $typeName = (string) $match['type'];
            $params   = $match['params'] ?? [];
            $item     = $match['item'];

            $slug = (string) (
                $params['slug']
                ?? $item['slug']
                ?? ''
            );
            $category = $this->categoryFromMatch($typeName, $params, $item);

            return new ContentDescriptor(
                source: $typeName,
                slug: $slug,
                category: $category,
                repositoryClass: (string) $match['config']['repository'],
                editableBody: true,
                editableTemplate: true,
            );
        }

        // Content-type index pages (/projects, /areas). public/index.php
        // renders these via the type's index_view template and they have no
        // single underlying file. Treat them as static so the inventory
        // can still flip the template + author an intro fall-through page.
        if ($match !== null && !empty($match['is_index'])) {
            $slug = trim($urlPath, '/');
            if ($slug === '') $slug = 'home';
            return new ContentDescriptor(
                source: 'static',
                slug: $slug,
                category: null,
                repositoryClass: \App\Content\PageRepository::class,
                editableBody: $this->staticOverrideExists($slug),
                editableTemplate: true,
            );
        }

        // Article category index pages: /<category> with no second segment.
        // ContentRouter::match doesn't emit these (they're rendered by
        // public/index.php's category branch), but the URL Inventory
        // surfaces them as their own rows.
        $segments = array_values(array_filter(explode('/', trim($urlPath, '/'))));
        if (count($segments) === 1 && preg_match('/^[a-z0-9-]+$/', $segments[0])) {
            $slug = $segments[0];
            if ($this->isArticleCategory($slug)) {
                return new ContentDescriptor(
                    source: 'static',
                    slug: $slug,
                    category: null,
                    repositoryClass: \App\Content\PageRepository::class,
                    editableBody: $this->staticOverrideExists($slug),
                    editableTemplate: true,
                );
            }
        }

        return null;
    }

    /**
     * Read the per-type 'category' field from the matched URL parameters.
     *
     * Articles encode category in the URL (`/{category}/{slug}`); services
     * use `{type}` as the bucket; everything else has no category.
     *
     * @param array<string, string|null> $params
     * @param array<string, mixed>       $item
     */
    private function categoryFromMatch(string $typeName, array $params, array $item): ?string
    {
        if ($typeName === 'articles') {
            return isset($params['category']) ? (string) $params['category'] : (string) ($item['category'] ?? '');
        }
        if ($typeName === 'services') {
            return isset($params['type']) ? (string) $params['type'] : (string) ($item['type'] ?? '');
        }
        if ($typeName === 'areas' && !empty($params['parent'])) {
            return (string) $params['parent'];
        }
        return null;
    }

    /**
     * Whether content/pages/<slug>.md (or bundle) exists on disk.
     * Used to flip the static descriptor's editableBody flag once the
     * operator creates a fall-through page.
     */
    private function staticOverrideExists(string $slug): bool
    {
        $pagesDir = $this->resolveSourcePath('content/pages');
        return file_exists($pagesDir . '/' . $slug . '.md')
            || file_exists($pagesDir . '/' . $slug . '/index.md');
    }

    /**
     * Cheap on-disk check for an article category directory. Avoids
     * spinning up ArticleRepository + a config load just to detect a
     * single-segment URL belongs to /blog vs /devops.
     */
    private function isArticleCategory(string $slug): bool
    {
        $articlesDir = $this->resolveSourcePath('content/articles');
        return is_dir($articlesDir . '/' . $slug);
    }

    /**
     * Get content type configuration
     */
    public function getType(string $typeName): ?array
    {
        return $this->types[$typeName] ?? null;
    }

    /**
     * Get all registered content types
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Check if a content type exists
     */
    public function hasType(string $typeName): bool
    {
        return isset($this->types[$typeName]);
    }
}
