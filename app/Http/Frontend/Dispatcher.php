<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;
use App\Content\MetricsRepository;
use App\Content\PageRepository;
use App\Support\HtmlCache;
use App\Support\UrlMeta;
use App\Theme\ThemeManager;

/**
 * Frontend request dispatcher.
 *
 * Owns the route table. Each route is a closure that decides whether it
 * claims the request (by inspecting the parsed URI / segments / query) and,
 * if so, runs the matching controller. Routes are tried top-to-bottom in
 * the same order the pre-refactor procedural index.php had them, which
 * matters: more specific routes (e.g. `/preview/<slug>`) come before
 * catch-alls (`/<category>/<slug>`), and the asset-passthrough check is
 * first because nginx-style routing for static files lives in PHP on the
 * current deployment shape.
 *
 * The dispatcher is intentionally small. It does NOT decide:
 *   - install-guard logic (lives at the top of public/index.php; runs
 *     before bootstrap.php)
 *   - security headers (set inline at the top of public/index.php)
 *   - sitemap.xml / rss.xml routing (handled by router-static.php
 *     require, which is more of a sub-front-controller than a route)
 *
 * Everything else dispatches through `handle()`.
 */
final class Dispatcher
{
    private ArticleRepository $articles;
    private PageRepository $pages;
    private MetricsRepository $metrics;
    private ThemeManager $theme;
    /** @var array<int|string, string> */
    private array $categoriesList;
    private string $siteRoot;
    private string $publicRoot;
    private string $articleUrlPrefix;
    private string $articlesPrefix;
    /** @var callable(string): string */
    private $resolveTemplate;

    /**
     * Build a dispatcher from the engine's standard repositories and the
     * current site's config(). Keeps public/index.php's bootstrap section
     * to a single call without hiding the fact that the dispatcher itself
     * still takes explicit dependencies (so tests construct it directly).
     */
    public static function fromSiteConfig(string $publicRoot): self
    {
        $articles = new ArticleRepository((string) config('articles_dir'));
        $pagesDir = defined('SITE_CONTENT_PATH')
            ? SITE_CONTENT_PATH . '/pages'
            : $publicRoot . '/../content/pages';
        $pages = new PageRepository($pagesDir);
        $metricsDb = defined('SITE_STORAGE_PATH')
            ? SITE_STORAGE_PATH . '/data/views.sqlite'
            : $publicRoot . '/../storage/data/views.sqlite';
        $metrics = new MetricsRepository($metricsDb);

        // Filter out empty categories so the chrome doesn't link to
        // index pages with zero articles.
        $allCategories = array_filter(
            $articles->categories(),
            static fn($category) => count($articles->inCategory($category, 1)) > 0,
        );

        return new self(
            $articles,
            $pages,
            $metrics,
            theme_manager(),
            $allCategories,
            SITE_ROOT,
            $publicRoot,
            (string) config('articles_prefix', ''),
        );
    }

    /**
     * @param array<int|string, string> $categoriesList
     */
    public function __construct(
        ArticleRepository $articles,
        PageRepository $pages,
        MetricsRepository $metrics,
        ThemeManager $theme,
        array $categoriesList,
        string $siteRoot,
        string $publicRoot,
        string $articlesPrefix,
    ) {
        $this->articles = $articles;
        $this->pages = $pages;
        $this->metrics = $metrics;
        $this->theme = $theme;
        $this->categoriesList = $categoriesList;
        $this->siteRoot = $siteRoot;
        $this->publicRoot = $publicRoot;
        $this->articlesPrefix = $articlesPrefix;
        $this->articleUrlPrefix = $articlesPrefix !== '' ? '/' . $articlesPrefix : '';

        // Per-URL view template override. UrlMeta resolves the template
        // name for the current request path; if the named template doesn't
        // exist in the active theme we fall back to the controller's
        // default. Captured as a closure so each controller can resolve at
        // render time (after $theme is fully set up).
        $themeRef = $this->theme;
        $this->resolveTemplate = static function (string $defaultView) use ($themeRef): string {
            $path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
            if ($path === '/') {
                $path = '/';
            }
            $override = UrlMeta::templateFor($path);
            if ($override === null) {
                return $defaultView;
            }
            if (!file_exists($themeRef->path('views/' . $override . '.php'))) {
                return $defaultView;
            }
            return $override;
        };
    }

