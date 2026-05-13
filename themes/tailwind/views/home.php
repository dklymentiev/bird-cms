<?php
/** @var array $hero */
/** @var array $topStories */
/** @var array $latest */
/** @var ?string $intro      Optional HTML rendered from content/pages/home.md (Step 5 fall-through) */
/** @var ?array  $introPage  Raw page record powering $intro, or null when no override exists */
/** @var array $categoryHighlights */
/** @var array $trending */
/** @var array $editorsPicks */
/** @var array $marketPulse */
/** @var array $playbookArticles */
/** @var array $bestPicks */
/** @var array $faceOffs */
/** @var array $howTos */
/** @var array $dealsStrip */
/** @var array $mostRead */
/** @var array $latestFeed */
/** @var \App\Content\MetricsRepository $metrics */
/** @var \App\Theme\ThemeManager $theme */

$pageTitle = page_title(site_tagline());
$siteUrl = rtrim(config('site_url'), '/');
$defaultOgImage = default_og_image();
$heroImage = !empty($hero['hero_image']) ? $hero['hero_image'] : $defaultOgImage;
$meta = [
    'description' => site_description(),
    'og_title' => $pageTitle,
    'og_description' => site_description(),
    'og_image' => $heroImage,
    'twitter_title' => $pageTitle,
    'twitter_description' => site_description(),
    'lang' => 'en'
];
$searchPrefix = search_prefix();
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => site_name(),
        'url' => $siteUrl . '/',
        'potentialAction' => $searchPrefix ? [
            '@type' => 'SearchAction',
            'target' => 'https://www.google.com/search?q=' . $searchPrefix . '+{query}',
            'query-input' => 'required name=query'
        ] : null
    ]
];

$badgeBaseClass = 'inline-flex items-center rounded-full border border-slate-200 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.35em] text-brand-600 dark:border-slate-700 dark:text-brand-300';
$meta['og_image'] = $heroImage;
$meta['twitter_image'] = $heroImage;

$buildResponsiveSrcset = static function (string $url, array $widths): string {
    $updateWidth = static function (string $imageUrl, int $width): string {
        $parts = parse_url($imageUrl);
        if ($parts === false) {
            return $imageUrl;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['w'] = $width;
        $parts['query'] = http_build_query($query);

        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . ($pass !== '' ? ':' . $pass : '') . '@' : '';
        $path = $parts['path'] ?? '';
        $queryString = $parts['query'] ?? '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        if ($scheme !== '') {
            $base = $scheme . '://' . $auth . $host . $port;
        } elseif ($host !== '') {
            $base = '//' . $auth . $host . $port;
        } else {
            $base = '';
        }

        $rebuilt = $base . $path;
        if ($queryString !== '') {
            $rebuilt .= '?' . $queryString;
        }

        return $rebuilt . $fragment;
    };

    $sources = [];
    foreach ($widths as $width) {
        $sources[] = $updateWidth($url, $width) . ' ' . $width . 'w';
    }

    return implode(', ', $sources);
};
?>
<!--
    Default brand-agnostic intro: site name, tagline, single CTA. Sites can
    override the entire top of the homepage by adding content/pages/home.md
    (rendered as $intro just below). Sites wanting a more elaborate hero can
    fork this view or include marketing/bird-animation directly.
-->
<section class="border-b" style="background: var(--bg); color: var(--text); border-color: var(--border);">
    <div class="relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-16 text-center" id="latest">
        <h1 class="text-4xl sm:text-5xl font-semibold tracking-tight"><?= htmlspecialchars(site_name()) ?></h1>
        <?php $tagline = site_description() ?: site_tagline(); ?>
        <?php if ($tagline): ?>
        <p class="mt-4 text-lg max-w-2xl mx-auto" style="color: var(--text-mute);"><?= htmlspecialchars($tagline) ?></p>
        <?php endif; ?>
        <div class="mt-8">
            <button type="button" data-open-newsletter class="inline-flex items-center rounded-full bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">Subscribe to the newsletter</button>
        </div>
    </div>
</section>

<?php if (!empty($intro)): ?>
<!-- Operator-authored homepage intro from content/pages/home.md.
     Rendered between the brand hero and the curated stories grid so the
     same hero stays consistent across sites. -->
<section class="border-b" style="background: var(--bg); border-color: var(--border);">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-12">
        <article class="prose prose-slate lg:prose-lg max-w-none dark:prose-invert">
            <?= $intro ?>
        </article>
    </div>
