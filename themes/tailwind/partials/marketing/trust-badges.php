<?php
/**
 * Trust Badges Partial - Google Rating + Customer Count
 *
 * Universal partial for local service businesses.
 * Can be moved to bird-cms/partials/marketing/
 *
 * @var array $trustBadges Configuration array:
 *   - google_rating: float (e.g. 4.9)
 *   - review_count: int (e.g. 127)
 *   - customer_count: string (e.g. "1,200+")
 *   - customer_label: string (e.g. "Families trust us")
 *   - style: string 'light' (for dark bg) or 'dark' (for light bg)
 */
$trustBadges = $trustBadges ?? [];
$rating = $trustBadges['google_rating'] ?? 4.9;
$reviewCount = $trustBadges['review_count'] ?? 127;
$customerCount = $trustBadges['customer_count'] ?? '1,200+';
$customerLabel = $trustBadges['customer_label'] ?? 'Families trust us';
$style = $trustBadges['style'] ?? 'light';

$isLight = $style === 'light';
$bgClass = $isLight ? 'bg-white/10 border-white/10' : 'bg-gray-100 border-gray-200';
$textClass = $isLight ? 'text-white' : 'text-gray-900';
$subTextClass = $isLight ? 'text-white/70' : 'text-gray-500';
?>
<div class="flex flex-wrap items-center gap-6">
    <!-- Google rating -->
    <div class="flex items-center gap-2 <?= $bgClass ?> backdrop-blur-sm rounded-xl px-4 py-2.5 border">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        <div class="flex text-yellow-400">
            <?php for ($i = 0; $i < 5; $i++): ?>
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
            <?php endfor; ?>
        </div>
        <div class="text-sm <?= $textClass ?>">
            <span class="font-bold"><?= htmlspecialchars($rating) ?></span>
            <span class="<?= $subTextClass ?>">(<?= htmlspecialchars($reviewCount) ?> reviews)</span>
        </div>
    </div>

    <!-- Customer count -->
    <div class="text-sm <?= $textClass ?>">
        <span class="block text-2xl font-bold"><?= htmlspecialchars($customerCount) ?></span>
        <span class="<?= $subTextClass ?>"><?= htmlspecialchars($customerLabel) ?></span>
    </div>
</div>
