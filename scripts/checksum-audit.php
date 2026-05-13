<?php
/**
 * Page Checksum Audit
 *
 * Creates snapshots of all site pages and compares with previous snapshots.
 * Used for post-update testing and site audits.
 *
 * Usage:
 *   php checksum-audit.php [options]
 *
 * Options:
 *   --base-url=URL      Base URL to scan (default: from .env SITE_URL)
 *   --json              Output as JSON
 *   --no-compare        Skip comparison with previous snapshot
 *   --skip-images       Skip image accessibility checking
 *   --check-external    Also check images on external domains (off by default)
 *   --help              Show this help
 *
 * Exit codes:
 *   0 = OK (no broken pages)
 *   1 = Some pages changed (warning)
 *   2 = Critical errors (500s, broken redirects, broken images)
 */

declare(strict_types=1);

// Find site root
$siteRoot = null;
$searchPaths = [
    dirname(__DIR__, 3),  // When in /versions/X.X.X/scripts/ -> site root
    dirname(__DIR__, 2),  // When in /engine/scripts/ -> site root
    dirname(__DIR__),     // Fallback
];

foreach ($searchPaths as $path) {
    if (file_exists($path . '/.env')) {
        $siteRoot = $path;
        break;
    }
}

if (!$siteRoot) {
    $siteRoot = dirname(__DIR__);
}

define('SITE_ROOT', $siteRoot);

// Load .env
$envFile = SITE_ROOT . '/.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, '"\'');
    }
}

// Parse arguments
$args = getopt('', ['base-url:', 'json', 'no-compare', 'skip-images', 'check-external', 'tag:', 'help']);

if (isset($args['help'])) {
    echo file_get_contents(__FILE__);
    exit(0);
}

$baseUrl = $args['base-url'] ?? $env['SITE_URL'] ?? null;
$jsonOutput = isset($args['json']);
$noCompare = isset($args['no-compare']);
$skipImages = isset($args['skip-images']);
$checkExternal = isset($args['check-external']);
$snapshotTag = $args['tag'] ?? null;

if (!$baseUrl) {
    fwrite(STDERR, "Error: No base URL. Use --base-url=URL or set SITE_URL in .env\n");
    exit(1);
}

$baseUrl = rtrim($baseUrl, '/');

// Paths
$storageDir = SITE_ROOT . '/storage/checksums';
$redirectsFile = SITE_ROOT . '/storage/redirects.json';
$versionFile = SITE_ROOT . '/engine/VERSION';

// Ensure storage directory exists
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// Get current version
$version = 'unknown';
if (file_exists($versionFile)) {
    $version = trim(file_get_contents($versionFile));
}

// ============================================================================
// Collect URLs to test
// ============================================================================

$urls = [];
$redirects = [];

// 1. From sitemap.xml (force fresh generation)
$sitemapUrl = $baseUrl . '/sitemap.xml?force=1';
// SSL context for self-signed certificates
$sslContext = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);
$sitemapContent = @file_get_contents($sitemapUrl, false, $sslContext);
if ($sitemapContent) {
    preg_match_all('/<loc>([^<]+)<\/loc>/', $sitemapContent, $matches);
    foreach ($matches[1] as $url) {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $urls[$path] = $url;
    }
}

// 2. From redirects.json
if (file_exists($redirectsFile)) {
    $redirectData = json_decode(file_get_contents($redirectsFile), true);
    if (isset($redirectData['redirects'])) {
        foreach ($redirectData['redirects'] as $r) {
            if (!empty($r['from']) && !empty($r['to']) && ($r['active'] ?? true)) {
                $redirects[$r['from']] = $r['to'];
            }
        }
    }
}

// Always add homepage
if (!isset($urls['/'])) {
    $urls['/'] = $baseUrl . '/';
}

// ============================================================================
// Fetch pages and calculate checksums
// ============================================================================

