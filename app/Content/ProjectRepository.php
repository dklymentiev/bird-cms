<?php

declare(strict_types=1);

namespace App\Content;

use App\Support\EditLog;
use App\Support\HtmlCache;
use App\Support\Markdown;

/**
 * Project Repository
 *
 * Manages portfolio projects stored as markdown files in content/projects/
 *
 * File structure:
 *   content/projects/
 *     project-slug.md       (with frontmatter)
 *     project-slug/         (bundle format)
 *       index.md
 *       hero.webp
 */
final class ProjectRepository implements ContentRepositoryInterface
{
    use AtomicMarkdownWrite;
    use ContentCache;

    public function __construct(private readonly string $projectsDir)
    {
    }

    /**
     * Persist a project from the URL Inventory editor.
     *
     * Projects use inline YAML frontmatter inside the .md (single-file
     * format) or the same inside a bundle's index.md. The save path
     * preserves whichever format the project already uses; new projects
     * default to single-file.
     *
     * @param array<string, mixed> $meta
     */
    public function save(string $slug, array $meta, string $body): void
    {
        $this->assertValidSlug($slug, 'slug');

        $bundleIndex = $this->projectsDir . '/' . $slug . '/index.md';
        $singleFile  = $this->projectsDir . '/' . $slug . '.md';

        $bodyPath = file_exists($bundleIndex) ? $bundleIndex : $singleFile;

        $contents = "---\n" . FrontMatter::encode($meta) . "\n---\n\n" . $body;
        $this->atomicWrite($bodyPath, $contents);

        $this->memoForget();
        $this->fsCacheForget('projects-index');

        // HTML cache invalidation. Project URLs follow the configured
        // content-type pattern (typically /projects/<slug>); the index
        // page (/projects) lists every project. The repository doesn't
        // know its URL pattern, so we forget the conventional shape and
        // let forgetByPrefix sweep any sub-tree variations.
        HtmlCache::forget('projects/' . $slug);
        HtmlCache::forget('projects');
        HtmlCache::forget('home');

        // Audit trail for the admin "Recent edits" card.
        EditLog::record(
            EditLog::$context ?? 'unknown',
            'save',
            '/projects/' . $slug,
            'project',
            $slug
        );
    }

    public function findByParams(array $params): ?array
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return null;
        }
        return $this->find($slug);
    }

    /**
     * Find a project by slug
     */
    public function find(string $slug): ?array
    {
        if (!$this->isValidSlug($slug)) {
            return null;
        }

        // Try bundle format first: project-slug/index.md
        $bundlePath = $this->projectsDir . '/' . $slug . '/index.md';
        if (file_exists($bundlePath)) {
            return $this->loadBundle($slug, $bundlePath);
        }

        // Try single file: project-slug.md
        $filePath = $this->projectsDir . '/' . $slug . '.md';
        if (file_exists($filePath)) {
            return $this->loadFile($slug, $filePath);
        }

        return null;
    }

    /**
     * Get all projects
     */
    public function all(): array
    {
        return $this->memo('all', function () {
            return $this->fsCache(
                'projects-index',
                [$this->projectsDir],
                fn() => $this->scanProjects()
            );
        });
    }

    /**
     * Raw assemble-from-disk path. Bundles win over single files when both
     * exist for the same slug. Stays separate so the cache wraps a pure
     * function.
     */
    private function scanProjects(): array
    {
        if (!is_dir($this->projectsDir)) {
            return [];
        }

        $projects = [];

        // Scan for bundle directories
        foreach (glob($this->projectsDir . '/*/index.md') as $indexFile) {
            $slug = basename(dirname($indexFile));
            $project = $this->loadBundle($slug, $indexFile);
            if ($project !== null) {
                $projects[] = $project;
            }
        }

        // Scan for single files
        foreach (glob($this->projectsDir . '/*.md') as $file) {
            $slug = basename($file, '.md');
            // Skip if already loaded as bundle
            if (isset($projects[$slug])) {
                continue;
            }
            $project = $this->loadFile($slug, $file);
            if ($project !== null) {
                $projects[] = $project;
            }
        }

        // Sort by order field, then by date
        usort($projects, static function ($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }
            return ($b['date'] ?? '') <=> ($a['date'] ?? '');
        });

        return $projects;
    }

    /**
     * Load project from bundle directory
     */
    private function loadBundle(string $slug, string $indexPath): ?array
    {
        $contents = file_get_contents($indexPath);
        if ($contents === false) {
            return null;
        }

        $parsed = FrontMatter::parseWithBody($contents);
        $meta = $parsed['meta'] ?? [];
        $body = $parsed['body'] ?? '';

        $bundleDir = dirname($indexPath);

        // Find hero image
        $heroImage = null;
        foreach (['hero.webp', 'hero.png', 'hero.jpg', 'screenshot.webp', 'screenshot.png'] as $heroFile) {
            if (file_exists($bundleDir . '/' . $heroFile)) {
                $heroImage = '/content/projects/' . $slug . '/' . $heroFile;
                break;
            }
        }

        return $this->buildProject($slug, $meta, $body, $heroImage);
    }

    /**
     * Load project from single markdown file
     */
    private function loadFile(string $slug, string $filePath): ?array
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $parsed = FrontMatter::parseWithBody($contents);
        $meta = $parsed['meta'] ?? [];
        $body = $parsed['body'] ?? '';

        return $this->buildProject($slug, $meta, $body, $meta['image'] ?? null);
    }

    /**
     * Build project array from parsed data
     */
    private function buildProject(string $slug, array $meta, string $body, ?string $image): array
    {
        // Explicit shape comes first; the spread ($meta) below adds any
        // remaining YAML fields (current, repo, stats, stack, screenshot,
        // social, subtitle, badge, related, placeholder, etc.) so themes
        // can read them directly without going through $project['meta'][...].
        // Using `+` (not array_merge) means explicit keys above always win.
        return [
            'slug' => $slug,
            'title' => $meta['title'] ?? ucfirst(str_replace('-', ' ', $slug)),
            'description' => $meta['description'] ?? '',
            'content' => $body,
            'html' => Markdown::toHtml($body),
            'image' => $image ?? $meta['image'] ?? null,
            'url' => $meta['url'] ?? null,
            'github' => $meta['github'] ?? null,
            'date' => $meta['date'] ?? null,
            'order' => $meta['order'] ?? 999,
            'tags' => $meta['tags'] ?? [],
            'tech' => $meta['tech'] ?? [],
            'featured' => $meta['featured'] ?? false,
            'status' => $meta['status'] ?? 'completed',
            'meta' => $meta,
        ] + $meta;
    }

    /**
     * Get featured projects
     */
    public function featured(int $limit = 4): array
    {
        $projects = array_filter($this->all(), static fn($p) => $p['featured'] === true);
        return array_slice($projects, 0, $limit);
    }

    /**
     * Validate slug format
     */
    private function isValidSlug(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[a-z0-9-]+$/', $slug) === 1;
    }
}
