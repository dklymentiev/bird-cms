<?php

declare(strict_types=1);

namespace App\Content;

use App\Support\EditLog;
use App\Support\HtmlCache;
use App\Support\Markdown;
use App\Support\ImageResolver;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class ArticleRepository implements ContentRepositoryInterface
{
    use AtomicMarkdownWrite;
    use ContentCache;

    private const SLUG_PATTERN = '/^[a-z0-9-]+$/';

    private DateTimeImmutable $now;

    private DateTimeZone $timezone;

    public function __construct(private readonly string $articlesDir)
    {
        $this->timezone = new DateTimeZone('UTC');
        $this->now = new DateTimeImmutable('now', $this->timezone);
    }

    public function all(bool $includeDrafts = false): array
    {
        $memoKey = 'all' . ($includeDrafts ? '_drafts' : '');

        return $this->memo($memoKey, function () use ($includeDrafts) {
            // includeDrafts=true is admin/preview-only and changes every time
            // content is touched; not worth a disk-cache entry of its own.
            // Published-only is the hot path that's worth caching.
            if ($includeDrafts) {
                return $this->loadAllArticles(true);
            }

            $cacheKey = 'articles-index';
            return $this->fsCache(
                $cacheKey,
                [$this->articlesDir],
                fn() => $this->loadAllArticles(false)
            );
        });
    }

    /**
     * Raw assemble-from-disk path. Called when the cache misses or is
     * disabled. Stays close to the original all() body so the cache layer
     * can't accidentally diverge from the no-cache behaviour.
     */
    private function loadAllArticles(bool $includeDrafts): array
    {
        $articles = [];
        foreach ($this->categorySlugs() as $category) {
            $articles = array_merge($articles, $this->getCategoryArticles($category, $includeDrafts));
        }

        usort($articles, static fn($a, $b) => strtotime($b['date'] ?? '1970-01-01') <=> strtotime($a['date'] ?? '1970-01-01'));

        return $articles;
    }

    public function latest(int $limit = 10): array
    {
        return array_slice($this->all(), 0, $limit);
    }

    public function categories(): array
    {
        return array_values(array_filter(
            $this->categorySlugs(),
            fn($slug) => $this->isValidSlug((string) $slug)
        ));
    }

    public function find(string $category, string $slug, bool $includeDrafts = false, ?string $subcategory = null): ?array
    {
        if (!$this->isValidSlug($category) || !$this->isValidSlug($slug)) {
            return null;
        }
        if ($subcategory !== null && !$this->isValidSlug($subcategory)) {
            return null;
        }

        $articles = $this->getCategoryArticles($category, $includeDrafts);
        foreach ($articles as $article) {
            if ($article['slug'] !== $slug) {
                continue;
            }
            $articleSub = $article['subcategory'] ?? null;
            // URL without subcategory must not reach a nested article -- a
            // /services/agent-dev request should 404 if the article actually
            // lives at /services/ai/agent-dev. Forces a canonical URL per
            // article instead of two paths to the same content.
            if ($subcategory === null && !empty($articleSub)) {
                continue;
            }
            if ($subcategory !== null && $articleSub !== $subcategory) {
                continue;
            }
            return $article;
        }

        return null;
    }

    public function findByParams(array $params): ?array
    {
        $category = (string) ($params['category'] ?? '');
        $slug = (string) ($params['slug'] ?? '');
        if ($category === '' || $slug === '') {
            return null;
        }
        $subcategory = $params['subcategory'] ?? null;
        if ($subcategory !== null) {
            $subcategory = (string) $subcategory;
            if ($subcategory === '') {
                $subcategory = null;
            }
        }
        return $this->find($category, $slug, false, $subcategory);
    }

    /**
     * Persist an article from the URL Inventory editor.
     *
     * Touches both the body file (.md) and the meta sidecar (.meta.yaml or
     * meta.yaml inside a bundle directory) atomically. The slug stays
     * fixed -- renaming a published URL is a separate operation that the
     * inventory editor doesn't expose.
     *
     * Detects the on-disk layout (flat vs bundle) and writes back into
     * whichever the article was loaded from. New articles default to flat
     * since that is what the MCP write_article tool emits.
     *
     * Invalidates the per-category cache so a subsequent find() in the
     * same request reflects the write.
     *
     * @param array<string, mixed> $meta Meta YAML keys (must include title)
     */
    public function save(string $category, string $slug, array $meta, string $body): void
    {
        $this->assertValidSlug($category, 'category');
        $this->assertValidSlug($slug, 'slug');

        // Slug stored in YAML stays consistent with the filename. Some
        // articles ship it explicitly; others rely on filename inference.
        // Keeping it in the meta makes round-tripping unambiguous.
        $meta['slug'] = $slug;

        $bundleIndex = $this->articlesDir . '/' . $category . '/' . $slug . '/index.md';
        $bundleMeta  = $this->articlesDir . '/' . $category . '/' . $slug . '/meta.yaml';
        $flatBody    = $this->articlesDir . '/' . $category . '/' . $slug . '.md';
        $flatMeta    = $this->articlesDir . '/' . $category . '/' . $slug . '.meta.yaml';

        if (file_exists($bundleIndex)) {
            $bodyPath = $bundleIndex;
            $metaPath = $bundleMeta;
        } else {
            $bodyPath = $flatBody;
            $metaPath = $flatMeta;
        }

        $this->atomicWrite($bodyPath, $body);
        $this->atomicWrite($metaPath, FrontMatter::encode($meta) . "\n");

        // Drop both cache tiers: a stale read here would silently hide the
        // just-saved title from the URL Inventory listing.
        $this->memoForget();
        $this->fsCacheForget('articles-index');

        // HTML cache invalidation: the article URL itself, the category
        // index that lists it, the homepage (latest feed), and llms.txt
        // (groups articles by type). Cheap idempotent unlinks; missing
        // entries are no-ops. articles_prefix is honoured so a site with
        // articles_prefix=articles forgets the /articles/<cat>/<slug> key.
        $articlesPrefix = trim((string) \config('articles_prefix', ''), '/');
        $articleKey = ($articlesPrefix !== '' ? $articlesPrefix . '/' : '')
            . $category . '/' . $slug;
        HtmlCache::forget($articleKey);
        HtmlCache::forget($category);
        HtmlCache::forget('home');
        HtmlCache::forget('llms.txt');

        // Audit trail for the admin "Recent edits" card. Source is whatever
        // the current request put in EditLog::$context (admin / api / mcp);
        // defaults to 'unknown' for direct/CLI callers. EditLog never
        // throws -- this call cannot fail the save.
        EditLog::record(
            EditLog::$context ?? 'unknown',
            'save',
            '/' . $articleKey,
            'article',
            $slug
        );
    }

    public function inCategory(string $category, int $limit = 10): array
    {
        if (!$this->isValidSlug($category)) {
            return [];
        }

        $articles = $this->getCategoryArticles($category);

        return array_slice($articles, 0, $limit);
    }

    public function related(array $tags, string $excludeSlug, int $limit = 3, ?string $category = null, ?string $type = null): array
    {
        $results = [];

        // Tier 1: Tag matching
        if (!empty($tags)) {
            $tags = array_map('strtolower', $tags);
            $tagMatches = [];

            foreach ($this->all() as $article) {
                if ($article['slug'] === $excludeSlug) {
                    continue;
                }
                $articleTags = array_map('strtolower', $article['tags']);
                $score = count(array_intersect($tags, $articleTags));
                if ($score > 0) {
                    $tagMatches[] = [$article, $score];
                }
            }

            usort($tagMatches, static fn($a, $b) => $b[1] <=> $a[1]);
            $results = array_map(static fn($item) => $item[0], $tagMatches);
        }

        // If we have enough results, return them
        if (count($results) >= $limit) {
            return array_slice($results, 0, $limit);
        }

        // Tier 2: Same category + same type (if provided)
        if ($category !== null && $type !== null) {
            $categoryTypeMatches = [];
            foreach ($this->getCategoryArticles($category) as $article) {
                if ($article['slug'] === $excludeSlug) {
                    continue;
                }
                if (strtolower($article['type'] ?? '') === strtolower($type)) {
                    // Skip if already in results
                    if (!in_array($article['slug'], array_column($results, 'slug'), true)) {
                        $categoryTypeMatches[] = $article;
                    }
                }
            }
            $results = array_merge($results, $categoryTypeMatches);
        }

        // If we have enough results, return them
        if (count($results) >= $limit) {
            return array_slice($results, 0, $limit);
        }

        // Tier 3: Same category (if provided)
        if ($category !== null) {
            foreach ($this->getCategoryArticles($category) as $article) {
                if ($article['slug'] === $excludeSlug) {
                    continue;
                }
                // Skip if already in results
                if (!in_array($article['slug'], array_column($results, 'slug'), true)) {
                    $results[] = $article;
                }
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($results, 0, $limit);
    }

    public function adjacent(string $category, string $slug): array
    {
        $articles = $this->getCategoryArticles($category);
        $count = count($articles);

        foreach ($articles as $index => $article) {
            if ($article['slug'] === $slug) {
                $previous = $index > 0 ? $articles[$index - 1] : null;
                $next = $index < $count - 1 ? $articles[$index + 1] : null;

                return ['previous' => $previous, 'next' => $next];
            }
        }

        return ['previous' => null, 'next' => null];
    }

    /**
     * Get related articles using pillar-cluster SEO strategy
     *
     * Logic:
     * - If current article is a cluster → include pillar + other clusters
     * - If current article is a pillar → include top clusters
     * - Falls back to tag/category matching
     *
     * @param array $article Current article data
     * @param int $limit Number of related articles to return
     * @return array Related articles
     */
    public function relatedPillarAware(array $article, int $limit = 3): array
    {
        $excludeSlug = $article['slug'];
        $category = $article['category'];
        $tags = $article['tags'] ?? [];
        $primary = strtolower($article['primary'] ?? '');

        // Determine if current article is a pillar
        $isPillar = $this->isPillarKeyword($primary);

        // Get all articles in same category
        $categoryArticles = $this->getCategoryArticles($category);

        // Find pillar(s) in category
        $pillars = [];
        $clusters = [];

        foreach ($categoryArticles as $a) {
            if ($a['slug'] === $excludeSlug) continue;

            $aPrimary = strtolower($a['primary'] ?? '');
            if ($this->isPillarKeyword($aPrimary)) {
                $pillars[] = $a;
            } else {
                $clusters[] = $a;
            }
        }

        $results = [];

        if ($isPillar) {
            // Current is pillar → show clusters first
            $results = array_slice($clusters, 0, $limit);
        } else {
            // Current is cluster → show pillar first, then other clusters
            if (!empty($pillars)) {
                $results[] = $pillars[0]; // Add main pillar
            }
            // Fill remaining with clusters
            foreach ($clusters as $cluster) {
                if (count($results) >= $limit) break;
                $results[] = $cluster;
            }
        }

        // If not enough, use tag-based related
        if (count($results) < $limit) {
            $tagRelated = $this->related($tags, $excludeSlug, $limit - count($results), $category);
            foreach ($tagRelated as $r) {
                if (!in_array($r['slug'], array_column($results, 'slug'), true)) {
                    $results[] = $r;
                    if (count($results) >= $limit) break;
                }
            }
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Check if keyword indicates a pillar page (broad topic, high search volume).
     *
     * Patterns are config-driven so non-English sites and non-blog content
     * types can supply their own. Default list left empty: in the engine,
     * everything is treated as non-pillar unless the operator opts in.
     */
    private function isPillarKeyword(string $primary): bool
    {
        if ($primary === '') {
            return false;
        }

        $patterns = (array) \config('seo.pillar_patterns', []);
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && @preg_match($pattern, $primary)) {
                return true;
            }
        }
        return false;
    }

    private function categorySlugs(): array
    {
        $configured = \config('categories', []);
        if (is_array($configured) && !empty($configured)) {
            return array_values(array_filter(
                array_keys($configured),
                fn($slug) => $this->isValidSlug((string) $slug)
            ));
        }

        return $this->directoryCategorySlugs();
    }

    private function directoryCategorySlugs(): array
    {
        if (!is_dir($this->articlesDir)) {
            return [];
        }

        $directories = glob($this->articlesDir . '/*', GLOB_ONLYDIR) ?: [];
        $slugs = array_map(static fn(string $dir) => basename($dir), $directories);
        $slugs = array_values(array_filter($slugs, fn($slug) => $this->isValidSlug($slug)));
        sort($slugs);

        return $slugs;
    }

    private function getCategoryArticles(string $category, bool $includeDrafts = false): array
    {
        if (!$this->isValidSlug($category)) {
            return [];
        }

        $memoKey = 'cat:' . $category . ($includeDrafts ? ':drafts' : '');
        return $this->memo($memoKey, function () use ($category, $includeDrafts) {
            $categoryDir = $this->articlesDir . '/' . $category;
            if (!is_dir($categoryDir)) {
                return [];
            }

            $articles = [];

            // 1. Scan for flat structure: category/*.md
            foreach (glob($categoryDir . '/*.md') as $file) {
                $article = $this->mapArticle($file, $category);
                if ($article !== null && ($includeDrafts || $this->isPublished($article))) {
                    $articles[] = $article;
                }
            }

            // 2. Scan for bundle structure: category/slug/index.md
            foreach (glob($categoryDir . '/*/index.md') as $file) {
                $article = $this->mapBundleArticle($file, $category);
                if ($article !== null && ($includeDrafts || $this->isPublished($article))) {
                    $articles[] = $article;
                }
            }

            // 3. Scan for nested-bundle structure: category/subcategory/slug/index.md.
            //    Capped at one level of nesting -- /{category}/{subcategory?}/{slug}
            //    is the router's URL grammar; deeper layouts have nowhere to be
            //    addressed and would only invite ambiguity (slug collision across
            //    subcategories, breadcrumb sprawl). Subcategory is derived from
            //    the directory name; any meta.subcategory in the file is
            //    overridden to keep filesystem and routing in lockstep.
            foreach (glob($categoryDir . '/*/*/index.md') as $file) {
                $article = $this->mapBundleArticle($file, $category, /* nested */ true);
                if ($article !== null && ($includeDrafts || $this->isPublished($article))) {
                    $articles[] = $article;
                }
            }

            usort($articles, static fn($a, $b) => strtotime($b['date'] ?? '1970-01-01') <=> strtotime($a['date'] ?? '1970-01-01'));

            return $articles;
        });
    }

    private function mapArticle(string $filePath, string $category): ?array
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException("Unable to read article: {$filePath}");
        }

        // Require .meta.yaml file
        $metaYamlPath = preg_replace('/\.md$/', '.meta.yaml', $filePath);
        if (!file_exists($metaYamlPath)) {
            return null; // Skip articles without meta file
        }
        $meta = $this->parseMetaYaml($metaYamlPath);
        $body = trim($contents);

        $slug = $meta['slug'] ?? $this->slugFromFilename($filePath);
        if (!$this->isValidSlug((string) $slug)) {
            return null;
        }
        $tags = $meta['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        $publishAtRaw = $meta['publish_at'] ?? null;
        $status = strtolower((string) ($meta['status'] ?? 'published'));

        $articleCategory = $meta['category'] ?? $category;
        if (!$this->isValidSlug((string) $articleCategory)) {
            $articleCategory = $category;
        }

        // Subcategory is optional. Null (not "general") so URL builders
        // can drop the segment from /{category}/{subcategory?}/{slug}.
        // Flat articles take subcategory only from meta -- there is no
        // directory hint to override with.
        $subcategoryMeta = $meta['subcategory'] ?? null;
        $subcategory = ($subcategoryMeta !== null && $this->isValidSlug((string) $subcategoryMeta))
            ? (string) $subcategoryMeta
            : null;

        $canonicalUrl = self::buildUrl($articleCategory, $slug, $subcategory);

        $type = $meta['type'] ?? 'insight';
        $featured = self::toBool($meta['featured'] ?? false);
        $trending = self::toBool($meta['trending'] ?? false);
        $deal = self::toBool($meta['deal'] ?? false);
        $priority = (int) ($meta['priority'] ?? 0);
        $readingTime = $meta['reading_time'] ?? null;

        $article = [
            'slug' => $slug,
            'title' => $meta['title'] ?? $slug,
            'description' => $meta['description'] ?? '',
            'category' => $articleCategory,
            'subcategory' => $subcategory,
            'date' => $meta['date'] ?? null,
            'tags' => $tags,
            'type' => $type,
            'status' => $status,
            'publish_at' => $publishAtRaw,
            'featured' => $featured,
            'trending' => $trending,
            'deal' => $deal,
            'priority' => $priority,
            'reading_time' => $readingTime,
            'hero_image' => $meta['hero_image'] ?? $meta['image'] ?? null,
            'images' => $meta['images'] ?? [],
            'meta' => $meta,
            'content' => $body,
            'path' => $filePath,
            'url' => $canonicalUrl,
        ];

        $article['meta']['canonical'] = $canonicalUrl;
        $article['meta']['publish_at'] = $publishAtRaw;
        $article['meta']['status'] = $status;
        $article['meta']['subcategory'] = $subcategory;
        $article['meta']['type'] = $type;
        $article['meta']['featured'] = $featured;
        $article['meta']['trending'] = $trending;
        $article['meta']['deal'] = $deal;
        $article['meta']['priority'] = $priority;
        $article['meta']['reading_time'] = $readingTime;

        $article['html'] = Markdown::toHtml($body);

        return $article;
    }

    /**
     * Map a bundle article (category/slug/index.md structure).
     *
     * $nested switches to category/subcategory/slug/index.md layout: the
     * extra path segment is treated as the subcategory and overrides any
     * meta.subcategory in the file (filesystem is source of truth, so a
     * mismatch can't silently desync the URL from the on-disk location).
     */
    private function mapBundleArticle(string $indexPath, string $category, bool $nested = false): ?array
    {
        $bundlePath = dirname($indexPath);
        $slug = basename($bundlePath);

        if (!$this->isValidSlug($slug)) {
            return null;
        }

        $subcategoryFromPath = null;
        if ($nested) {
            $subcategoryFromPath = basename(dirname($bundlePath));
            if (!$this->isValidSlug($subcategoryFromPath)) {
                return null;
            }
        }

        $contents = file_get_contents($indexPath);
        if ($contents === false) {
            throw new RuntimeException("Unable to read article: {$indexPath}");
        }

        // Check for meta.yaml in bundle
        $metaYamlPath = $bundlePath . '/meta.yaml';
        if (!file_exists($metaYamlPath)) {
            return null; // Skip bundles without meta file
        }

        $meta = $this->parseMetaYaml($metaYamlPath);
        $body = trim($contents);

        $tags = $meta['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        $publishAtRaw = $meta['publish_at'] ?? null;
        $status = strtolower((string) ($meta['status'] ?? 'published'));

        $articleCategory = $meta['category'] ?? $category;
        if (!$this->isValidSlug((string) $articleCategory)) {
            $articleCategory = $category;
        }

        if ($nested) {
            $subcategory = $subcategoryFromPath;
        } else {
            $subcategoryMeta = $meta['subcategory'] ?? null;
            $subcategory = ($subcategoryMeta !== null && $this->isValidSlug((string) $subcategoryMeta))
                ? (string) $subcategoryMeta
                : null;
        }

        $canonicalUrl = self::buildUrl($articleCategory, $slug, $subcategory);

        $type = $meta['type'] ?? 'insight';
        $featured = self::toBool($meta['featured'] ?? false);
        $trending = self::toBool($meta['trending'] ?? false);
        $deal = self::toBool($meta['deal'] ?? false);
        $priority = (int) ($meta['priority'] ?? 0);
        $readingTime = $meta['reading_time'] ?? null;

        // Auto-detect hero image in bundle
        $heroImage = $meta['hero_image'] ?? $meta['image'] ?? null;
        if ($heroImage === null) {
            $imageResolver = new ImageResolver();
            $imageResolver->setBundlePath($bundlePath);
            $heroImage = $imageResolver->findHeroInBundle();
        }

        // Resolve ./path to absolute URL path for templates
        $heroImage = $this->resolveHeroImagePath($heroImage, $bundlePath, $articleCategory, $slug);

        $article = [
            'slug' => $slug,
            'title' => $meta['title'] ?? $slug,
            'description' => $meta['description'] ?? '',
            'category' => $articleCategory,
            'subcategory' => $subcategory,
            'date' => $meta['date'] ?? null,
            'tags' => $tags,
            'type' => $type,
            'status' => $status,
            'publish_at' => $publishAtRaw,
            'featured' => $featured,
            'trending' => $trending,
            'deal' => $deal,
            'priority' => $priority,
            'reading_time' => $readingTime,
            'hero_image' => $heroImage,
            'images' => $meta['images'] ?? [],
            'meta' => $meta,
            'content' => $body,
            'path' => $indexPath,
            'bundle_path' => $bundlePath,  // New: bundle directory path
            'url' => $canonicalUrl,
        ];

        $article['meta']['canonical'] = $canonicalUrl;
        $article['meta']['publish_at'] = $publishAtRaw;
        $article['meta']['status'] = $status;
        $article['meta']['subcategory'] = $subcategory;
        $article['meta']['type'] = $type;
        $article['meta']['featured'] = $featured;
        $article['meta']['trending'] = $trending;
        $article['meta']['deal'] = $deal;
        $article['meta']['priority'] = $priority;
        $article['meta']['reading_time'] = $readingTime;

        // Render markdown with bundle context for image resolution
        $article['html'] = Markdown::toHtml($body, $bundlePath);

        return $article;
    }

    private function isPublished(array $article): bool
    {
        $status = strtolower((string) ($article['status'] ?? $article['meta']['status'] ?? 'published'));
        if ($status === 'draft') {
            return false;
        }

        // Check publish_at first, then fall back to date
        $publishAt = $this->parsePublishAt($article['publish_at'] ?? $article['meta']['publish_at'] ?? null);

        if ($publishAt === null) {
            // No publish_at - check date field (future date = scheduled)
            $publishAt = $this->parsePublishAt($article['date'] ?? $article['meta']['date'] ?? null);
        }

        if ($publishAt === null) {
            return $status !== 'scheduled';
        }

        if ($publishAt > $this->now) {
            return false;
        }

        return true;
    }

    private function parsePublishAt(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            try {
                return new DateTimeImmutable($value, $this->timezone);
            } catch (\Exception $e) {
                $date = DateTimeImmutable::createFromFormat('Y-m-d', $value, $this->timezone);
                if ($date !== false) {
                    return $date;
                }
            }
        }

        return null;
    }

    private function slugFromFilename(string $filePath): string
    {
        $filename = pathinfo($filePath, PATHINFO_FILENAME);

        // Strip a leading ISO-8601 date prefix if and only if it actually
        // matches YYYY-MM-DD. Pre-3.1 sites named files `2026-05-10-my-post.md`
        // for chronological sorting; the YYYY-MM-DD bit was redundant with the
        // meta.yaml `date` field. New writes (MCP, admin) use the bare slug
        // (e.g. `mcp-to-admin.md`); the old "always shift the first dash
        // segment" heuristic truncated those to `to-admin`. The parity tests
        // catch that round-trip; this conditional keeps backward compat for
        // dated files without mangling everything else.
        if (preg_match('/^\d{4}-\d{2}-\d{2}-(.+)$/', $filename, $m)) {
            return $m[1];
        }

        return $filename;
    }

    /**
     * Parse meta.yaml file into array
     */
    private function parseMetaYaml(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $result = FrontMatter::parse($content);

        // Parse FAQ separately (special format: - q: "..." a: "...")
        if (preg_match_all('/- q: "([^"]+)"\s+a: "([^"]+)"/', $content, $faqMatches, PREG_SET_ORDER)) {
            $result['faq'] = [];
            foreach ($faqMatches as $match) {
                $result['faq'][] = [
                    'q' => $match[1],
                    'a' => $match[2],
                ];
            }
        }

        return $result;
    }

    private static function buildUrl(string $category, string $slug, ?string $subcategory = null): string
    {
        $category = trim($category, '/');
        $slug = trim($slug, '/');

        $siteUrl = \config('site_url') ?: throw new \RuntimeException('site_url not configured');
        $base = rtrim((string) $siteUrl, '/') . '/' . $category;
        if ($subcategory !== null && $subcategory !== '') {
            $base .= '/' . trim($subcategory, '/');
        }
        return $base . '/' . $slug;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (bool) $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function isValidSlug(?string $value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        return preg_match(self::SLUG_PATTERN, $value) === 1;
    }

    /**
     * Resolve hero image path from bundle-relative (./hero.webp) to absolute URL path
     */
    private function resolveHeroImagePath(?string $heroImage, ?string $bundlePath, string $category, string $slug): ?string
    {
        if ($heroImage === null) {
            return null;
        }

        // Already absolute URL or path
        if (str_starts_with($heroImage, 'http') || str_starts_with($heroImage, '/')) {
            return $heroImage;
        }

        // Bundle-relative path: ./hero.webp -> /content/articles/category/slug/hero.webp
        if (str_starts_with($heroImage, './')) {
            $filename = substr($heroImage, 2);

            // Verify file exists in bundle
            if ($bundlePath !== null && file_exists($bundlePath . '/' . $filename)) {
                return '/content/articles/' . $category . '/' . $slug . '/' . $filename;
            }

            // File doesn't exist - return null to trigger fallback
            return null;
        }

        // Plain filename - assume it's in bundle
        if ($bundlePath !== null && file_exists($bundlePath . '/' . $heroImage)) {
            return '/content/articles/' . $category . '/' . $slug . '/' . $heroImage;
        }

        return $heroImage;
    }
}