function fetchPage(string $url, bool $followRedirects = true): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $followRedirects,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BirdCMS-Audit/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['status' => 0, 'error' => $error, 'hash' => null, 'body' => ''];
    }

    $body = substr($response, $headerSize);

    // Extract main content for hashing (ignore dynamic parts)
    $content = $body;
    // Remove CSRF tokens from forms
    $content = preg_replace('/name="csrf[^"]*"\s+value="[^"]*"/', '', $content);
    // Remove CSRF tokens from meta tags
    $content = preg_replace('/<meta\s+name="csrf-token"\s+content="[^"]*">/', '', $content);
    // Remove timestamps (ISO format)
    $content = preg_replace('/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', '', $content);
    // Remove nonces
    $content = preg_replace('/nonce="[^"]*"/', '', $content);

    $hash = md5($content);

    // Detect PHP errors in response body. Match only PHP's `display_errors`
    // output format (`<b>Fatal error</b>:`), not arbitrary text containing
    // "Warning:" or "Fatal:" — articles routinely include those words in
    // body copy, and a plain-text regex causes false positives that
    // auto-rollback a perfectly fine release.
    $errors = [];   // critical: Fatal / Parse — will fail the audit (exit 2)
    $warnings = []; // soft: Warning / Notice / Deprecated — informational
    if (preg_match_all('#<b>(Fatal error|Parse error)</b>:\s*.{0,200}#i', $body, $m)) {
        $errors = array_slice(array_unique(array_map('strip_tags', $m[0])), 0, 3);
    }
    if (preg_match_all('#<b>(Warning|Notice|Deprecated)</b>:\s*.{0,200}#i', $body, $m)) {
        $warnings = array_slice(array_unique(array_map('strip_tags', $m[0])), 0, 3);
    }

    return [
        'status' => $httpCode,
        'hash' => $hash,
        'size' => strlen($body),
        'final_url' => $finalUrl !== $url ? $finalUrl : null,
        'errors' => $errors ?: null,
        'warnings' => $warnings ?: null,
        'body' => $body,
    ];
}

function fetchRedirect(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BirdCMS-Audit/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'location' => $redirectUrl ?: null,
    ];
}

/**
 * Normalize URL (make relative URLs absolute)
 */
function normalizeUrl(string $url, string $baseUrl): string {
    $url = trim($url);

    // Already absolute
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return $url;
    }

    // Protocol-relative
    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }

    // Absolute path
    if (str_starts_with($url, '/')) {
        return $baseUrl . $url;
    }

    // Relative path
    return $baseUrl . '/' . $url;
}

/**
 * Extract image URLs from HTML
 */
function extractImages(string $html, string $baseUrl): array {
    $images = [];

    // Match <img src="...">
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
        foreach ($matches[1] as $src) {
            $images[] = normalizeUrl($src, $baseUrl);
        }
    }

    // Match srcset
    if (preg_match_all('/srcset=["\']([^"\']+)["\']/', $html, $matches)) {
        foreach ($matches[1] as $srcset) {
            $parts = preg_split('/,\s*/', $srcset);
            foreach ($parts as $part) {
                $urlPart = preg_split('/\s+/', trim($part))[0];
                if ($urlPart) {
                    $images[] = normalizeUrl($urlPart, $baseUrl);
                }
            }
        }
    }

    // Match background-image: url(...)
    if (preg_match_all('/background-image:\s*url\(["\']?([^"\')\s]+)["\']?\)/', $html, $matches)) {
        foreach ($matches[1] as $url) {
            $images[] = normalizeUrl($url, $baseUrl);
        }
    }

    // Filter out data URIs and duplicates
    $images = array_filter($images, fn($url) => !str_starts_with($url, 'data:'));
    $images = array_unique($images);

    return array_values($images);
}

/**
 * Check if image URL is accessible via HEAD request
 */
function checkImageUrl(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BirdCMS-Audit/1.0)',
        CURLOPT_HTTPHEADER => [
            'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
        ],
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'ok' => $httpCode >= 200 && $httpCode < 400,
        'code' => $httpCode,
        'error' => $error ?: null,
    ];
}

// Progress output
$total = count($urls) + count($redirects);
$current = 0;
$startTime = microtime(true);

if (!$jsonOutput) {
    echo "Bird CMS Checksum Audit\n";
    echo "========================\n";
    echo "Base URL: {$baseUrl}\n";
    echo "Version: {$version}\n";
    echo "Pages: " . count($urls) . "\n";
    echo "Redirects: " . count($redirects) . "\n";
    echo "\n";
}

$results = [
    'timestamp' => date('c'),
    'version' => $version,
    'base_url' => $baseUrl,
    'pages' => [],
    'redirects' => [],
    'summary' => [
        'total_pages' => count($urls),
        'total_redirects' => count($redirects),
        'ok' => 0,
        'changed' => 0,
        'broken' => 0,
        'php_errors' => 0,    // Fatal / Parse — fails the audit
        'php_warnings' => 0,  // Warning / Notice / Deprecated — informational
        'redirect_ok' => 0,
        'redirect_broken' => 0,
    ],
];