</section>
<?php endif; ?>

<!-- Top stories below the hero (was the right column in the old hero grid) -->
<section class="border-y" style="background: var(--bg); border-color: var(--border);">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-sm font-semibold mb-6" style="color: var(--text);">Top stories</h2>
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php if (!empty($hero)): ?>
                <article class="rounded-xl border p-4 shadow-sm" style="background: var(--surface); border-color: var(--border);">
                    <?php if (!empty($hero['hero_image'])): ?>
                        <div class="relative h-40 -mx-4 -mt-4 mb-4 overflow-hidden bg-[var(--surface-mute)]">
                            <img src="<?= htmlspecialchars($hero['hero_image']) ?>" alt="<?= htmlspecialchars($hero['title']) ?>" loading="lazy" decoding="async" class="h-full w-full object-cover" />
                        </div>
                    <?php endif; ?>
                    <div class="text-[11px] font-semibold uppercase tracking-[0.35em]" style="color: var(--accent);">
                        <?php if (!empty($hero['date'])): ?><?= date('M j, Y', strtotime($hero['date'])) ?> · <?php endif; ?><?= htmlspecialchars(ucfirst($hero['category'])) ?>
                    </div>
                    <a href="/<?= htmlspecialchars($hero['category']) ?>/<?= htmlspecialchars($hero['slug']) ?>" class="mt-2 block text-base font-semibold line-clamp-2" style="color: var(--text);">
                        <?= htmlspecialchars($hero['title']) ?>
                    </a>
                    <p class="mt-2 text-sm line-clamp-2" style="color: var(--text-mute);"><?= htmlspecialchars($hero['description']) ?></p>
                </article>
            <?php endif; ?>
            <?php foreach (array_slice($topStories, 0, 2) as $story): ?>
                <article class="rounded-xl border p-4 shadow-sm" style="background: var(--surface); border-color: var(--border);">
                    <?php if (!empty($story['hero_image'])): ?>
                        <div class="relative h-40 -mx-4 -mt-4 mb-4 overflow-hidden bg-[var(--surface-mute)]">
                            <img src="<?= htmlspecialchars($story['hero_image']) ?>" alt="<?= htmlspecialchars($story['title']) ?>" loading="lazy" decoding="async" class="h-full w-full object-cover" />
                        </div>
                    <?php endif; ?>
                    <div class="text-[11px] font-semibold uppercase tracking-[0.35em]" style="color: var(--accent);">
                        <?php if (!empty($story['date'])): ?><?= date('M j, Y', strtotime($story['date'])) ?> · <?php endif; ?><?= htmlspecialchars(ucfirst($story['category'])) ?>
                    </div>
                    <a href="/<?= htmlspecialchars($story['category']) ?>/<?= htmlspecialchars($story['slug']) ?>" class="mt-2 block text-base font-semibold line-clamp-2" style="color: var(--text);">
                        <?= htmlspecialchars($story['title']) ?>
                    </a>
                    <p class="mt-2 text-sm line-clamp-2" style="color: var(--text-mute);"><?= htmlspecialchars($story['description']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="border-y border-slate-200 bg-white py-6 dark:border-slate-800/80 dark:bg-slate-900/70" id="newsletter-signup">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.35em] text-brand-600 dark:text-brand-300"><?= htmlspecialchars(site_name()) ?> Briefing</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">Weekly intelligence across automation, tools, and markets</h2>
            <p class="mt-3 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
                Join our Sunday briefing for actionable insights on AI automation, productivity stacks, wellness tech, and market shifts-minus the noise.
            </p>
            <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs text-slate-500 dark:text-slate-400">
                <li class="inline-flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>Curated by the <?= htmlspecialchars(site_name()) ?> editorial desk</li>
                <li class="inline-flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>Signals across automation, tools, wellness, and markets</li>
                <li class="inline-flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>Each issue includes next-step recommendations</li>
            </ul>
        </div>
        <form id="market-pulse-form" class="flex w-full max-w-md flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/80">
            <label for="market-pulse-email" class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-slate-400">Join the newsletter</label>
            <input id="market-pulse-email" name="email" type="email" required placeholder="you@example.com" class="w-full rounded-full border border-slate-300 px-4 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" />
            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:bg-brand-500 dark:hover:bg-brand-400 dark:focus-visible:ring-offset-slate-900">
                <span class="submit-text">Subscribe</span>
            </button>
            <p class="text-xs text-slate-400 dark:text-slate-500">By subscribing you agree to our <a class="underline decoration-dotted hover:text-brand-600" href="/privacy" target="_blank" rel="noopener">privacy policy</a>.</p>
        </form>
    </div>
