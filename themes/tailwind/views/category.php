<?php
/** @var string $category */
/** @var array $articles */
/** @var array $latest */
/** @var array $activeFilters */
/** @var ?string $intro      Optional HTML rendered from content/pages/<category>.md (Step 5 fall-through) */
/** @var ?array  $introPage  Raw page record powering $intro, or null when no override exists */

$pageTitle = page_title(ucfirst($category));
$siteUrl = rtrim(config('site_url'), '/');
$categoryUrl = $siteUrl . '/' . $category;
$categoryConfig = config('categories', [])[$category] ?? [];
$description = $categoryConfig['description'] ?? ('Articles in ' . ucfirst($category) . '.');
$meta = [
    'description' => $description,
    'og_title' => $pageTitle,
    'og_description' => $description,
    'og_image' => default_og_image(),
    'twitter_image' => default_og_image(),
    'canonical' => $categoryUrl,
    'lang' => 'en'
];
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitle,
        'description' => $description,
        'url' => $categoryUrl,
    ]
];

$breadcrumbItems = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => ucfirst($category), 'url' => null], // Current page
];

// Prepend BreadcrumbList JSON-LD before the page-level CollectionPage schema
$breadcrumbSchema = \App\Support\SchemaGenerator::buildBreadcrumbSchema($breadcrumbItems);
if ($breadcrumbSchema) {
    array_unshift($structuredData, $breadcrumbSchema);
}
?>
<section class="bg-gradient-to-b from-brand-50 via-white to-slate-50 dark:from-slate-900 dark:via-slate-900 dark:to-slate-900/40">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16">
        <?php
        $theme = theme_manager();
        $theme->partial('breadcrumbs', ['items' => $breadcrumbItems]);
        ?>
        <header class="mt-6 max-w-2xl">
            <p class="text-xs uppercase tracking-[0.4em] font-semibold text-brand-600 dark:text-brand-300">Category</p>
            <h1 class="mt-4 text-4xl font-semibold text-slate-900 dark:text-slate-100"><?= htmlspecialchars(ucfirst($category)) ?></h1>
            <p class="mt-4 text-base text-slate-600 dark:text-slate-400"><?= htmlspecialchars($description) ?></p>
        </header>

        <?php if (!empty($intro)): ?>
        <!-- Operator-authored category intro from content/pages/<?= htmlspecialchars($category) ?>.md.
             Sits between the header and the article grid so it reads as
             editorial copy, not as a sidebar widget. -->
        <article class="mt-8 max-w-3xl prose prose-slate lg:prose-lg dark:prose-invert">
            <?= $intro ?>
        </article>
        <?php endif; ?>

        <?php
        $currentType = $activeFilters['type'] ?? null;
        $currentTag = $activeFilters['tag'] ?? null;
        $currentSort = $activeFilters['sort'] ?? 'latest';
        $categoryPath = '/' . $category;

        // Helper to build filter URL
        $buildFilterUrl = function($type = null, $sort = null, $tag = null) use ($categoryPath, $currentType, $currentTag, $currentSort) {
            $params = [];
            $useType = $type ?? $currentType;
            $useTag = $tag ?? $currentTag;
            $useSort = $sort ?? $currentSort;

            if ($useType !== null && $useType !== '') {
                $params['type'] = $useType;
            }
            if ($useTag !== null && $useTag !== '') {
                $params['tag'] = $useTag;
            }
            if ($useSort !== 'latest') {
                $params['sort'] = $useSort;
            }

            return $categoryPath . (empty($params) ? '' : '?' . http_build_query($params));
        };
        ?>

        <?php if (!empty($currentTag)): ?>
        <div class="mt-6 flex items-center gap-2">
            <span class="text-sm text-slate-600 dark:text-slate-400">Filtered by tag:</span>
            <a href="<?= htmlspecialchars($buildFilterUrl(null, null, '')) ?>" class="inline-flex items-center gap-2 rounded-full bg-brand-500 px-4 py-1.5 text-sm font-semibold text-white transition hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
                #<?= htmlspecialchars($currentTag) ?>
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </a>
        </div>
        <?php endif; ?>

        <div class="mt-8 flex flex-wrap gap-3 items-center">
            <span class="text-xs uppercase tracking-[0.3em] font-semibold text-slate-500 dark:text-slate-400">Filter:</span>
            <a href="<?= htmlspecialchars($buildFilterUrl(null, null, '')) ?>"
               class="rounded-full border px-4 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900 <?= empty($currentType) && empty($currentTag) ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-400' ?>">
                All
            </a>
            <a href="<?= htmlspecialchars($buildFilterUrl('insight')) ?>"
               class="rounded-full border px-4 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900 <?= $currentType === 'insight' ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-400' ?>">
                Insight
            </a>
            <a href="<?= htmlspecialchars($buildFilterUrl('guide')) ?>"
               class="rounded-full border px-4 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900 <?= $currentType === 'guide' ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-400' ?>">
                Guide
            </a>
            <a href="<?= htmlspecialchars($buildFilterUrl('playbook')) ?>"
               class="rounded-full border px-4 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900 <?= $currentType === 'playbook' ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-400' ?>">
                Playbook
            </a>
            <a href="<?= htmlspecialchars($buildFilterUrl('trend')) ?>"
               class="rounded-full border px-4 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900 <?= $currentType === 'trend' ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-400' ?>">
                Trend
            </a>

            <span class="ml-4 text-xs uppercase tracking-[0.3em] font-semibold text-slate-500 dark:text-slate-400">Sort:</span>
            <a href="<?= htmlspecialchars($buildFilterUrl(null, 'latest')) ?>"
               class="rounded-full border px-4 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900 <?= $currentSort === 'latest' ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-400' ?>">
                Latest
            </a>
            <a href="<?= htmlspecialchars($buildFilterUrl(null, 'popular')) ?>"
               class="rounded-full border px-4 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900 <?= $currentSort === 'popular' ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-400' ?>">
                Popular
            </a>
        </div>
    </div>
