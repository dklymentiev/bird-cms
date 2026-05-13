#!/usr/bin/env php
<?php

declare(strict_types=1);

// Load bootstrap from current working directory (site) or fallback to engine
$siteBootstrap = getcwd() . '/bootstrap.php';
$engineBootstrap = __DIR__ . '/../bootstrap.php';
require file_exists($siteBootstrap) ? $siteBootstrap : $engineBootstrap;

use App\Content\FrontMatter;

$options = getopt('', [
    'type:',          // residential or commercial
    'slug:',
    'title:',
    'description:',
    'hero-text::',
    'hero-image::',
    'priority::',
    'keywords::',
    'features::',     // JSON array
    'included::',     // comma-separated
    'pricing::',      // JSON array
    'faqs::',         // JSON array
    'content::',
    'content-file::',
]);

$type = trim($options['type'] ?? '');
$slug = trim($options['slug'] ?? '');
$title = trim($options['title'] ?? '');
$description = trim($options['description'] ?? '');

if ($type === '' || $slug === '' || $title === '' || $description === '') {
    fwrite(STDERR, "Usage: php scripts/add-service.php --type=residential --slug=pressure-washing --title='Pressure Washing' --description='...' [options]\n\n");
    fwrite(STDERR, "Required:\n");
    fwrite(STDERR, "  --type          Service type (residential or commercial)\n");
    fwrite(STDERR, "  --slug          URL slug (e.g., pressure-washing)\n");
    fwrite(STDERR, "  --title         Service title\n");
    fwrite(STDERR, "  --description   Meta description\n\n");
    fwrite(STDERR, "Optional:\n");
    fwrite(STDERR, "  --hero-text     Hero section text (defaults to description)\n");
    fwrite(STDERR, "  --hero-image    Path to hero image\n");
    fwrite(STDERR, "  --priority      Sort priority (higher = first)\n");
    fwrite(STDERR, "  --keywords      Comma-separated keywords\n");
    fwrite(STDERR, "  --features      JSON array of features\n");
    fwrite(STDERR, "  --included      Comma-separated list of what's included\n");
    fwrite(STDERR, "  --pricing       JSON array of pricing tiers\n");
    fwrite(STDERR, "  --faqs          JSON array of FAQs\n");
    fwrite(STDERR, "  --content       Markdown content\n");
    fwrite(STDERR, "  --content-file  Path to markdown file\n");
    exit(1);
}

if (!in_array($type, ['residential', 'commercial'], true)) {
    fwrite(STDERR, "Error: Type must be 'residential' or 'commercial'\n");
    exit(1);
}

if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    fwrite(STDERR, "Error: Slug must contain only lowercase letters, numbers, and hyphens\n");
    exit(1);
}

$heroText = trim($options['hero-text'] ?? $description);
$heroImage = trim($options['hero-image'] ?? '');
$priority = (int) ($options['priority'] ?? 50);
$keywordsInput = trim($options['keywords'] ?? '');
$featuresJson = $options['features'] ?? null;
$includedInput = trim($options['included'] ?? '');
$pricingJson = $options['pricing'] ?? null;
$faqsJson = $options['faqs'] ?? null;
$content = $options['content'] ?? null;
$contentFile = $options['content-file'] ?? null;

// Parse keywords
$keywords = $keywordsInput !== '' ? array_map('trim', explode(',', $keywordsInput)) : [];

// Parse features
$features = [];
if ($featuresJson !== null) {
    $features = json_decode($featuresJson, true) ?? [];
}

// Parse included
$included = $includedInput !== '' ? array_map('trim', explode(',', $includedInput)) : [];

// Parse pricing
$pricing = [];
if ($pricingJson !== null) {
    $pricing = json_decode($pricingJson, true) ?? [];
}

// Parse FAQs
$faqs = [];
if ($faqsJson !== null) {
    $faqs = json_decode($faqsJson, true) ?? [];
}

// Load content from file if specified
if ($content === null && $contentFile !== null) {
    $content = file_get_contents($contentFile);
    if ($content === false) {
        fwrite(STDERR, "Error: Unable to read content file: {$contentFile}\n");
        exit(1);
    }
}

// Default content if none provided
if ($content === null || trim($content) === '') {
    $content = <<<MARKDOWN
## Why Choose Our {$title} Service

Professional {$title} service for Toronto and GTA homes and businesses.

## Our Process

We follow a systematic approach to deliver consistent, high-quality results.

## Service Areas

We serve all of Toronto and the Greater Toronto Area.
MARKDOWN;
}

// Create directory if needed
$servicesDir = defined('SITE_CONTENT_PATH')
    ? SITE_CONTENT_PATH . '/services/' . $type
    : __DIR__ . '/../content/services/' . $type;

if (!is_dir($servicesDir)) {
    mkdir($servicesDir, 0755, true);
}

// Build metadata
$metadata = [
    'title' => $title,
    'slug' => $slug,
    'description' => $description,
    'hero_text' => $heroText,
    'priority' => $priority,
];

if ($heroImage !== '') {
    $metadata['hero_image'] = $heroImage;
}

if (!empty($keywords)) {
    $metadata['keywords'] = $keywords;
}

if (!empty($features)) {
    $metadata['features'] = $features;
}

if (!empty($included)) {
    $metadata['included'] = $included;
}

if (!empty($pricing)) {
    $metadata['pricing'] = $pricing;
}

if (!empty($faqs)) {
    $metadata['faqs'] = $faqs;
}

$metadata['schema'] = 'Service';

// Write .meta.yaml file
$metaFilePath = $servicesDir . '/' . $slug . '.meta.yaml';
$yamlContent = FrontMatter::encode($metadata);
file_put_contents($metaFilePath, $yamlContent);

// Write .md file (content only, no frontmatter)
$mdFilePath = $servicesDir . '/' . $slug . '.md';
file_put_contents($mdFilePath, trim($content) . "\n");

echo "✅ Service created:\n";
echo "   Meta: {$metaFilePath}\n";
echo "   Content: {$mdFilePath}\n";
echo "   URL: /{$type}/{$slug}\n";
