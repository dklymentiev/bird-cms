#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Content\FrontMatter;

$options = getopt('', [
    'category:',
    'title:',
    'slug::',
    'description::',
    'tags::',
    'date::',
    'author::',
    'content::',
    'content-file::',
    'images::',
    'related::'
]);

$category = trim($options['category'] ?? '');
$title = trim($options['title'] ?? '');

if ($category === '' || $title === '') {
    fwrite(STDERR, "Usage: php scripts/add-article.php --category=tech --title='Title' [--content='...'] [--content-file=/path/to/markdown.md]\n");
    exit(1);
}

$slug = trim($options['slug'] ?? slugify($title));
$description = trim($options['description'] ?? '');
$tagsInput = trim($options['tags'] ?? '');
$date = trim($options['date'] ?? date('Y-m-d'));
$author = trim($options['author'] ?? 'AI Bot');
$content = $options['content'] ?? null;
$contentFile = $options['content-file'] ?? null;
$related = trim($options['related'] ?? '');

if ($content === null && $contentFile === null) {
    $stdin = stream_get_contents(STDIN);
    $content = $stdin !== false ? trim($stdin) : '';
}

if ($content === null && $contentFile !== null) {
    $content = file_get_contents($contentFile);
    if ($content === false) {
        fwrite(STDERR, "Unable to read content file: {$contentFile}\n");
        exit(1);
    }
}

if ($content === null) {
    fwrite(STDERR, "Content must be provided via --content, --content-file, or STDIN.\n");
    exit(1);
}

$tags = $tagsInput !== '' ? array_map('trim', explode(',', $tagsInput)) : [];

$articleDir = article_path($category);
if (!is_dir($articleDir)) {
    mkdir($articleDir, 0755, true);
}

$imageDir = image_path($category . '/' . $slug);
if (!is_dir($imageDir)) {
    mkdir($imageDir, 0755, true);
}

$images = [];
$heroImage = null;
if (!empty($options['images'])) {
    $sources = array_filter(array_map('trim', explode(',', $options['images'])));
    foreach ($sources as $index => $source) {
        $filename = $index === 0 ? 'hero' : 'image-' . $index;
        $ext = pathinfo(parse_url($source, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $targetPath = $imageDir . '/' . $filename . '.' . $ext;

        if (filter_var($source, FILTER_VALIDATE_URL)) {
            $data = @file_get_contents($source);
            if ($data === false) {
                fwrite(STDERR, "Warning: failed to download {$source}\n");
                continue;
            }
            file_put_contents($targetPath, $data);
        } elseif (file_exists($source)) {
            copy($source, $targetPath);
        } else {
            fwrite(STDERR, "Warning: image source {$source} not found\n");
            continue;
        }

        $publicPath = '/images/' . $category . '/' . $slug . '/' . basename($targetPath);
        if ($index === 0) {
            $heroImage = $publicPath;
        } else {
            $images[] = $publicPath;
        }
    }
}

$metadata = [
    'title' => $title,
    'slug' => $slug,
    'category' => $category,
    'description' => $description,
    'tags' => $tags,
    'date' => $date,
    'author' => $author,
];

if ($heroImage) {
    $metadata['image'] = $heroImage;
}

if (!empty($images)) {
    $metadata['images'] = $images;
}

if ($related !== '') {
    $metadata['related'] = array_map('trim', explode(',', $related));
}

$metadata['seo'] = [
    'canonical' => rtrim(config('site_url'), '/') . '/' . $category . '/' . $slug,
    'keywords' => implode(', ', $tags),
];

$filename = sprintf('%s-%s.md', $date, $slug);
$path = $articleDir . '/' . $filename;

$body = trim($content);

if (!empty($images)) {
    $body .= "\n\n";
    foreach ($images as $image) {
        $body .= sprintf("![Изображение](%s)\n\n", $image);
    }
}

$fileContents = "---\n";
$fileContents .= FrontMatter::encode($metadata) . "\n";
$fileContents .= "---\n\n";
$fileContents .= $body . "\n";

file_put_contents($path, $fileContents);

echo "✅ Article created: {$path}\n";
echo "URL: " . rtrim(config('site_url'), '/') . '/' . $category . '/' . $slug . "\n";

echo "Remember to add supporting images to " . image_path($category . '/' . $slug) . " if you skipped downloads.\n";

function slugify(string $value): string
{
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
    $value = strtolower($transliterated ?: $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'article-' . uniqid();
}
