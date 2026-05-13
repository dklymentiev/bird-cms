<?php
/**
 * Categories List View
 *
 * Variables:
 * - $categories: array - All categories config
 * - $articleCounts: array - Article count per category
 * - $flash: array|null - Flash message
 */

$csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '');

// Available icons for reference
$availableIcons = [
    'sparkles', 'chart-bar', 'compass', 'cpu-chip', 'wrench', 'banknotes',
    'heart', 'star', 'server-stack', 'shield-check', 'tag', 'book-open',
    'scale', 'clock', 'folder', 'document-text', 'globe-alt', 'light-bulb',
];
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Categories</h1>
        <p class="text-gray-600"><?= count($categories) ?> categories configured</p>
    </div>
    <a href="/admin/categories/new"
       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
        <i class="ri-add-line text-lg mr-2 leading-none"></i>
        New Category
    </a>
</div>

<!-- Categories Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($categories as $slug => $cat): ?>
        <?php if ($slug === 'latest') continue; /* Skip virtual category */ ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 mr-3">
                        <i class="<?= getCategoryIconClass($cat['icon'] ?? 'folder') ?> text-xl leading-none"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($cat['title']) ?></h3>
                        <span class="text-xs text-gray-500 font-mono">/<?= htmlspecialchars($slug) ?></span>
                    </div>
                </div>

                <div class="flex items-center space-x-1">
                    <a href="/admin/categories/<?= htmlspecialchars($slug) ?>/edit"
                       class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                       title="Edit">
                        <i class="ri-edit-line text-base leading-none"></i>
                    </a>
                </div>
            </div>

            <?php if (!empty($cat['description'])): ?>
                <p class="mt-2 text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($cat['description']) ?></p>
            <?php endif; ?>

            <div class="mt-3 flex items-center justify-between text-sm">
                <span class="text-gray-500">
                    <?= $articleCounts[$slug] ?? 0 ?> article<?= ($articleCounts[$slug] ?? 0) !== 1 ? 's' : '' ?>
                </span>
                <span class="text-gray-400">
                    <?= count($cat['subcategories'] ?? []) ?> subcategories
                </span>
            </div>

            <?php if (!empty($cat['subcategories'])): ?>
                <div class="mt-3 flex flex-wrap gap-1">
                    <?php foreach (array_slice($cat['subcategories'], 0, 5) as $subSlug => $sub): ?>
                        <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">
                            <?= htmlspecialchars($sub['title']) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($cat['subcategories']) > 5): ?>
                        <span class="px-2 py-0.5 text-xs text-gray-400">
                            +<?= count($cat['subcategories']) - 5 ?> more
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
function getCategoryIconClass(string $icon): string {
    // Maps category icon name to a Remix Icon class (loaded via remixicon.css
    // in themes/admin/layout.php).
    $icons = [
        'sparkles'      => 'ri-magic-line',
        'chart-bar'     => 'ri-bar-chart-2-line',
        'compass'       => 'ri-compass-3-line',
        'cpu-chip'      => 'ri-cpu-line',
        'wrench'        => 'ri-tools-line',
        'banknotes'     => 'ri-bank-card-line',
        'heart'         => 'ri-heart-line',
        'star'          => 'ri-star-line',
        'server-stack'  => 'ri-server-line',
        'shield-check'  => 'ri-shield-check-line',
        'tag'           => 'ri-price-tag-3-line',
        'book-open'     => 'ri-book-open-line',
        'scale'         => 'ri-scales-3-line',
        'clock'         => 'ri-time-line',
        'folder'        => 'ri-folder-line',
        'document-text' => 'ri-article-line',
        'globe-alt'     => 'ri-global-line',
        'light-bulb'    => 'ri-lightbulb-line',
    ];

    return $icons[$icon] ?? $icons['folder'];
}
?>

<style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
