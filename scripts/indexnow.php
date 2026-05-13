<?php
/**
 * IndexNow URL Submission Script
 *
 * Submits URLs to search engines (Bing, Yandex) for faster indexing.
 *
 * Usage:
 *   php indexnow.php                           # Submit all published articles
 *   php indexnow.php --url=/tools/slug         # Submit single URL
 *   php indexnow.php --category=tools          # Submit category
 *   php indexnow.php --recent=7                # Submit articles from last N days
 *   php indexnow.php --dry-run                 # Show what would be submitted
 */

declare(strict_types=1);

// Load bootstrap from current working directory (site) or fallback to engine
$siteBootstrap = getcwd() . '/bootstrap.php';
$engineBootstrap = __DIR__ . '/../bootstrap.php';
require_once file_exists($siteBootstrap) ? $siteBootstrap : $engineBootstrap;

// Configuration
$config = [
    'site_url' => config('site_url'),
    // No fallback: every install must register its own IndexNow key.
    // Generate one at https://www.bing.com/indexnow and set INDEXNOW_KEY in .env.
    'api_key' => config('indexnow_key'),
    'engines' => [
        'api.indexnow.org',  // Generic endpoint (routes to all search engines)
    ],
    'articles_dir' => config('articles_dir'),
];

// Parse CLI args
$args = getopt('', ['url:', 'category:', 'recent:', 'dry-run', 'help']);

if (isset($args['help'])) {
    echo <<<HELP
IndexNow URL Submission Script

Usage:
  php indexnow.php                    Submit all published articles
  php indexnow.php --url=/tools/slug  Submit single URL
  php indexnow.php --category=tools   Submit all articles in category
  php indexnow.php --recent=7         Submit articles from last N days
  php indexnow.php --dry-run          Show what would be submitted

HELP;
    exit(0);
}

$dryRun = isset($args['dry-run']);
$singleUrl = $args['url'] ?? null;
$categoryFilter = $args['category'] ?? null;
$recentDays = isset($args['recent']) ? (int)$args['recent'] : null;

if (empty($config['api_key'])) {
    fwrite(STDERR, "ERROR: INDEXNOW_KEY is not set. Generate a key at https://www.bing.com/indexnow\n");
    fwrite(STDERR, "       and add it to your site .env as INDEXNOW_KEY=...\n");
    exit(1);
}

