<?php
/**
 * CTA Strip Partial - Mini colored call-to-action bar
 *
 * Universal partial for any page.
 * Can be moved to bird-cms/partials/marketing/
 *
 * @var array $ctaStripConfig Configuration array:
 *   - text: string (default: "Ready to get started?")
 *   - buttonText: string (default: "Get Free Quote")
 *   - buttonUrl: string (default: "/book/")
 *   - accentColor: string (default: "primary")
 */
$ctaStripConfig = $ctaStripConfig ?? [];
$_csText = $ctaStripConfig['text'] ?? 'Ready to get started?';
$_csButtonText = $ctaStripConfig['buttonText'] ?? 'Get Free Quote';
$_csButtonUrl = $ctaStripConfig['buttonUrl'] ?? '/book/';
$_csAccentColor = $ctaStripConfig['accentColor'] ?? 'primary';
?>
<section class="py-6 bg-<?= $_csAccentColor ?>-600">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-white text-lg font-medium"><?= htmlspecialchars($_csText) ?></p>
            <a href="<?= htmlspecialchars($_csButtonUrl) ?>" class="bg-white text-<?= $_csAccentColor ?>-700 hover:bg-gray-100 px-6 py-2.5 rounded-lg font-bold transition flex items-center gap-2">
                <?= htmlspecialchars($_csButtonText) ?>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>
