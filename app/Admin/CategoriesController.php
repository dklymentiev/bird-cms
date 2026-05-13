<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Admin controller for managing categories
 */
final class CategoriesController extends Controller
{
    private string $configPath;
    private array $categories;

    public function __construct()
    {
        parent::__construct();
        $this->configPath = \App\Support\Config::writePath('categories');
        $this->categories = \App\Support\Config::load('categories');
    }

    /**
     * List all categories
     */
    public function index(): void
    {
        $this->requireAuth();

        // Count articles per category. Bird CMS supports two on-disk shapes:
        //   - flat:   content/articles/<cat>/<slug>.md
        //   - bundle: content/articles/<cat>/<slug>/index.md  (with meta.yaml + assets)
        // Counting only flat .md misses every bundle-format article; sites that
        // standardised on bundles read as "0 articles" here even when the
        // category is full. Sum both shapes.
        $articlesPath = dirname(__DIR__, 2) . '/content/articles';
        $counts = [];

        foreach (array_keys($this->categories) as $slug) {
            $categoryPath = $articlesPath . '/' . $slug;
            if (is_dir($categoryPath)) {
                $flat   = count(glob($categoryPath . '/*.md') ?: []);
                $bundle = count(glob($categoryPath . '/*/index.md') ?: []);
                $counts[$slug] = $flat + $bundle;
            } else {
                $counts[$slug] = 0;
            }
        }

        $this->render('categories/index', [
            'pageTitle' => 'Categories',
            'categories' => $this->categories,
            'articleCounts' => $counts,
            'flash' => $this->getFlash(),
        ]);
    }

    /**
     * Show edit form for a category
     */
    public function edit(string $slug): void
    {
        $this->requireAuth();

        if (!isset($this->categories[$slug])) {
            $this->flash('error', 'Category not found.');
            $this->redirect('/admin/categories');
            return;
        }

        $this->render('categories/edit', [
            'pageTitle' => 'Edit Category: ' . $this->categories[$slug]['title'],
            'slug' => $slug,
            'category' => $this->categories[$slug],
            'isNew' => false,
            'flash' => $this->getFlash(),
        ]);
    }

    /**
     * Show create form for new category
     */
    public function create(): void
    {
        $this->requireAuth();

        $this->render('categories/edit', [
            'pageTitle' => 'New Category',
            'slug' => '',
            'category' => [
                'title' => '',
                'description' => '',
                'icon' => 'folder',
                'subcategories' => [
                    'general' => ['title' => 'General'],
                ],
            ],
            'isNew' => true,
            'flash' => $this->getFlash(),
        ]);
    }

    /**
     * Store new category
     */
    public function store(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/categories/new');
            return;
        }

        $slug = $this->sanitizeSlug($this->post('slug', ''));

        if ($slug === '') {
            $this->flash('error', 'Slug is required.');
            $this->redirect('/admin/categories/new');
            return;
        }

        if (isset($this->categories[$slug])) {
            $this->flash('error', 'A category with this slug already exists.');
            $this->redirect('/admin/categories/new');
            return;
        }

        $categoryData = $this->buildCategoryData();
        $this->categories[$slug] = $categoryData;

