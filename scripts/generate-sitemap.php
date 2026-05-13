#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Sitemap Generator v2
 *
 * Uses ContentRouter as single source of truth for URL generation.
 *
 * Usage: php scripts/generate-sitemap-v2.php [--output=path] [--quiet]
 */

require __DIR__ . '/../bootstrap.php';

use App\Http\ContentRouter;
use App\Content\ArticleRepository;

$options = getopt('', ['output::', 'quiet']);

$outputPath = $options['output'] ?? getcwd() . '/public/sitemap.xml';
$quiet = isset($options['quiet']);

$siteUrl = rtrim(config('site_url'), '/');

$contentConfig = \App\Support\Config::load('content');
$router = new ContentRouter($contentConfig);

$urls = [];
$counts = [];

// Add homepage
$urls[] = [
    'loc' => $siteUrl . '/',
    'lastmod' => date('Y-m-d'),
    'changefreq' => 'daily',
    'priority' => '1.0',
];
$counts['homepage'] = 1;

// Get all URLs from ContentRouter
$contentUrls = $router->allUrls($siteUrl);

foreach ($contentUrls as $url) {
    $urls[] = $url;
}

// Count by type (extract from URL patterns)
foreach ($router->getTypes() as $typeName => $config) {
    $counts[$typeName] = 0;
}

// Each URL emitted by ContentRouter carries the originating type name —
// no URL-prefix heuristics required.
foreach ($contentUrls as $url) {
    $typeName = $url['type'] ?? 'unknown';
    $counts[$typeName] = ($counts[$typeName] ?? 0) + 1;
}

// Add category index pages for articles (special case - not a content type but derived)
if ($router->hasType('articles')) {
    $articlesConfig = $router->getType('articles');
    $articlesDir = (defined('SITE_CONTENT_PATH') ? SITE_CONTENT_PATH : getcwd() . '/content') . '/articles';

    if (is_dir($articlesDir)) {
        $repository = new ArticleRepository($articlesDir);
        $categories = $repository->categories();

        foreach ($categories as $category) {
            // Only add if category has articles
            $articles = $repository->inCategory($category, 1);
            if (!empty($articles)) {
                $urls[] = [
                    'loc' => $siteUrl . '/' . $category,
                    'lastmod' => date('Y-m-d'),
                    'changefreq' => 'daily',
                    'priority' => '0.9',
                ];
                $counts['categories'] = ($counts['categories'] ?? 0) + 1;
            }
        }
    }
}

// Generate XML
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $url) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
    if (!empty($url['lastmod'])) {
        $lastmod = $url['lastmod'];
        if (strtotime($lastmod) !== false) {
            $lastmod = date('Y-m-d', strtotime($lastmod));
        }
        $xml .= "    <lastmod>" . htmlspecialchars($lastmod, ENT_XML1) . "</lastmod>\n";
    }
    $xml .= "    <changefreq>" . htmlspecialchars($url['changefreq'] ?? 'weekly', ENT_XML1) . "</changefreq>\n";
    $xml .= "    <priority>" . htmlspecialchars($url['priority'] ?? '0.5', ENT_XML1) . "</priority>\n";
    $xml .= "  </url>\n";
}

$xml .= "</urlset>\n";

// Save to file
file_put_contents($outputPath, $xml);

if (!$quiet) {
    echo "[OK] Sitemap generated: {$outputPath}\n";
    echo "Total URLs: " . count($urls) . "\n";

    foreach ($counts as $type => $count) {
        if ($count > 0) {
            echo "   - " . ucfirst($type) . ": {$count}\n";
        }
    }
}

exit(0);
