#!/usr/bin/env php
<?php

declare(strict_types=1);

// Load bootstrap from current working directory (site) or fallback to engine
$siteBootstrap = getcwd() . '/bootstrap.php';
$engineBootstrap = __DIR__ . '/../bootstrap.php';
require file_exists($siteBootstrap) ? $siteBootstrap : $engineBootstrap;

use App\Content\ArticleRepository;

$options = getopt('', ['category::', 'verbose']);

$filterCategory = $options['category'] ?? null;
$verbose = isset($options['verbose']);

$repository = new ArticleRepository(config('articles_dir'));
$siteUrl = rtrim(config('site_url'), '/');

$errors = [];
$warnings = [];
$stats = [
    'total' => 0,
    'with_og_image' => 0,
    'with_hero_image' => 0,
    'with_canonical' => 0,
    'with_reading_time' => 0,
];

$articles = $filterCategory ? $repository->inCategory($filterCategory, 999) : $repository->all();

foreach ($articles as $article) {
    $stats['total']++;
    $location = "[{$article['category']}/{$article['slug']}]";
    $meta = $article['meta'] ?? [];

    // Check hero image
    if (!empty($article['hero_image'])) {
        $stats['with_hero_image']++;
    } else {
        $warnings[] = "{$location} No hero_image defined";
    }

    // Check OG image
    if (!empty($meta['og_image'])) {
        $stats['with_og_image']++;

        // Validate OG image URL
        $ogImage = $meta['og_image'];
        if (!filter_var($ogImage, FILTER_VALIDATE_URL)) {
            $errors[] = "{$location} Invalid og_image URL: {$ogImage}";
        } elseif (strpos($ogImage, 'http://') === 0) {
            $warnings[] = "{$location} og_image should use HTTPS: {$ogImage}";
        }
    } else {
        $warnings[] = "{$location} No og_image in metadata";
    }

    // Check canonical URL
    if (!empty($meta['canonical'])) {
        $stats['with_canonical']++;
        $canonical = $meta['canonical'];

        if (!filter_var($canonical, FILTER_VALIDATE_URL)) {
            $errors[] = "{$location} Invalid canonical URL: {$canonical}";
        }

        // Check if canonical matches expected URL
        $expectedUrl = $siteUrl . '/' . $article['category'] . '/' . $article['slug'];
        if ($canonical !== $expectedUrl) {
            $warnings[] = "{$location} Canonical URL mismatch: expected {$expectedUrl}, got {$canonical}";
        }
    } else {
        $warnings[] = "{$location} No canonical URL defined";
    }

    // Check reading time
    if (!empty($meta['reading_time'])) {
        $stats['with_reading_time']++;
    } else {
        $warnings[] = "{$location} No reading_time in metadata";
    }

    // Check description length for SEO
    if (!empty($article['description'])) {
        $descLength = strlen($article['description']);
        if ($descLength < 50) {
            $warnings[] = "{$location} Description too short for SEO ({$descLength} chars)";
        } elseif ($descLength > 160) {
            $warnings[] = "{$location} Description too long for SEO ({$descLength} chars)";
        }
    }

    // Check twitter card metadata
    if (isset($meta['twitter_card'])) {
        $validCards = ['summary', 'summary_large_image', 'app', 'player'];
        if (!in_array($meta['twitter_card'], $validCards, true)) {
            $errors[] = "{$location} Invalid twitter_card value: {$meta['twitter_card']}";
        }
    }

    // Check language code
    if (isset($meta['language'])) {
        $lang = $meta['language'];
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $lang)) {
            $errors[] = "{$location} Invalid language code: {$lang} (expected format: en, en-US)";
        }
    }

    // Check author information
    if (empty($meta['author']) && empty($article['author'])) {
        $warnings[] = "{$location} No author defined";
    }

    // Check tags for SEO keywords
    if (empty($article['tags']) || count($article['tags']) === 0) {
        $warnings[] = "{$location} No tags defined (important for SEO)";
    } elseif (count($article['tags']) > 10) {
        $warnings[] = "{$location} Too many tags (" . count($article['tags']) . "), recommended max 10";
    }

    // Check for updated date
    if (isset($meta['updated'])) {
        $updated = $meta['updated'];
        $published = $article['date'] ?? null;

        if ($published && strtotime($updated) < strtotime($published)) {
            $errors[] = "{$location} Updated date ({$updated}) is before published date ({$published})";
        }
    }

    if ($verbose) {
        echo ".";
    }
}

if ($verbose) {
    echo "\n\n";
}

// Output results
echo "🔍 Metadata Validation Report\n";
echo "==============================\n\n";

echo "📊 Statistics:\n";
echo "  Total articles: {$stats['total']}\n";
echo "  With hero_image: {$stats['with_hero_image']} (" . round($stats['with_hero_image'] / max($stats['total'], 1) * 100, 1) . "%)\n";
echo "  With og_image: {$stats['with_og_image']} (" . round($stats['with_og_image'] / max($stats['total'], 1) * 100, 1) . "%)\n";
echo "  With canonical: {$stats['with_canonical']} (" . round($stats['with_canonical'] / max($stats['total'], 1) * 100, 1) . "%)\n";
echo "  With reading_time: {$stats['with_reading_time']} (" . round($stats['with_reading_time'] / max($stats['total'], 1) * 100, 1) . "%)\n";
echo "\n";

if (!empty($errors)) {
    echo "❌ ERRORS (" . count($errors) . "):\n";
    foreach (array_slice($errors, 0, 20) as $error) {
        echo "  - {$error}\n";
    }
    if (count($errors) > 20) {
        echo "  ... and " . (count($errors) - 20) . " more\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach (array_slice($warnings, 0, 20) as $warning) {
        echo "  - {$warning}\n";
    }
    if (count($warnings) > 20) {
        echo "  ... and " . (count($warnings) - 20) . " more\n";
    }
    echo "\n";
}

if (empty($errors) && empty($warnings)) {
    echo "✅ All metadata is valid!\n";
}

echo "\nOptions:\n";
echo "  --category=NAME  Validate only articles in specific category\n";
echo "  --verbose        Show progress dots\n";

exit(empty($errors) ? 0 : 1);