// Image tracking
$imagePages = []; // url => [page1, page2, ...]
$baseHost = parse_url($baseUrl, PHP_URL_HOST);

// Fetch pages
foreach ($urls as $path => $url) {
    $current++;
    if (!$jsonOutput) {
        echo "\r[{$current}/{$total}] Fetching {$path}...                    ";
    }

    $result = fetchPage($url);

    // Extract images from body before storing
    if (!$skipImages && !empty($result['body']) && $result['status'] >= 200 && $result['status'] < 400) {
        $pageImages = extractImages($result['body'], $baseUrl);
        foreach ($pageImages as $imgUrl) {
            // Skip external domains unless --check-external
            if (!$checkExternal) {
                $imgHost = parse_url($imgUrl, PHP_URL_HOST);
                if ($imgHost && $imgHost !== $baseHost) {
                    continue;
                }
            }
            if (!isset($imagePages[$imgUrl])) {
                $imagePages[$imgUrl] = [];
            }
            $imagePages[$imgUrl][] = $path;
        }
    }

    // Strip body before storing in results
    unset($result['body']);
    $results['pages'][$path] = $result;

    if ($result['status'] >= 200 && $result['status'] < 400) {
        $results['summary']['ok']++;
    } else {
        $results['summary']['broken']++;
    }
    if (!empty($result['errors'])) {
        $results['summary']['php_errors']++;
    }
    if (!empty($result['warnings'])) {
        $results['summary']['php_warnings']++;
    }
}

// Fetch redirects
foreach ($redirects as $from => $to) {
    $current++;
    if (!$jsonOutput) {
        echo "\r[{$current}/{$total}] Checking redirect {$from}...                    ";
    }

    $url = $baseUrl . $from;
    $result = fetchRedirect($url);

    // Normalize expected target
    $expectedTarget = $to;
    if (strpos($to, 'http') !== 0) {
        $expectedTarget = $baseUrl . $to;
    }

    // Check if redirect is correct
    $isCorrect = false;
    $targetStatus = null;
    $targetHash = null;
    if ($result['status'] === 301 || $result['status'] === 302) {
        $actualTarget = $result['location'];
        // Normalize for comparison
        $actualPath = parse_url($actualTarget, PHP_URL_PATH);
        $expectedPath = parse_url($expectedTarget, PHP_URL_PATH);
        $isCorrect = ($actualPath === $expectedPath);

        // Follow redirect and hash the destination page
        $targetUrl = $actualTarget;
        if ($targetUrl && strpos($targetUrl, 'http') !== 0) {
            $targetUrl = $baseUrl . $targetUrl;
        }
        if ($targetUrl) {
            $targetResult = fetchPage($targetUrl);
            $targetStatus = $targetResult['status'];
            $targetHash = $targetResult['hash'];
        }
    }

    $results['redirects'][$from] = [
        'status' => $result['status'],
        'expected' => $to,
        'actual' => $result['location'],
        'correct' => $isCorrect,
        'target_status' => $targetStatus,
        'target_hash' => $targetHash,
    ];

    if ($isCorrect) {
        $results['summary']['redirect_ok']++;
    } else {
        $results['summary']['redirect_broken']++;
    }
}

if (!$jsonOutput) {
    echo "\r" . str_repeat(' ', 60) . "\r";
}

// ============================================================================
// Check image accessibility
// ============================================================================

$results['images'] = [];
$results['summary']['images_total'] = 0;
$results['summary']['images_ok'] = 0;
$results['summary']['images_broken'] = 0;

if (!$skipImages && !empty($imagePages)) {
    $imageTotal = count($imagePages);
    $imageCurrent = 0;

    if (!$jsonOutput) {
        echo "Checking {$imageTotal} unique images...\n";
    }

    foreach ($imagePages as $imgUrl => $pages) {
        $imageCurrent++;
        if (!$jsonOutput) {
            $imgPath = parse_url($imgUrl, PHP_URL_PATH) ?: $imgUrl;
            echo "\r[{$imageCurrent}/{$imageTotal}] HEAD {$imgPath}" . str_repeat(' ', 20);
        }

        $imgResult = checkImageUrl($imgUrl);
        $results['images'][$imgUrl] = [
            'status' => $imgResult['code'],
            'pages' => array_values(array_unique($pages)),
        ];

        if ($imgResult['ok']) {
            $results['summary']['images_ok']++;
        } else {
            $results['summary']['images_broken']++;
        }
    }

    $results['summary']['images_total'] = $imageTotal;

    if (!$jsonOutput) {
        echo "\r" . str_repeat(' ', 60) . "\r";
    }
}