    /**
     * Parse and dispatch the current request.
     *
     * @param array<string, mixed> $query  $_GET-shaped
     */
    public function dispatch(string $requestUri, array $query): void
    {
        $uri = trim((string) parse_url($requestUri, PHP_URL_PATH), '/');
        $segments = $uri === ''
            ? []
            : array_values(array_filter(
                explode('/', $uri),
                static fn($part) => $part !== '',
            ));

        // 1. Asset passthrough (uploads/, content/ images). Calls exit
        //    internally on hit — the early position matters because nginx
        //    on the current deployment can't reach these paths.
        if (AssetController::matches($uri)) {
            (new AssetController($this->siteRoot))->handle($uri);
        }

        // Compute HtmlCache key + eligibility once for the rest of the
        // dispatch. The cache wraps stable-URL controllers below; the gate
        // here keeps everything in one place rather than scattered across
        // five `if (HtmlCache::shouldServe(...))` blocks in each route.
        $cacheKey = HtmlCache::keyForPath($requestUri);
        $cacheEligible = HtmlCache::shouldServe($_SERVER, $_COOKIE);

        // 2. Homepage.
        if ($uri === '') {
            $this->withCache('home', $cacheEligible, function () {
                (new HomeController(
                    $this->articles,
                    $this->pages,
                    $this->metrics,
                    $this->theme,
                    $this->categoriesList,
                    $this->resolveTemplate,
                ))->handle();
            });
            return;
        }

        // 3. sitemap.xml / rss.xml are handled by a sub-front-controller
        //    that owns its own response. Kept as a require in
        //    public/index.php (not threaded through the dispatcher) to
        //    avoid swallowing its `return` semantics.
        if ($uri === 'sitemap.xml' || $uri === 'rss.xml') {
            require $this->publicRoot . '/router-static.php';
            return;
        }

        // 4. robots.txt — static-file passthrough.
        if ($uri === 'robots.txt') {
            header('Content-Type: text/plain; charset=utf-8');
            readfile($this->publicRoot . '/robots.txt');
            return;
        }

        // 5. llms.txt — generated text/plain feed for AI search engines.
        if ($uri === 'llms.txt') {
            $this->withCache('llms.txt', $cacheEligible, function () {
                (new LlmsTxtController($this->articles, $this->articleUrlPrefix))->handle();
            });
            return;
        }

        // 6. /search — in-memory query against published articles.
        if ($uri === 'search') {
            (new SearchController($this->articles, $this->theme, $this->categoriesList))->handle($query);
            return;
        }

        // 7. IndexNow key verification file. We don't have a controller
        //    for this — it's a single readfile, kept inline because it
        //    isn't worth a class.
        if (preg_match('/^[a-f0-9]{32}\.txt$/', $uri) && file_exists($this->publicRoot . '/' . $uri)) {
            header('Content-Type: text/plain; charset=utf-8');
            readfile($this->publicRoot . '/' . $uri);
            return;
        }

        // 8. /preview/<slug> — token-validated draft preview.
        if (count($segments) === 2 && $segments[0] === 'preview') {
            (new PreviewController(
                $this->articles,
                $this->metrics,
                $this->theme,
                $this->categoriesList,
                $this->siteRoot,
            ))->handle($segments, $query);
            return;
        }

        // 9. /blog/page/<n> — explicit pagination URL.
        if (count($segments) === 3
            && $segments[0] === 'blog'
            && $segments[1] === 'page'
            && ctype_digit($segments[2])) {
            (new BlogPaginationController($this->articles, $this->theme, $this->categoriesList))
                ->handle($segments);
            return;
        }

        // 10. Custom content types (projects/services/areas/...). Yields
        //     to the static-page + category + article handlers for
        //     articles/pages, which need their own enrichment passes.
        //     Cached under the URL-derived key when the route claims the
        //     request; on yield, the captured output is discarded by
        //     withCache() so the page/category/article handlers still see
        //     a clean output buffer.
        $contentType = new ContentTypeController(
            $this->articles,
            $this->metrics,
            $this->theme,
            $this->categoriesList,
            $this->siteRoot,
            $this->resolveTemplate,
        );
        $contentHandled = false;
        $this->withCache($cacheKey, $cacheEligible, function () use ($contentType, $uri, &$contentHandled) {
            $contentHandled = $contentType->handle($uri);
            return $contentHandled;
        });
        if ($contentHandled) {
            return;
        }

        // 11. Static pages (theme view + content/pages/<slug>.md). Falls
        //     through if neither resolves so /<segment> can still be tried
        //     as a category. The cache wrap captures output regardless of
        //     the page-vs-category branch; commit is gated on handle()
        //     returning true so a fall-through doesn't persist a partial
        //     response.
        $pageController = new PageController(
            $this->articles,
            $this->pages,
            $this->metrics,
            $this->theme,
            $this->categoriesList,
            $this->resolveTemplate,
        );
        $pageHandled = false;
        $this->withCache($cacheKey, $cacheEligible, function () use ($pageController, $segments, $query, &$pageHandled) {
            $pageHandled = $pageController->handle($segments, $query);
            // Returning false here tells withCache() not to persist the
            // captured output: the dispatcher will try the next route.
            return $pageHandled;
        });
        if ($pageHandled) {
            return;
        }

        // 12. /<category> — article category index. Cache key follows the
        //     URL (e.g. /blog -> "blog") so the get/put path matches the
        //     fall-through page-controller miss above without rewriting the
        //     key when the route changes hands.
        if (count($segments) === 1) {
            $this->withCache($cacheKey, $cacheEligible, function () use ($segments, $query) {
                (new CategoryController(
                    $this->articles,
                    $this->pages,
                    $this->metrics,
                    $this->theme,
                    $this->categoriesList,
                    $this->resolveTemplate,
                ))->handle($segments, $query);
            });
            return;
        }

        // 13. /<category>/<slug> (or /<prefix>/<category>/<slug>) — article
        //     detail. Cache key follows the URL verbatim so the article
        //     repository's invalidation hook (which knows the URL it just
        //     wrote) can call HtmlCache::forget() with the same key.
        $articleSegments = ArticleController::resolveSegments($segments, $this->articlesPrefix);
        if ($articleSegments !== null && (count($articleSegments) === 2 || count($articleSegments) === 3)) {
            $this->withCache($cacheKey, $cacheEligible, function () use ($articleSegments, $query) {
                (new ArticleController(
                    $this->articles,
                    $this->metrics,
                    $this->theme,
                    $this->categoriesList,
                    $this->resolveTemplate,
                ))->handle($articleSegments, $query);
            });
            return;
        }

        // 14. Fall-through 404. Distinct from controller-level 404s
        //     because callers here didn't claim the URL at all.
        http_response_code(404);
        $this->theme->render('404', [
            'slug' => $uri,
            'categoriesList' => $this->categoriesList,
        ]);
    }

