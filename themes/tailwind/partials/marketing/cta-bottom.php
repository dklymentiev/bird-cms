<?php
/**
 * Bottom CTA Partial - Large call-to-action with image
 *
 * Universal partial for page endings.
 * Can be moved to bird-cms/partials/marketing/
 *
 * @var array $ctaBottomConfig Configuration array:
 *   - title: string
 *   - subtitle: string
 *   - description: string
 *   - buttonText: string
 *   - buttonUrl: string
 *   - phone: string
 *   - bgImage: string (background image URL)
 *   - mainImage: string (main CTA image URL)
 *   - customerCount: string
 *   - avatars: array of avatar URLs
 *   - rating: float
 *   - isCommercial: bool (changes color scheme)
 */
$ctaBottomConfig = $ctaBottomConfig ?? [];
$_cbTitle = $ctaBottomConfig['title'] ?? 'Ready to Get Started?';
$_cbSubtitle = $ctaBottomConfig['subtitle'] ?? 'Available 7 Days a Week';
$_cbDescription = $ctaBottomConfig['description'] ?? '';
$_cbButtonText = $ctaBottomConfig['buttonText'] ?? 'Get Free Quote';
$_cbButtonUrl = $ctaBottomConfig['buttonUrl'] ?? '/book/';
$_cbPhone = $ctaBottomConfig['phone'] ?? '(437) 483-2583';
$_cbBgImage = $ctaBottomConfig['bgImage'] ?? '/assets/images/bank/toronto-skyline.webp';
$_cbMainImage = $ctaBottomConfig['mainImage'] ?? '';
$_cbCustomerCount = $ctaBottomConfig['customerCount'] ?? '1,200+';
$_cbAvatars = $ctaBottomConfig['avatars'] ?? [];
$_cbRating = $ctaBottomConfig['rating'] ?? 4.9;
$_cbIsCommercial = $ctaBottomConfig['isCommercial'] ?? false;

$_cbBgGradient = $_cbIsCommercial
    ? 'bg-gradient-to-br from-gray-950 via-blue-950 to-gray-900'
    : 'bg-gradient-to-br from-primary-950 via-primary-800 to-primary-700';
$_cbTextMuted = $_cbIsCommercial ? 'text-gray-300' : 'text-white/80';
$_cbButtonHoverText = $_cbIsCommercial ? 'text-gray-900' : 'text-primary-700';
?>
<section class="relative py-20 <?= $_cbBgGradient ?> text-white overflow-hidden">
    <!-- Background image overlay -->
    <?php if ($_cbBgImage): ?>
    <div class="absolute inset-0">
        <img src="<?= htmlspecialchars($_cbBgImage) ?>" alt="" class="w-full h-full object-cover opacity-15">
    </div>
    <?php endif; ?>
    <div class="absolute inset-0 bg-gradient-to-r from-black/50 to-transparent"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <?php if ($_cbSubtitle): ?>
                <span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm text-white/90 text-sm font-medium px-4 py-2 rounded-full mb-6">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= htmlspecialchars($_cbSubtitle) ?>
                </span>
                <?php endif; ?>
                <h2 class="text-3xl lg:text-5xl font-bold mb-4 leading-tight"><?= htmlspecialchars($_cbTitle) ?></h2>
                <?php if ($_cbDescription): ?>
                <p class="text-lg <?= $_cbTextMuted ?> mb-8 max-w-lg">
                    <?= htmlspecialchars($_cbDescription) ?>
                </p>
                <?php endif; ?>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="<?= htmlspecialchars($_cbButtonUrl) ?>" class="bg-white <?= $_cbButtonHoverText ?> hover:bg-gray-100 px-8 py-4 rounded-xl font-bold text-lg text-center transition shadow-xl hover:shadow-2xl hover:-translate-y-0.5">
                        <?= htmlspecialchars($_cbButtonText) ?>
                    </a>
                    <?= phone_widget('cta-bottom', $_cbIsCommercial ? 'hero-commercial' : 'hero') ?>
                </div>
            </div>
            <?php if ($_cbMainImage): ?>
            <div class="hidden lg:block">
                <div class="relative">
                    <img src="<?= htmlspecialchars($_cbMainImage) ?>" alt="<?= htmlspecialchars($_cbTitle) ?>" class="rounded-2xl shadow-2xl">
                    <?php if (!empty($_cbAvatars) || $_cbCustomerCount): ?>
                    <div class="absolute -bottom-4 -left-4 bg-white rounded-xl shadow-xl p-4">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($_cbAvatars)): ?>
                            <div class="flex -space-x-2">
                                <?php foreach (array_slice($_cbAvatars, 0, 3) as $_cbAvatar): ?>
                                <img src="<?= htmlspecialchars($_cbAvatar) ?>" alt="" class="w-8 h-8 rounded-full border-2 border-white object-cover">
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-gray-900 font-bold text-sm"><?= htmlspecialchars($_cbCustomerCount) ?> Happy Customers</p>
                                <div class="flex text-yellow-400">
                                    <?php for ($_cbI = 0; $_cbI < 5; $_cbI++): ?>
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <?php endfor; ?>
                                    <span class="text-xs text-gray-500 ml-1"><?= htmlspecialchars($_cbRating) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
