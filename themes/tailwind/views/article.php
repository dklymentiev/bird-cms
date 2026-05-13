<?php
/** @var array $article */
/** @var array $related */
/** @var array $latest */
/** @var array $adjacent */

$pageTitle = page_title($article['title']);
$siteUrl = rtrim(config('site_url'), '/');
$articleMeta = $article['meta'] ?? [];

// Resolve hero image path (supports bundle ./hero.webp and absolute paths)
$heroImageRaw = $article['hero_image'] ?? ($articleMeta['og_image'] ?? null);
if ($heroImageRaw === null) {
    $heroImage = default_og_image();
} elseif (str_starts_with($heroImageRaw, 'http')) {
    $heroImage = $heroImageRaw;
} elseif (str_starts_with($heroImageRaw, './')) {
    // Bundle-relative path: ./hero.webp -> /content/articles/category/slug/hero.webp
    $bundlePath = $article['bundle_path'] ?? null;
    if ($bundlePath !== null && defined('SITE_ROOT')) {
        $relativePath = str_replace(SITE_ROOT, '', $bundlePath);
        $heroImage = $siteUrl . $relativePath . '/' . substr($heroImageRaw, 2);
    } else {
        // Fallback: construct path from category/slug
        $heroImage = $siteUrl . '/content/articles/' . $article['category'] . '/' . $article['slug'] . '/' . substr($heroImageRaw, 2);
    }
} else {
    $heroImage = $siteUrl . $heroImageRaw;
}
$publishedAt = $article['date'] ?? null;
$modifiedAt = $articleMeta['updated'] ?? $publishedAt;
$language = $articleMeta['language'] ?? 'en';
$canonical = $articleMeta['canonical'] ?? $article['url'];
$readTime = $articleMeta['reading_time'] ?? null;
$authorSlug = $articleMeta['author'] ?? $article['author'] ?? null;
$author = get_author($authorSlug);
$authorName = $author['name'];
$authorAvatar = $author['avatar'] ?? null;
$authorBio = $author['bio'] ?? null;
$geo = $articleMeta['geo'] ?? [];

$meta = [
    'description' => $article['description'] ?? '',
    'og_title' => $pageTitle,
    'og_description' => $article['description'] ?? '',
    'og_image' => $heroImage,
    'og_type' => 'article',
    'twitter_title' => $pageTitle,
    'twitter_description' => $article['description'] ?? '',
    'twitter_image' => $heroImage,
    'canonical' => $canonical,
    'lang' => $language,
];

$breadcrumbItems = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => ucfirst($article['category']), 'url' => '/' . $article['category']],
    ['label' => $article['title'], 'url' => null], // Current page
];

$structuredData = [
    array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $article['title'],
        'description' => $article['description'] ?? '',
        'inLanguage' => $language,
        'image' => [$heroImage],
        'url' => $canonical,
        'mainEntityOfPage' => $canonical,
        'datePublished' => $publishedAt ? date(DATE_ATOM, strtotime($publishedAt)) : null,
        'dateModified' => $modifiedAt ? date(DATE_ATOM, strtotime($modifiedAt)) : null,
        'author' => [
            '@type' => 'Person',
            'name' => $authorName,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => publisher_name(),
            'url' => $siteUrl . '/',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $siteUrl . '/assets/brand/logo-square.png',
            ],
        ],
        'keywords' => $article['tags'],
        'articleSection' => ucfirst($article['category']),
    ])
];

// Add type-specific schemas (Review, FAQ, HowTo, etc.) plus BreadcrumbList
$articleMeta['breadcrumb'] = $breadcrumbItems;
$additionalSchemas = \App\Support\SchemaGenerator::generate($article, $articleMeta);
$structuredData = array_merge($structuredData, $additionalSchemas);

?>
<style>
/* Markdown table styles */
.prose .markdown-table {
    width: 100%;
    margin: 2rem 0;
    border-collapse: collapse;
    font-size: 0.95rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    border-radius: 0.5rem;
    overflow: hidden;
}

.prose .markdown-table thead {
    background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
}

