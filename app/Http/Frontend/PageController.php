<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;
use App\Content\MetricsRepository;
use App\Content\PageRepository;
use App\Theme\ThemeManager;

/**
 * `/<slug>` — static pages (about, contact, blog, etc.).
 *
 * Two resolution strategies, in order:
 *
 *   1. Theme view: if `themes/<active>/views/<slug>.php` exists, render
 *      with the standard shared view data. `/blog` gets an extra
 *      pagination payload (10 per page) so the index view can paginate
 *      without authoring a separate route. The `?page=N` query is clamped
 *      to [1, totalPages] so out-of-range numbers fall back to the last
 *      page rather than 404 — matches the pre-refactor behavior of
 *      `min(totalPages, $_GET['page'])`.
 *
 *   2. PageRepository fall-through: render `content/pages/<slug>.md` via
 *      the theme's `page` view.
 *
 * Returns true if a page was rendered, false to let the next handler
 * (CategoryController) try. Invalid slugs (non-`[a-z0-9-]+`) short-circuit
 * to 404 via the theme — same as the pre-refactor behavior, because the
 * old code returned 404 there rather than yielding.
 */
final class PageController
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
     * @param array<string, mixed>  $query  $_GET-shaped
     */
    public function handle(array $segments, array $query): bool
    {
        if (count($segments) !== 1 || $segments[0] === '') {
            return false;
        }

        $pageSlug = $segments[0];
        if (preg_match('/^[a-z0-9-]+$/', $pageSlug) !== 1) {
            http_response_code(404);
            $this->theme->render('404', [
                'slug' => $pageSlug,
                'categoriesList' => $this->categoriesList,
            ]);
            return true;
        }

        $themeViewPath = $this->theme->path('views/' . $pageSlug . '.php');
        if (file_exists($themeViewPath)) {
            $viewData = [
                'config' => config(),
                'recentPosts' => $this->articles->latest(10),
                'latest' => $this->articles->latest(6),
                'categoriesList' => $this->categoriesList,
            ];

            // Blog index pagination — same `?page=N` handling as before,
            // clamped to the available range rather than 404ing on
            // out-of-bounds.
            if ($pageSlug === 'blog') {
                $perPage = 10;
                $allPosts = $this->articles->all();
                $totalPosts = count($allPosts);
                $totalPages = max(1, (int) ceil($totalPosts / $perPage));
                $currentPage = max(1, min($totalPages, (int) ($query['page'] ?? 1)));
                $offset = ($currentPage - 1) * $perPage;

                $viewData['posts'] = array_slice($allPosts, $offset, $perPage);
                $viewData['pagination'] = [
                    'current' => $currentPage,
                    'total' => $totalPages,
                    'perPage' => $perPage,
                    'totalItems' => $totalPosts,
                ];
            }

            $resolve = $this->templateResolver;
            $this->theme->render($resolve($pageSlug), $viewData);
            return true;
        }

        $page = $this->pages->find($pageSlug);
        if ($page !== null) {
            $resolve = $this->templateResolver;
            $this->theme->render($resolve('page'), [
                'page' => $page,
                'latest' => $this->articles->latest(6),
                'categoriesList' => $this->categoriesList,
                'metrics' => $this->metrics,
            ]);
            return true;
        }

        return false;
    }
}
