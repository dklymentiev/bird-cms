<?php
/**
 * Universal Hero Section Partial
 *
 * Unified height across all pages with contextual backgrounds.
 *
 * @var array $heroConfig Configuration array:
 *   - title: string (required)
 *   - subtitle: string (text below title)
 *   - badge: string (small text in badge above title)
 *   - badgeIcon: string (icon name: clock, location, star, shield, check)
 *   - breadcrumbs: array of ['label' => '', 'url' => ''] (optional)
 *   - buttons: array of ['text' => '', 'url' => '', 'style' => 'primary|secondary']
 *   - bgImage: string (background image path)
 *   - bgType: string (residential|commercial|areas|contact|default)
 *   - isCommercial: bool (blue color scheme instead of green)
 *   - showSocialProof: bool (show avatars + rating)
 *   - socialProofText: string (e.g., "4.9 · 200+ reviews")
 *   - categoryBadge: string (e.g., "Residential Services")
 *   - rightContent: string (html for right column content)
 */
$heroConfig = $heroConfig ?? [];
$_hTitle = $heroConfig['title'] ?? 'Page Title';
$_hSubtitle = $heroConfig['subtitle'] ?? '';
$_hBadge = $heroConfig['badge'] ?? '';
$_hBadgeIcon = $heroConfig['badgeIcon'] ?? 'clock';
$_hBreadcrumbs = $heroConfig['breadcrumbs'] ?? [];
$_hButtons = $heroConfig['buttons'] ?? [];
$_hBgImage = $heroConfig['bgImage'] ?? '';
$_hBgType = $heroConfig['bgType'] ?? 'default';
$_hIsCommercial = $heroConfig['isCommercial'] ?? false;
$_hShowSocialProof = $heroConfig['showSocialProof'] ?? false;
$_hSocialProofText = $heroConfig['socialProofText'] ?? '4.9 · 200+ reviews';
$_hCategoryBadge = $heroConfig['categoryBadge'] ?? '';
$_hRightContent = $heroConfig['rightContent'] ?? '';
$_hCentered = $heroConfig['centered'] ?? false;

// Background images by type
$_hBgImages = [
    'residential' => '/assets/images/bank/cleaner-female-vacuum-01.webp',
    'commercial' => '/assets/images/bank/office-cleaning.webp',
    'areas' => '/assets/images/bank/toronto-skyline.webp',
    'contact' => '/assets/images/bank/team-group.webp',
    'about' => '/assets/images/bank/team-group.webp',
    'book' => '/assets/images/bank/cleaner-female-kitchen-01.webp',
    'pricing' => '/assets/images/bank/happy-family-clean-home.webp',
    'service' => '/assets/images/bank/deep-cleaning-oven.webp',
    'default' => '/assets/images/bank/toronto-skyline.webp',
];

// Use provided image or fall back to type default
if (empty($_hBgImage) && isset($_hBgImages[$_hBgType])) {
    $_hBgImage = $_hBgImages[$_hBgType];
}

// Color scheme
$_hGradient = $_hIsCommercial
    ? 'from-gray-950/95 via-blue-950/90 to-gray-900/80'
    : 'from-gray-900/95 via-primary-900/90 to-primary-800/80';
$_hTextMuted = $_hIsCommercial ? 'text-gray-300' : 'text-white/80';
$_hCategoryBgColor = $_hIsCommercial ? 'bg-accent-500/80' : 'bg-primary-500/80';
$_hButtonPrimaryClass = $_hIsCommercial
    ? 'bg-accent-500 hover:bg-accent-600 text-white'
    : 'bg-white text-primary-700 hover:bg-gray-100';

