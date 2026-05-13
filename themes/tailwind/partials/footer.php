<?php
/** @var array $config */
/** @var array $categoriesList */

$categoriesList = $categoriesList ?? [];
$primaryCategories = array_slice($categoriesList, 0, 6);
$branding = config('site.branding', []);
$logoPrimary = $branding['logo_text']['primary'] ?? substr(site_name(), 0, 3);
$logoSecondary = $branding['logo_text']['secondary'] ?? '';
$logoInitials = strtoupper(substr($logoPrimary, 0, 1) . ($logoSecondary ? substr($logoSecondary, 0, 1) : substr($logoPrimary, 1, 1)));
?>
<footer class="mt-20 bg-slate-950 text-slate-200">
    <div class="border-t border-slate-800/60 bg-gradient-to-r from-brand-600/10 via-transparent to-brand-500/10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid gap-12 lg:grid-cols-[2fr,1fr,1fr,1fr]">
                <div class="space-y-6">
                    <?php $logoImage = $branding['logo_image'] ?? null; ?>
                    <?php if ($logoImage): ?>
                    <a href="/" class="brand-logo flex items-center gap-3" aria-label="<?= htmlspecialchars(site_name()) ?> home">
                        <img src="<?= htmlspecialchars($logoImage) ?>" width="40" height="40" alt="">
                        <span class="text-base font-semibold text-white"><?= htmlspecialchars(site_name()) ?></span>
                    </a>
                    <?php else: ?>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-600 text-base font-semibold text-white"><?= htmlspecialchars($logoInitials) ?></span>
                        <div class="flex flex-col leading-tight">
                            <span class="text-base font-semibold uppercase tracking-[0.2em] text-white"><?= htmlspecialchars($logoPrimary) ?></span>
                            <?php if ($logoSecondary): ?>
                            <span class="text-base font-semibold uppercase tracking-[0.2em] text-white"><?= htmlspecialchars($logoSecondary) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <p class="text-sm leading-relaxed text-slate-400">
                        Insights on tech, markets, wellness, and everyday decisions — sharp analysis to help you stay current.
                    </p>
                    <div class="space-y-3 text-sm text-slate-400">
                        <div class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 text-brand-300" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M1.5 5.25A2.25 2.25 0 0 1 3.75 3h16.5a2.25 2.25 0 0 1 0 4.5h-.18l.18.135v11.115a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V7.635L3.93 7.5H3.75A2.25 2.25 0 0 1 1.5 5.25ZM3.75 5.25l8.25 6 8.25-6v-.75H3.75v.75Zm16.5 3.285-7.155 5.205a2.25 2.25 0 0 1-2.19 0L3.75 8.535v10.215c0 .414.336.75.75.75h12.75a.75.75 0 0 0 .75-.75V8.535Z"/>
                            </svg>
                            <span>Editorial desk: <a class="hover:text-white transition" href="mailto:<?= htmlspecialchars(contact_email('editor')) ?>"><?= htmlspecialchars(contact_email('editor')) ?></a></span>
                        </div>
                        <div class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 text-brand-300" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2.25a9.75 9.75 0 1 0 9.75 9.75A9.762 9.762 0 0 0 12 2.25Zm0 1.5a8.25 8.25 0 1 1-8.25 8.25A8.259 8.259 0 0 1 12 3.75Zm-.75 3v5.438l4.688 2.813.75-1.266-3.938-2.362V6.75h-1.5Z"/>
                            </svg>
                            <span>Weekly intelligence memo, delivered every Sunday at 18:00&nbsp;UTC.</span>
                        </div>
                    </div>
                    <form id="footer-newsletter-form" class="flex flex-col gap-3 sm:flex-row">
                        <label class="sr-only" for="footer-email">Email</label>
                        <input
                            id="footer-email"
                            name="email"
                            type="email"
                            required
                            placeholder="you@example.com"
                            class="w-full flex-1 rounded-full border border-slate-800 bg-slate-900/60 px-4 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-400"
                        />
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-full bg-brand-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span class="submit-text">Subscribe</span>
                        </button>
                    </form>
                </div>
                <div class="space-y-4">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400">Coverage</h4>
                    <ul class="space-y-2 text-sm">
                        <?php foreach ($primaryCategories as $category): ?>
                            <li>
                                <a class="group inline-flex items-center gap-2 text-slate-300 transition hover:text-white" href="/<?= htmlspecialchars($category) ?>">
                                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500 transition group-hover:bg-brand-300"></span>
                                    <?= htmlspecialchars(ucfirst($category)) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($primaryCategories)): ?>
                            <li class="text-slate-500">Articles will appear here once categories are published.</li>
                        <?php endif; ?>
                    </ul>
                    <a class="inline-flex items-center text-xs font-semibold uppercase tracking-[0.3em] text-brand-300 transition hover:text-brand-100" href="#categories">
                        Browse all topics
                        <svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M7.293 15.707a1 1 0 0 1 0-1.414L11.586 10 7.293 5.707a1 1 0 1 1 1.414-1.414l5 5a1 1 0 0 1 0 1.414l-5 5a1 1 0 0 1-1.414 0Z"/>
                        </svg>
                    </a>
                </div>
                <div class="space-y-4">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400">Briefings</h4>
                    <ul class="space-y-2 text-sm text-slate-300">
                        <li><a class="hover:text-white transition" href="#latest">Latest analysis</a></li>
                        <li><a class="hover:text-white transition" href="#categories">Deep dives by category</a></li>
                        <li><a class="hover:text-white transition" href="#newsletter">Newsletter memo</a></li>
                        <li><a class="hover:text-white transition" href="#latest-analysis">Research archive</a></li>
                        <li><a class="hover:text-white transition" href="/rss.xml">RSS feed</a></li>
                    </ul>
                    <p class="text-xs text-slate-500">
                        In-depth analysis across categories — from market trends to wellness insights.
                    </p>
                </div>
                <div class="space-y-4">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400">Connect</h4>
                    <ul class="space-y-2 text-sm text-slate-300">
                        <li><a class="hover:text-white transition" href="mailto:<?= htmlspecialchars(contact_email('tips')) ?>">Send a tip</a></li>
                        <li><a class="hover:text-white transition" href="mailto:<?= htmlspecialchars(contact_email('press')) ?>">Press &amp; speaking</a></li>
                        <li><a class="hover:text-white transition" href="mailto:<?= htmlspecialchars(contact_email('advertising')) ?>">Media kit &amp; advertising</a></li>
                    </ul>
                    <p class="text-xs text-slate-500">
                        Daily signals, quick reads, and event coverage land on these channels first.
                    </p>
                    <div class="flex gap-3 text-slate-400">
                        <a class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-800 transition hover:border-brand-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900" aria-label="Telegram" href="<?= htmlspecialchars(config('social.telegram')) ?>" target="_blank" rel="noopener noreferrer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M21.5 2.5a1 1 0 0 0-1.02-.07L2.86 11.01a1 1 0 0 0 .1 1.83l4.77 1.6 1.9 5.47a1 1 0 0 0 1.77.2l2.7-3.72 4.34 3.2a1 1 0 0 0 1.58-.6l3-15a1 1 0 0 0-.52-1.15ZM9.7 15.1l-.27 2.47-1.18-3.43 7.36-5.66-5.91 6.54Z" />
                            </svg>
                        </a>
                        <a class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-800 transition hover:border-brand-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900" aria-label="X (Twitter)" href="<?= htmlspecialchars(config('social.x')) ?>" target="_blank" rel="noopener noreferrer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.98 3H17.9l-4.35 5.78L9.9 3H3.02l6.94 9.8L3 21h3.08l4.73-6.28L14.1 21h6.87l-6.96-9.86L20.98 3Z" />
                            </svg>
                        </a>
                        <a class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-800 transition hover:border-brand-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900" aria-label="LinkedIn" href="<?= htmlspecialchars(config('social.linkedin')) ?>" target="_blank" rel="noopener noreferrer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4.5 3C3.67 3 3 3.67 3 4.5S3.67 6 4.5 6 6 5.33 6 4.5 5.33 3 4.5 3ZM3 8h3v13H3V8Zm6 0h2.89v1.78h.04c.4-.76 1.38-1.56 2.84-1.56 3.03 0 3.6 1.99 3.6 4.58V21h-3v-7.16c0-1.71-.03-3.9-2.38-3.9-2.38 0-2.75 1.86-2.75 3.78V21H9V8Z" />
                            </svg>
                        </a>
                        <a class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-800 transition hover:border-brand-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900" aria-label="Facebook" href="<?= htmlspecialchars(config('social.facebook')) ?>" target="_blank" rel="noopener noreferrer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9.19795 21.5H13.198V13.4901H16.8021L17.198 9.50977H13.198V7.5C13.198 6.94772 13.6457 6.5 14.198 6.5H17.198V2.5H14.198C11.4365 2.5 9.19795 4.73858 9.19795 7.5V9.50977H7.19795L6.80206 13.4901H9.19795V21.5Z" />
                            </svg>
                        </a>
                        <a class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-800 transition hover:border-brand-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900" aria-label="Pinterest" href="<?= htmlspecialchars(config('social.pinterest')) ?>" target="_blank" rel="noopener noreferrer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.477 2 2 6.477 2 12c0 4.237 2.636 7.855 6.356 9.312-.088-.791-.167-2.005.035-2.868.182-.78 1.172-4.97 1.172-4.97s-.299-.6-.299-1.486c0-1.39.806-2.428 1.81-2.428.852 0 1.264.64 1.264 1.408 0 .858-.545 2.14-.828 3.33-.236.995.5 1.807 1.48 1.807 1.778 0 3.144-1.874 3.144-4.58 0-2.393-1.72-4.068-4.177-4.068-2.845 0-4.515 2.135-4.515 4.34 0 .859.331 1.781.745 2.281a.3.3 0 0 1 .069.288l-.278 1.133c-.044.183-.145.223-.335.134-1.249-.581-2.03-2.407-2.03-3.874 0-3.154 2.292-6.052 6.608-6.052 3.469 0 6.165 2.473 6.165 5.776 0 3.447-2.173 6.22-5.19 6.22-1.013 0-1.965-.525-2.291-1.148l-.623 2.378c-.226.869-.835 1.958-1.244 2.621.937.29 1.931.446 2.962.446 5.523 0 10-4.477 10-10S17.523 2 12 2Z" />
                            </svg>
                        </a>
                        <a class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-800 transition hover:border-brand-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900" aria-label="RSS feed" href="<?= htmlspecialchars(config('social.rss')) ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 18.75A2.25 2.25 0 1 1 3.75 21 2.25 2.25 0 0 1 6 18.75Zm-3-6A2.25 2.25 0 0 1 5.25 10.5 11.25 11.25 0 0 1 16.5 21 2.25 2.25 0 0 1 12 21 6.75 6.75 0 0 0 5.25 14.25 2.25 2.25 0 0 1 3 12.75Zm0-6A2.25 2.25 0 0 1 5.25 4.5 17.25 17.25 0 0 1 22.5 21a2.25 2.25 0 0 1-3.75 0 12.75 12.75 0 0 0-12-12A2.25 2.25 0 0 1 3 6.75Z" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
            <div class="mt-12 border-t border-slate-800/80 pt-6 text-xs text-slate-500">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($config['site_name']) ?> Media. All rights reserved.</span>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <a class="hover:text-white transition" href="/privacy">Privacy</a>
                        <a class="hover:text-white transition" href="/terms">Terms</a>
                        <a class="hover:text-white transition" href="/cookies">Cookies</a>
                        <a class="hover:text-white transition" href="/sitemap.xml">Sitemap</a>
                        <a class="hover:text-white transition" href="/contact">Contact</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
