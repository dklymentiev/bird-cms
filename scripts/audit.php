<?php
/**
 * Article Audit Script
 *
 * Checks all articles for SEO, meta, links, and content issues.
 * Can auto-fix some problems.
 *
 * Usage:
 *   php audit.php                    # Report only
 *   php audit.php --fix              # Auto-fix what's possible
 *   php audit.php --category=tools   # Audit specific category
 */

declare(strict_types=1);

// Load bootstrap from current working directory (site) or fallback to engine
$siteBootstrap = getcwd() . '/bootstrap.php';
$engineBootstrap = __DIR__ . '/../bootstrap.php';
require_once file_exists($siteBootstrap) ? $siteBootstrap : $engineBootstrap;

// Configuration
$siteRoot = defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__);
$baseUrl = config('site_url');
$contentDir = config('articles_dir', $siteRoot . '/content/articles');
$heroDir = $siteRoot . '/public/assets/hero';

// Parse CLI args
$args = getopt('', ['fix', 'category:', 'verbose']);
$autoFix = isset($args['fix']);
$categoryFilter = $args['category'] ?? null;
$verbose = isset($args['verbose']);

// Colors for output
function color(string $text, string $color): string {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'gray' => "\033[90m",
        'reset' => "\033[0m",
        'bold' => "\033[1m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

// Parse YAML frontmatter
function parseFrontmatter(string $content): array {
    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
        return ['meta' => [], 'body' => $content];
    }

    $yaml = $matches[1];
    $body = $matches[2];
    $meta = [];

    // Simple YAML parser for frontmatter
    $lines = explode("\n", $yaml);
    $currentKey = null;
    $inArray = false;

    foreach ($lines as $line) {
        if (preg_match('/^(\w+):\s*(.*)$/', $line, $m)) {
            $currentKey = $m[1];
            $value = trim($m[2], ' "\'');
            if ($value === '' || $value === '|' || $value === '>') {
                $meta[$currentKey] = '';
                $inArray = false;
            } elseif ($value === '') {
                $meta[$currentKey] = [];
                $inArray = true;
            } else {
                $meta[$currentKey] = $value;
                $inArray = false;
            }
        } elseif ($inArray && preg_match('/^\s+-\s*(.+)$/', $line, $m)) {
            $meta[$currentKey][] = trim($m[1], ' "\'');
        }
    }

    return ['meta' => $meta, 'body' => $body];
}

// Check if URL is valid (basic check, no HTTP request)
function isValidUrl(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Check if internal link target exists
function internalLinkExists(string $path, string $contentDir): bool {
    // Convert URL path to file path
    // /tools/slug -> /content/articles/tools/slug.md
    $path = ltrim($path, '/');
    $parts = explode('/', $path);

    if (count($parts) >= 2) {
        $category = $parts[0];
        $slug = $parts[1];
        $filePath = $contentDir . '/' . $category . '/' . $slug . '.md';
        return file_exists($filePath);
    }

    return false;
}

// Extract links from markdown
function extractLinks(string $body): array {
    $links = [];

    // Markdown links: [text](url)
    if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $body, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $links[] = [
                'text' => $match[1],
                'url' => $match[2],
                'type' => strpos($match[2], 'http') === 0 ? 'external' : 'internal'
            ];
        }
    }

    return $links;
}

// Count words in body (excluding markdown syntax)
function countWords(string $body): int {
    $clean = preg_replace('/```[\s\S]*?```/', '', $body); // code blocks
    $clean = preg_replace('/`[^`]+`/', '', $clean); // inline code
    $clean = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $clean); // links
    $clean = preg_replace('/[#*_~>\-|]/', '', $clean); // markdown
    $clean = preg_replace('/\s+/', ' ', $clean);

    return str_word_count(trim($clean));
}

