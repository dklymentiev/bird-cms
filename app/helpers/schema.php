<?php
/**
 * Short global schema helpers.
 *
 * Thin discoverable wrappers around App\Support\SchemaGenerator. The class API
 * remains the canonical entry point; these are sugar for theme code that wants
 * a one-liner inside a view.
 *
 * Loaded by composer autoload "files" (see composer.json).
 */

declare(strict_types=1);

use App\Support\SchemaGenerator;

if (!function_exists('schema_article')) {
    /**
     * Generate type-specific JSON-LD schema(s) for an article.
     * Returns an array of schemas (Article + optional FAQ + optional BreadcrumbList).
     */
    function schema_article(array $article, array $meta = []): array
    {
        return SchemaGenerator::generate($article, $meta);
    }
}

if (!function_exists('schema_faq')) {
    /**
     * Build a FAQPage schema from an array of [question, answer] items.
     */
    function schema_faq(array $faq): ?array
    {
        return SchemaGenerator::buildFAQSchema($faq);
    }
}

if (!function_exists('schema_breadcrumb')) {
    /**
     * Build a BreadcrumbList schema from items shaped {label|name|title, url}.
     * Relative URLs are resolved against config('site_url').
     */
    function schema_breadcrumb(array $items): ?array
    {
        return SchemaGenerator::buildBreadcrumbSchema($items);
    }
}

if (!function_exists('schema_render')) {
    /**
     * Convenience: render an array of schemas as <script type="application/ld+json"> tags.
     */
    function schema_render(array $schemas): string
    {
        return SchemaGenerator::render($schemas);
    }
}