    /**
     * HtmlCache wrapper for a single dispatched route.
     *
     * Flow:
     *   1. If $eligible is false, run $render() inline -- no cache touch.
     *   2. Otherwise, attempt HtmlCache::get(). On a hit, echo and return.
     *   3. On a miss, capture $render()'s output via ob_start(), echo it,
     *      and (when the status code is 200 and $render didn't return false)
     *      persist it for the next request.
     *
     * The optional `false` return from $render is the "don't cache this
     * outcome" escape hatch the PageController fall-through needs: the
     * page controller may decide a slug isn't its problem and yield to
     * the next route, in which case the captured bytes mustn't be
     * persisted as the canonical render of that URL.
     */
    private function withCache(string $cacheKey, bool $eligible, callable $render): void
    {
        if (!$eligible) {
            $render();
            return;
        }

        $cached = HtmlCache::get($cacheKey);
        if ($cached !== null) {
            echo $cached;
            return;
        }

        ob_start();
        $renderResult = null;
        try {
            $renderResult = $render();
            $captured = (string) ob_get_clean();
        } catch (\Throwable $e) {
            // Drop the partial output silently and let the error bubble.
            // We don't want a half-rendered page persisted as the cache
            // copy of this URL.
            ob_end_clean();
            throw $e;
        }

        echo $captured;

        // Don't persist failures: 4xx/5xx renders shouldn't be served as
        // "the page" for the next 5 minutes, and an explicit `false`
        // return signals the route didn't claim the URL.
        if ($renderResult === false) {
            return;
        }
        $status = http_response_code();
        if (is_int($status) && $status >= 400) {
            return;
        }

        HtmlCache::put($cacheKey, $captured);
    }
}
