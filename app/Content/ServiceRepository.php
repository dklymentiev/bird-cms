<?php

declare(strict_types=1);

namespace App\Content;

use App\Support\EditLog;
use App\Support\HtmlCache;
use App\Support\Markdown;

/**
 * Repository for loading services from .md + .meta.yaml content files
 *
 * Supports both flat and bundle structures:
 *   Flat:   content/services/residential/house-cleaning.md + .meta.yaml
 *   Bundle: content/services/residential/house-cleaning/index.md + meta.yaml
 */
final class ServiceRepository implements ContentRepositoryInterface
{
    use AtomicMarkdownWrite;
    use ContentCache;

    public function __construct(private readonly string $servicesDir)
    {
    }

    /**
     * Persist a service from the URL Inventory editor.
     *
     * 'type' here is the URL pattern's first segment (residential /
     * commercial / etc.) and doubles as the on-disk subdirectory.
     * Layout follows the read path: bundle if it exists, flat otherwise.
     *
     * @param array<string, mixed> $meta
     */
    public function save(string $type, string $slug, array $meta, string $body): void
    {
        $this->assertValidSlug($type, 'type');
        $this->assertValidSlug($slug, 'slug');

        $meta['slug'] = $slug;

        $bundleIndex = $this->servicesDir . '/' . $type . '/' . $slug . '/index.md';
        $bundleMeta  = $this->servicesDir . '/' . $type . '/' . $slug . '/meta.yaml';
        $flatBody    = $this->servicesDir . '/' . $type . '/' . $slug . '.md';
        $flatMeta    = $this->servicesDir . '/' . $type . '/' . $slug . '.meta.yaml';

        if (file_exists($bundleIndex)) {
            $bodyPath = $bundleIndex;
            $metaPath = $bundleMeta;
        } else {
            $bodyPath = $flatBody;
            $metaPath = $flatMeta;
        }

        $this->atomicWrite($bodyPath, $body);
        $this->atomicWrite($metaPath, FrontMatter::encode($meta) . "\n");

        // Both tiers: the just-written type's list and any whole-repository
        // aggregate (all(), allGrouped()) would otherwise serve a stale row.
        $this->memoForget();
        $this->fsCacheForget('services-' . $type);

        // HTML cache invalidation. Service URLs follow the configured
        // content-type pattern (typically /services/<type>/<slug> or
        // /<type>/<slug> depending on config); forget every shape we know
        // about so a stale render never outlives the save.
        HtmlCache::forget('services/' . $type . '/' . $slug);
        HtmlCache::forget($type . '/' . $slug);
        HtmlCache::forget('services');
        HtmlCache::forget($type);
        HtmlCache::forget('home');

        // Audit trail for the admin "Recent edits" card. Services live at
        // /<type>/<slug> in most site configs (the configurable prefix is
        // dropped to keep the dashboard URL short and unambiguous).
        EditLog::record(
            EditLog::$context ?? 'unknown',
            'save',
            '/' . $type . '/' . $slug,
            'service',
            $slug
        );
    }

    /**
     * Get all services of a specific type (residential/commercial)
     */
    public function byType(string $type): array
    {
        return $this->memo('type:' . $type, function () use ($type) {
            return $this->fsCache(
                'services-' . $type,
                [$this->servicesDir . '/' . $type],
                fn() => $this->loadType($type)
            );
        });
    }

    /**
     * Raw assemble-from-disk path for one service type. Stays as the
     * un-cached version of byType() so the cache layer can't drift.
     */
    private function loadType(string $type): array
    {
        $dir = $this->servicesDir . '/' . $type;
        if (!is_dir($dir)) {
            return [];
        }

        $services = [];

        // 1. Scan for flat structure: type/*.meta.yaml
        $metaFiles = glob($dir . '/*.meta.yaml');
        foreach ($metaFiles as $metaFile) {
            $service = $this->parseServiceFiles($metaFile, $type);
            if ($service !== null) {
                $services[$service['slug']] = $service;
            }
        }

        // 2. Scan for bundle structure: type/slug/meta.yaml
        $bundleDirs = glob($dir . '/*/meta.yaml');
        foreach ($bundleDirs as $bundleMetaFile) {
            $service = $this->parseBundleService($bundleMetaFile, $type);
            if ($service !== null) {
                $services[$service['slug']] = $service;
            }
        }

        // Sort by priority (higher first), then by title
        uasort($services, static function ($a, $b) {
            $priorityDiff = ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }
            return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
        });

