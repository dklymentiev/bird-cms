<?php

declare(strict_types=1);

namespace App\Support;

use App\Content\ArticleRepository;

/**
 * Filters internal links to unpublished/scheduled articles
 *
 * Removes or replaces links to articles that:
 * - Have a future publication date
 * - Are marked as draft
 * - Don't exist
 */
final class LinkFilter
{
    private ArticleRepository $repository;
    private array $publishedCache = [];

    public function __construct(ArticleRepository $repository)
    {
        $this->repository = $repository;
        $this->buildPublishedCache();
    }

    /**
     * Build cache of all published article URLs
     */
    private function buildPublishedCache(): void
    {
        $today = date('Y-m-d');
        $allArticles = $this->repository->all();

        foreach ($allArticles as $article) {
            $date = $article['date'] ?? '1970-01-01';
            $status = $article['status'] ?? 'published';

            // Skip future-dated or draft articles
            if ($date > $today || $status === 'draft') {
                continue;
            }

            // Build URL patterns this article responds to
            $category = $article['category'] ?? '';
            $slug = $article['slug'] ?? '';

            if ($category && $slug) {
                // Standard article URL
                $this->publishedCache["/{$category}/{$slug}"] = true;

                // Also cache with articles prefix if configured
                $prefix = config('articles_prefix', '');
                if ($prefix) {
                    $this->publishedCache["/{$prefix}/{$category}/{$slug}"] = true;
                }
            }
        }
    }

    /**
     * Check if a URL points to a published article
     */
    public function isPublished(string $url): bool
    {
        // Normalize URL
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $path = '/' . ltrim($path, '/');

        // External URLs are always "published"
        if (str_starts_with($url, 'http') && !str_contains($url, config('site_url', ''))) {
            return true;
        }

        // Check if it's in our published cache
        if (isset($this->publishedCache[$path])) {
            return true;
        }

        // Non-article internal links (services, pages, etc.) - assume published
        // Only filter /category/slug pattern links
        $segments = array_filter(explode('/', trim($path, '/')));
        if (count($segments) !== 2) {
            return true; // Not an article URL pattern
        }

        // Check if this looks like an article URL
        $possibleCategory = $segments[0] ?? '';
        $categories = $this->repository->categories();

        if (!in_array($possibleCategory, $categories, true)) {
            return true; // Not an article category, don't filter
        }

        // It's an article URL that's not in published cache = unpublished
        return false;
    }

    /**
     * Filter HTML content, removing links to unpublished articles
     *
     * @param string $html The HTML content to filter
     * @param string $mode 'remove' = remove link entirely, 'unlink' = keep text without link
     * @return string Filtered HTML
     */
    public function filter(string $html, string $mode = 'unlink'): string
    {
        // Match all anchor tags
        return preg_replace_callback(
            '/<a\s+([^>]*href=["\']([^"\']+)["\'][^>]*)>([^<]*)<\/a>/i',
            function ($matches) use ($mode) {
                $fullTag = $matches[0];
                $href = $matches[2];
                $linkText = $matches[3];

                if (!$this->isPublished($href)) {
                    // This link points to an unpublished article
                    if ($mode === 'remove') {
                        return ''; // Remove entirely
                    }
                    // 'unlink' mode - keep the text without the link
                    return $linkText;
                }

                return $fullTag; // Keep link as-is
            },
            $html
        );
    }

    /**
     * Get list of unpublished article URLs found in HTML
     */
    public function findUnpublishedLinks(string $html): array
    {
        $unpublished = [];

        preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);

        foreach ($matches[1] as $href) {
            if (!$this->isPublished($href)) {
                $unpublished[] = $href;
            }
        }

        return array_unique($unpublished);
    }
}
