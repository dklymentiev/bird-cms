<?php

declare(strict_types=1);

use App\Content\ArticleRepository;
use App\Http\ContentRouter;
use App\Support\Config;

$repository = new ArticleRepository(config('articles_dir'));
$siteUrl = rtrim(config('site_url'), '/');

switch (trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/')) {
    case 'sitemap.xml':
        header('Content-Type: application/xml; charset=utf-8');
        echo generateSitemap($repository, $siteUrl);
        break;
    case 'rss.xml':
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo generateRss($repository, $siteUrl);
        break;
    default:
        http_response_code(404);
        break;
}

function generateSitemap(ArticleRepository $repository, string $siteUrl): string
{
    $contentConfig = Config::load('content');
    $router = new ContentRouter($contentConfig);

    // Per-URL operator overrides from /admin/pages, persisted to
    // storage/url-meta.json. Used to drop URLs from the sitemap
    // (in_sitemap=false or noindex=true) and to override priority/changefreq.
    $metaPath = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__)) . '/storage/url-meta.json';
    $meta = is_file($metaPath) ? (json_decode((string) file_get_contents($metaPath), true) ?: []) : [];

    $urls = [];

    $urls[] = [
        'loc'        => $siteUrl . '/',
        'lastmod'    => date('Y-m-d'),
        'changefreq' => 'daily',
        'priority'   => '1.0',
    ];

    foreach ($router->allUrls($siteUrl) as $url) {
        $urls[] = $url;
    }

    if ($router->hasType('articles')) {
        $articlesDir = (defined('SITE_CONTENT_PATH') ? SITE_CONTENT_PATH : dirname(__DIR__) . '/content') . '/articles';
        if (is_dir($articlesDir)) {
            $articleRepo = new ArticleRepository($articlesDir);
            foreach ($articleRepo->categories() as $category) {
                if (!empty($articleRepo->inCategory($category, 1))) {
                    $urls[] = [
                        'loc'        => $siteUrl . '/' . $category,
                        'lastmod'    => date('Y-m-d'),
                        'changefreq' => 'daily',
                        'priority'   => '0.9',
                    ];
                }
            }
        }
    }

    $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
    $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $u) {
        $path     = (string) parse_url($u['loc'], PHP_URL_PATH) ?: '/';
        $override = $meta[$path] ?? [];

        // Operator opt-out wins over auto-discovery.
        if (isset($override['in_sitemap']) && $override['in_sitemap'] === false) continue;
        if (!empty($override['noindex'])) continue;

        $priority   = $override['priority']   ?? ($u['priority']   ?? '0.5');
        $changefreq = $override['changefreq'] ?? ($u['changefreq'] ?? 'weekly');

        $xml[] = '  <url>';
        $xml[] = '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
        if (!empty($u['lastmod'])) {
            $lm = strtotime($u['lastmod']) !== false ? date('Y-m-d', strtotime($u['lastmod'])) : $u['lastmod'];
            $xml[] = '    <lastmod>' . htmlspecialchars($lm, ENT_XML1) . '</lastmod>';
        }
        $xml[] = '    <changefreq>' . htmlspecialchars($changefreq, ENT_XML1) . '</changefreq>';
        $xml[] = '    <priority>'   . htmlspecialchars($priority, ENT_XML1)   . '</priority>';
        $xml[] = '  </url>';
    }
    $xml[] = '</urlset>';

    return implode("\n", $xml);
}

function generateRss(ArticleRepository $repository, string $siteUrl): string
{
    $articles = $repository->latest(20);

    $xml = [];
    $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml[] = '<rss version="2.0">';
    $xml[] = '  <channel>';
    $xml[] = '    <title>' . htmlspecialchars((string) config('site_name'), ENT_XML1) . '</title>';
    $xml[] = '    <link>' . htmlspecialchars($siteUrl . '/', ENT_XML1) . '</link>';
    $xml[] = '    <description>' . htmlspecialchars((string) config('site_name') . ' feed', ENT_XML1) . '</description>';

    foreach ($articles as $article) {
        $xml[] = '    <item>';
        $xml[] = '      <title>' . htmlspecialchars($article['title'], ENT_XML1) . '</title>';
        $xml[] = '      <link>' . htmlspecialchars($siteUrl . '/' . $article['category'] . '/' . $article['slug'], ENT_XML1) . '</link>';
        $xml[] = '      <guid>' . htmlspecialchars($siteUrl . '/' . $article['category'] . '/' . $article['slug'], ENT_XML1) . '</guid>';
        if (!empty($article['date'])) {
            $xml[] = '      <pubDate>' . date(DATE_RSS, strtotime($article['date'])) . '</pubDate>';
        }
        $xml[] = '      <description>' . htmlspecialchars($article['description'], ENT_XML1) . '</description>';
        $xml[] = '    </item>';
    }

    $xml[] = '  </channel>';
    $xml[] = '</rss>';

    return implode("\n", $xml);
}
