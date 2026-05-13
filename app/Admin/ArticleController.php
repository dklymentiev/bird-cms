<?php

declare(strict_types=1);

namespace App\Admin;

use App\Content\ArticleRepository;

/**
 * Admin controller for managing articles
 */
final class ArticleController extends Controller
{
    private ArticleRepository $articles;
    private array $categories;

    public function __construct()
    {
        parent::__construct();
        $this->articles = new ArticleRepository(
            dirname(__DIR__, 2) . '/content/articles'
        );
        $this->categories = \App\Support\Config::load('categories');
    }

    /**
     * List all articles with filters
     */
    public function index(): void
    {
        $this->requireAuth();

        // Get filter parameters
        $categoryFilter = $this->get('category', '');
        $statusFilter = $this->get('status', '');
        $typeFilter = $this->get('type', '');
        $search = trim($this->get('q', ''));
        $page = max(1, (int) $this->get('page', 1));
        $perPage = 50;

        // Get all articles including drafts for admin
        $allArticles = $this->articles->all(true);

        // Apply filters
        $filtered = array_filter($allArticles, function ($article) use ($categoryFilter, $statusFilter, $typeFilter, $search) {
            // Category filter
            if ($categoryFilter !== '' && ($article['category'] ?? '') !== $categoryFilter) {
                return false;
            }

            // Status filter (published = has date, draft = no date or status=draft)
            if ($statusFilter !== '') {
                $isDraft = ($article['status'] ?? 'published') === 'draft';
                if ($statusFilter === 'published' && $isDraft) {
                    return false;
                }
                if ($statusFilter === 'draft' && !$isDraft) {
                    return false;
                }
            }

            // Type filter
            if ($typeFilter !== '' && ($article['type'] ?? 'insight') !== $typeFilter) {
                return false;
            }

            // Search filter
            if ($search !== '') {
                $searchLower = strtolower($search);
                $title = strtolower($article['title'] ?? '');
                $description = strtolower($article['description'] ?? '');
                $tags = array_map('strtolower', $article['tags'] ?? []);

                $matchesTitle = str_contains($title, $searchLower);
                $matchesDescription = str_contains($description, $searchLower);
                $matchesTags = !empty(array_filter($tags, fn($t) => str_contains($t, $searchLower)));

                if (!$matchesTitle && !$matchesDescription && !$matchesTags) {
                    return false;
                }
            }

            return true;
        });

        // Pagination
        $total = count($filtered);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $articles = array_slice(array_values($filtered), $offset, $perPage);

        // Get unique types from all articles
        $types = array_unique(array_filter(array_column($allArticles, 'type')));
        sort($types);

        $this->render('articles/index', [
            'pageTitle' => 'Articles',
            'articles' => $articles,
            'categories' => $this->categories,
            'types' => $types,
            'filters' => [
                'category' => $categoryFilter,
                'status' => $statusFilter,
                'type' => $typeFilter,
                'q' => $search,
            ],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
            'flash' => $this->getFlash(),
        ]);
    }

    /**
     * Show single article (preview/edit mode)
     */
    public function show(string $category, string $slug): void
    {
        $this->requireAuth();

        $article = $this->articles->find($category, $slug, true);

        if ($article === null) {
            $this->flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
            return;
        }

        $this->render('articles/show', [
            'pageTitle' => $article['title'],
            'article' => $article,
            'flash' => $this->getFlash(),
        ]);
    }

    /**
     * Build URL with current filters
     */
    public static function buildFilterUrl(array $filters, array $override = []): string
    {
        $params = array_merge($filters, $override);
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);

        if (empty($params)) {
            return '/admin/articles';
        }