</section>

<?php if (!empty($trending)): ?>
<section class="bg-white border-y border-slate-100 dark:bg-slate-900/80 dark:border-slate-800" aria-label="Trending topics">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="inline-flex items-center rounded-full bg-slate-900 text-white px-2.5 py-1 text-xs uppercase tracking-[0.35em] dark:bg-white dark:text-slate-900">Trending</span>
            <?php foreach ($trending as $item): ?>
                <a class="group inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 transition hover:border-brand-400 hover:text-brand-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-brand-300 dark:hover:text-brand-200 w-full sm:w-64" href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>">
                    <span class="flex-1 truncate" title="<?= htmlspecialchars($item['title']) ?>"><?= htmlspecialchars($item['title']) ?></span>
                    <span class="text-slate-400 transition group-hover:text-brand-500 dark:text-slate-500 dark:group-hover:text-brand-300" aria-hidden="true">→</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($latestFeed)): ?>
<section class="bg-slate-50 dark:bg-slate-900/70" id="latest-feed">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Latest stories</h2>
            <a href="/" class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 hover:text-brand-700">Browse archive</a>
        </div>
        <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($latestFeed as $article): ?>
                <article class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.35em] text-brand-600 dark:text-brand-300">
                        <?php if (!empty($article['date'])): ?><?= date('M j, Y', strtotime($article['date'])) ?> · <?php endif; ?><?= htmlspecialchars(ucfirst($article['category'])) ?>
                    </div>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        <a class="hover:text-brand-700" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>"><?= htmlspecialchars($article['title']) ?></a>
                    </h3>
                    <p class="mt-2 text-sm text-slate-600 line-clamp-3 dark:text-slate-400"><?= htmlspecialchars($article['description']) ?></p>
                    <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <?php if (!empty($article['reading_time'])): ?>
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <span><?= htmlspecialchars($article['reading_time']) ?></span>
                            </div>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <div class="flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                <circle cx="12" cy="12" r="2.25" />
                            </svg>
                            <span><?= number_format($metrics->getViews($article['slug'])) ?></span>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end">
                        <a class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-brand-500 dark:hover:bg-brand-400" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>">
                            Read more
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($editorsPicks)): ?>
<section class="bg-white dark:bg-slate-900" id="editors-picks">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.35em] text-slate-400">Curated</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Editors’ Picks</h2>
            </div>
            <a class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 hover:text-brand-700" href="#categories">See all categories</a>
        </div>
        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <?php foreach ($editorsPicks as $article): ?>
                <article class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <?php if (!empty($article['hero_image'])): ?>
                    <div class="relative aspect-[4/3] overflow-hidden">
                        <img class="h-full w-full object-cover" src="<?= htmlspecialchars($article['hero_image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" loading="lazy" decoding="async" />
                    </div>
                    <?php endif; ?>
                    <div class="flex flex-1 flex-col p-6">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.35em] text-brand-600 dark:text-brand-300"><?= htmlspecialchars(ucfirst($article['category'])) ?></div>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                            <a class="hover:text-brand-700" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>"><?= htmlspecialchars($article['title']) ?></a>
                        </h3>
                        <p class="mt-2 text-sm text-slate-600 line-clamp-3 dark:text-slate-400"><?= htmlspecialchars($article['description']) ?></p>
                        <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <?php if (!empty($article['reading_time'])): ?>
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span><?= htmlspecialchars($article['reading_time']) ?></span>
                                </div>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                    <circle cx="12" cy="12" r="2.25" />
                                </svg>
                                <span><?= number_format($metrics->getViews($article['slug'])) ?></span>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <a class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-brand-500 dark:hover:bg-brand-400" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>">
                                Read more
                                <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($marketPulse)): ?>
<section class="bg-slate-50 dark:bg-slate-900/70" id="market-pulse">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Market Pulse</h2>
            <a class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 hover:text-brand-700" href="/market">More briefs</a>
        </div>
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <?php foreach ($marketPulse as $column): ?>
                <article class="rounded-2xl bg-white p-6 ring-1 ring-slate-100 shadow-sm dark:bg-slate-900 dark:ring-slate-800">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($column['label']) ?></h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <?php foreach ($column['articles'] as $item): ?>
                            <li>
                                <a class="hover:text-brand-700" href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($column['articles'])): ?>
                            <li class="text-slate-400">Coming soon.</li>
                        <?php endif; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($playbookArticles)): ?>