// Colors
function color(string $text, string $color): string {
    $colors = [
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'red' => "\033[31m",
        'blue' => "\033[34m",
        'reset' => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

// Simple frontmatter parser
function parseFrontmatter(string $content): array {
    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
        return [];
    }

    $yaml = $matches[1];
    $meta = [];

    foreach (explode("\n", $yaml) as $line) {
        if (preg_match('/^(\w+):\s*(.*)$/', $line, $m)) {
            $value = trim($m[2], ' "\'');
            $meta[$m[1]] = $value;
        }
    }

    return $meta;
}

// Check if article is published
function isPublished(array $meta): bool {
    $status = strtolower($meta['status'] ?? 'published');
    if ($status === 'draft') {
        return false;
    }

    $publishAt = $meta['publish_at'] ?? null;
    if ($publishAt) {
        $publishTime = strtotime($publishAt);
        if ($publishTime && $publishTime > time()) {
            return false;
        }
    } elseif ($status === 'scheduled') {
        return false;
    }

    return true;
}

// Get all published articles
function getArticles(string $articlesDir, ?string $categoryFilter = null): array {
    $articles = [];
    $categories = glob($articlesDir . '/*', GLOB_ONLYDIR);

    foreach ($categories as $catDir) {
        $category = basename($catDir);

        if ($categoryFilter && $category !== $categoryFilter) {
            continue;
        }

        foreach (glob($catDir . '/*.md') as $file) {
            $content = file_get_contents($file);
            $meta = parseFrontmatter($content);

            if (!isPublished($meta)) {
                continue;
            }

            $articles[] = [
                'category' => $category,
                'slug' => $meta['slug'] ?? basename($file, '.md'),
                'date' => $meta['date'] ?? null,
            ];
        }
    }

    // Sort by date descending
    usort($articles, fn($a, $b) =>
        strtotime($b['date'] ?? '1970-01-01') <=> strtotime($a['date'] ?? '1970-01-01')
    );

    return $articles;
}

// Get categories
function getCategories(string $articlesDir): array {
    $categories = [];
    foreach (glob($articlesDir . '/*', GLOB_ONLYDIR) as $dir) {
        $categories[] = basename($dir);
    }
    return $categories;
}

// Collect URLs to submit
$urls = [];

if ($singleUrl) {
    // Single URL mode
    $urls[] = rtrim($config['site_url'], '/') . '/' . ltrim($singleUrl, '/');
} else {
    // Gather from articles
    $articles = getArticles($config['articles_dir'], $categoryFilter);
    $cutoffDate = $recentDays ? strtotime("-{$recentDays} days") : null;

    foreach ($articles as $article) {
        // Filter by recent days
        if ($cutoffDate) {
            $articleDate = strtotime($article['date'] ?? '1970-01-01');
            if ($articleDate < $cutoffDate) {
                continue;
            }
        }

        $urls[] = $config['site_url'] . '/' . $article['category'] . '/' . $article['slug'];
    }

    // Add homepage and category pages (only for full submission)
    if (!$categoryFilter && !$recentDays) {
        array_unshift($urls, $config['site_url'] . '/');
        foreach (getCategories($config['articles_dir']) as $cat) {
            $urls[] = $config['site_url'] . '/' . $cat . '/';
        }
    }
}

if (empty($urls)) {
    echo color("No URLs to submit.\n", 'yellow');
    exit(0);
}

echo "IndexNow Submission\n";
echo "==================\n";
echo "URLs to submit: " . count($urls) . "\n";
echo "Mode: " . ($dryRun ? color("DRY RUN", 'yellow') : color("LIVE", 'green')) . "\n\n";

if ($dryRun) {
    echo "URLs that would be submitted:\n";
    foreach ($urls as $url) {
        echo "  - {$url}\n";
    }
    exit(0);
}

// Submit to IndexNow
function submitToIndexNow(array $urls, array $config): array {
    $payload = [
        'host' => parse_url($config['site_url'], PHP_URL_HOST),
        'key' => $config['api_key'],
        'keyLocation' => $config['site_url'] . '/' . $config['api_key'] . '.txt',
        'urlList' => $urls,
    ];

    $results = [];

    foreach ($config['engines'] as $engine) {
        $endpoint = "https://{$engine}/indexnow";

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $results[$engine] = [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
        ];
    }

    return $results;
}

// Batch URLs (max 10000 per request, but we'll use 100 for safety)
$batchSize = 100;
$batches = array_chunk($urls, $batchSize);
$totalSuccess = 0;
$totalFailed = 0;

foreach ($batches as $i => $batch) {
    echo "Submitting batch " . ($i + 1) . "/" . count($batches) . " (" . count($batch) . " URLs)...\n";

    $results = submitToIndexNow($batch, $config);

    foreach ($results as $engine => $result) {
        if ($result['success']) {
            echo "  " . color("✓", 'green') . " {$engine}: HTTP {$result['http_code']}\n";
            $totalSuccess += count($batch);
        } else {
            echo "  " . color("✗", 'red') . " {$engine}: HTTP {$result['http_code']}";
            if ($result['error']) {
                echo " - {$result['error']}";
            }
            if ($result['response']) {
                echo " - " . substr($result['response'], 0, 100);
            }
            echo "\n";
            $totalFailed += count($batch);
        }
    }

    // Small delay between batches
    if ($i < count($batches) - 1) {
        usleep(500000); // 0.5 sec
    }
}

echo "\n";
echo "Done!\n";
echo "Submitted: " . color((string)$totalSuccess, 'green') . " URLs\n";
if ($totalFailed > 0) {
    echo "Failed: " . color((string)$totalFailed, 'red') . " URLs\n";
}
