<?php
/**
 * Breadcrumbs partial
 *
 * @var array $items - Array of breadcrumb items
 * Example: [
 *   ['label' => 'Home', 'url' => '/'],
 *   ['label' => 'Market', 'url' => '/market'],
 *   ['label' => 'Article Title', 'url' => null], // null = current page
 * ]
 */

if (empty($items) || !is_array($items)) {
    return;
}
?>
<nav aria-label="Breadcrumb" class="mb-6">
    <ol class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
        <?php foreach ($items as $index => $item): ?>
            <?php if ($index > 0): ?>
                <li aria-hidden="true" class="text-slate-400 dark:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 0 1 0-1.414L10.586 10 7.293 6.707a1 1 0 1 1 1.414-1.414l4 4a1 1 0 0 1 0 1.414l-4 4a1 1 0 0 1-1.414 0Z" clip-rule="evenodd" />
                    </svg>
                </li>
            <?php endif; ?>
            <li class="inline-flex items-center">
                <?php if (!empty($item['url'])): ?>
                    <a href="<?= htmlspecialchars($item['url']) ?>" class="transition hover:text-brand-600 dark:hover:text-brand-300">
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php else: ?>
                    <span class="font-medium text-slate-900 dark:text-slate-100" aria-current="page">
                        <?= htmlspecialchars($item['label']) ?>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php
// JSON-LD BreadcrumbList is now emitted by SchemaGenerator::buildBreadcrumbSchema()
// in the calling view (article.php / category.php / page.php). The partial only
// renders the visible <nav>.

?>
