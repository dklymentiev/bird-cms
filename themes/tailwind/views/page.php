<?php
/** @var array $page */
/** @var array $latest */

$pageTitle = page_title($page['title']);
$siteUrl = rtrim(config('site_url'), '/');
$pageUrl = $siteUrl . '/' . $page['slug'];

$meta = [
    'description' => $page['description'] ?? '',
    'og_title' => $pageTitle,
    'og_description' => $page['description'] ?? '',
    'og_image' => default_og_image(),
    'canonical' => $pageUrl,
    'lang' => 'en',
];

$breadcrumbItems = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => $page['title'], 'url' => null],
];

$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $page['title'],
        'description' => $page['description'] ?? '',
        'url' => $pageUrl,
    ]
];

$breadcrumbSchema = \App\Support\SchemaGenerator::buildBreadcrumbSchema($breadcrumbItems);
if ($breadcrumbSchema) {
    array_unshift($structuredData, $breadcrumbSchema);
}
?>
<section class="bg-gradient-to-b from-white to-slate-100/60 dark:from-slate-900 dark:to-slate-900/40">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-16">
        <?php
        $theme = theme_manager();
        $theme->partial('breadcrumbs', ['items' => $breadcrumbItems]);
        ?>
        <header class="mt-6">
            <h1 class="text-4xl font-semibold text-slate-900 leading-tight dark:text-slate-100">
                <?= htmlspecialchars($page['title']) ?>
            </h1>
            <?php if (!empty($page['description'])): ?>
                <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">
                    <?= htmlspecialchars($page['description']) ?>
                </p>
            <?php endif; ?>
        </header>
    </div>
</section>

<section class="bg-white dark:bg-slate-900">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-12">
        <article class="prose prose-slate lg:prose-lg max-w-none dark:prose-invert">
            <?= $page['html'] ?>
        </article>
    </div>
</section>

<?php if (!empty($latest)): ?>
<section class="bg-slate-50 dark:bg-slate-900/60">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-16">
        <h2 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Latest insights</h2>
        <div class="mt-10 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach (array_slice($latest, 0, 3) as $item): ?>
                <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="text-xs uppercase tracking-[0.3em] text-brand-600 dark:text-brand-300">
                        <?= htmlspecialchars(ucfirst($item['category'])) ?>
                    </div>
                    <h3 class="mt-3 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        <a href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>" class="hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                    </h3>
                    <p class="mt-2 text-sm text-slate-600 line-clamp-3 dark:text-slate-400"><?= htmlspecialchars($item['description']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
