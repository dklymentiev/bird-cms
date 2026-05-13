<?php
/** @var string $query */
/** @var array $results */
/** @var array $latest */

$pageTitle = $query ? page_title("Search: {$query}") : page_title("Search");
$meta = [
    "description" => "Search " . site_name() . " articles",
    "robots" => "noindex, follow",
];
?>

<section class="bg-gradient-to-b from-white to-slate-100/60 dark:from-slate-900 dark:to-slate-900/40">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-12">
        <h1 class="text-3xl font-semibold text-slate-900 dark:text-slate-100">Search</h1>

        <form action="/search" method="get" class="mt-6">
            <div class="flex gap-3">
                <input
                    type="search"
                    name="q"
                    value="<?= htmlspecialchars($query) ?>"
                    placeholder="Search articles..."
                    class="flex-1 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    autofocus
                />
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300">
                    Search
                </button>
            </div>
        </form>

        <?php if ($query): ?>
            <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">
                <?= count($results) ?> result<?= count($results) !== 1 ? "s" : "" ?> for "<?= htmlspecialchars($query) ?>"
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="bg-white dark:bg-slate-900">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-12">
        <?php if (empty($results) && $query): ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">No results found</h2>
                <p class="mt-2 text-slate-500 dark:text-slate-400">Try different keywords or browse categories below.</p>
            </div>
        <?php elseif (!empty($results)): ?>
            <div class="space-y-6">
                <?php foreach ($results as $item): ?>
                    <article class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900/70">
                        <div class="flex items-start gap-4">
                            <?php if (!empty($item["hero_image"])): ?>
                                <div class="hidden sm:block flex-shrink-0 w-32 h-20 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800">
                                    <img src="<?= htmlspecialchars($item["hero_image"]) ?>" alt="" class="w-full h-full object-cover" loading="lazy" />
                                </div>
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="uppercase tracking-wider text-brand-600 dark:text-brand-300"><?= htmlspecialchars(ucfirst($item["category"])) ?></span>
                                    <?php if (!empty($item["date"])): ?>
                                        <span class="text-slate-400">•</span>
                                        <span class="text-slate-500 dark:text-slate-400"><?= date("M j, Y", strtotime($item["date"])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <h2 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    <a href="/<?= htmlspecialchars($item["category"]) ?>/<?= htmlspecialchars($item["slug"]) ?>" class="hover:text-brand-600 dark:hover:text-brand-300">
                                        <?= htmlspecialchars($item["title"]) ?>
                                    </a>
                                </h2>
                                <p class="mt-2 text-sm text-slate-600 line-clamp-2 dark:text-slate-400"><?= htmlspecialchars($item["description"]) ?></p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$query): ?>
            <div class="text-center py-8">
                <p class="text-slate-500 dark:text-slate-400">Enter a search term to find articles.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($latest)): ?>
            <div class="mt-16 pt-8 border-t border-slate-200 dark:border-slate-800">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Latest articles</h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($latest as $post): ?>
                        <a href="/<?= htmlspecialchars($post["category"]) ?>/<?= htmlspecialchars($post["slug"]) ?>" class="block rounded-xl bg-slate-50 p-4 transition hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800">
                            <div class="text-xs uppercase tracking-wider text-brand-600 dark:text-brand-300"><?= htmlspecialchars(ucfirst($post["category"])) ?></div>
                            <div class="mt-2 font-semibold text-slate-900 line-clamp-2 dark:text-slate-100"><?= htmlspecialchars($post["title"]) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