// ============================================================================
// Compare with previous snapshot (BEFORE saving current snapshot)
// ============================================================================

$comparison = null;
$exitCode = 0;
$timestamp = date('Y-m-d_H-i');

if (!$noCompare) {
    // Find previous snapshot (excluding latest.json symlink)
    $snapshots = glob($storageDir . '/*.json');
    $snapshots = array_filter($snapshots, fn($f) => basename($f) !== 'latest.json');
    usort($snapshots, fn($a, $b) => filemtime($b) - filemtime($a));

    if (!empty($snapshots)) {
        $prevFile = $snapshots[0];
        $prevData = json_decode(file_get_contents($prevFile), true);

        $comparison = [
            'previous_file' => basename($prevFile),
            'previous_version' => $prevData['version'] ?? 'unknown',
            'page_changes' => [],
            'redirect_changes' => [],
            'new_pages' => [],
            'removed_pages' => [],
        ];

        // Compare pages
        $prevPages = $prevData['pages'] ?? [];
        foreach ($results['pages'] as $path => $data) {
            if (!isset($prevPages[$path])) {
                $comparison['new_pages'][] = $path;
            } elseif ($prevPages[$path]['hash'] !== $data['hash']) {
                $comparison['page_changes'][] = [
                    'path' => $path,
                    'prev_status' => $prevPages[$path]['status'],
                    'curr_status' => $data['status'],
                    'hash_changed' => true,
                ];
                $results['summary']['changed']++;
            }
        }

        // Find removed pages
        foreach ($prevPages as $path => $data) {
            if (!isset($results['pages'][$path])) {
                $comparison['removed_pages'][] = $path;
            }
        }

        // Compare redirects
        $prevRedirects = $prevData['redirects'] ?? [];
        foreach ($results['redirects'] as $from => $data) {
            if (!isset($prevRedirects[$from])) {
                $comparison['redirect_changes'][] = [
                    'path' => $from,
                    'change' => 'new',
                ];
            } else {
                $prev = $prevRedirects[$from];
                $hashChanged = ($prev['target_hash'] ?? null) !== ($data['target_hash'] ?? null);
                $correctChanged = $prev['correct'] !== $data['correct'];
                $statusChanged = ($prev['target_status'] ?? null) !== ($data['target_status'] ?? null);
                if ($hashChanged || $correctChanged || $statusChanged) {
                    $comparison['redirect_changes'][] = [
                        'path' => $from,
                        'change' => $correctChanged ? 'correct' : 'hash',
                        'prev_hash' => $prev['target_hash'] ?? null,
                        'curr_hash' => $data['target_hash'] ?? null,
                    ];
                }
            }
        }

        // Compare images
        $prevImages = $prevData['images'] ?? [];
        $comparison['image_changes'] = [];

        foreach ($results['images'] as $imgUrl => $data) {
            $isBroken = $data['status'] >= 400 || $data['status'] === 0;
            if (isset($prevImages[$imgUrl])) {
                $wasBroken = ($prevImages[$imgUrl]['status'] ?? 200) >= 400 || ($prevImages[$imgUrl]['status'] ?? 200) === 0;
                if ($isBroken && !$wasBroken) {
                    $comparison['image_changes'][] = [
                        'url' => $imgUrl,
                        'change' => 'newly_broken',
                        'status' => $data['status'],
                        'pages' => $data['pages'],
                    ];
                } elseif (!$isBroken && $wasBroken) {
                    $comparison['image_changes'][] = [
                        'url' => $imgUrl,
                        'change' => 'fixed',
                        'status' => $data['status'],
                        'pages' => $data['pages'],
                    ];
                }
            } elseif ($isBroken) {
                $comparison['image_changes'][] = [
                    'url' => $imgUrl,
                    'change' => 'new_broken',
                    'status' => $data['status'],
                    'pages' => $data['pages'],
                ];
            }
        }

        $results['comparison'] = $comparison;
    }
}

// ============================================================================
// Save snapshot (now includes comparison data)
// ============================================================================

