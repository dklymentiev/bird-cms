<?php

declare(strict_types=1);

namespace App\Http;

use App\Content\ArticleRepository;

/**
 * Content collection strategies for homepage sections.
 *
 * Each method implements a specific collection strategy that can be
 * configured via config/home.php. All collectors track used slugs
 * to prevent duplicate articles across sections.
 */
class ContentCollectors
{
    private ArticleRepository $repository;

    /** @var array<string, bool> Track used article slugs */
    private array $usedSlugs = [];

    public function __construct(ArticleRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get the current used slugs array (for reference).
     *
     * @return array<string, bool>
     */
    public function getUsedSlugs(): array
    {
        return $this->usedSlugs;
    }

    /**
     * Reset used slugs tracking.
     */
    public function resetUsedSlugs(): void
    {
        $this->usedSlugs = [];
    }

    /**
     * Collect latest articles with deduplication.
     *
     * Config options:
     * - limit: number of articles to collect (default: 0)
     * - offset: skip first N articles (default: 0)
     * - mark_used: track these articles as used (default: true)
     *
     * @param array $config Collection configuration
     * @param int $extra Extra pool size for filtering (default: 24)
     * @return array<int, array>
     */
    public function collectLatest(array $config, int $extra = 24): array
    {
        $limit = max(0, (int) ($config['limit'] ?? 0));
        if ($limit === 0) {
            return [];
        }

        $offset = max(0, (int) ($config['offset'] ?? 0));
        $markUsed = $config['mark_used'] ?? true;

        $poolSize = $offset + $limit + $extra;
        $pool = $this->repository->latest($poolSize);
        $results = [];
        $localSeen = [];
        $index = 0;

        foreach ($pool as $article) {
            $slug = $article['slug'] ?? null;
            if ($index < $offset) {
                $index++;
                continue;
            }
            if ($slug === null) {
                continue;
            }
            if ($markUsed && isset($this->usedSlugs[$slug])) {
                continue;
            }
            if (isset($localSeen[$slug])) {
                continue;
            }
            $results[] = $article;
            $localSeen[$slug] = true;
            if ($markUsed) {
                $this->usedSlugs[$slug] = true;
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Collect articles from multiple categories in sequence.
     *
     * Config options:
     * - categories: array of category slugs
     * - per_category: articles per category (default: 3)
     * - total: total limit across all categories
     *
     * Falls back to latest articles if not enough found.
     *
     * @param array $config Collection configuration
     * @return array<int, array>
     */
    public function collectCategorySequence(array $config): array
    {
        $categories = (array) ($config['categories'] ?? []);
        $perCategory = max(1, (int) ($config['per_category'] ?? 3));
        $total = max(1, (int) ($config['total'] ?? count($categories) * $perCategory));

        $results = [];
        foreach ($categories as $category) {
            foreach ($this->repository->inCategory($category, $perCategory) as $article) {
                $slug = $article['slug'] ?? null;
                if ($slug === null || isset($this->usedSlugs[$slug])) {
                    continue;
                }
                $results[] = $article;
                $this->usedSlugs[$slug] = true;
                if (count($results) >= $total) {
                    break 2;
                }
            }
        }

        // Fallback to latest if not enough
        if (count($results) < $total) {
            $remaining = $total - count($results);
            $fallbackConfig = [
                'limit' => $remaining,
                'offset' => 0,
                'mark_used' => true,
            ];
            $fallback = $this->collectLatest($fallbackConfig);
            $results = array_merge($results, $fallback);
        }

        return $results;
    }

    /**
     * Collect articles from specified categories (pooled).
     *
     * Config options:
     * - categories: array of category slugs
     * - limit: total articles to return (default: 6)
     * - per_category: articles to fetch per category (default: 3)
     *
     * @param array $config Collection configuration
     * @return array<int, array>
     */
    public function collectCategories(array $config): array
    {
        $categories = (array) ($config['categories'] ?? []);
        $limit = max(1, (int) ($config['limit'] ?? 6));
        $perCategory = max(1, (int) ($config['per_category'] ?? 3));

        $pool = [];
        foreach ($categories as $category) {
            foreach ($this->repository->inCategory($category, $perCategory) as $article) {
                $slug = $article['slug'] ?? null;
                if ($slug === null || isset($pool[$slug]) || isset($this->usedSlugs[$slug])) {
                    continue;
                }
                $pool[$slug] = $article;
            }
        }

        $results = array_slice(array_values($pool), 0, $limit);
        foreach ($results as $article) {
            if (!empty($article['slug'])) {
                $this->usedSlugs[$article['slug']] = true;
            }
        }

        return $results;
    }

    /**
     * Collect articles from a single category.
     *
     * Config options:
     * - category: category slug (required)
     * - limit: number of articles (default: 4)
     * - mark_used: track as used (default: true)
     *
     * @param array $config Collection configuration
     * @return array<int, array>
     */
    public function collectCategorySingle(array $config): array
    {
        $category = (string) ($config['category'] ?? '');
        if ($category === '') {
            return [];
        }

        $limit = max(1, (int) ($config['limit'] ?? 4));
        $markUsed = $config['mark_used'] ?? true;
        $results = [];

        foreach ($this->repository->inCategory($category, $limit * 3) as $article) {
            $slug = $article['slug'] ?? null;
            if ($slug === null) {
                continue;
            }
            if ($markUsed && isset($this->usedSlugs[$slug])) {
                continue;
            }
            $results[] = $article;
            if ($markUsed) {
                $this->usedSlugs[$slug] = true;
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Collect articles by content type.
     *
     * Config options:
     * - types: array of type slugs (e.g., ['review', 'comparison'])
     * - limit: number of articles (default: 4)
     * - mark_used: track as used (default: true)
     *
     * @param array $config Collection configuration
     * @return array<int, array>
     */
    public function collectByType(array $config): array
    {
        $types = array_map(
            static fn($type) => strtolower((string) $type),
            (array) ($config['types'] ?? [])
        );
        $types = array_filter($types);

        if (empty($types)) {
            return [];
        }

        $limit = max(1, (int) ($config['limit'] ?? 4));
        $markUsed = $config['mark_used'] ?? true;
        $results = [];

        foreach ($this->repository->all() as $article) {
            $slug = $article['slug'] ?? null;
            if ($slug === null) {
                continue;
            }
            if ($markUsed && isset($this->usedSlugs[$slug])) {
                continue;
            }
            $type = strtolower((string) ($article['type'] ?? ''));
            if (!in_array($type, $types, true)) {
                continue;
            }
            $results[] = $article;
            if ($markUsed) {
                $this->usedSlugs[$slug] = true;
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Collect articles by boolean flag.
     *
     * Config options:
     * - flag: article property name to check (e.g., 'featured', 'has_deal')
     * - limit: number of articles (default: 5)
     * - mark_used: track as used (default: false)
     *
     * @param array $config Collection configuration
     * @return array<int, array>
     */
    public function collectByFlag(array $config): array
    {
        $flag = (string) ($config['flag'] ?? '');
        if ($flag === '') {
            return [];
        }

        $limit = max(1, (int) ($config['limit'] ?? 5));
        $markUsed = $config['mark_used'] ?? false;
        $results = [];

        foreach ($this->repository->all() as $article) {
            $slug = $article['slug'] ?? null;
            if ($slug === null) {
                continue;
            }
            if ($markUsed && isset($this->usedSlugs[$slug])) {
                continue;
            }
            $flagValue = $article[$flag] ?? false;
            if (!$flagValue) {
                continue;
            }
            $results[] = $article;
            if ($markUsed) {
                $this->usedSlugs[$slug] = true;
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Collect articles organized into columns (for multi-column layouts).
     *
     * Config options:
     * - category: source category slug (required)
     * - columns: array of column definitions, each with:
     *   - label: column heading
     *   - limit: articles per column (default: 2)
     *
     * @param array $config Collection configuration
     * @return array<int, array{label: string, articles: array}>
     */
    public function collectCategoryColumns(array $config): array
    {
        $category = (string) ($config['category'] ?? '');
        if ($category === '') {
            return [];
        }

        $columns = (array) ($config['columns'] ?? []);
        $resultColumns = [];

        foreach ($columns as $column) {
            $label = (string) ($column['label'] ?? '');
            $limit = max(1, (int) ($column['limit'] ?? 2));
            $items = [];

            foreach ($this->repository->inCategory($category, $limit * 3) as $article) {
                $slug = $article['slug'] ?? null;
                if ($slug === null || isset($this->usedSlugs[$slug])) {
                    continue;
                }
                $items[] = $article;
                $this->usedSlugs[$slug] = true;
                if (count($items) >= $limit) {
                    break;
                }
            }

            $resultColumns[] = [
                'label' => $label,
                'articles' => $items,
            ];
        }

        return $resultColumns;
    }

    /**
     * Collect category highlights (articles grouped by category).
     *
     * @param array<int, string> $categories List of category slugs
     * @param int $perCategory Articles per category (default: 3)
     * @return array<string, array<int, array>>
     */
    public function collectCategoryHighlights(array $categories, int $perCategory = 3): array
    {
        $highlights = [];

        foreach ($categories as $category) {
            $items = $this->repository->inCategory($category, $perCategory);
            if (!empty($items)) {
                $highlights[$category] = $items;
            }
        }

        return $highlights;
    }
}
