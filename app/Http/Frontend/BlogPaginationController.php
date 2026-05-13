<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;
use App\Theme\ThemeManager;

/**
 * `/blog/page/<n>` — pagination for the global article feed.
 *
 * Mirror of the procedural block in public/index.php (lines ~190-225 of
 * the pre-refactor version). The canonical first page is `/blog`, so a
 * request for `/blog/page/1` 301-redirects to `/blog` rather than
 * rendering a duplicate URL — same behavior as before.
 *
 * Out-of-range page numbers return 404 (theme 404 view). 10 posts per
 * page, matching the existing config.
 */
final class BlogPaginationController
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ThemeManager $theme,
        /** @var list<string> */
        private readonly array $categoriesList,
    ) {
    }

    /**
     * @param list<string> $segments Already validated as ['blog', 'page', '<digits>']
     */
    public function handle(array $segments): void
    {
        $pageNum = (int) $segments[2];
        $allPosts = $this->articles->all();
        $totalPosts = count($allPosts);
        $totalPages = max(1, (int) ceil($totalPosts / self::PER_PAGE));

        if ($pageNum < 1 || $pageNum > $totalPages) {
            http_response_code(404);
            $this->theme->render('404', ['categoriesList' => $this->categoriesList]);
            return;
        }

        if ($pageNum === 1) {
            header('Location: /blog', true, 301);
            return;
        }

        $offset = ($pageNum - 1) * self::PER_PAGE;
        $this->theme->render('blog', [
            'config' => config(),
            'recentPosts' => $this->articles->latest(10),
            'latest' => $this->articles->latest(6),
            'categoriesList' => $this->categoriesList,
            'posts' => array_slice($allPosts, $offset, self::PER_PAGE),
            'pagination' => [
                'current' => $pageNum,
                'total' => $totalPages,
                'perPage' => self::PER_PAGE,
                'totalItems' => $totalPosts,
            ],
        ]);
    }
}
