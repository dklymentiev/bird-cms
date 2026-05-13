<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Common contract for content repositories used by ContentRouter and the
 * sitemap generator.
 *
 * Implementations expose all published content of one type as a flat list
 * of records, plus a parameter-driven lookup that maps URL pattern parameters
 * (extracted from config/content.php) back to a single record.
 *
 * This contract intentionally stays minimal so repositories can keep their
 * domain-specific helpers (e.g. ArticleRepository::inCategory(),
 * ServiceRepository::residential(), AreaRepository::findSubarea()) untouched.
 *
 * Record shape — every item returned by `all()` MUST be an associative array
 * containing at minimum:
 *   - 'slug'     => string      (required, valid slug)
 *   - 'lastmod'  => string|null  (optional ISO 8601 date for sitemap)
 *
 * Items MAY include any additional fields. Any key referenced as
 * `{name}` in the type's `url`, `index_url`, or `subarea_url` patterns
 * MUST be present on the item if that pattern is used to render the URL.
 *
 * Common URL pattern parameters and their record fields:
 *   /{slug}                  → 'slug'
 *   /{category}/{slug}       → 'slug', 'category'
 *   /{type}/{slug}           → 'slug', 'type'
 *   /areas/{parent}/{slug}   → 'slug', 'parent'
 *
 * Per-item sitemap overrides (optional):
 *   - 'sitemap_priority'   => string ('0.0' to '1.0')
 *   - 'sitemap_changefreq' => string
 * If absent, the type-level config values from config/content.php are used.
 */
interface ContentRepositoryInterface
{
    /**
     * Return all published content items as a flat list.
     *
     * Repositories that have hierarchical content (e.g. areas with subareas)
     * MUST flatten the hierarchy here, emitting each entity as a separate
     * record. Discriminating fields (e.g. 'parent', 'type') let
     * ContentRouter pick the right URL pattern.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Find a single item by URL parameters extracted from the matched
     * URL pattern.
     *
     * $params is the parsed parameter map produced by ContentRouter, e.g.:
     *   /{category}/{slug} → ['category' => 'devops', 'slug' => 'docker-tutorial']
     *   /{type}/{slug}     → ['type' => 'residential', 'slug' => 'house-cleaning']
     *   /{slug}            → ['slug' => 'about']
     *
     * Returns null when no item matches.
     *
     * @param array<string, string|null> $params
     * @return array<string, mixed>|null
     */
    public function findByParams(array $params): ?array;
}