// Main audit function
function auditArticle(string $filePath, array $config): array {
    $issues = [];
    $warnings = [];
    $fixes = [];

    $content = file_get_contents($filePath);
    $parsed = parseFrontmatter($content);
    $meta = $parsed['meta'];
    $body = $parsed['body'];

    $category = basename(dirname($filePath));
    $filename = basename($filePath, '.md');

    // === FRONTMATTER CHECKS ===

    // Required fields
    $requiredFields = ['title', 'slug', 'description', 'date', 'category'];
    foreach ($requiredFields as $field) {
        if (empty($meta[$field])) {
            $issues[] = "Missing required field: {$field}";
        }
    }

    // Title length (50-60 chars ideal for SEO)
    if (!empty($meta['title'])) {
        $titleLen = strlen($meta['title']);
        if ($titleLen > 70) {
            $warnings[] = "Title too long ({$titleLen} chars, recommend <70)";
        } elseif ($titleLen < 30) {
            $warnings[] = "Title too short ({$titleLen} chars, recommend 30-60)";
        }
    }

    // Description length (150-160 chars ideal)
    if (!empty($meta['description'])) {
        $descLen = strlen($meta['description']);
        if ($descLen > 170) {
            $warnings[] = "Description too long ({$descLen} chars, recommend <160)";
        } elseif ($descLen < 100) {
            $warnings[] = "Description too short ({$descLen} chars, recommend 120-160)";
        }
    }

    // Slug format
    if (!empty($meta['slug'])) {
        if (!preg_match('/^[a-z0-9-]+$/', $meta['slug'])) {
            $issues[] = "Invalid slug format (use lowercase, numbers, hyphens only)";
        }
        if ($meta['slug'] !== $filename) {
            $warnings[] = "Slug '{$meta['slug']}' doesn't match filename '{$filename}'";
        }
    }

    // Hero image
    if (empty($meta['hero_image'])) {
        $issues[] = "Missing hero_image";
        // Check if file exists anyway
        $expectedHero = $config['heroDir'] . '/' . $category . '/' . $filename . '.jpg';
        if (file_exists($expectedHero)) {
            $fixes['hero_image'] = '/assets/hero/' . $category . '/' . $filename . '.jpg';
        }
    } else {
        // Verify hero file exists
        $heroPath = $config['heroDir'] . '/' . ltrim(str_replace('/assets/hero/', '', $meta['hero_image']), '/');
        if (!file_exists($heroPath)) {
            $issues[] = "Hero image file not found: {$meta['hero_image']}";
        }
    }

    // OG Image (should be full URL)
    if (empty($meta['og_image'])) {
        $warnings[] = "Missing og_image (will use default)";
        if (!empty($meta['hero_image'])) {
            $fixes['og_image'] = $config['baseUrl'] . $meta['hero_image'];
        }
    } elseif (strpos($meta['og_image'], 'http') !== 0) {
        $issues[] = "og_image should be full URL, not relative path";
        $fixes['og_image'] = $config['baseUrl'] . $meta['og_image'];
    }

    // Canonical URL
    if (empty($meta['canonical'])) {
        $warnings[] = "Missing canonical URL";
        $fixes['canonical'] = $config['baseUrl'] . '/' . $category . '/' . ($meta['slug'] ?? $filename);
    }

    // Date validation
    if (!empty($meta['date'])) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $meta['date'])) {
            $issues[] = "Invalid date format (use YYYY-MM-DD)";
        }
    }

    // === CONTENT CHECKS ===

    // Word count
    $wordCount = countWords($body);
    if ($wordCount < 1000) {
        $warnings[] = "Low word count ({$wordCount}, recommend 1500+)";
    }

    // Check for H1
    if (!preg_match('/^#\s+.+$/m', $body)) {
        $issues[] = "Missing H1 heading";
    }

    // Check for multiple H1s
    if (preg_match_all('/^#\s+.+$/m', $body) > 1) {
        $warnings[] = "Multiple H1 headings (should have only one)";
    }

    // Check for FAQ section
    if (stripos($body, '## Frequently Asked Questions') === false &&
        stripos($body, '## FAQ') === false) {
        $warnings[] = "Missing FAQ section";
    }

    // Check for Sources section
    if (stripos($body, '## Sources') === false &&
        stripos($body, '## References') === false) {
        $warnings[] = "Missing Sources section";
    }

    // === LINK CHECKS ===

    $links = extractLinks($body);
    $internalLinks = array_filter($links, fn($l) => $l['type'] === 'internal');
    $externalLinks = array_filter($links, fn($l) => $l['type'] === 'external');

    // Check internal links
    foreach ($internalLinks as $link) {
        if (!internalLinkExists($link['url'], $config['contentDir'])) {
            $issues[] = "Broken internal link: {$link['url']}";
        }
    }

    // Check if article has internal links (for interlinking)
    if (count($internalLinks) === 0) {
        $warnings[] = "No internal links (add links to related articles)";
    }

    // External links - just count, don't check (too slow)
    // Could add async checking later

    return [
        'file' => $filePath,
        'category' => $category,
        'slug' => $meta['slug'] ?? $filename,
        'title' => $meta['title'] ?? 'Unknown',
        'date' => $meta['date'] ?? null,
        'wordCount' => $wordCount,
        'internalLinks' => count($internalLinks),
        'externalLinks' => count($externalLinks),
        'issues' => $issues,
        'warnings' => $warnings,
        'fixes' => $fixes,
        'meta' => $meta
    ];
}

// Apply fixes to article
function applyFixes(string $filePath, array $fixes): bool {
    if (empty($fixes)) return false;

    $content = file_get_contents($filePath);

    foreach ($fixes as $field => $value) {
        // Check if field exists
        if (preg_match('/^' . preg_quote($field, '/') . ':\s*.+$/m', $content)) {
            // Update existing
            $content = preg_replace(
                '/^' . preg_quote($field, '/') . ':\s*.+$/m',
                $field . ': "' . $value . '"',
                $content
            );
        } else {
            // Add after date field
            $content = preg_replace(
                '/^(date:\s*.+)$/m',
                "$1\n" . $field . ': "' . $value . '"',
                $content,
                1
            );
        }
    }

    return file_put_contents($filePath, $content) !== false;
}

