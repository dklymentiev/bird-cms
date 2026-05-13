#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Content\ArticleRepository;
use App\Content\PageRepository;

const EXIT_OK = 0;
const EXIT_BROKEN_LINKS = 1;

$appRoot = dirname(__DIR__);
$siteUrl = rtrim((string) config('site_url'), '/');
$articlesDir = (string) config('articles_dir');
$pagesDir = config('content_dir') . '/pages';

$articleRepository = new ArticleRepository($articlesDir);
$pageRepository = new PageRepository($pagesDir);

$articles = $articleRepository->all();

// Collect known routes
$knownPaths = collectKnownPaths($articles, $articleRepository->categories(), $pagesDir);

$contexts = [];
foreach ($articles as $article) {
    $contexts[] = [
        'type' => 'article',
        'id' => $article['category'] . '/' . $article['slug'],
        'path' => '/' . $article['category'] . '/' . $article['slug'],
        'html' => $article['html'] ?? '',
    ];
}

// Also validate static pages if they exist
foreach (glob($pagesDir . '/*.md') as $pageFile) {
    $slug = basename($pageFile, '.md');
    $page = $pageRepository->find($slug);
    if ($page !== null) {
        $contexts[] = [
            'type' => 'page',
            'id' => $slug,
            'path' => '/' . $slug,
            'html' => $page['html'] ?? '',
        ];
    }
}

$broken = [];
$checked = 0;
$skipped = 0;

foreach ($contexts as $context) {
    $links = extractLinks($context['html']);
    foreach ($links as $link) {
        $href = trim($link['href']);
        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript):#i', $href)) {
            $skipped++;
            continue;
        }

        $resolved = resolveInternalPath($href, $context['path'], $siteUrl);
        if ($resolved === null) {
            $skipped++;
            continue;
        }

        $checked++;
        if (!isKnownPath($resolved['path'], $knownPaths, $appRoot, $resolved['isAsset'])) {
            $broken[] = [
                'context' => $context,
                'href' => $href,
                'normalized' => $resolved['path'],
                'reason' => $resolved['isAsset']
                    ? 'Asset not found on disk'
                    : 'Unknown internal route',
            ];
        }
    }
}

$totalContexts = count($contexts);
$totalLinks = $checked + $skipped;

echo "🔍 Internal Link Audit\n";
echo "=======================\n";
echo "Contexts scanned : {$totalContexts}\n";
echo "Links discovered : {$totalLinks}\n";
echo "Links checked    : {$checked}\n";
echo "Links skipped    : {$skipped} (external or non-HTTP)\n\n";

if (!empty($broken)) {
    echo "❌ Broken internal links found (" . count($broken) . "):\n";
    foreach ($broken as $issue) {
        $contextId = $issue['context']['id'];
        $type = $issue['context']['type'];
        echo "  - [{$type}: {$contextId}] {$issue['href']} → {$issue['normalized']} ({$issue['reason']})\n";
    }
    exit(EXIT_BROKEN_LINKS);
}

echo "✅ All internal links resolved successfully.\n";
exit(EXIT_OK);

/**
 * @param array<int, array<string, mixed>> $articles
 * @param array<int, string> $categories
 * @param string $pagesDir
 * @return array<string, true>
 */
function collectKnownPaths(array $articles, array $categories, string $pagesDir): array
{
    $paths = [
        '/' => true,
        '/rss.xml' => true,
        '/sitemap.xml' => true,
    ];

    foreach ($categories as $category) {
        $path = normalizePath('/' . $category);
        $paths[$path] = true;
    }

    foreach ($articles as $article) {
        $category = $article['category'] ?? null;
        $slug = $article['slug'] ?? null;
        if ($category === null || $slug === null) {
            continue;
        }
        $path = normalizePath('/' . $category . '/' . $slug);
        $paths[$path] = true;
    }

    foreach (glob($pagesDir . '/*.md') as $pageFile) {
        $slug = basename($pageFile, '.md');
        $path = normalizePath('/' . $slug);
        $paths[$path] = true;
    }

    return $paths;
}

/**
 * @param string $html
 * @return array<int, array{href: string}>
 */
function extractLinks(string $html): array
{
    if ($html === '') {
        return [];
    }

    $dom = new DOMDocument();
    $htmlWrapped = '<!DOCTYPE html><html><body>' . $html . '</body></html>';

    libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML($htmlWrapped);
    libxml_clear_errors();

    if ($loaded === false) {
        return [];
    }

    $result = [];
    foreach ($dom->getElementsByTagName('a') as $anchor) {
        /** @var DOMElement $anchor */
        $result[] = ['href' => $anchor->getAttribute('href')];
    }

    return $result;
}

/**
 * @param string $href
 * @param string $currentPath
 * @param string $siteUrl
 * @return array{path: string, isAsset: bool}|null
 */
function resolveInternalPath(string $href, string $currentPath, string $siteUrl): ?array
{
    $href = trim($href);
    if ($href === '') {
        return null;
    }

    // Scheme-relative URLs
    if (str_starts_with($href, '//')) {
        $href = 'https:' . $href;
    }

    // Absolute URLs
    if (preg_match('#^https?://#i', $href) === 1) {
        $hostUrl = $siteUrl;
        if (stripos($href, $hostUrl) !== 0) {
            return null; // external
        }
        $path = parse_url($href, PHP_URL_PATH) ?? '/';
        $isAsset = str_starts_with($path, '/assets/') || str_starts_with($path, '/storage/');
        return [
            'path' => normalizePath($path),
            'isAsset' => $isAsset,
        ];
    }

    if ($href[0] === '/') {
        $path = parse_url($href, PHP_URL_PATH) ?? '/';
        $isAsset = str_starts_with($path, '/assets/') || str_starts_with($path, '/storage/');
        return [
            'path' => normalizePath($path),
            'isAsset' => $isAsset,
        ];
    }

    // Relative link
    $base = normalizePath($currentPath);
    $resolved = resolveRelativePath($base, $href);
    $isAsset = str_starts_with($resolved, '/assets/') || str_starts_with($resolved, '/storage/');

    return [
        'path' => $resolved,
        'isAsset' => $isAsset,
    ];
}

function normalizePath(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $path = preg_replace('#/+#', '/', $path);
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    return $path === '' ? '/' : $path;
}

function resolveRelativePath(string $basePath, string $relative): string
{
    $base = $basePath === '/' ? [] : explode('/', trim($basePath, '/'));
    if (!empty($base)) {
        array_pop($base); // remove current file segment
    }

    $parts = explode('/', $relative);
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($base);
            continue;
        }
        $base[] = $part;
    }

    return normalizePath('/' . implode('/', $base));
}

function isKnownPath(string $path, array $knownPaths, string $appRoot, bool $isAsset): bool
{
    if (isset($knownPaths[$path])) {
        return true;
    }

    if ($isAsset) {
        $candidate = $appRoot . '/public' . $path;
        return file_exists($candidate);
    }

    return false;
}
