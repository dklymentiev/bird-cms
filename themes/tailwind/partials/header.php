<?php
/** @var array $config */
/** @var array $categoriesList */

$branding = config('site.branding', []);
$logoImage = $branding['logo_image'] ?? null;
$logoPrimary = $branding['logo_text']['primary'] ?? substr(site_name(), 0, 3);
$logoSecondary = $branding['logo_text']['secondary'] ?? '';
$logoInitials = strtoupper(substr($logoPrimary, 0, 1) . ($logoSecondary ? substr($logoSecondary, 0, 1) : substr($logoPrimary, 1, 1)));
?>
<header class="backdrop-blur-md sticky top-0 z-40" style="background: color-mix(in oklab, var(--bg) 80%, transparent); border-bottom: 1px solid var(--border);">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-4">
                <?php if ($logoImage): ?>
                <a href="/" class="brand-logo flex items-center gap-2" aria-label="<?= htmlspecialchars(site_name()) ?> home">
                    <img src="<?= htmlspecialchars($logoImage) ?>" width="36" height="36" alt="">
                    <span class="text-base font-semibold text-slate-900 dark:text-slate-100"><?= htmlspecialchars(site_name()) ?></span>
                </a>
                <?php else: ?>
                <a href="/" class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-600 text-base font-semibold text-white"><?= htmlspecialchars($logoInitials) ?></span>
                    <div class="flex flex-col leading-tight">
                        <span class="text-base font-semibold uppercase tracking-[0.2em] text-slate-900 transition hover:text-brand-600 dark:text-slate-100"><?= htmlspecialchars($logoPrimary) ?></span>
                        <?php if ($logoSecondary): ?>
                        <span class="text-base font-semibold uppercase tracking-[0.2em] text-slate-900 transition hover:text-brand-600 dark:text-slate-100"><?= htmlspecialchars($logoSecondary) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endif; ?>
            </div>
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600 dark:text-slate-300" aria-label="Primary">
                <?php foreach (config('site.navigation', []) as $navItem): ?>
                <a href="<?= htmlspecialchars($navItem['href']) ?>" class="hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900"><?= htmlspecialchars($navItem['label']) ?></a>
                <?php endforeach; ?>
                <button type="button" data-open-newsletter class="inline-flex items-center rounded-full border border-transparent px-3 py-1 text-sm font-medium text-slate-600 transition hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:text-slate-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900">Newsletter</button>
            </nav>
            <div class="flex items-center gap-2">
                <button type="button" id="search-toggle" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="Open search">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                    </svg>
                </button>
                <button type="button" id="theme-toggle" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="Toggle dark mode">
                    <svg class="h-5 w-5 dark:hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25M18.364 5.636 16.95 7.05M21 12h-2.25M18.364 18.364 16.95 16.95M12 18.75V21M7.05 16.95 5.636 18.364M5.25 12H3M7.05 7.05 5.636 5.636M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                    </svg>
                    <svg class="hidden h-5 w-5 dark:inline" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/>
                    </svg>
                </button>
                <div class="hidden md:flex items-center gap-2 pr-1">
                    <a href="<?= htmlspecialchars(config('social.rss')) ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="RSS feed">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6 18.75A2.25 2.25 0 1 1 3.75 21 2.25 2.25 0 0 1 6 18.75Zm-3-6A2.25 2.25 0 0 1 5.25 10.5 11.25 11.25 0 0 1 16.5 21 2.25 2.25 0 0 1 12 21 6.75 6.75 0 0 0 5.25 14.25 2.25 2.25 0 0 1 3 12.75Zm0-6A2.25 2.25 0 0 1 5.25 4.5 17.25 17.25 0 0 1 22.5 21a2.25 2.25 0 0 1-3.75 0 12.75 12.75 0 0 0-12-12A2.25 2.25 0 0 1 3 6.75Z" />
                        </svg>
                    </a>
                    <a href="<?= htmlspecialchars(config('social.x')) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="X profile">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.98 3H17.9l-4.35 5.78L9.9 3H3.02l6.94 9.8L3 21h3.08l4.73-6.28L14.1 21h6.87l-6.96-9.86L20.98 3Z" />
                        </svg>
                    </a>
                    <a href="<?= htmlspecialchars(config('social.linkedin')) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="LinkedIn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M4.5 3C3.67 3 3 3.67 3 4.5S3.67 6 4.5 6 6 5.33 6 4.5 5.33 3 4.5 3ZM3 8h3v13H3V8Zm6 0h2.89v1.78h.04c.4-.76 1.38-1.56 2.84-1.56 3.03 0 3.6 1.99 3.6 4.58V21h-3v-7.16c0-1.71-.03-3.9-2.38-3.9-2.38 0-2.75 1.86-2.75 3.78V21H9V8Z" />
                        </svg>
                    </a>
                    <a href="<?= htmlspecialchars(config('social.facebook')) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9.19795 21.5H13.198V13.4901H16.8021L17.198 9.50977H13.198V7.5C13.198 6.94772 13.6457 6.5 14.198 6.5H17.198V2.5H14.198C11.4365 2.5 9.19795 4.73858 9.19795 7.5V9.50977H7.19795L6.80206 13.4901H9.19795V21.5Z" />
                        </svg>
                    </a>
                    <a href="<?= htmlspecialchars(config('social.pinterest')) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="Pinterest">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.477 2 2 6.477 2 12c0 4.237 2.636 7.855 6.356 9.312-.088-.791-.167-2.005.035-2.868.182-.78 1.172-4.97 1.172-4.97s-.299-.6-.299-1.486c0-1.39.806-2.428 1.81-2.428.852 0 1.264.64 1.264 1.408 0 .858-.545 2.14-.828 3.33-.236.995.5 1.807 1.48 1.807 1.778 0 3.144-1.874 3.144-4.58 0-2.393-1.72-4.068-4.177-4.068-2.845 0-4.515 2.135-4.515 4.34 0 .859.331 1.781.745 2.281a.3.3 0 0 1 .069.288l-.278 1.133c-.044.183-.145.223-.335.134-1.249-.581-2.03-2.407-2.03-3.874 0-3.154 2.292-6.052 6.608-6.052 3.469 0 6.165 2.473 6.165 5.776 0 3.447-2.173 6.22-5.19 6.22-1.013 0-1.965-.525-2.291-1.148l-.623 2.378c-.226.869-.835 1.958-1.244 2.621.937.29 1.931.446 2.962.446 5.523 0 10-4.477 10-10S17.523 2 12 2Z" />
                        </svg>
                    </a>
                </div>
                <button type="button" data-open-newsletter class="hidden sm:inline-flex items-center rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
                    Join newsletter
                </button>
            </div>
        </div>
    </div>

</header>
