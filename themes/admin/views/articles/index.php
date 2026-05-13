<?php
/**
 * Articles List View
 *
 * Variables:
 * - $articles: array - List of articles for current page
 * - $categories: array - All categories config
 * - $types: array - All article types
 * - $filters: array - Current filters (category, status, type, q)
 * - $pagination: array - Pagination data (page, perPage, total, totalPages)
 */

use App\Admin\ArticleController;

$pageTitle = 'Articles';
?>

<!-- Header with actions -->
<div x-data="{ view: localStorage.getItem('bird-articles-view') || 'cards' }"
     x-init="$watch('view', v => localStorage.setItem('bird-articles-view', v))"
     class="space-y-6">
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Articles</h2>
        <p class="text-gray-500 mt-1"><?= $pagination['total'] ?> total <?= $pagination['total'] === 1 ? 'article' : 'articles' ?></p>
    </div>
    <div class="flex items-center gap-2">
        <!-- View toggle: cards good for browsing 12, table good for triaging 200. -->
        <div class="inline-flex border border-slate-700 bg-slate-900">
            <button type="button" @click="view = 'cards'"
                    :class="view === 'cards' ? 'bg-slate-700 text-slate-100' : 'text-slate-400 hover:text-slate-200'"
                    class="px-3 py-1.5 text-sm flex items-center gap-1.5">
                <i class="ri-grid-line text-base leading-none"></i>
                Cards
            </button>
            <button type="button" @click="view = 'table'"
                    :class="view === 'table' ? 'bg-slate-700 text-slate-100' : 'text-slate-400 hover:text-slate-200'"
                    class="px-3 py-1.5 text-sm flex items-center gap-1.5 border-l border-slate-700">
                <i class="ri-table-line text-base leading-none"></i>
                Table
            </button>
        </div>
        <a href="/admin/articles/new"
           class="inline-flex items-center px-4 py-2 bg-blue-500 text-white font-medium hover:bg-blue-600 transition-colors">
            <i class="ri-add-line text-lg mr-2 leading-none"></i>
            New Article
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" action="/admin/articles" class="flex flex-wrap items-center gap-4">
        <!-- Search -->
        <div class="flex-1 min-w-[200px]">
            <div class="relative">
                <i class="ri-search-line text-lg text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 leading-none"></i>
                <input type="text"
                       name="q"
                       value="<?= htmlspecialchars($filters['q']) ?>"
                       placeholder="Search articles..."
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <!-- Category filter -->
        <select name="category"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
            <option value="">All Categories</option>
            <?php foreach ($categories as $slug => $cat): ?>
                <option value="<?= htmlspecialchars($slug) ?>"
                        <?= $filters['category'] === $slug ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Status filter -->
        <select name="status"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
            <option value="">All Statuses</option>
            <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>

        <!-- Type filter -->
        <select name="type"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
            <option value="">All Types</option>
            <?php foreach ($types as $type): ?>
                <option value="<?= htmlspecialchars($type) ?>"
                        <?= $filters['type'] === $type ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($type)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Submit -->
        <button type="submit"
                class="px-4 py-2 bg-blue-900 text-blue-200 font-medium hover:bg-blue-800 transition-colors border border-blue-700">
            Filter
        </button>

        <?php if (!empty($filters['category']) || !empty($filters['status']) || !empty($filters['type']) || !empty($filters['q'])): ?>
            <a href="/admin/articles"
               class="px-4 py-2 text-gray-500 hover:text-gray-700 transition-colors">
                Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Articles list -->
<?php if (empty($articles)): ?>
    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
        <i class="ri-article-line text-6xl text-gray-300 block mb-4 leading-none"></i>
        <h3 class="text-lg font-medium text-gray-800 mb-2">No articles found</h3>
        <p class="text-gray-500 mb-6">
            <?php if (!empty($filters['q']) || !empty($filters['category']) || !empty($filters['status']) || !empty($filters['type'])): ?>
                Try adjusting your filters or search query.
            <?php else: ?>
                Get started by creating your first article.
            <?php endif; ?>
        </p>
        <a href="/admin/articles/new"
           class="inline-flex items-center px-4 py-2 bg-blue-500 text-white font-medium rounded-lg hover:bg-blue-600 transition-colors">
            <i class="ri-add-line text-lg mr-2 leading-none"></i>
            Create Article
        </a>
    </div>
<?php else: ?>
    <!-- Pagination Top -->
    <?php $paginationClass = 'mb-4'; include __DIR__ . '/_pagination.php'; ?>

    <!-- Cards view (default) -->
    <div x-show="view === 'cards'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
        <?php foreach ($articles as $article): ?>
            <?php include __DIR__ . '/_card.php'; ?>
        <?php endforeach; ?>
    </div>

    <!-- Table view: dense list good for triaging 100+ articles. -->
    <div x-show="view === 'table'" x-cloak class="bg-slate-800 border border-slate-700 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800 text-slate-400 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Title</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php foreach ($articles as $article): ?>
                    <tr class="hover:bg-slate-800">
                        <td class="px-4 py-2.5 text-slate-100 font-medium">
                            <a href="/admin/articles/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>/edit"
                               class="hover:text-blue-400"><?= htmlspecialchars($article['title'] ?? $article['slug']) ?></a>
                        </td>
                        <td class="px-4 py-2.5 text-slate-400 text-xs"><?= htmlspecialchars($article['category'] ?? '') ?></td>
                        <td class="px-4 py-2.5 text-xs">
                            <?php $st = $article['status'] ?? 'draft'; ?>
                            <span class="<?= $st === 'published' ? 'text-emerald-400' : ($st === 'scheduled' ? 'text-amber-400' : 'text-slate-500') ?>">
                                <?= htmlspecialchars(ucfirst($st)) ?>
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-400 text-xs"><?= htmlspecialchars($article['date'] ?? '') ?></td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="/admin/articles/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>/edit"
                               class="text-slate-500 hover:text-blue-400 inline-block px-1.5">
                                <i class="ri-edit-line text-base leading-none"></i>
                            </a>
                            <a href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>"
                               target="_blank" rel="noopener"
                               class="text-slate-500 hover:text-blue-400 inline-block px-1.5">
                                <i class="ri-external-link-line text-base leading-none"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Bottom -->
    <?php $paginationClass = 'mt-4'; include __DIR__ . '/_pagination.php'; ?>
<?php endif; ?>
</div><!-- /x-data view toggle -->