        return '/admin/articles?' . http_build_query($params);
    }

    /**
     * Publish an article (set status to published with current date)
     */
    public function publish(string $category, string $slug): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/articles');
            return;
        }

        $article = $this->articles->find($category, $slug, true);
        if ($article === null) {
            $this->flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
            return;
        }

        $filePath = $this->getArticlePath($category, $slug);
        if ($filePath && $this->updateArticleStatus($filePath, 'published')) {
            $this->flash('success', 'Article published successfully.');
        } else {
            $this->flash('error', 'Failed to publish article.');
        }

        $this->redirect('/admin/articles');
    }

    /**
     * Unpublish an article (set status to draft)
     */
    public function unpublish(string $category, string $slug): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/articles');
            return;
        }

        $article = $this->articles->find($category, $slug, true);
        if ($article === null) {
            $this->flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
            return;
        }

        $filePath = $this->getArticlePath($category, $slug);
        if ($filePath && $this->updateArticleStatus($filePath, 'draft')) {
            $this->flash('success', 'Article unpublished.');
        } else {
            $this->flash('error', 'Failed to unpublish article.');
        }

        $this->redirect('/admin/articles');
    }

    /**
     * Set publication date (auto-determines status: scheduled if future, published if past)
     */
    public function schedule(string $category, string $slug): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/articles');
            return;
        }

        $publishAt = $this->post('publish_at', '');
        if ($publishAt === '') {
            $this->flash('error', 'Publication date is required.');
            $this->redirect('/admin/articles');
            return;
        }

        $filePath = $this->getArticlePath($category, $slug);
        if (!$filePath || !file_exists($filePath)) {
            $this->flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
            return;
        }

        $content = file_get_contents($filePath);
        $publishTimestamp = strtotime($publishAt);
        $isFuture = $publishTimestamp > time();

        // Format datetime for frontmatter
        $publishAtFormatted = date('Y-m-d\TH:i:sP', $publishTimestamp);

        // Update or add publish_at
        if (preg_match('/^publish_at:\s*.+$/m', $content)) {
            $content = preg_replace('/^publish_at:\s*.+$/m', 'publish_at: ' . $publishAtFormatted, $content);
        } else {
            $content = preg_replace('/^(date:\s*.+)$/m', "$1\npublish_at: " . $publishAtFormatted, $content, 1);
        }

        // Update date field to match
        $dateFormatted = date('Y-m-d', $publishTimestamp);
        if (preg_match('/^date:\s*.+$/m', $content)) {
            $content = preg_replace('/^date:\s*.+$/m', 'date: ' . $dateFormatted, $content);
        }

        // Auto-determine status: scheduled if future, published if past/now
        $newStatus = $isFuture ? 'scheduled' : 'published';
        if (preg_match('/^status:\s*.+$/m', $content)) {
            $content = preg_replace('/^status:\s*.+$/m', 'status: ' . $newStatus, $content);
        } else {
            $content = preg_replace('/^(title:.+)$/m', "$1\nstatus: " . $newStatus, $content, 1);
        }

        if (file_put_contents($filePath, $content)) {
            if ($isFuture) {
                $this->flash('success', 'Scheduled for ' . date('M j, Y H:i', $publishTimestamp));
            } else {
                $this->flash('success', 'Published with date ' . $dateFormatted);
            }
        } else {
            $this->flash('error', 'Failed to update article.');
        }

        $this->redirect('/admin/articles');
    }

    /**
     * Duplicate an article
     */
    public function duplicate(string $category, string $slug): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/articles');
            return;
        }

        $sourcePath = $this->getArticlePath($category, $slug);
        if (!$sourcePath || !file_exists($sourcePath)) {
            $this->flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
            return;
        }

        // Generate new slug
        $newSlug = $slug . '-copy-' . date('Ymd-His');
        $newPath = dirname($sourcePath) . '/' . $newSlug . '.md';

        // Copy file
        $content = file_get_contents($sourcePath);

        // Update title in frontmatter to indicate it's a copy
        $content = preg_replace(
            '/^(title:\s*["\']?)(.+?)(["\']?\s*)$/m',
            '$1$2 (Copy)$3',
            $content,
            1
        );

        // Set as draft
        if (preg_match('/^status:\s*.+$/m', $content)) {
            $content = preg_replace('/^status:\s*.+$/m', 'status: draft', $content);
        } else {
            // Add status after title
            $content = preg_replace('/^(title:.+)$/m', "$1\nstatus: draft", $content, 1);
        }

        if (file_put_contents($newPath, $content)) {
            $this->flash('success', 'Article duplicated as draft.');
        } else {
            $this->flash('error', 'Failed to duplicate article.');
        }

        $this->redirect('/admin/articles');
    }

    /**
     * Delete an article
     */
    public function delete(string $category, string $slug): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/articles');
            return;
        }

        $filePath = $this->getArticlePath($category, $slug);
        if (!$filePath || !file_exists($filePath)) {
            $this->flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
            return;
        }

        // Move to trash instead of permanent delete
        $trashDir = dirname(__DIR__, 2) . '/storage/trash';
        if (!is_dir($trashDir)) {
            mkdir($trashDir, 0755, true);
        }

        $trashPath = $trashDir . '/' . $category . '-' . $slug . '-' . date('Ymd-His') . '.md';

        if (rename($filePath, $trashPath)) {
            $this->flash('success', 'Article moved to trash.');
        } else {
            $this->flash('error', 'Failed to delete article.');
        }

        $this->redirect('/admin/articles');
    }

    /**
     * Show create new article form
     */
    public function create(): void
    {
        $this->requireAuth();

        $this->render('articles/edit', [
            'pageTitle' => 'New Article',
            'article' => null,
            'categories' => $this->categories,
            'isNew' => true,
            'allTags' => $this->collectAllTags(),
            'flash' => $this->getFlash(),
        ]);
    }

    /**
     * Store new article
     */
    public function store(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/articles/new');
            return;
        }

        $category = $this->post('category', '');
        $slug = $this->sanitizeSlug($this->post('slug', ''));

        if ($category === '' || $slug === '') {
            $this->flash('error', 'Category and slug are required.');
            $this->redirect('/admin/articles/new');
            return;
        }

        // Check if article already exists
        if ($this->getArticlePath($category, $slug) !== null) {
            $this->flash('error', 'An article with this slug already exists in this category.');
            $this->redirect('/admin/articles/new');
            return;
        }

        // Build article content
        $content = $this->buildArticleContent();

        // Ensure category directory exists
        $categoryDir = dirname(__DIR__, 2) . '/content/articles/' . $category;
        if (!is_dir($categoryDir)) {
            mkdir($categoryDir, 0755, true);
        }

        $filePath = $categoryDir . '/' . $slug . '.md';

        if (file_put_contents($filePath, $content) !== false) {
            $this->flash('success', 'Article created successfully.');
            $this->redirect('/admin/articles/' . $category . '/' . $slug . '/edit');
        } else {
            $this->flash('error', 'Failed to create article.');
            $this->redirect('/admin/articles/new');
        }
    }

    /**
     * Show edit article form
     */
    public function edit(string $category, string $slug): void
    {
        $this->requireAuth();

        $filePath = $this->getArticlePath($category, $slug);
        if ($filePath === null) {
            $this->flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
            return;
        }

        $article = $this->articles->find($category, $slug, true);
        if ($article === null) {
            $this->flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
            return;
        }

        // Read raw content for editing
        $rawContent = file_get_contents($filePath);
        $article['content'] = $this->extractContent($rawContent);

        // Build a "view in new tab" URL for the preview button.
        // Drafts/scheduled get a signed token query so the public index.php
        // preview branch will serve them; published articles use the canonical
        // public URL straight.
        $status = strtolower((string)($article['status'] ?? 'draft'));
        if (in_array($status, ['draft', 'scheduled'], true)) {
            $expires    = time() + 3600;
            $secretKey  = config('app_key');
            $signSlug   = $category . '/' . $slug;
            $token      = hash_hmac('sha256', $signSlug . '|' . $expires, $secretKey);
            $previewUrl = '/' . rawurlencode($category) . '/' . rawurlencode($slug)
                . '?preview=1&token=' . $token . '&expires=' . $expires;
        } else {
            $previewUrl = '/' . rawurlencode($category) . '/' . rawurlencode($slug);
        }

        $this->render('articles/edit', [
            'pageTitle' => 'Edit: ' . $article['title'],
            'article' => $article,
            'categories' => $this->categories,
            'isNew' => false,
            'allTags' => $this->collectAllTags(),
            'previewUrl' => $previewUrl,
            'flash' => $this->getFlash(),
        ]);
    }

    /**
     * Distinct tag corpus across all articles, sorted by frequency desc.
     * Used by the editor's tag autocomplete via a <datalist>.
     *
     * @return list<string>
     */
    private function collectAllTags(): array
    {
        $counts = [];
        foreach ($this->articles->all(true) as $article) {
            foreach ((array)($article['tags'] ?? []) as $tag) {
                $tag = trim((string) $tag);
                if ($tag === '') continue;
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_keys($counts);
    }

    /**
     * Update existing article
     */
    public function update(string $category, string $slug): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/articles/' . $category . '/' . $slug . '/edit');
            return;
        }

        $filePath = $this->getArticlePath($category, $slug);
        if ($filePath === null) {
            $this->flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
            return;
        }

        $newCategory = $this->post('category', $category);
        $newSlug = $this->sanitizeSlug($this->post('slug', $slug));

        // Build updated content
        $content = $this->buildArticleContent();

        // Handle category/slug change
        if ($newCategory !== $category || $newSlug !== $slug) {
            // Ensure new category directory exists
            $newCategoryDir = dirname(__DIR__, 2) . '/content/articles/' . $newCategory;
            if (!is_dir($newCategoryDir)) {
                mkdir($newCategoryDir, 0755, true);
            }

            $newPath = $newCategoryDir . '/' . $newSlug . '.md';

            // Check if target already exists (and it's not the same file)
            if (file_exists($newPath) && realpath($newPath) !== realpath($filePath)) {
                $this->flash('error', 'An article with this slug already exists in the target category.');
                $this->redirect('/admin/articles/' . $category . '/' . $slug . '/edit');
                return;
            }

            // Write to new location
            if (file_put_contents($newPath, $content) !== false) {
                // Delete old file if different
                if (realpath($newPath) !== realpath($filePath)) {
                    unlink($filePath);
                }
                $this->flash('success', 'Article updated successfully.');
                $this->redirect('/admin/articles/' . $newCategory . '/' . $newSlug . '/edit');
            } else {
                $this->flash('error', 'Failed to update article.');
                $this->redirect('/admin/articles/' . $category . '/' . $slug . '/edit');
            }
        } else {
            // Same location, just update
            if (file_put_contents($filePath, $content) !== false) {
                $this->flash('success', 'Article updated successfully.');
            } else {
                $this->flash('error', 'Failed to update article.');
            }
            $this->redirect('/admin/articles/' . $category . '/' . $slug . '/edit');
        }
    }

    /**
     * Get the file path for an article
     */
    private function getArticlePath(string $category, string $slug): ?string
    {
        $basePath = dirname(__DIR__, 2) . '/content/articles/' . $category;

        // Flat format: content/articles/<cat>/<slug>.md
        $flat = $basePath . '/' . $slug . '.md';
        if (file_exists($flat)) {
            return $flat;
        }

        // Bundle format: content/articles/<cat>/<slug>/index.md (with sibling
        // meta.yaml, hero.webp, etc). The repository handles this shape end-
        // to-end; the admin controller was the last place still flat-only.
        $bundle = $basePath . '/' . $slug . '/index.md';
        if (file_exists($bundle)) {
            return $bundle;
        }

        return null;
    }

    /**
     * Update article status in frontmatter
     */
    private function updateArticleStatus(string $filePath, string $status): bool
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        if ($status === 'published') {
            // Set status to published (remove any draft/scheduled status)
            if (preg_match('/^status:\s*.+$/m', $content)) {
                $content = preg_replace('/^status:\s*.+$/m', 'status: published', $content);
            } else {
                $content = preg_replace('/^(title:.+)$/m', "$1\nstatus: published", $content, 1);
            }

            // Set publish_at to now (so scheduled articles become immediately visible)
            $now = date('Y-m-d\TH:i:sP');
            if (preg_match('/^publish_at:\s*.+$/m', $content)) {
                $content = preg_replace('/^publish_at:\s*.+$/m', 'publish_at: ' . $now, $content);
            }

            // Update date to today if publishing now
            $content = preg_replace('/^date:\s*.+$/m', 'date: ' . date('Y-m-d'), $content);

            // Add date if not present
            if (!preg_match('/^date:\s*.+$/m', $content)) {
                $content = preg_replace(
                    '/^(title:.+)$/m',
                    "$1\ndate: " . date('Y-m-d'),
                    $content,
                    1
                );
            }
        } else {
            // Set to draft
            if (preg_match('/^status:\s*.+$/m', $content)) {
                $content = preg_replace('/^status:\s*.+$/m', 'status: draft', $content);
            } else {
                $content = preg_replace('/^(title:.+)$/m', "$1\nstatus: draft", $content, 1);
            }
        }

        return file_put_contents($filePath, $content) !== false;
    }

    /**
     * Build article content from POST data
     */
    private function buildArticleContent(): string
    {
        $title = $this->post('title', 'Untitled');
        $slug = $this->sanitizeSlug($this->post('slug', ''));
        $category = $this->post('category', '');
        $type = $this->post('type', 'insight');
        $status = $this->post('status', 'draft');
        $description = $this->post('description', '');
        $date = $this->post('date', date('Y-m-d'));
        $tagsStr = $this->post('tags', '');
        $heroImage = $this->post('hero_image', '');
        $author = $this->post('author', 'editorial-team');
        $bodyContent = $this->post('content', '');

        // Parse tags
        $tags = array_filter(array_map('trim', explode(',', $tagsStr)));

        // Build frontmatter
        $frontmatter = "---\n";
        $frontmatter .= 'title: ' . $this->yamlEscape($title) . "\n";
        $frontmatter .= 'slug: ' . $this->yamlEscape($slug) . "\n";
        $frontmatter .= "category: $category\n";
        $frontmatter .= "type: $type\n";

        if ($status !== 'published') {
            $frontmatter .= "status: $status\n";
        }

        if ($description !== '') {
            $frontmatter .= 'description: ' . $this->yamlEscape($description) . "\n";
        }

        $frontmatter .= "date: $date\n";

        if (!empty($tags)) {
            $frontmatter .= "tags:\n";
            foreach ($tags as $tag) {
                $frontmatter .= "  - " . strtolower(trim($tag)) . "\n";
            }
        }

        $frontmatter .= "author: $author\n";

        if ($heroImage !== '') {
            $frontmatter .= 'hero_image: ' . $this->yamlEscape($heroImage) . "\n";
        }

        $frontmatter .= "---\n\n";

        return $frontmatter . $bodyContent;
    }

    /**
     * Extract body content from raw markdown (remove frontmatter)
     */
    private function extractContent(string $rawContent): string
    {
        // Match frontmatter delimiters
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $rawContent, $matches)) {
            return trim($matches[2]);
        }

        return $rawContent;
    }

    /**
     * Escape string for YAML (using single quotes)
     */
    private function yamlEscape(string $value): string
    {
        // Single-quoted strings only need single quotes escaped as ''
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