// Redistribute dates evenly
function redistributeDates(array $articles, string $startDate = null): array {
    if (empty($articles)) return [];

    $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');

    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end)->days;

    $count = count($articles);
    $daysBetween = max(1, floor($interval / $count));

    $newDates = [];
    $current = clone $start;

    foreach ($articles as $article) {
        $newDates[$article['file']] = $current->format('Y-m-d');
        $current->modify("+{$daysBetween} days");
        if ($current > $end) {
            $current = clone $end;
        }
    }

    return $newDates;
}

// ============ MAIN ============

echo color("\n=== Article Audit Report ===\n", 'bold');
echo "Base URL: {$baseUrl}\n";
echo "Content: {$contentDir}\n";
echo "Mode: " . ($autoFix ? color("AUTO-FIX", 'yellow') : "Report only") . "\n\n";

// Scan articles
$articles = [];
$categories = glob($contentDir . '/*', GLOB_ONLYDIR);

foreach ($categories as $catDir) {
    $catName = basename($catDir);

    if ($categoryFilter && $catName !== $categoryFilter) {
        continue;
    }

    $files = glob($catDir . '/*.md');

    foreach ($files as $file) {
        $result = auditArticle($file, [
            'baseUrl' => $baseUrl,
            'contentDir' => $contentDir,
            'heroDir' => $heroDir
        ]);
        $articles[] = $result;
    }
}

// Sort by date
usort($articles, fn($a, $b) => ($a['date'] ?? '9999') <=> ($b['date'] ?? '9999'));

// Statistics
$totalIssues = 0;
$totalWarnings = 0;
$totalFixes = 0;
$datesByDate = [];

foreach ($articles as $article) {
    $date = $article['date'] ?? 'no-date';
    $datesByDate[$date] = ($datesByDate[$date] ?? 0) + 1;
}

// Output results
foreach ($articles as $article) {
    $hasProblems = !empty($article['issues']) || !empty($article['warnings']);

    if (!$hasProblems && !$verbose) {
        continue;
    }

    echo color("─────────────────────────────────────────\n", 'gray');
    echo color($article['category'] . '/', 'blue') . color($article['slug'], 'bold') . "\n";
    echo "  Title: " . substr($article['title'], 0, 50) . (strlen($article['title']) > 50 ? '...' : '') . "\n";
    echo "  Date: " . ($article['date'] ?? 'none') . " | Words: {$article['wordCount']} | Links: {$article['internalLinks']} int / {$article['externalLinks']} ext\n";

    if (!empty($article['issues'])) {
        foreach ($article['issues'] as $issue) {
            echo "  " . color("✗ ", 'red') . $issue . "\n";
            $totalIssues++;
        }
    }

    if (!empty($article['warnings'])) {
        foreach ($article['warnings'] as $warning) {
            echo "  " . color("⚠ ", 'yellow') . $warning . "\n";
            $totalWarnings++;
        }
    }

    if (!empty($article['fixes'])) {
        foreach ($article['fixes'] as $field => $value) {
            echo "  " . color("→ ", 'green') . "Can fix: {$field}\n";
            $totalFixes++;
        }

        if ($autoFix) {
            if (applyFixes($article['file'], $article['fixes'])) {
                echo "  " . color("✓ Fixed!", 'green') . "\n";
            }
        }
    }
}

// Date distribution warning
echo color("\n─────────────────────────────────────────\n", 'gray');
echo color("Date Distribution:\n", 'bold');
foreach ($datesByDate as $date => $count) {
    $bar = str_repeat('█', min($count, 20));
    $warning = $count > 3 ? color(" (too many!)", 'yellow') : '';
    echo "  {$date}: {$bar} {$count}{$warning}\n";
}

// Summary
echo color("\n=== Summary ===\n", 'bold');
echo "Articles scanned: " . count($articles) . "\n";
echo color("Issues: {$totalIssues}", $totalIssues > 0 ? 'red' : 'green') . "\n";
echo color("Warnings: {$totalWarnings}", $totalWarnings > 0 ? 'yellow' : 'green') . "\n";
echo "Auto-fixable: {$totalFixes}\n";

if ($totalFixes > 0 && !$autoFix) {
    echo "\n" . color("Run with --fix to auto-fix issues", 'blue') . "\n";
}

// Suggest date redistribution if needed
$maxPerDay = max($datesByDate);
if ($maxPerDay > 3) {
    echo "\n" . color("⚠ Multiple articles on same date. Consider redistributing.", 'yellow') . "\n";
    echo "  Run: php audit.php --redistribute-dates\n";
}

echo "\n";