// Badge icons
$_hBadgeIcons = [
    'clock' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    'location' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>',
    'star' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
    'shield' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
    'check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
    'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
];
$_hBadgeIconPath = $_hBadgeIcons[$_hBadgeIcon] ?? $_hBadgeIcons['clock'];
?>
<section class="relative text-white py-16 lg:py-24 overflow-hidden">
    <!-- Background Image -->
    <?php if ($_hBgImage): ?>
    <div class="absolute inset-0">
        <img src="<?= htmlspecialchars($_hBgImage) ?>" alt="" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-r <?= $_hGradient ?>"></div>
    </div>
    <?php else: ?>
    <!-- Fallback: Gradient + Pattern -->
    <div class="absolute inset-0 bg-gradient-to-r from-gray-900 via-primary-900 to-primary-800"></div>
    <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.4&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
    </div>
    <?php endif; ?>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php if ($_hCentered): ?>
        <!-- Centered Layout -->
        <div class="text-center max-w-4xl mx-auto">
            <?php if (!empty($_hBreadcrumbs)): ?>
            <nav class="text-sm mb-4">
                <?php foreach ($_hBreadcrumbs as $_hIdx => $_hCrumb): ?>
                    <?php if ($_hIdx > 0): ?><span class="mx-2 text-white/50">›</span><?php endif; ?>
                    <?php if (!empty($_hCrumb['url'])): ?>
                        <a href="<?= htmlspecialchars($_hCrumb['url']) ?>" class="text-white/70 hover:text-white"><?= htmlspecialchars($_hCrumb['label']) ?></a>
                    <?php else: ?>
                        <span class="text-white"><?= htmlspecialchars($_hCrumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <?php if ($_hBadge): ?>
            <span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm text-white/90 text-sm font-medium px-4 py-2 rounded-full mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $_hBadgeIconPath ?></svg>
                <?= htmlspecialchars($_hBadge) ?>
            </span>
            <?php endif; ?>

            <h1 class="text-3xl lg:text-5xl xl:text-6xl font-bold leading-tight mb-6"><?= $_hTitle ?></h1>

            <?php if ($_hSubtitle): ?>
            <p class="text-xl <?= $_hTextMuted ?> max-w-2xl mx-auto mb-8"><?= htmlspecialchars($_hSubtitle) ?></p>
            <?php endif; ?>

            <?php if (!empty($_hButtons)): ?>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <?php foreach ($_hButtons as $_hBtn): ?>
                    <?php if (($_hBtn['type'] ?? '') === 'phone_widget'): ?>
                        <?= phone_widget($_hBtn['location'] ?? 'hero', $_hBtn['style'] ?? ($_hIsCommercial ? 'hero-commercial' : 'hero')) ?>
                    <?php else: ?>
                        <?php
                        $_hBtnStyle = $_hBtn['style'] ?? 'primary';
                        $_hBtnClass = $_hBtnStyle === 'primary'
                            ? $_hButtonPrimaryClass . ' px-8 py-4 rounded-xl font-bold text-lg transition shadow-lg hover:shadow-xl hover:-translate-y-0.5'
                            : 'border-2 border-white/80 text-white hover:bg-white hover:text-primary-700 px-8 py-4 rounded-xl font-bold text-lg transition';
                        ?>
                        <a href="<?= htmlspecialchars($_hBtn['url']) ?>" class="<?= $_hBtnClass ?> text-center">
                            <?= htmlspecialchars($_hBtn['text']) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Two Column Layout -->
        <div class="<?= $_hRightContent ? 'grid lg:grid-cols-2 gap-12 items-center' : '' ?>">
            <div class="<?= $_hRightContent ? '' : 'max-w-3xl' ?>">
                <?php if (!empty($_hBreadcrumbs)): ?>
                <nav class="text-sm mb-4">
                    <?php foreach ($_hBreadcrumbs as $_hIdx => $_hCrumb): ?>
                        <?php if ($_hIdx > 0): ?><span class="mx-2 text-white/50">›</span><?php endif; ?>
                        <?php if (!empty($_hCrumb['url'])): ?>
                            <a href="<?= htmlspecialchars($_hCrumb['url']) ?>" class="text-white/70 hover:text-white"><?= htmlspecialchars($_hCrumb['label']) ?></a>
                        <?php else: ?>
                            <span class="text-white"><?= htmlspecialchars($_hCrumb['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>

                <?php if ($_hShowSocialProof): ?>
                <!-- Social Proof Badge -->
                <div class="inline-flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-full px-4 py-2 mb-6">
                    <div class="flex -space-x-2">
                        <img src="/assets/images/bank/avatar-sarah.webp" alt="" class="w-7 h-7 rounded-full border-2 border-white object-cover">
                        <img src="/assets/images/bank/avatar-james.webp" alt="" class="w-7 h-7 rounded-full border-2 border-white object-cover">
                        <img src="/assets/images/bank/avatar-jennifer.webp" alt="" class="w-7 h-7 rounded-full border-2 border-white object-cover">
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="flex text-yellow-400">
                            <?php for ($_hI = 0; $_hI < 5; $_hI++): ?>
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <?php endfor; ?>
                        </div>
                        <span class="text-white/90 text-sm font-medium"><?= htmlspecialchars($_hSocialProofText) ?></span>
                    </div>
                </div>
                <?php elseif ($_hBadge): ?>
                <span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm text-white/90 text-sm font-medium px-4 py-2 rounded-full mb-6">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $_hBadgeIconPath ?></svg>
                    <?= htmlspecialchars($_hBadge) ?>
                </span>
                <?php endif; ?>

                <?php if ($_hCategoryBadge): ?>
                <span class="inline-block <?= $_hCategoryBgColor ?> text-white text-sm font-semibold px-4 py-1.5 rounded-full mb-4">
                    <?= htmlspecialchars($_hCategoryBadge) ?>
                </span>
                <?php endif; ?>

                <h1 class="text-4xl lg:text-5xl xl:text-6xl font-bold leading-tight mb-6"><?= $_hTitle ?></h1>

                <?php if ($_hSubtitle): ?>
                <p class="text-xl <?= $_hTextMuted ?> mb-8 max-w-lg"><?= htmlspecialchars($_hSubtitle) ?></p>
                <?php endif; ?>

                <?php if (!empty($_hButtons)): ?>
                <div class="flex flex-col sm:flex-row gap-4">
                    <?php foreach ($_hButtons as $_hBtn): ?>
                        <?php if (($_hBtn['type'] ?? '') === 'phone_widget'): ?>
                            <?= phone_widget($_hBtn['location'] ?? 'hero', $_hBtn['style'] ?? ($_hIsCommercial ? 'hero-commercial' : 'hero')) ?>
                        <?php else: ?>
                            <?php
                            $_hBtnStyle = $_hBtn['style'] ?? 'primary';
                            $_hBtnClass = $_hBtnStyle === 'primary'
                                ? $_hButtonPrimaryClass . ' px-8 py-4 rounded-xl font-bold text-lg transition shadow-lg hover:shadow-xl hover:-translate-y-0.5'
                                : 'border-2 border-white/80 text-white hover:bg-white hover:text-primary-700 px-8 py-4 rounded-xl font-bold text-lg transition';
                            ?>
                            <a href="<?= htmlspecialchars($_hBtn['url']) ?>" class="<?= $_hBtnClass ?> text-center">
                                <?= htmlspecialchars($_hBtn['text']) ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($_hRightContent): ?>
            <div class="hidden lg:block">
                <?= $_hRightContent ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
