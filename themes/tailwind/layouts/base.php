<?php
/** @var string $content */
/** @var array $config */
/** @var \App\Theme\ThemeManager $theme */
/** @var string|null $pageTitle */
/** @var array $meta */
/** @var array $structuredData */

$meta = $meta ?? [];
$structuredData = $structuredData ?? [];

$siteUrl = rtrim($config['site_url'], '/');
$requestPath = $_SERVER['REQUEST_URI'] ?? '/';
$canonical = $meta['canonical'] ?? $siteUrl . ($requestPath === '/' ? '' : $requestPath);
$description = $meta['description'] ?? site_description();
$ogImage = $meta['og_image'] ?? default_og_image();
$pageTitle = $pageTitle ?? $config['site_name'];
$lang = $meta['lang'] ?? 'en';
$alternateLocales = $meta['alternates'] ?? [];

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" class="scroll-smooth">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>" />
    <meta name="description" content="<?= htmlspecialchars($description) ?>" />
    <?= robots_meta_for_current_url() ?>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#6366f1" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(site_name()) ?>" />
    <link rel="apple-touch-icon" href="/assets/brand/icon-192.png" />

    <meta property="og:type" content="<?= htmlspecialchars($meta['og_type'] ?? 'website') ?>" />
    <meta property="og:title" content="<?= htmlspecialchars($meta['og_title'] ?? $pageTitle) ?>" />
    <meta property="og:description" content="<?= htmlspecialchars($meta['og_description'] ?? $description) ?>" />
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>" />
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>" />
    <meta property="og:site_name" content="<?= htmlspecialchars($config['site_name']) ?>" />

    <?php if (($meta['og_type'] ?? '') === 'article'): ?>
        <?php if (!empty($meta['date'])): ?>
            <meta property="article:published_time" content="<?= htmlspecialchars(date(DATE_ATOM, strtotime($meta['date']))) ?>" />
        <?php endif; ?>
        <?php if (!empty($meta['updated'])): ?>
            <meta property="article:modified_time" content="<?= htmlspecialchars(date(DATE_ATOM, strtotime($meta['updated']))) ?>" />
        <?php endif; ?>
        <?php if (!empty($meta['author'])): ?>
            <meta property="article:author" content="<?= htmlspecialchars((string) $meta['author']) ?>" />
        <?php endif; ?>
        <?php if (!empty($meta['category'])): ?>
            <meta property="article:section" content="<?= htmlspecialchars(ucfirst((string) $meta['category'])) ?>" />
        <?php endif; ?>
        <?php foreach ((array) ($meta['tags'] ?? []) as $tag): ?>
            <meta property="article:tag" content="<?= htmlspecialchars((string) $tag) ?>" />
        <?php endforeach; ?>
    <?php endif; ?>

    <meta name="twitter:card" content="<?= htmlspecialchars($meta['twitter_card'] ?? 'summary_large_image') ?>" />
    <meta name="twitter:title" content="<?= htmlspecialchars($meta['twitter_title'] ?? $pageTitle) ?>" />
    <meta name="twitter:description" content="<?= htmlspecialchars($meta['twitter_description'] ?? $description) ?>" />
    <meta name="twitter:image" content="<?= htmlspecialchars($meta['twitter_image'] ?? $ogImage) ?>" />

    <?php foreach ($alternateLocales as $locale => $href): ?>
        <link rel="alternate" hreflang="<?= htmlspecialchars($locale) ?>" href="<?= htmlspecialchars($href) ?>" />
    <?php endforeach; ?>

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <!-- Font loaded asynchronously to prevent render blocking -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'" />
    <noscript><link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet" /></noscript>

    <!-- Optional per-site brand tokens / site overrides. Only emitted when
         the site actually ships public/assets/frontend/*.css. -->
    <?php
        $brandCssPath = SITE_ROOT . '/public/assets/frontend/brand.css';
        $siteCssPath  = SITE_ROOT . '/public/assets/frontend/site.css';
    ?>
    <?php if (is_file($brandCssPath)): ?>
    <link rel="stylesheet" href="/assets/frontend/brand.css?v=<?= filemtime($brandCssPath) ?>" />
    <?php endif; ?>
    <?php if (is_file($siteCssPath)): ?>
    <link rel="stylesheet" href="/assets/frontend/site.css?v=<?= filemtime($siteCssPath) ?>" />
    <?php endif; ?>

    <!-- Tailwind CSS via CDN -->
    <?= tailwind_cdn_script() ?>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
    <script>
        // Sync Tailwind dark class + Bird brand data-theme attribute. Inline so
        // the page never flashes the wrong palette on first paint. Bird CMS
        // defaults to DARK -- only an explicit "light" preference (or
        // prefers-color-scheme: light when user has not chosen) opts out.
        (function () {
            var pref = localStorage.theme;
            var isDark = pref === 'dark'
                || (!pref && !window.matchMedia('(prefers-color-scheme: light)').matches);
            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        })();
    </script>
    <?php if (!empty($structuredData)): ?>
        <?php foreach ((array) $structuredData as $schema): ?>
            <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars(site_name()) ?> RSS Feed" href="/rss.xml" />
</head>
<body class="bg-slate-50 text-slate-900 text-lg dark:bg-slate-900 dark:text-slate-100">
    <div class="min-h-screen flex flex-col">
        <?php
        $categoriesList = $categoriesList ?? [];
        $theme->partial('header', ['config' => $config, 'categoriesList' => $categoriesList, 'pageTitle' => $pageTitle]);
        $theme->partial('subnav', ['categoriesList' => $categoriesList]);
        ?>
        <div id="search-overlay" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/80 px-6 py-8 backdrop-blur-sm" role="dialog" aria-labelledby="search-dialog-title" aria-modal="true">
            <div class="w-full max-w-2xl rounded-3xl bg-white p-8 shadow-xl dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h2 id="search-dialog-title" class="text-lg font-semibold text-slate-900 dark:text-slate-100">Search <?= htmlspecialchars(site_name()) ?></h2>
                    <button type="button" id="search-close" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="Close search">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M6 18 18 6" />
                        </svg>
                    </button>
                </div>
                <div class="mt-6 relative">
                    <label for="global-search" class="sr-only">Search query</label>
                    <input id="global-search" type="search" autocomplete="off" placeholder="Search articles..." class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500" />
                    <div id="search-results" class="absolute left-0 right-0 top-full mt-2 hidden max-h-80 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"></div>
                </div>
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Start typing to search articles...</p>
            </div>
        </div>
        <div id="newsletter-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/80 px-6 py-8 backdrop-blur-sm" role="dialog" aria-labelledby="newsletter-modal-title" aria-modal="true">
            <div class="w-full max-w-md rounded-3xl bg-white p-8 shadow-xl dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h2 id="newsletter-modal-title" class="text-lg font-semibold text-slate-900 dark:text-slate-100">Newsletter</h2>
                    <button type="button" id="newsletter-modal-close" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M6 18 18 6" />
                        </svg>
                    </button>
                </div>
                <div id="newsletter-modal-body" class="mt-6 text-center">
                    <p id="newsletter-modal-message" class="text-base text-slate-700 dark:text-slate-300"></p>
                    <button id="newsletter-modal-ok" class="mt-6 inline-flex items-center justify-center rounded-full bg-brand-600 px-12 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
                        OK
                    </button>
                </div>
            </div>
        </div>

        <div id="newsletter-signup-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/80 px-6 py-8 backdrop-blur-sm" role="dialog" aria-labelledby="newsletter-signup-title" aria-modal="true">
            <div class="w-full max-w-lg rounded-3xl bg-white p-8 shadow-xl dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h2 id="newsletter-signup-title" class="text-lg font-semibold text-slate-900 dark:text-slate-100">Join the <?= htmlspecialchars(site_name()) ?> briefing</h2>
                    <button type="button" id="newsletter-signup-close" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-brand-500 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-300 dark:hover:text-brand-200 dark:focus-visible:ring-offset-slate-900" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M6 18 18 6" />
                        </svg>
                    </button>
                </div>
                <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">Get weekly briefings on automation, tools, wellness tech, and market shifts-curated by the <?= htmlspecialchars(site_name()) ?> editorial desk.</p>
                <form id="modal-newsletter-form" data-close-on-success="true" class="mt-6 space-y-4">
                    <div>
                        <label for="modal-newsletter-email" class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-slate-400">Email</label>
                        <input id="modal-newsletter-email" name="email" type="email" required placeholder="you@example.com" class="mt-2 w-full rounded-full border border-slate-300 px-4 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" />
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
                        <span class="submit-text">Subscribe</span>
                    </button>
                    <p class="text-xs text-slate-400 dark:text-slate-500">No spam. Unsubscribe any time. Read our <a class="underline decoration-dotted hover:text-brand-600" href="/privacy" target="_blank" rel="noopener">privacy policy</a>.</p>
                </form>
            </div>
        </div>

        <main class="flex-1">
            <?= $content ?>
        </main>
        <?php
        $footerCategories = $categoriesList ?? [];
        $theme->partial('footer', [
            'config' => $config,
            'categoriesList' => $footerCategories,
        ]);
        ?>
    </div>

    <div
        id="cookie-consent-banner"
        class="hidden fixed inset-x-0 bottom-0 z-50 border-t border-slate-200 bg-white/95 px-4 py-4 shadow-lg backdrop-blur-sm transition dark:border-slate-700 dark:bg-slate-900/95"
        role="dialog"
        aria-label="Cookie consent"
        aria-live="polite"
    >
        <div class="mx-auto flex max-w-5xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-slate-700 dark:text-slate-200">
                We use cookies to analyze traffic and improve the site experience. Learn more in our <a class="font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-300" href="/cookies" target="_blank" rel="noopener">cookie policy</a>.
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button
                    type="button"
                    id="cookie-consent-decline"
                    class="inline-flex items-center justify-center rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100 dark:focus-visible:ring-offset-slate-900"
                >
                    Use essential only
                </button>
                <button
                    type="button"
                    id="cookie-consent-accept"
                    class="inline-flex items-center justify-center rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900"
                >
                    Accept all cookies
                </button>
            </div>
        </div>
    </div>

    <!-- Scroll to top button -->
    <button
        id="scroll-to-top"
        class="fixed bottom-8 right-4 sm:right-6 lg:right-12 xl:right-20 z-40 hidden h-12 w-12 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg transition hover:bg-brand-700 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900"
        aria-label="Scroll to top"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
    </button>
    <script>
        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        }

        document.addEventListener('DOMContentLoaded', () => {
            const SEARCH_PREFIX = '<?= search_prefix() ?>';

            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    const isDark = document.documentElement.classList.toggle('dark');
                    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
                    localStorage.setItem('theme', isDark ? 'dark' : 'light');
                });
            }

            const searchToggle = document.getElementById('search-toggle');
            const searchOverlay = document.getElementById('search-overlay');
            const searchClose = document.getElementById('search-close');
            const searchInput = document.getElementById('global-search');
            const searchResults = document.getElementById('search-results');

            const toggleSearch = (show) => {
                if (!searchOverlay) return;
                searchOverlay.classList.toggle('hidden', !show);
                searchOverlay.classList.toggle('flex', show);
                if (show && searchInput) {
                    requestAnimationFrame(() => searchInput.focus());
                }
                if (!show && searchResults) {
                    searchResults.classList.add('hidden');
                    searchResults.innerHTML = '';
                }
            };

            // Live search
            let searchTimeout;
            if (searchInput && searchResults) {
                // Enter key navigates to search page
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const q = searchInput.value.trim();
                        if (q.length >= 2) {
                            window.location.href = '/search?q=' + encodeURIComponent(q);
                        }
                    }
                });

                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    const query = searchInput.value.trim();
                    if (query.length < 2) {
                        searchResults.classList.add('hidden');
                        searchResults.innerHTML = '';
                        return;
                    }
                    searchTimeout = setTimeout(async () => {
                        try {
                            const res = await fetch('/api/search.php?q=' + encodeURIComponent(query));
                            const data = await res.json();
                            if (data.results && data.results.length > 0) {
                                searchResults.innerHTML = data.results.map(r => `
                                    <a href="${r.url}" class="block px-4 py-3 border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                        <div class="text-xs uppercase tracking-wider text-brand-600 dark:text-brand-300">${r.category}</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-slate-100">${r.title}</div>
                                        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400 line-clamp-1">${r.description}</div>
                                    </a>
                                `).join('');
                                searchResults.classList.remove('hidden');
                            } else {
                                searchResults.innerHTML = '<div class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">No results found</div>';
                                searchResults.classList.remove('hidden');
                            }
                        } catch (e) {
                            searchResults.innerHTML = '<div class="px-4 py-6 text-center text-slate-500">Search error</div>';
                            searchResults.classList.remove('hidden');
                        }
                    }, 200);
                });
            }

            if (searchToggle) {
                searchToggle.addEventListener('click', () => toggleSearch(true));
            }
            if (searchClose) {
                searchClose.addEventListener('click', () => toggleSearch(false));
            }
            if (searchOverlay) {
                searchOverlay.addEventListener('click', (event) => {
                    if (event.target === searchOverlay) {
                        toggleSearch(false);
                    }
                });
            }

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    toggleSearch(false);
                }
            });

            // Scroll to top button
            const scrollToTopBtn = document.getElementById('scroll-to-top');
            if (scrollToTopBtn) {
                // Show/hide button based on scroll position
                const toggleScrollButton = () => {
                    const scrolled = window.scrollY > 300;
                    scrollToTopBtn.classList.toggle('hidden', !scrolled);
                    scrollToTopBtn.classList.toggle('flex', scrolled);
                };

                // Smooth scroll to top
                scrollToTopBtn.addEventListener('click', () => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });

                // Listen to scroll events
                window.addEventListener('scroll', toggleScrollButton);
                toggleScrollButton(); // Check initial state
            }

            // Newsletter signup modal
            const newsletterSignupModal = document.getElementById('newsletter-signup-modal');
            const newsletterSignupClose = document.getElementById('newsletter-signup-close');
            const openNewsletterSignup = () => {
                if (newsletterSignupModal) {
                    newsletterSignupModal.classList.remove('hidden');
                    newsletterSignupModal.classList.add('flex');
                }
            };
            const closeNewsletterSignup = () => {
                if (newsletterSignupModal) {
                    newsletterSignupModal.classList.add('hidden');
                    newsletterSignupModal.classList.remove('flex');
                }
            };
            if (newsletterSignupModal) {
                newsletterSignupModal.addEventListener('click', (event) => {
                    if (event.target === newsletterSignupModal) {
                        closeNewsletterSignup();
                    }
                });
            }
            if (newsletterSignupClose) {
                newsletterSignupClose.addEventListener('click', closeNewsletterSignup);
            }
            document.querySelectorAll('[data-open-newsletter]').forEach((trigger) => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    openNewsletterSignup();
                });
            });

            // Newsletter subscription handler
            const handleNewsletterForm = (formId) => {
                const form = document.getElementById(formId);

                if (!form) {
                    return; // Form not on this page, skip silently
                }

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const formData = new FormData(form);
                    const email = formData.get('email');
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const submitText = submitBtn.querySelector('.submit-text');
                    const defaultLabel = submitText ? submitText.textContent : null;

                    // Disable button and show loading
                    submitBtn.disabled = true;
                    if (submitText) {
                        submitText.textContent = 'Subscribing...';
                    }

                    try {
                        const response = await fetch('/api/subscribe.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ email }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Replace form with success message
                            const successHtml = `
                                <div class="flex items-center justify-center gap-3 py-4 animate-fade-in">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-500 text-white">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <span class="text-base font-medium text-green-600 dark:text-green-400">
                                        ${data.already_subscribed ? "You're already subscribed!" : "Subscribed! Welcome aboard."}
                                    </span>
                                </div>
                            `;
                            form.innerHTML = successHtml;
                        } else {
                            // Show error inline
                            let errorDiv = form.querySelector('.form-error');
                            if (!errorDiv) {
                                errorDiv = document.createElement('div');
                                errorDiv.className = 'form-error mt-2 text-sm text-red-500 dark:text-red-400';
                                form.appendChild(errorDiv);
                            }
                            errorDiv.textContent = data.error || 'Something went wrong. Please try again.';
                            submitBtn.disabled = false;
                            if (submitText) submitText.textContent = defaultLabel || 'Subscribe';
                        }

                    } catch (error) {
                        let errorDiv = form.querySelector('.form-error');
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'form-error mt-2 text-sm text-red-500 dark:text-red-400';
                            form.appendChild(errorDiv);
                        }
                        errorDiv.textContent = 'Network error. Please check your connection.';
                        submitBtn.disabled = false;
                        if (submitText) submitText.textContent = defaultLabel || 'Subscribe';
                    }
                });
            };

            // Show newsletter modal
            const showNewsletterModal = (message, type) => {
                const modal = document.getElementById('newsletter-modal');
                const modalMessage = document.getElementById('newsletter-modal-message');
                const modalClose = document.getElementById('newsletter-modal-close');
                const modalOk = document.getElementById('newsletter-modal-ok');

                if (modal && modalMessage) {
                    modalMessage.textContent = message;

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');

                    // Close on button click
                    const closeModal = () => {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    };

                    if (modalClose) {
                        modalClose.onclick = closeModal;
                    }

                    if (modalOk) {
                        modalOk.onclick = closeModal;
                    }

                    // Close on overlay click
                    modal.onclick = (e) => {
                        if (e.target === modal) {
                            closeModal();
                        }
                    };

                    // Auto close after 5 seconds
                    setTimeout(closeModal, 5000);
                }
            };

            // Initialize newsletter forms
            handleNewsletterForm('footer-newsletter-form');
            handleNewsletterForm('article-newsletter-form');
            handleNewsletterForm('market-pulse-form');
            handleNewsletterForm('modal-newsletter-form');

            <?php $ga = config('tracking.ga_id'); if ($ga): ?>
            // Cookie consent banner (only wired when a GA property is configured)
            const cookieBanner = document.getElementById('cookie-consent-banner');
            const cookieAccept = document.getElementById('cookie-consent-accept');
            const cookieDecline = document.getElementById('cookie-consent-decline');
            const consentStorageKey = 'bird_cookie_consent_v1';
            const analyticsDisableKey = 'ga-disable-<?= htmlspecialchars($ga, ENT_QUOTES) ?>';

            const applyAnalyticsPreference = (value) => {
                if (value === 'necessary') {
                    window[analyticsDisableKey] = true;
                    if (typeof gtag === 'function') {
                        gtag('consent', 'update', {
                            analytics_storage: 'denied'
                        });
                    }
                } else if (value === 'all') {
                    window[analyticsDisableKey] = undefined;
                    if (typeof gtag === 'function') {
                        gtag('consent', 'update', {
                            analytics_storage: 'granted'
                        });
                    }
                }
            };

            const storedConsent = localStorage.getItem(consentStorageKey);
            if (!storedConsent && cookieBanner) {
                cookieBanner.classList.remove('hidden');
            } else if (storedConsent) {
                applyAnalyticsPreference(storedConsent);
            }

            if (cookieAccept) {
                cookieAccept.addEventListener('click', () => {
                    localStorage.setItem(consentStorageKey, 'all');
                    applyAnalyticsPreference('all');
                    cookieBanner.classList.add('hidden');
                });
            }

            if (cookieDecline) {
                cookieDecline.addEventListener('click', () => {
                    localStorage.setItem(consentStorageKey, 'necessary');
                    applyAnalyticsPreference('necessary');
                    cookieBanner.classList.add('hidden');
                });
            }
            <?php endif; ?>

            // Bookmarks functionality
            window.BirdBookmarks = {
                key: 'bird_bookmarks_v1',
                get: function() {
                    try {
                        return JSON.parse(localStorage.getItem(this.key)) || [];
                    } catch (e) { return []; }
                },
                save: function(bookmarks) {
                    localStorage.setItem(this.key, JSON.stringify(bookmarks));
                },
                isBookmarked: function(url) {
                    return this.get().some(b => b.url === url);
                },
                add: function(article) {
                    const bookmarks = this.get();
                    if (!this.isBookmarked(article.url)) {
                        bookmarks.unshift({ ...article, savedAt: Date.now() });
                        this.save(bookmarks);
                    }
                    return true;
                },
                remove: function(url) {
                    const bookmarks = this.get().filter(b => b.url !== url);
                    this.save(bookmarks);
                    return false;
                },
                toggle: function(article) {
                    return this.isBookmarked(article.url) ? this.remove(article.url) : this.add(article);
                }
            };

            // Initialize bookmark buttons
            document.querySelectorAll('[data-bookmark-btn]').forEach(btn => {
                const url = btn.dataset.bookmarkUrl;
                const title = btn.dataset.bookmarkTitle;
                const category = btn.dataset.bookmarkCategory;
                const updateUI = () => {
                    const isBookmarked = BirdBookmarks.isBookmarked(url);
                    btn.setAttribute('aria-pressed', isBookmarked);
                    btn.querySelector('.bookmark-icon-empty')?.classList.toggle('hidden', isBookmarked);
                    btn.querySelector('.bookmark-icon-filled')?.classList.toggle('hidden', !isBookmarked);
                };
                updateUI();
                btn.addEventListener('click', () => {
                    BirdBookmarks.toggle({ url, title, category });
                    updateUI();
                });
            });
        });
    </script>

    <?= config('tracking.body_end', '') ?>
</body>
</html>