        if ($this->saveCategories()) {
            // Create category directory
            $categoryDir = dirname(__DIR__, 2) . '/content/articles/' . $slug;
            if (!is_dir($categoryDir)) {
                mkdir($categoryDir, 0755, true);
            }

            $this->flash('success', 'Category created successfully.');
            $this->redirect('/admin/categories/' . $slug . '/edit');
        } else {
            $this->flash('error', 'Failed to save category.');
            $this->redirect('/admin/categories/new');
        }
    }

    /**
     * Update existing category
     */
    public function update(string $slug): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/categories/' . $slug . '/edit');
            return;
        }

        if (!isset($this->categories[$slug])) {
            $this->flash('error', 'Category not found.');
            $this->redirect('/admin/categories');
            return;
        }

        $newSlug = $this->sanitizeSlug($this->post('slug', $slug));
        $categoryData = $this->buildCategoryData();

        // Handle slug change
        if ($newSlug !== $slug) {
            if (isset($this->categories[$newSlug])) {
                $this->flash('error', 'A category with this slug already exists.');
                $this->redirect('/admin/categories/' . $slug . '/edit');
                return;
            }

            // Rename directory if it exists
            $oldDir = dirname(__DIR__, 2) . '/content/articles/' . $slug;
            $newDir = dirname(__DIR__, 2) . '/content/articles/' . $newSlug;

            if (is_dir($oldDir)) {
                rename($oldDir, $newDir);
            }

            // Update categories array
            unset($this->categories[$slug]);
            $this->categories[$newSlug] = $categoryData;
        } else {
            $this->categories[$slug] = $categoryData;
        }

        if ($this->saveCategories()) {
            $this->flash('success', 'Category updated successfully.');
            $this->redirect('/admin/categories/' . $newSlug . '/edit');
        } else {
            $this->flash('error', 'Failed to save category.');
            $this->redirect('/admin/categories/' . $slug . '/edit');
        }
    }

    /**
     * Delete category
     */
    public function delete(string $slug): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/categories');
            return;
        }

        if (!isset($this->categories[$slug])) {
            $this->flash('error', 'Category not found.');
            $this->redirect('/admin/categories');
            return;
        }

        // Check if category has articles -- count both flat (.md) and
        // bundle-format (.../index.md) shapes so bundle-only categories
        // are not treated as empty (which would let us delete a category
        // that still contains content).
        $categoryDir = dirname(__DIR__, 2) . '/content/articles/' . $slug;
        if (is_dir($categoryDir)) {
            $flat   = count(glob($categoryDir . '/*.md') ?: []);
            $bundle = count(glob($categoryDir . '/*/index.md') ?: []);
            $articleCount = $flat + $bundle;
            if ($articleCount > 0) {
                $this->flash('error', "Cannot delete category: it contains $articleCount article(s). Move or delete them first.");
                $this->redirect('/admin/categories');
                return;
            }
        }

        // Remove from config
        unset($this->categories[$slug]);

        if ($this->saveCategories()) {
            // Remove empty directory
            if (is_dir($categoryDir)) {
                rmdir($categoryDir);
            }

            $this->flash('success', 'Category deleted successfully.');
        } else {
            $this->flash('error', 'Failed to delete category.');
        }

        $this->redirect('/admin/categories');
    }

    /**
     * Build category data from POST
     */
    private function buildCategoryData(): array
    {
        $title = trim($this->post('title', ''));
        $description = trim($this->post('description', ''));
        $icon = trim($this->post('icon', 'folder'));

        // Parse subcategories
        $subcatKeys = $this->post('subcat_keys', []);
        $subcatTitles = $this->post('subcat_titles', []);

        $subcategories = [];
        if (is_array($subcatKeys) && is_array($subcatTitles)) {
            foreach ($subcatKeys as $i => $key) {
                $key = $this->sanitizeSlug($key);
                $subTitle = trim($subcatTitles[$i] ?? '');

                if ($key !== '' && $subTitle !== '') {
                    $subcategories[$key] = ['title' => $subTitle];
                }
            }
        }

        // Ensure 'general' subcategory exists
        if (!isset($subcategories['general'])) {
            $subcategories = array_merge(['general' => ['title' => 'General']], $subcategories);
        }

        return [
            'title' => $title ?: 'Untitled',
            'description' => $description,
            'icon' => $icon,
            'subcategories' => $subcategories,
        ];
    }

    /**
     * Save categories to config file
     */
    private function saveCategories(): bool
    {
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $this->exportArray($this->categories) . ";\n";

        return file_put_contents($this->configPath, $content) !== false;
    }
}