.dark .prose .markdown-table thead {
    background: linear-gradient(to bottom, #1e293b, #0f172a);
}

.prose .markdown-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #0f172a;
    border-bottom: 2px solid #e2e8f0;
}

.dark .prose .markdown-table th {
    color: #f1f5f9;
    border-bottom-color: #334155;
}

.prose .markdown-table td {
    padding: 0.875rem 1rem;
    color: #475569;
    border-bottom: 1px solid #f1f5f9;
}

.dark .prose .markdown-table td {
    color: #cbd5e1;
    border-bottom-color: #1e293b;
}

.prose .markdown-table tbody tr:hover {
    background-color: #f8fafc;
}

.dark .prose .markdown-table tbody tr:hover {
    background-color: #1e293b;
}

.prose .markdown-table tbody tr:last-child td {
    border-bottom: none;
}

.prose .markdown-table {
    table-layout: fixed;
    word-break: break-word;
}

.prose .markdown-table::-webkit-scrollbar {
    height: 6px;
}

.prose .markdown-table::-webkit-scrollbar-thumb {
    background-color: rgba(148, 163, 184, 0.6);
    border-radius: 9999px;
}

.prose .markdown-table::-webkit-scrollbar-track {
    background-color: transparent;
}

/* Make tables responsive */
@media (max-width: 768px) {
    .prose .markdown-table {
        font-size: 0.875rem;
    }

    .prose .markdown-table th,
    .prose .markdown-table td {
        padding: 0.75rem 0.5rem;
    }
}

@media (max-width: 640px) {
    .prose .markdown-table th,
    .prose .markdown-table td {
        padding: 0.65rem 0.5rem;
    }
}

@media (max-width: 480px) {
    .prose .markdown-table {
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 0.5rem;
    }

    .prose .markdown-table tbody tr:hover {
        background-color: transparent;
    }
}

/* Collapsible Sources Section */
.prose .sources-section {
    margin: 2rem 0;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #f8fafc;
    overflow: hidden;
}

.dark .prose .sources-section {
    border-color: #334155;
    background: #1e293b;
}

.prose .sources-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s;
}

.prose .sources-toggle:hover {
    background-color: #f1f5f9;
}

.dark .prose .sources-toggle:hover {
    background-color: #334155;
}

.prose .sources-toggle h2 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #475569;
}

.dark .prose .sources-toggle h2 {
    color: #cbd5e1;
}

.prose .sources-icon {
    flex-shrink: 0;
    transition: transform 0.2s;
    color: #64748b;
}

.dark .prose .sources-icon {
    color: #94a3b8;
}

.prose details[open] .sources-icon {
    transform: rotate(180deg);
}

.prose .sources-content {
    padding: 0 1.25rem 1.25rem;
    color: #64748b;
    font-size: 0.875rem;
}

.dark .prose .sources-content {
    color: #94a3b8;
}

.prose .sources-content p {
    margin: 0.5rem 0;
}

.prose .sources-content a {
    color: #0ea5e9;
    text-decoration: none;
    word-break: break-word;
}

.prose .sources-content a:hover {
    text-decoration: underline;
}

.dark .prose .sources-content a {
    color: #38bdf8;
}

/* Text alignment - justify */
.prose p,
.prose li {
    text-align: justify;
}

.prose h1,
.prose h2,
.prose h3,
.prose h4,
.prose h5,
.prose h6 {
    text-align: left;
}

