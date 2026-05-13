<?php

declare(strict_types=1);

namespace App\Http\Api;

use App\Content\ArticleRepository;
use App\Http\ContentRouter;
use App\Support\Config;
use App\Support\UrlMeta;

/**
 * GET /api/v1/url-inventory -- read-only mirror of the admin URL
 * Inventory page (`/admin/pages`).
 *
 * Lists every URL the site exposes (homepage, every registered
 * content-type item, article category indexes) plus the per-URL
 * sitemap/noindex/template overrides from storage/url-meta.json.
 *
 * The shape mirrors what PagesController::index() builds for its
 * Alpine view so a mobile / third-party tool that wants to render
 * its own dashboard sees identical data without scraping admin HTML.
 *
 * Implementation duplicates a slice of PagesController::collectAllUrls
 * verbatim rather than calling that controller -- pulling in
 * App\Admin would drag IP-allowlist enforcement into a Bearer-auth
 * surface, which is exactly the boundary we don't want to cross.
 */
final class UrlInventoryController
{
    public function index(): void
    {
        $siteUrl = rtrim((string) \config('site_url'), '/');
        $urls    = $this->collect($siteUrl);
        $meta    = UrlMeta::all();

        foreach ($urls as &$u) {
            $key = $u['path'];
            $row = is_array($meta[$key] ?? null) ? $meta[$key] : [];
            $u['meta']       = $row;
            $u['in_sitemap'] = $row['in_sitemap'] ?? true;
            $u['noindex']    = $row['noindex']    ?? false;
            $u['priority']   = $row['priority']   ?? ($u['priority']   ?? '0.5');
            $u['changefreq'] = $row['changefreq'] ?? ($u['changefreq'] ?? 'weekly');
            $u['template']   = $row['template']   ?? null;
        }
        unset($u);

        usort($urls, static fn(array $a, array $b) =>
            ($a['source'] <=> $b['source']) ?: strcmp($a['path'], $b['path'])
        );

        Response::json([
            'site_url' => $siteUrl,
            'total'    => count($urls),
            'urls'     => $urls,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collect(string $siteUrl): array
    {
        $rows = [];

        $rows[] = [
            'path'       => '/',
            'loc'        => $siteUrl . '/',
            'source'     => 'static',
            'category'   => '',
            'lastmod'    => date('Y-m-d'),
            'priority'   => '1.0',
            'changefreq' => 'daily',
        ];

        try {
            $contentConfig = Config::load('content');
            $router = new ContentRouter($contentConfig);
            foreach ($router->allUrls($siteUrl) as $u) {
                $path = (string) parse_url($u['loc'], PHP_URL_PATH) ?: '/';
                $rows[] = [
                    'path'       => $path,
                    'loc'        => $u['loc'],
                    'source'     => $u['type'] ?? 'content',
                    'category'   => $this->categoryFromPath($path, $u['type'] ?? ''),
                    'lastmod'    => $u['lastmod']    ?? date('Y-m-d'),
                    'priority'   => $u['priority']   ?? '0.5',
                    'changefreq' => $u['changefreq'] ?? 'weekly',
                ];
            }
        } catch (\Throwable) {
            // content.php missing -- only the homepage will be returned;
            // that matches what the admin URL Inventory shows in the
            // same situation.
        }

        // Article category indexes (e.g. /blog, /tips). ContentRouter
        // doesn't emit these but they're real URLs the user navigates.
        $articlesDir = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 3)) . '/content/articles';
        if (is_dir($articlesDir)) {
            $repo = new ArticleRepository($articlesDir);
            foreach ($repo->categories() as $cat) {
                if (empty($repo->inCategory($cat, 1))) continue;
                $rows[] = [
                    'path'       => '/' . $cat,
                    'loc'        => $siteUrl . '/' . $cat,
                    'source'     => 'category-index',
                    'category'   => $cat,
                    'lastmod'    => date('Y-m-d'),
                    'priority'   => '0.9',
                    'changefreq' => 'daily',
                ];
            }
        }

        return $rows;
    }

    private function categoryFromPath(string $path, string $source): string
    {
        $segments = array_values(array_filter(explode('/', $path)));
        if (count($segments) >= 2 && ($source === 'articles' || $source === 'services')) {
            return $segments[0];
        }
        return '';
    }
}