<section class="bg-white dark:bg-slate-900" id="playbooks">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.35em] text-slate-400">Featured</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Playbooks &amp; Templates</h2>
            </div>
        </div>
        <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($playbookArticles as $article): ?>
                <article class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <?php if (!empty($article['hero_image'])): ?>
                    <div class="relative aspect-[4/3] overflow-hidden">
                        <img src="<?= htmlspecialchars($article['hero_image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" loading="lazy" decoding="async" class="h-full w-full object-cover" />
                    </div>
                    <?php endif; ?>
                    <div class="flex flex-1 flex-col p-6">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.35em] text-brand-600 dark:text-brand-300"><?= htmlspecialchars(ucfirst($article['category'])) ?></div>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                            <a class="hover:text-brand-700" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>"><?= htmlspecialchars($article['title']) ?></a>
                        </h3>
                        <p class="mt-2 text-sm text-slate-600 line-clamp-3 dark:text-slate-400"><?= htmlspecialchars($article['description']) ?></p>
                        <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <?php if (!empty($article['reading_time'])): ?>
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span><?= htmlspecialchars($article['reading_time']) ?></span>
                                </div>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                    <circle cx="12" cy="12" r="2.25" />
                                </svg>
                                <span><?= number_format($metrics->getViews($article['slug'])) ?></span>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <a class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-brand-500 dark:hover:bg-brand-400" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>">
                                Read more
                                <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="bg-slate-900 text-white" aria-label="Deals and offers" id="deals">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4">
        <?php if (!empty($dealsStrip)): ?>
            <div class="flex items-center gap-3 overflow-x-auto text-sm">
                <span class="shrink-0 inline-flex items-center rounded-full bg-brand-500 px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em]">Deals</span>
                <?php foreach ($dealsStrip as $index => $item): ?>
                    <?php if ($index > 0): ?>
                        <span class="text-slate-500">•</span>
                    <?php endif; ?>
                    <a class="shrink-0 text-white/80 transition hover:text-white" href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>">
                        <?= htmlspecialchars($item['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-dashed border-white/40 px-4 py-3 text-sm text-white/70">Partner offers will appear here soon.</div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($bestPicks)): ?>
<section class="bg-white dark:bg-slate-900" id="best-picks">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Best Picks &amp; Reviews</h2>
            <a class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 hover:text-brand-700" href="/reviews">All reviews</a>
        </div>
        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <?php foreach ($bestPicks as $article): ?>
                <article class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <?php if (!empty($article['hero_image'])): ?>
                    <div class="relative aspect-[16/9] overflow-hidden">
                        <img src="<?= htmlspecialchars($article['hero_image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" loading="lazy" decoding="async" class="h-full w-full object-cover" />
                    </div>
                    <?php endif; ?>
                    <div class="flex flex-1 flex-col p-6">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.35em] text-brand-600 dark:text-brand-300"><?= htmlspecialchars(ucfirst($article['category'])) ?></div>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                            <a class="hover:text-brand-700" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>"><?= htmlspecialchars($article['title']) ?></a>
                        </h3>
                        <p class="mt-2 text-sm text-slate-600 line-clamp-3 dark:text-slate-400"><?= htmlspecialchars($article['description']) ?></p>
                        <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <?php if (!empty($article['reading_time'])): ?>
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span><?= htmlspecialchars($article['reading_time']) ?></span>
                                </div>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                    <circle cx="12" cy="12" r="2.25" />
                                </svg>
                                <span><?= number_format($metrics->getViews($article['slug'])) ?></span>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <a class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-brand-500 dark:hover:bg-brand-400" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>">
                                Read more
                                <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="bg-slate-50 dark:bg-slate-900/70" id="faceoffs">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Face-Offs</h2>
            <a class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 hover:text-brand-700" href="/face-offs">Browse all</a>
        </div>
        <?php if (!empty($faceOffs)): ?>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                <?php foreach ($faceOffs as $article): ?>
                    <article class="flex h-full flex-col justify-between rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.35em] text-brand-600 dark:text-brand-300">Comparison</p>
                            <h3 class="mt-3 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                <a class="hover:text-brand-700" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>"><?= htmlspecialchars($article['title']) ?></a>
                            </h3>
                            <p class="mt-2 text-sm text-slate-600 line-clamp-3 dark:text-slate-400"><?= htmlspecialchars($article['description']) ?></p>
                        </div>
                        <div class="mt-5 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <?php if (!empty($article['reading_time'])): ?>
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span><?= htmlspecialchars($article['reading_time']) ?></span>
                                </div>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                    <circle cx="12" cy="12" r="2.25" />
                                </svg>
                                <span><?= number_format($metrics->getViews($article['slug'])) ?></span>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <a class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-brand-500 dark:hover:bg-brand-400" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>">Read comparison<span aria-hidden="true" class="ml-1">→</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 p-6 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">Head-to-head comparisons will be published soon.</p>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($howTos)): ?>
<section class="bg-white dark:bg-slate-900" id="howtos">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">How-Tos &amp; Troubleshooting</h2>
            <a class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 hover:text-brand-700" href="/how-tos">View all guides</a>
        </div>
        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <?php foreach ($howTos as $article): ?>
                <article class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.35em] text-brand-600 dark:text-brand-300">How-To</div>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        <a class="hover:text-brand-700" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>"><?= htmlspecialchars($article['title']) ?></a>
                    </h3>
                    <p class="mt-2 text-sm text-slate-600 line-clamp-3 dark:text-slate-400"><?= htmlspecialchars($article['description']) ?></p>
                    <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <span>
                            <?php if (!empty($article['reading_time'])): ?>
                                <?= htmlspecialchars($article['reading_time']) ?>
                            <?php endif; ?>
                        </span>
                        <div class="flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                <circle cx="12" cy="12" r="2.25" />
                            </svg>
                            <span><?= number_format($metrics->getViews($article['slug'])) ?></span>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end">
                        <a class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-brand-500 dark:hover:bg-brand-400" href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>">
                            Read more
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($mostRead)): ?>
<section class="bg-white dark:bg-slate-900" id="most-read">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Most Read This Week</h2>
            <a href="/" class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 hover:text-brand-700">Full ranking</a>
        </div>
        <ol class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3 list-decimal list-inside text-slate-700 dark:text-slate-300">
            <?php foreach ($mostRead as $item): ?>
                <li class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-600 dark:text-brand-300"><?= htmlspecialchars(ucfirst($item['category'])) ?></p>
                    <div class="flex-1 mt-2">
                        <a class="block text-sm font-semibold text-slate-900 hover:text-brand-600 dark:text-slate-100 dark:hover:text-brand-400" href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                    </div>
                    <div class="mt-4 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <?php if (!empty($item['reading_time'])): ?>
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <span><?= htmlspecialchars($item['reading_time']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                <circle cx="12" cy="12" r="2.25" />
                            </svg>
                            <span><?= number_format($metrics->getViews($item['slug'])) ?></span>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<section class="bg-white dark:bg-slate-900" id="categories">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-slate-900">Browse by category</h2>
                <p class="mt-2 text-base text-slate-600"><?= htmlspecialchars(site_description()) ?></p>
            </div>
            <a href="#newsletter" class="inline-flex items-center rounded-full border border-brand-500 px-4 py-2 text-sm font-semibold text-brand-600 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">Become an insider</a>
        </div>

        <div class="mt-10 grid gap-6 md:grid-cols-2">
            <?php foreach ($categoryHighlights as $category => $articles): ?>
                <section class="rounded-xl border border-slate-100 bg-slate-50 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100"><?= htmlspecialchars(ucfirst($category)) ?></h3>
                        <a href="/<?= htmlspecialchars($category) ?>" class="text-xs font-semibold uppercase tracking-[0.35em] text-brand-600 hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">View all</a>
                    </div>
                    <div class="mt-4 space-y-3">
                        <?php foreach (array_slice($articles, 0, 2) as $article): ?>
                            <article class="flex items-center gap-3">
                                <?php if (!empty($article['hero_image'])): ?>
                                <div class="w-24 aspect-[4/3] flex-shrink-0 overflow-hidden rounded-xl bg-slate-200 dark:bg-slate-800">
                                    <img src="<?= htmlspecialchars($article['hero_image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" loading="lazy" decoding="async" sizes="(min-width:1024px) 180px, (min-width:768px) 200px, 40vw" class="h-full w-full object-cover" />
                                </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <a href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>" class="text-sm font-semibold text-slate-900 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:text-slate-100 dark:focus-visible:ring-offset-slate-900">
                                        <?= htmlspecialchars($article['title']) ?>
                                    </a>
                                    <p class="mt-1 text-xs text-slate-500 line-clamp-2 dark:text-slate-400"><?= htmlspecialchars($article['description']) ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (count($articles) > 2): ?>
                            <a href="/<?= htmlspecialchars($category) ?>" class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">Explore more<span aria-hidden="true">→</span></a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="bg-slate-900" id="newsletter">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20 text-white">
        <div class="grid gap-10 lg:grid-cols-[1.2fr,0.8fr]">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.4em] text-brand-200">Inside track</p>
                <h2 class="mt-3 text-3xl font-semibold lg:text-4xl">Weekly memo with experiments you can ship on Monday.</h2>
                <p class="mt-4 text-base text-white/70 max-w-2xl">Every Sunday we distill the most useful AI launches, teardown go-to-market plays, and share templates to operationalize them fast.</p>
                <ul class="mt-6 grid gap-3 text-sm text-white/70 sm:grid-cols-2">
                    <li class="flex items-center gap-2"><span class="inline-flex h-2 w-2 rounded-full bg-brand-400"></span> Product teardown + market pulse</li>
                    <li class="flex items-center gap-2"><span class="inline-flex h-2 w-2 rounded-full bg-brand-400"></span> Automation experiments with ROI math</li>
                    <li class="flex items-center gap-2"><span class="inline-flex h-2 w-2 rounded-full bg-brand-400"></span> Toolkits &amp; swipe files</li>
                    <li class="flex items-center gap-2"><span class="inline-flex h-2 w-2 rounded-full bg-brand-400"></span> Hiring news &amp; notable plays</li>
                </ul>
            </div>
            <form class="rounded-2xl bg-white/10 p-8 shadow-2xl backdrop-blur" action="#" method="post">
                <label class="block text-sm font-semibold uppercase tracking-widest text-white/60" for="newsletter-email">Join the list</label>
                <input id="newsletter-email" name="email" type="email" placeholder="you@example.com" required class="mt-3 w-full rounded-full border border-white/20 bg-white/10 px-4 py-3 text-base text-white placeholder:text-white/60 focus:border-brand-200 focus:outline-none focus:ring-2 focus:ring-brand-200" />
                <button class="mt-4 w-full rounded-full bg-brand-500 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-200 focus-visible:ring-offset-2 focus-visible:ring-offset-brand-700/10" type="submit">Subscribe now</button>
                <p class="mt-4 text-xs text-white/60">No spam. Just insights we use to advise teams and build products.</p>
            </form>
        </div>
    </div>
