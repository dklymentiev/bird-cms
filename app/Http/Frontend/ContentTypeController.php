<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;
use App\Content\MetricsRepository;
use App\Http\ContentRouter;
use App\Support\Config;
use App\Theme\ThemeManager;

/**
 * Generic dispatch for content types declared in `config/content.php`:
 * projects, services, areas, and any future custom type a site adds.
 *
 * One controller for all of them by design — repositories implement
 * ContentRepositoryInterface uniformly, and ContentRouter resolves
 * patterns without a per-type switch. Adding per-type
 * ServiceController/AreaController/ProjectController classes would
 * duplicate identical code (verified against the pre-refactor index.php:
 * the two branches handled every type the same way).
 *
 * Two render shapes are produced:
 *   - INDEX (`is_index=true`):     /<type>            -> theme's index_view
 *   - ITEM  (`item` is non-null):  /<type>/<slug>     -> theme's view
 *
 * Articles and pages are intentionally excluded here — the dispatcher
 * yields those to ArticleController / PageController, which apply
 * post-load enrichment (TOC, LinkFilter, adjacent, pillar-aware related)
 * that this generic branch doesn't know about.
 */
final class ContentTypeController
{
    /** @param callable(string): string $templateResolver */
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly MetricsRepository $metrics,
        private readonly ThemeManager $theme,
        /** @var list<string> */
        private readonly array $categoriesList,
        private readonly string $siteRoot,
        private $templateResolver,
    ) {
    }

    /**
     * Returns true if this controller handled the URI; false if the
     * dispatcher should try the next handler. We don't render a 404
     * ourselves here — page/category/article handlers run after.
     */
    public function handle(string $uri): bool
    {
        $contentTypesConfig = [];
        try {
            $contentTypesConfig = Config::load('content');
        } catch (\Throwable) {
            $contentTypesConfig = (array) config('content', []);
        }
        if (empty($contentTypesConfig['types'])) {
            return false;
        }

        $router = new ContentRouter($contentTypesConfig);
        $match = $router->match('/' . $uri);

        // Articles and pages have dedicated downstream handlers that apply
        // TOC heading IDs, ArticleRepository::adjacent, LinkFilter, related
        // pools, etc. Generic dispatch would skip all of that, so yield.
        if ($match !== null && in_array($match['type'], ['articles', 'pages'], true)) {
            return false;
        }
        if ($match === null) {
            return false;
        }

        $typeConfig = $match['config'];
        $resolve = $this->templateResolver;
        $sharedData = [
            'config'         => config(),
            'latest'         => $this->articles->latest(6),
            'recentPosts'    => $this->articles->latest(10),
            'categoriesList' => $this->categoriesList,
            'metrics'        => $this->metrics,
        ];

        // Index URL (e.g. /projects -> projects list)
        if (!empty($match['is_index']) && !empty($typeConfig['index_view'])) {
            $repoClass  = $typeConfig['repository'];
            $sourcePath = $this->siteRoot . '/' . ltrim($typeConfig['source'] ?? 'content/' . $match['type'], '/');
            $typeRepo   = new $repoClass($sourcePath);
            $items      = method_exists($typeRepo, 'all') ? $typeRepo->all() : [];
            $this->theme->render($resolve($typeConfig['index_view']), $sharedData + [
                $match['type'] => $items,
                'items'        => $items,
            ]);
            return true;
        }

        // Item URL (e.g. /projects/bird-cms).
        // We pass both the singular key (matched item) and the plural key
        // (full list) — themes that iterate $projects for "See also"
        // sections need the list, themes that read $project use the
        // singular. Skipping either crashes partials downstream.
        if (!empty($match['item']) && !empty($typeConfig['view'])) {
            $singularKey = rtrim($match['type'], 's');
            $repoClass   = $typeConfig['repository'];
            $sourcePath  = $this->siteRoot . '/' . ltrim($typeConfig['source'] ?? 'content/' . $match['type'], '/');
            $typeRepo    = new $repoClass($sourcePath);
            $allItems    = method_exists($typeRepo, 'all') ? $typeRepo->all() : [];
            $this->theme->render($resolve($typeConfig['view']), $sharedData + [
                $singularKey   => $match['item'],
                $match['type'] => $allItems,
                'item'         => $match['item'],
            ]);
            return true;
        }

        return false;
    }
}