/* Inline logos in headings */
.prose h2 > img:first-child,
.prose h3 > img:first-child {
    display: inline-block;
    width: 1.5em;
    height: 1.5em;
    vertical-align: middle;
    margin-right: 0.35em;
    margin-top: -0.15em;
    border-radius: 0.25rem;
}
/* Star ratings */
.stars-5 { color: #fbbf24; }
.stars-5::before { content: "★★★★★"; }
.stars-4 { color: #fbbf24; }
.stars-4::after { content: "★"; color: #d1d5db; }
.stars-4::before { content: "★★★★"; }
.stars-3::before { content: "★★★"; color: #fbbf24; }
.stars-3::after { content: "★★"; color: #d1d5db; }

</style>
<section class="bg-gradient-to-b from-white to-slate-100/60 dark:from-slate-900 dark:to-slate-900/40">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-16">
        <?php
        $theme = theme_manager();
        $theme->partial('breadcrumbs', ['items' => $breadcrumbItems]);
        // BreadcrumbList JSON-LD is now emitted by SchemaGenerator::generate()
        // when $articleMeta['breadcrumb'] is set (see line 92 above).
        ?>
        <header class="mt-6">
            <p class="text-xs uppercase tracking-[0.4em] font-semibold text-brand-600 dark:text-brand-300">Insight</p>
            <h1 class="mt-4 text-4xl font-semibold text-slate-900 leading-tight dark:text-slate-100">
                <?= htmlspecialchars($article['title']) ?>
            </h1>
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                <?php if ($publishedAt): ?>
                    <span><?= date('F j, Y', strtotime($publishedAt)) ?></span>
                <?php endif; ?>
                <?php if ($modifiedAt && $modifiedAt !== $publishedAt): ?>
                    <span class="inline-flex items-center gap-1 text-slate-400 dark:text-slate-500">•
                        <span class="text-slate-500 dark:text-slate-400">Updated <?= date('M j, Y', strtotime($modifiedAt)) ?></span>
                    </span>
                <?php endif; ?>
                <span class="inline-flex items-center gap-2 text-slate-400 dark:text-slate-500">•
                    <?php if ($authorAvatar): ?>
                        <img src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= htmlspecialchars($authorName) ?>" class="h-5 w-5 rounded-full" />
                    <?php endif; ?>
                    <span class="text-slate-600 dark:text-slate-300"><?= htmlspecialchars($authorName) ?></span>
                </span>
                <?php if ($readTime): ?>
                    <span class="inline-flex items-center gap-2 text-slate-400 dark:text-slate-500">•
                        <span class="inline-flex items-center gap-1 text-slate-600 dark:text-slate-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <?= htmlspecialchars($readTime) ?>
                        </span>
                    </span>
                <?php endif; ?>
            </div>
            <div class="mt-6 flex items-center gap-4">
                <?php $theme->partial('share-buttons', ['url' => $canonical, 'title' => $article['title'], 'size' => 'small']); ?>
                <button
                    type="button"
                    data-bookmark-btn
                    data-bookmark-url="<?= htmlspecialchars($canonical) ?>"
                    data-bookmark-title="<?= htmlspecialchars($article['title']) ?>"
                    data-bookmark-category="<?= htmlspecialchars($article['category']) ?>"
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-brand-400 dark:hover:text-brand-300"
                    aria-label="Save for later"
                    aria-pressed="false"
                >
                    <svg class="bookmark-icon-empty h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                    <svg class="bookmark-icon-filled hidden h-4 w-4 text-brand-600 dark:text-brand-400" fill="currentColor" viewBox="0 0 24 24"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                    <span class="hidden sm:inline">Save</span>
                </button>
            </div>
        </header>
        <?php if (!empty($article['hero_image'])): ?>
            <figure class="mt-10 overflow-hidden rounded-lg shadow-xl">
                <img src="<?= htmlspecialchars($heroImage) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="w-full h-auto" loading="lazy" decoding="async" />
            </figure>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($isPreview)): ?>
<div class="bg-amber-500 text-amber-950">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span class="font-semibold">Preview Mode</span>
            <span class="text-amber-800">This article is not published yet</span>
        </div>
        <a href="/admin/pipeline/draft/<?= htmlspecialchars(urlencode($article['slug'])) ?>"
           class="px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium transition-colors">
            Edit Draft
        </a>
    </div>
</div>
<?php endif; ?>

<section class="bg-white dark:bg-slate-900">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-1 gap-12 lg:grid-cols-[minmax(0,1fr),320px]">
        <article class="prose prose-slate lg:prose-lg max-w-none min-w-0 dark:prose-invert">
            <?= $article['html'] ?>
            <?php if (!empty($geo)): ?>
                <aside class="not-prose mt-8 text-xs text-slate-400 dark:text-slate-500">Geo focus: <?= htmlspecialchars($geo['region'] ?? '') ?></aside>
            <?php endif; ?>
            <div class="not-prose mt-12 pt-8 border-t border-slate-200 dark:border-slate-800">
                <?php $theme->partial('share-buttons', ['url' => $canonical, 'title' => $article['title'], 'size' => 'large']); ?>
            </div>

            <!-- Author Box -->
            <div class="not-prose mt-12 pt-8 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-start gap-4">
                    <?php if ($authorAvatar): ?>
                        <img src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= htmlspecialchars($authorName) ?>" class="h-14 w-14 rounded-full flex-shrink-0" />
                    <?php endif; ?>
                    <div>
                        <p class="text-xs uppercase tracking-widest text-slate-400 dark:text-slate-500">Written by</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($authorName) ?></p>
                        <?php if ($authorBio): ?>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400"><?= htmlspecialchars($authorBio) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
        <aside class="space-y-10 min-w-0">
            <?php if (!empty($article['toc']) && count($article['toc']) >= 3): ?>
                <nav class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900/70" aria-labelledby="toc-heading">
                    <h3 id="toc-heading" class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-slate-400">Contents</h3>
                    <ul class="mt-4 space-y-2 text-sm">
                        <?php foreach ($article['toc'] as $item): ?>
                            <li class="<?= $item['level'] === 3 ? 'ml-4' : '' ?>">
                                <a href="#<?= htmlspecialchars($item['id']) ?>"
                                   class="block py-1 text-slate-600 transition hover:text-brand-600 focus:outline-none focus-visible:text-brand-600 dark:text-slate-400 dark:hover:text-brand-300 dark:focus-visible:text-brand-300">
                                    <?= htmlspecialchars($item['text']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>

            <?php if (!empty($article['tags'])): ?>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-900/70" aria-labelledby="article-tags">
                    <h3 id="article-tags" class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-slate-400">Tags</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <?php foreach ($article['tags'] as $tag): ?>
                            <a href="/<?= htmlspecialchars($article['category']) ?>?tag=<?= urlencode($tag) ?>" class="inline-flex items-center rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-700 transition hover:bg-brand-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:bg-brand-200/20 dark:text-brand-200 dark:hover:bg-brand-200/30 dark:focus-visible:ring-offset-slate-900">#<?= htmlspecialchars($tag) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="rounded-3xl border border-brand-100 bg-brand-50 p-6 dark:border-brand-300/40 dark:bg-brand-200/10" id="newsletter">
                <h3 class="text-base font-semibold text-brand-700 dark:text-brand-200">Get the <?= htmlspecialchars(site_name()) ?> memo</h3>
                <p class="mt-2 text-sm text-brand-700/80 dark:text-brand-100/80">Weekly insights on tech, markets, wellness, and everyday decisions.</p>
                <form id="article-newsletter-form" class="mt-4 flex flex-col gap-3">
                    <label for="article-newsletter-email" class="sr-only">Email</label>
                    <input id="article-newsletter-email" type="email" name="email" required placeholder="you@example.com" class="w-full rounded-full border border-brand-200 bg-white px-4 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:bg-slate-800 dark:border-brand-300/40 dark:text-slate-100" />
                    <button type="submit" class="inline-flex items-center justify-center rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-200 focus-visible:ring-offset-2 focus-visible:ring-offset-brand-600/20 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="submit-text">Subscribe</span>
                    </button>
                </form>
            </div>

            <?php if (!empty($globalLatest ?? [])): ?>
                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70" aria-labelledby="latest-sitewide">
                    <h3 id="latest-sitewide" class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-slate-400">Latest analysis</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        <?php foreach ($globalLatest as $item): ?>
                            <li>
                                <a href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>"
                                   class="block rounded-lg px-3 py-2 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-900">
                                    <div class="text-xs uppercase tracking-[0.3em] text-brand-600 dark:text-brand-300">
                                        <?= htmlspecialchars(ucfirst($item['category'])) ?>
                                    </div>
                                    <div class="mt-1 font-semibold text-slate-800 line-clamp-2 dark:text-slate-100">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($latest)): ?>
                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70" aria-labelledby="latest-analysis">
                    <h3 id="latest-analysis" class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-slate-400">Stay on topic</h3>
                    <ul class="mt-5 space-y-4 text-sm">
                        <?php
                        $primaryTags = array_map('strtolower', $article['tags'] ?? []);
                        foreach ($latest as $item):
                            $itemTags = array_map('strtolower', $item['tags'] ?? []);
                            $sharedTag = null;
                            foreach ($primaryTags as $tag) {
                                if (in_array($tag, $itemTags, true)) {
                                    $sharedTag = $tag;
                                    break;
                                }
                            }
                            if ($sharedTag === null) {
                                $sharedTag = $itemTags[0] ?? ($item['category'] ?? '');
                            }
                            $thumb = $item['hero_image']
                                ?? ($item['meta']['og_image'] ?? default_og_image());
                        ?>
                            <li>
                                <a href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>"
                                   class="group flex gap-4 rounded-xl px-3 py-2 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-900">
                                    <div class="relative h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg bg-slate-200 dark:bg-slate-800">
                                        <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async" />
                                    </div>
                                    <div class="flex-1">
                                        <?php if (!empty($sharedTag)): ?>
                                            <div class="text-[0.65rem] uppercase tracking-[0.35em] text-brand-600 dark:text-brand-300">
                                                <?= htmlspecialchars($sharedTag) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-1 font-semibold text-slate-800 line-clamp-2 transition group-hover:text-brand-600 dark:text-slate-100 dark:group-hover:text-brand-200">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php if (!empty($adjacent['previous']) || !empty($adjacent['next'])): ?>
<section class="bg-slate-50 dark:bg-slate-900/60">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-10 grid gap-6 md:grid-cols-2">
        <?php if (!empty($adjacent['previous'])): $prev = $adjacent['previous']; ?>
            <a href="/<?= htmlspecialchars($prev['category']) ?>/<?= htmlspecialchars($prev['slug']) ?>" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 dark:text-brand-300">Previous</p>
                <h3 class="mt-3 text-lg font-semibold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($prev['title']) ?></h3>
                <p class="mt-2 text-sm text-slate-600 line-clamp-2 dark:text-slate-400"><?= htmlspecialchars($prev['description']) ?></p>
            </a>
        <?php endif; ?>
        <?php if (!empty($adjacent['next'])): $next = $adjacent['next']; ?>
            <a href="/<?= htmlspecialchars($next['category']) ?>/<?= htmlspecialchars($next['slug']) ?>" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 dark:text-brand-300">Next up</p>
                <h3 class="mt-3 text-lg font-semibold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($next['title']) ?></h3>
                <p class="mt-2 text-sm text-slate-600 line-clamp-2 dark:text-slate-400"><?= htmlspecialchars($next['description']) ?></p>
            </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($related)): ?>
<section class="bg-slate-900">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-16 text-white">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-2xl font-semibold">Related reads</h2>
            <a href="/<?= htmlspecialchars($article['category']) ?>" class="text-sm font-semibold text-brand-200 hover:text-brand-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-200 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">More from <?= htmlspecialchars(ucfirst($article['category'])) ?></a>
        </div>
        <div class="mt-10 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($related as $item): ?>
                <article class="flex flex-col rounded-3xl border border-white/10 bg-white/10 p-6 shadow-lg backdrop-blur transition hover:border-brand-200/60 focus-within:border-brand-200/60">
                    <div class="text-xs uppercase tracking-[0.3em] text-brand-200">
                        <?= htmlspecialchars(ucfirst($item['category'])) ?>
                    </div>
                    <h3 class="mt-3 text-lg font-semibold text-white">
                        <a href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>" class="hover:text-brand-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-200 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                    </h3>
                    <p class="mt-2 text-sm text-white/70 line-clamp-3"><?= htmlspecialchars($item['description']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