</section>

<section class="bg-white dark:bg-slate-900" id="latest-analysis">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Latest analysis</h2>
            <a href="/" class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-600 hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">See everything</a>
        </div>
        <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach (array_slice($latest, 0, 6) as $article): ?>
                <article class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <?php if (!empty($article['hero_image'])): ?>
                    <div class="relative aspect-[4/3] overflow-hidden">
                        <img src="<?= htmlspecialchars($article['hero_image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" loading="lazy" decoding="async" srcset="<?= htmlspecialchars($buildResponsiveSrcset($article['hero_image'], [640, 960, 1400])) ?>" sizes="(min-width:1024px) 33vw, (min-width:768px) 45vw, 100vw" class="h-full w-full object-cover" />
                    </div>
                    <?php endif; ?>
                    <div class="flex flex-1 flex-col p-6">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.35em] text-brand-600 dark:text-brand-300">
                            <?php if (!empty($article['date'])): ?><?= date('M j, Y', strtotime($article['date'])) ?> · <?php endif; ?><?= htmlspecialchars(ucfirst($article['category'])) ?>
                        </div>
                        <h3 class="mt-3 text-lg font-semibold text-slate-900 flex-1 dark:text-slate-100">
                            <a href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>" class="hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
                                <?= htmlspecialchars($article['title']) ?>
                            </a>
                        </h3>
                        <p class="mt-3 text-sm text-slate-600 line-clamp-3 dark:text-slate-400"><?= htmlspecialchars($article['description']) ?></p>
                        <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <?php if (!empty($article['reading_time'])): ?>
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span><?= htmlspecialchars($article['reading_time']) ?></span>
                                </div>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                    <circle cx="12" cy="12" r="2.25" />
                                </svg>
                                <span><?= number_format($metrics->getViews($article['slug'])) ?></span>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <a href="/<?= htmlspecialchars($article['category']) ?>/<?= htmlspecialchars($article['slug']) ?>" class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-brand-500 dark:hover:bg-brand-400">Read insight<span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
