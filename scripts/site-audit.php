#!/usr/bin/env php
<?php
/**
 * Site Audit Script
 *
 * Universal audit for any CMS Engine site.
 * Checks services, pages, images, and content.
 *
 * Usage:
 *   php scripts/site-audit.php           # Full audit
 *   php scripts/site-audit.php --images  # Check images only
 *   php scripts/site-audit.php --content # Check content only
 *
 * @version 1.0.0 (2025-12-14)
 */

declare(strict_types=1);

const VERSION = '1.0.0';

// Load bootstrap from current working directory (site) or fallback to engine
$siteBootstrap = getcwd() . '/bootstrap.php';
$engineBootstrap = __DIR__ . '/../bootstrap.php';
require file_exists($siteBootstrap) ? $siteBootstrap : $engineBootstrap;

// Parse args
$checkAll = true;
$checkImages = false;
$checkContent = false;

foreach ($argv as $arg) {
    if ($arg === '--images') {
        $checkImages = true;
        $checkAll = false;
    }
    if ($arg === '--content') {
        $checkContent = true;
        $checkAll = false;
    }
}

if ($checkAll) {
    $checkImages = true;
    $checkContent = true;
}

$siteRoot = defined('SITE_ROOT') ? SITE_ROOT : getcwd();
$publicDir = $siteRoot . '/public';
$contentDir = $siteRoot . '/content';

$issues = [];
$warnings = [];
$stats = [
    'services' => 0,
    'pages' => 0,
    'images_checked' => 0,
    'images_missing' => 0,
];

echo "\033[1m=== Site Audit ===\033[0m\n";
echo "Site: " . config('site_name', basename($siteRoot)) . "\n";
echo "Root: $siteRoot\n\n";

// Check services
$serviceTypes = ['residential', 'commercial'];
foreach ($serviceTypes as $type) {
    $servicesDir = "$contentDir/services/$type";
    if (!is_dir($servicesDir)) continue;

    foreach (glob("$servicesDir/*.meta.yaml") as $metaFile) {
        $stats['services']++;
        $slug = basename($metaFile, '.meta.yaml');
        $mdFile = "$servicesDir/$slug.md";
        $content = file_get_contents($metaFile);

        // Check .md file exists
        if (!file_exists($mdFile)) {
            $issues[] = "[$type/$slug] Missing content file: $slug.md";
        }

        // Check hero_image
        if ($checkImages && preg_match('/hero_image:\s*(.+)/', $content, $m)) {
            $img = trim($m[1]);
            $stats['images_checked']++;
            if (!file_exists("$publicDir$img")) {
                $stats['images_missing']++;
                $issues[] = "[$type/$slug] Missing hero image: $img";
            }
        }

        // Check required fields
        if (strpos($content, 'title:') === false) {
            $issues[] = "[$type/$slug] Missing title in meta.yaml";
        }
        if (strpos($content, 'description:') === false) {
            $warnings[] = "[$type/$slug] Missing description in meta.yaml";
        }
        if (strpos($content, 'keywords:') === false) {
            $warnings[] = "[$type/$slug] No keywords defined";
        }

        // Check content quality
        if ($checkContent && file_exists($mdFile)) {
            $mdContent = file_get_contents($mdFile);
            $wordCount = str_word_count(strip_tags($mdContent));

            if ($wordCount < 100) {
                $warnings[] = "[$type/$slug] Short content ($wordCount words)";
            }

            // Check for H2 headings
            if (strpos($mdContent, '## ') === false) {
                $warnings[] = "[$type/$slug] No H2 headings in content";
            }
        }
    }
}

// Check pages
$pagesDir = "$contentDir/pages";
if (is_dir($pagesDir)) {
    foreach (glob("$pagesDir/*.meta.yaml") as $metaFile) {
        $stats['pages']++;
        $slug = basename($metaFile, '.meta.yaml');
        $mdFile = "$pagesDir/$slug.md";

        if (!file_exists($mdFile)) {
            $issues[] = "[page/$slug] Missing content file: $slug.md";
        }
    }
}

// Check image bank for orphans
if ($checkImages) {
    $bankDir = "$publicDir/assets/images/bank";
    if (is_dir($bankDir)) {
        $usedImages = [];

        // Collect all referenced images
        foreach (glob("$contentDir/services/*/*.meta.yaml") as $file) {
            $content = file_get_contents($file);
            if (preg_match('/hero_image:\s*(.+)/', $content, $m)) {
                $usedImages[] = trim($m[1]);
            }
        }

        // Check landing pages for image references
        $themeDir = "$siteRoot/themes";
        if (is_dir($themeDir)) {
            foreach (glob("$themeDir/*/views/*.php") as $file) {
                $content = file_get_contents($file);
                preg_match_all('/\/assets\/images\/bank\/[^"\']+/', $content, $matches);
                $usedImages = array_merge($usedImages, $matches[0]);
            }
        }

        $usedImages = array_unique($usedImages);

        // Count total bank images
        $bankImages = glob("$bankDir/*.webp");
        $orphanCount = 0;
        foreach ($bankImages as $img) {
            $relativePath = '/assets/images/bank/' . basename($img);
            if (!in_array($relativePath, $usedImages)) {
                $orphanCount++;
            }
        }

        if ($orphanCount > 0) {
            $warnings[] = "$orphanCount unused images in /assets/images/bank/";
        }
    }
}

// Output results
echo "\033[1m📊 Statistics:\033[0m\n";
echo "  Services: {$stats['services']}\n";
echo "  Pages: {$stats['pages']}\n";
echo "  Images checked: {$stats['images_checked']}\n";
echo "  Images missing: {$stats['images_missing']}\n\n";

if (!empty($issues)) {
    echo "\033[31m❌ ISSUES (" . count($issues) . "):\033[0m\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "\033[33m⚠️  WARNINGS (" . count($warnings) . "):\033[0m\n";
    foreach (array_slice($warnings, 0, 20) as $warning) {
        echo "  - $warning\n";
    }
    if (count($warnings) > 20) {
        echo "  ... and " . (count($warnings) - 20) . " more\n";
    }
    echo "\n";
}

if (empty($issues) && empty($warnings)) {
    echo "\033[32m✅ All checks passed!\033[0m\n";
} elseif (empty($issues)) {
    echo "\033[32m✅ No critical issues found.\033[0m\n";
}

exit(empty($issues) ? 0 : 1);
