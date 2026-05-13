<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;
use App\Content\MetricsRepository;
use App\Content\PageRepository;
use App\Theme\ThemeManager;

/**
 * `/<category>` — article category index.
 *
 * Renders up to 12 articles per page from the category, with optional
 * type filter (`?type=`), tag filter (`?tag=`), and sort (`?sort=popular`
 * or `?sort=latest`, default latest). All semantics preserved verbatim
 * from the procedural index.php:
 *
 *   - Pulls up to 100 articles for in-memory filtering (the limit prevents
 *     pathological cases on very large categories; can grow later if
 *     someone hits it).
 *   - Type/tag filters are case-insensitive and exact-match.
 *   - `popular` sort prefers `priority` (higher first), tie-breaks by
 *     date desc; `latest` reuses the repository's default ordering.
 *   - Category existence check uses `inCategory(..., 1)` so an empty
 *     filter result on a real category renders an empty listing (not
 *     404), but an unknown category does 404.
 *   - `intro` comes from `content/pages/<category>.md` when present
 *     (Step 5 of the URL inventory work).
 */
final class CategoryController
{
    /** @param callable(string): string $templateResolver */
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly PageRepository $pages,
        private readonly MetricsRepository $metrics,
        private readonly ThemeManager $theme,
        /** @var list<string> */
        private readonly array $categoriesList,
        private $templateResolver,
    ) {
    }

    /**
     * @param list<string>          $segments
     * @param array<string, mixed>  $query
     */
    public function handle(array $segments, array $query): void
    {
        $category = $segments[0];
        if (preg_match('/^[a-z0-9-]+$/', $category) !== 1) {
            http_response_code(404);
            $this->theme->render('404', [
                'category' => $category,
                'categoriesList' => $this->categoriesList,
            ]);
            return;
        }

        $articles = $this->articles->inCategory($category, 100);

        $filterType = isset($query['type']) ? strtolower(trim((string) $query['type'])) : null;
        if ($filterType !== null && $filterType !== '') {
            $articles = array_values(array_filter(
                $articles,
                static fn($a) => strtolower((string) ($a['type'] ?? '')) === $filterType,
            ));
        }

        $filterTag = isset($query['tag']) ? trim((string) $query['tag']) : null;
        if ($filterTag !== null && $filterTag !== '') {
            $articles = array_values(array_filter($articles, static function ($a) use ($filterTag) {
                $tags = array_map('strtolower', $a['tags'] ?? []);
                return in_array(strtolower($filterTag), $tags, true);
            }));
        }

        $sortBy = isset($query['sort']) ? strtolower(trim((string) $query['sort'])) : 'latest';
        if ($sortBy === 'popular') {
            usort($articles, static function ($a, $b) {
                $priorityDiff = ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
                if ($priorityDiff !== 0) {
                    return $priorityDiff;
                }
                return strtotime((string) ($b['date'] ?? '1970-01-01'))
                    <=> strtotime((string) ($a['date'] ?? '1970-01-01'));
            });
        }
        // 'latest' = repository default order.

        // Distinguish "unknown category" (404) from "category exists but
        // filter returned nothing" (empty listing).
        if (empty($this->articles->inCategory($category, 1))) {
            http_response_code(404);
            $this->theme->render('404', [
                'category' => $category,
                'categoriesList' => $this->categoriesList,
            ]);
            return;
        }

        $perPage = 12;
        $totalArticles = count($articles);
        $totalPages = (int) ceil($totalArticles / $perPage);
        $currentPage = max(1, min($totalPages, (int) ($query['page'] ?? 1)));
        $offset = ($currentPage - 1) * $perPage;
        $paginatedArticles = array_slice($articles, $offset, $perPage);

        $categoryIntro = $this->pages->find($category);

        $resolve = $this->templateResolver;
        $this->theme->render($resolve('category'), [
            'category' => $category,
            'articles' => $paginatedArticles,
            'latest' => $this->articles->latest(9),
            'categoriesList' => $this->categoriesList,
            'metrics' => $this->metrics,
            'intro' => $categoryIntro['html'] ?? null,
            'introPage' => $categoryIntro,
            'activeFilters' => [
                'type' => $filterType,
                'tag' => $filterTag,
                'sort' => $sortBy,
            ],
            'pagination' => [
                'current' => $currentPage,
                'total' => $totalPages,
                'perPage' => $perPage,
                'totalItems' => $totalArticles,
            ],
        ]);
    }
}