        return $services;
    }

    /**
     * Find a service by type and slug
     */
    public function find(string $type, string $slug): ?array
    {
        $services = $this->byType($type);
        return $services[$slug] ?? null;
    }

    /**
     * Get all residential services
     */
    public function residential(): array
    {
        return $this->byType('residential');
    }

    /**
     * Get all commercial services
     */
    public function commercial(): array
    {
        return $this->byType('commercial');
    }

    /**
     * Get all services as a flat list, residential first then commercial.
     *
     * Each record carries its own 'type' field, so consumers needing the
     * grouped shape can re-bucket via array_filter on $service['type'].
     */
    public function all(): array
    {
        return array_merge(
            array_values($this->residential()),
            array_values($this->commercial()),
        );
    }

    /**
     * Get all services grouped by type. Convenience for views/controllers
     * that want the legacy ['residential' => [...], 'commercial' => [...]]
     * shape without rebucketing manually.
     *
     * @return array{residential: array, commercial: array}
     */
    public function allGrouped(): array
    {
        return [
            'residential' => $this->residential(),
            'commercial' => $this->commercial(),
        ];
    }

    public function findByParams(array $params): ?array
    {
        $type = (string) ($params['type'] ?? '');
        $slug = (string) ($params['slug'] ?? '');
        if ($type === '' || $slug === '') {
            return null;
        }
        return $this->find($type, $slug);
    }

    /**
     * Parse service from .meta.yaml and corresponding .md file
     */
    private function parseServiceFiles(string $metaFilePath, string $type): ?array
    {
        // Load meta.yaml
        $metaContents = file_get_contents($metaFilePath);
        if ($metaContents === false) {
            return null;
        }

        $meta = FrontMatter::parse($metaContents);
        if (empty($meta['title']) || empty($meta['slug'])) {
            return null;
        }

        // Load corresponding .md file
        $mdFilePath = preg_replace('/\.meta\.yaml$/', '.md', $metaFilePath);
        $body = '';
        if (file_exists($mdFilePath)) {
            $body = file_get_contents($mdFilePath) ?: '';
        }

        // Parse body sections
        $sections = $this->parseBodySections($body);

        return [
            'slug' => $meta['slug'],
            'title' => $meta['title'],
            'description' => $meta['description'] ?? '',
            'hero_text' => $meta['hero_text'] ?? $meta['description'] ?? '',
            'hero_image' => $meta['hero_image'] ?? null,
            'type' => $type,
            'priority' => $meta['priority'] ?? 0,
            'features' => $meta['features'] ?? [],
            'included' => $meta['included'] ?? [],
            'pricing' => $meta['pricing'] ?? [],
            'faqs' => $meta['faqs'] ?? [],
            'keywords' => $meta['keywords'] ?? [],
            'schema' => $meta['schema'] ?? 'Service',
            'content' => $body,
            'html' => !empty($body) ? Markdown::toHtml($body) : '',
            'sections' => $sections,
            'meta' => $meta,
        ];
    }

    /**
     * Parse service from bundle structure (slug/meta.yaml + slug/index.md)
     */
    private function parseBundleService(string $bundleMetaPath, string $type): ?array
    {
        $bundleDir = dirname($bundleMetaPath);
        $slug = basename($bundleDir);

        // Load meta.yaml
        $metaContents = file_get_contents($bundleMetaPath);
        if ($metaContents === false) {
            return null;
        }

        $meta = FrontMatter::parse($metaContents);
        if (empty($meta['title'])) {
            return null;
        }

        // Use directory name as slug if not in meta
        $slug = $meta['slug'] ?? $slug;

        // Load index.md if exists
        $mdFilePath = $bundleDir . '/index.md';
        $body = '';
        if (file_exists($mdFilePath)) {
            $body = file_get_contents($mdFilePath) ?: '';
        }

        // Resolve hero_image path
        $heroImage = $this->resolveHeroImagePath($meta['hero_image'] ?? null, $bundleDir, $type, $slug);

        // Parse body sections
        $sections = $this->parseBodySections($body);

        return [
            'slug' => $slug,
            'title' => $meta['title'],
            'description' => $meta['description'] ?? '',
            'hero_text' => $meta['hero_text'] ?? $meta['description'] ?? '',
            'hero_image' => $heroImage,
            'type' => $type,
            'priority' => $meta['priority'] ?? 0,
            'features' => $meta['features'] ?? [],
            'included' => $meta['included'] ?? [],
            'pricing' => $meta['pricing'] ?? [],
            'faqs' => $meta['faqs'] ?? [],
            'keywords' => $meta['keywords'] ?? [],
            'schema' => $meta['schema'] ?? 'Service',
            'content' => $body,
            'html' => !empty($body) ? Markdown::toHtml($body) : '',
            'sections' => $sections,
            'meta' => $meta,
        ];
    }

    /**
     * Resolve hero image path for bundle services
     */
    private function resolveHeroImagePath(?string $heroImage, string $bundleDir, string $type, string $slug): ?string
    {
        if ($heroImage === null) {
            return null;
        }

        // Already absolute URL or path
        if (str_starts_with($heroImage, 'http') || str_starts_with($heroImage, '/')) {
            return $heroImage;
        }

        // Relative path like ./hero.webp
        if (str_starts_with($heroImage, './')) {
            $filename = substr($heroImage, 2);
            if (file_exists($bundleDir . '/' . $filename)) {
                return '/content/services/' . $type . '/' . $slug . '/' . $filename;
            }
            return null;
        }

        // Just filename like hero.webp
        if (file_exists($bundleDir . '/' . $heroImage)) {
            return '/content/services/' . $type . '/' . $slug . '/' . $heroImage;
        }

        return $heroImage;
    }

    /**
     * Parse markdown body into sections (for custom layouts)
     */
    private function parseBodySections(string $body): array
    {
        $sections = [];
        $currentSection = null;
        $currentContent = '';

        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            // Check for h2 headers (## Section Name)
            if (preg_match('/^##\s+(.+)$/', $line, $matches)) {
                // Save previous section
                if ($currentSection !== null) {
                    $sections[$currentSection] = trim($currentContent);
                }
                $currentSection = $this->slugify($matches[1]);
                $currentContent = '';
            } else {
                $currentContent .= $line . "\n";
            }
        }

        // Save last section
        if ($currentSection !== null) {
            $sections[$currentSection] = trim($currentContent);
        }

        return $sections;
    }

    /**
     * Convert string to slug
     */
    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