$snapshotData = json_encode($results, JSON_PRETTY_PRINT);
$snapshotHash = substr(md5($snapshotData), 0, 6);
$label = $snapshotTag ?? 'v' . $version;
$filename = "{$timestamp}_{$label}_{$snapshotHash}.json";
$filepath = $storageDir . '/' . $filename;

file_put_contents($filepath, $snapshotData);

// Update latest symlink
$latestLink = $storageDir . '/latest.json';
if (is_link($latestLink)) {
    unlink($latestLink);
}
symlink($filename, $latestLink);

// Determine exit code
if ($results['summary']['broken'] > 0) {
    $exitCode = 2;
} elseif ($results['summary']['redirect_broken'] > 0) {
    $exitCode = 2;
} elseif ($results['summary']['php_errors'] > 0) {
    $exitCode = 2;
} elseif (($results['summary']['images_broken'] ?? 0) > 0) {
    $exitCode = 2;
} elseif ($results['summary']['changed'] > 0) {
    $exitCode = 1;
}

// ============================================================================
// Output
// ============================================================================

$duration = round(microtime(true) - $startTime, 2);

if ($jsonOutput) {
    $results['snapshot_file'] = $filename;
    $results['duration'] = $duration;
    $results['exit_code'] = $exitCode;
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "\nResults:\n";
    echo "--------\n";
    echo "Pages OK: {$results['summary']['ok']}\n";
    echo "Pages Broken: {$results['summary']['broken']}\n";
    if ($results['summary']['php_errors'] > 0) {
        echo "PHP Errors (Fatal/Parse): {$results['summary']['php_errors']}\n";
    }
    if (($results['summary']['php_warnings'] ?? 0) > 0) {
        echo "PHP Warnings (informational): {$results['summary']['php_warnings']}\n";
    }
    echo "Redirects OK: {$results['summary']['redirect_ok']}\n";
    echo "Redirects Broken: {$results['summary']['redirect_broken']}\n";
    if (!$skipImages) {
        echo "Images Total: {$results['summary']['images_total']}\n";
        echo "Images OK: {$results['summary']['images_ok']}\n";
        echo "Images Broken: {$results['summary']['images_broken']}\n";
    }

    if ($comparison) {
        echo "\nComparison with {$comparison['previous_file']}:\n";
        echo "  Previous version: {$comparison['previous_version']}\n";
        echo "  Pages changed: " . count($comparison['page_changes']) . "\n";
        echo "  Redirects changed: " . count($comparison['redirect_changes']) . "\n";
        echo "  New pages: " . count($comparison['new_pages']) . "\n";
        echo "  Removed pages: " . count($comparison['removed_pages']) . "\n";

        // Changed pages table
        if (!empty($comparison['page_changes'])) {
            echo "\nChanged pages:\n";
            $changedPaths = array_column($comparison['page_changes'], 'path');
            $colPath = max(4, ...array_map('strlen', $changedPaths));
            $colPath = min($colPath, 50);
            $header = sprintf("  %-{$colPath}s  %-6s  %-6s", 'Path', 'Before', 'After');
            echo $header . "\n";
            echo "  " . str_repeat('-', strlen($header) - 2) . "\n";
            foreach ($comparison['page_changes'] as $change) {
                echo sprintf("  %-{$colPath}s  %-6s  %-6s\n",
                    substr($change['path'], 0, $colPath),
                    $change['prev_status'],
                    $change['curr_status']
                );
            }
        }

        // Changed redirects table
        if (!empty($comparison['redirect_changes'])) {
            echo "\nChanged redirects:\n";
            $changedRedirects = [];
            foreach ($comparison['redirect_changes'] as $rc) {
                $from = $rc['path'];
                if (isset($results['redirects'][$from])) {
                    $changedRedirects[$from] = $results['redirects'][$from];
                }
            }
            $colFrom = 6;
            $colTo = 11;
            foreach ($changedRedirects as $from => $data) {
                $colFrom = max($colFrom, strlen($from));
                $actualPath = $data['actual'] ? parse_url($data['actual'], PHP_URL_PATH) ?: $data['actual'] : '-';
                $colTo = max($colTo, strlen($actualPath));
            }
            $colFrom = min($colFrom, 40);
            $colTo = min($colTo, 40);
            $header = sprintf("  %-{$colFrom}s  %-{$colTo}s  %-6s  %-6s  %s",
                'Source', 'Destination', 'Redir', 'Target', 'Status');
            echo $header . "\n";
            echo "  " . str_repeat('-', strlen($header) - 2) . "\n";
            foreach ($changedRedirects as $from => $data) {
                $actualPath = $data['actual'] ? parse_url($data['actual'], PHP_URL_PATH) ?: $data['actual'] : '-';
                $redir = $data['status'] ?: '-';
                $target = $data['target_status'] ?: '-';
                if ($data['correct'] && ($data['target_status'] >= 200 && $data['target_status'] < 400)) {
                    $status = 'OK';
                } elseif (!$data['correct']) {
                    $status = 'WRONG';
                } elseif ($data['target_status'] >= 400 || $data['target_status'] === 0) {
                    $status = 'DEAD';
                } else {
                    $status = '?';
                }
                echo sprintf("  %-{$colFrom}s  %-{$colTo}s  %-6s  %-6s  %s\n",
                    substr($from, 0, $colFrom),
                    substr($actualPath, 0, $colTo),
                    $redir, $target, $status);
            }
        }

        if (!empty($comparison['new_pages'])) {
            echo "\nNew pages:\n";
            foreach ($comparison['new_pages'] as $path) {
                $status = $results['pages'][$path]['status'] ?? '?';
                echo "  [{$status}] {$path}\n";
            }
        }

        if (!empty($comparison['removed_pages'])) {
            echo "\nRemoved pages:\n";
            foreach ($comparison['removed_pages'] as $path) {
                echo "  {$path}\n";
            }
        }

        // Image changes
        if (!empty($comparison['image_changes'])) {
            echo "\nImage changes:\n";
            foreach ($comparison['image_changes'] as $ic) {
                $imgPath = parse_url($ic['url'], PHP_URL_PATH) ?: $ic['url'];
                $changeLabel = match($ic['change']) {
                    'newly_broken' => 'BROKEN',
                    'fixed' => 'FIXED',
                    'new_broken' => 'NEW BROKEN',
                    default => $ic['change'],
                };
                $pages = implode(', ', array_slice($ic['pages'], 0, 3));
                if (count($ic['pages']) > 3) {
                    $pages .= ' (+' . (count($ic['pages']) - 3) . ' more)';
                }
                echo "  [{$ic['status']}] [{$changeLabel}] {$imgPath}\n";
                echo "    Pages: {$pages}\n";
            }
        }
    } else {
        // No comparison - show broken items
        if ($results['summary']['broken'] > 0) {
            echo "\nBroken pages:\n";
            foreach ($results['pages'] as $path => $data) {
                if ($data['status'] >= 400 || $data['status'] === 0) {
                    echo "  [{$data['status']}] {$path}\n";
                }
            }
        }
        if ($results['summary']['redirect_broken'] > 0) {
            echo "\nBroken redirects:\n";
            foreach ($results['redirects'] as $from => $data) {
                if (!$data['correct']) {
                    echo "  {$from} -> expected: {$data['expected']}, got: [{$data['status']}] {$data['actual']}\n";
                }
            }
        }
        if (($results['summary']['images_broken'] ?? 0) > 0) {
            echo "\nBroken images:\n";
            foreach ($results['images'] as $imgUrl => $data) {
                if ($data['status'] >= 400 || $data['status'] === 0) {
                    $imgPath = parse_url($imgUrl, PHP_URL_PATH) ?: $imgUrl;
                    $pages = implode(', ', array_slice($data['pages'], 0, 3));
                    if (count($data['pages']) > 3) {
                        $pages .= ' (+' . (count($data['pages']) - 3) . ' more)';
                    }
                    echo "  [{$data['status']}] {$imgPath}\n";
                    echo "    Pages: {$pages}\n";
                }
            }
        }
    }

    // Always show pages with PHP errors
    if ($results['summary']['php_errors'] > 0) {
        echo "\nPages with PHP errors:\n";
        foreach ($results['pages'] as $path => $data) {
            if (!empty($data['errors'])) {
                echo "  {$path}\n";
                foreach ($data['errors'] as $err) {
                    echo "    " . substr($err, 0, 120) . "\n";
                }
            }
        }
    }

    echo "\nSnapshot saved: {$filename}\n";
    echo "Duration: {$duration}s\n";

    if ($exitCode === 0) {
        echo "\nStatus: OK\n";
    } elseif ($exitCode === 1) {
        echo "\nStatus: WARNING (pages changed)\n";
    } else {
        echo "\nStatus: ERROR (broken pages/redirects/images)\n";
    }
}

exit($exitCode);
