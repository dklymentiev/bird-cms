<?php
/** @var array $categoriesList */
if (empty($categoriesList)) {
    return;
}
?>
<div class="sticky top-16 z-30 bg-white/80 supports-[backdrop-filter]:bg-white/60 backdrop-blur border-b border-slate-100 dark:bg-slate-900/80 dark:border-slate-800">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <nav class="flex gap-2 overflow-x-auto py-2 text-sm" aria-label="Categories">
            <?php foreach ($categoriesList as $category): ?>
                <a href="/<?= htmlspecialchars($category) ?>" class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-brand-400 hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:text-slate-300 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900">
                    <?= htmlspecialchars(ucfirst($category)) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