</section>

<section class="bg-white dark:bg-slate-900">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16">
        <?php if (empty($articles)): ?>
            <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-12 text-center dark:border-slate-700 dark:bg-slate-900/60">
                <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?php if (!empty($currentType) || !empty($currentTag)): ?>
                    <p class="mt-4 text-slate-500 dark:text-slate-400">No articles match this filter combination.</p>
                    <a href="/<?= htmlspecialchars($category) ?>" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear filters
                    </a>
                <?php else: ?>
                    <p class="mt-4 text-slate-500 dark:text-slate-400">More stories in this category are coming soon.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($articles as $item): ?>
                    <article class="flex flex-col overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg focus-within:-translate-y-1 focus-within:shadow-lg dark:border-slate-800 dark:bg-slate-900/70">
                        <?php if (!empty($item['hero_image'])): ?>
                        <div class="relative h-48 overflow-hidden">
                            <img src="<?= htmlspecialchars($item['hero_image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy" decoding="async" sizes="(min-width:1024px) 320px, (min-width:768px) 45vw, 100vw" class="h-full w-full object-cover transition duration-700 hover:scale-105" />
                        </div>
                        <?php endif; ?>
                        <div class="flex flex-1 flex-col p-6">
                            <?php if (!empty($item['date'])): ?>
                                <p class="text-xs uppercase tracking-[0.3em] text-brand-600 dark:text-brand-300"><?= date('M j, Y', strtotime($item['date'])) ?></p>
                            <?php endif; ?>
                            <h2 class="mt-3 text-lg font-semibold text-slate-900 flex-1 dark:text-slate-100">
                                <a href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>" class="hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
                                    <?= htmlspecialchars($item['title']) ?>
                                </a>
                            </h2>
                            <p class="mt-3 text-sm text-slate-600 line-clamp-3 dark:text-slate-400"><?= htmlspecialchars($item['description']) ?></p>
                            <div class="mt-6 text-sm font-semibold text-brand-600">
                                <a href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>" class="inline-flex items-center gap-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">Read insight<span aria-hidden="true">→</span></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($pagination) && $pagination['total'] > 1): ?>
            <nav class="mt-12 flex items-center justify-center gap-2" aria-label="Pagination">
                <?php
                $current = $pagination['current'];
                $total = $pagination['total'];
                $baseUrl = '/' . htmlspecialchars($category);
                $queryParams = [];
                if (!empty($activeFilters['type'])) $queryParams['type'] = $activeFilters['type'];
                if (!empty($activeFilters['tag'])) $queryParams['tag'] = $activeFilters['tag'];
                if (!empty($activeFilters['sort']) && $activeFilters['sort'] !== 'latest') $queryParams['sort'] = $activeFilters['sort'];

                $buildUrl = function($page) use ($baseUrl, $queryParams) {
                    $params = $queryParams;
                    if ($page > 1) $params['page'] = $page;
                    return $baseUrl . ($params ? '?' . http_build_query($params) : '');
                };
                ?>

                <?php if ($current > 1): ?>
                    <a href="<?= $buildUrl($current - 1) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-brand-500 hover:text-brand-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" aria-label="Previous page">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $current - 2);
                $end = min($total, $current + 2);
                if ($start > 1): ?>
                    <a href="<?= $buildUrl(1) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">1</a>
                    <?php if ($start > 2): ?><span class="px-1 text-slate-400">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $current): ?>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $buildUrl($i) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $total): ?>
                    <?php if ($end < $total - 1): ?><span class="px-1 text-slate-400">...</span><?php endif; ?>
                    <a href="<?= $buildUrl($total) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><?= $total ?></a>
                <?php endif; ?>

                <?php if ($current < $total): ?>
                    <a href="<?= $buildUrl($current + 1) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-brand-500 hover:text-brand-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" aria-label="Next page">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                <?php endif; ?>
            </nav>
            <p class="mt-4 text-center text-sm text-slate-500 dark:text-slate-400">
                Showing <?= (($current - 1) * $pagination['perPage']) + 1 ?>–<?= min($current * $pagination['perPage'], $pagination['totalItems']) ?> of <?= $pagination['totalItems'] ?> articles
            </p>
        <?php endif; ?>

        <?php if (!empty($latest)): ?>
            <div class="mt-16 rounded-3xl border border-slate-200 bg-slate-50 p-8 dark:border-slate-700 dark:bg-slate-900/60">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Other fresh stories</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <?php foreach (array_slice($latest, 0, 6) as $post): ?>
                        <a href="/<?= htmlspecialchars($post['category']) ?>/<?= htmlspecialchars($post['slug']) ?>" class="rounded-2xl bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:bg-slate-900 dark:hover:shadow-brand-900/40 dark:focus-visible:ring-offset-slate-900">
                            <div class="text-xs uppercase tracking-[0.3em] text-brand-600 dark:text-brand-300"><?= htmlspecialchars(ucfirst($post['category'])) ?></div>
                            <div class="mt-2 text-base font-semibold text-slate-900 line-clamp-2 dark:text-slate-100"><?= htmlspecialchars($post['title']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
