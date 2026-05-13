<?php
/** @var string|null $slug */
$pageTitle = page_title('Page not found');
$meta = [
    'description' => 'The page you requested is unavailable. Explore the latest ' . site_name() . ' analysis and insights instead.',
    'og_title' => $pageTitle,
    'og_description' => 'Explore the latest ' . site_name() . ' analysis and insights.',
    'og_image' => default_og_image(),
    'twitter_image' => default_og_image(),
    'canonical' => rtrim(config('site_url'), '/') . ($_SERVER['REQUEST_URI'] ?? '/404'),
    'lang' => 'en'
];
?>
<section class="bg-gradient-to-b from-white via-brand-50/40 to-white min-h-[60vh] dark:from-slate-900 dark:via-slate-900 dark:to-slate-900">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-24 text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.4em] text-brand-600 dark:text-brand-300">Error 404</p>
        <h1 class="mt-6 text-4xl font-semibold text-slate-900 dark:text-slate-100">We can’t find that story yet</h1>
        <p class="mt-4 text-base text-slate-600 dark:text-slate-400">
            <?= isset($slug) ? 'Request for “' . htmlspecialchars($slug) . '” returned no results.' : 'The page you’re looking for has moved or never existed.' ?>
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="/" class="inline-flex items-center rounded-full bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">Back to homepage</a>
            <a href="#latest" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:text-slate-200 dark:focus-visible:ring-offset-slate-900">See latest articles</a>
        </div>
    </div>
</section>
