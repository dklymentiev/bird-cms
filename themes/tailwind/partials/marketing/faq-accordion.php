<?php
/**
 * FAQ Accordion Partial
 *
 * Universal partial for any page with FAQs.
 * Can be moved to bird-cms/partials/marketing/
 *
 * @var array $faqConfig Configuration array:
 *   - title: string (default: "Frequently Asked Questions")
 *   - subtitle: string (default: "Got questions? We've got answers.")
 *   - faqs: array of [question, answer]
 *   - accentColor: string (default: "primary")
 *   - phone: string (optional, for CTA)
 *   - showCta: bool (default: true)
 *   - bgClass: string (default: "bg-white")
 *   - hasGradientTop: bool (default: false)
 */
$faqConfig = $faqConfig ?? [];
$_faqTitle = $faqConfig['title'] ?? 'Frequently Asked Questions';
$_faqSubtitle = $faqConfig['subtitle'] ?? 'Got questions? We\'ve got answers.';
$_faqItems = $faqConfig['faqs'] ?? [];
$_faqAccentColor = $faqConfig['accentColor'] ?? 'primary';
$_faqPhone = $faqConfig['phone'] ?? '(437) 483-2583';
$_faqShowCta = $faqConfig['showCta'] ?? true;
$_faqBgClass = $faqConfig['bgClass'] ?? 'bg-white';
$_faqHasGradientTop = $faqConfig['hasGradientTop'] ?? false;

if (empty($_faqItems)) return;
?>
<section class="relative py-14 <?= htmlspecialchars($_faqBgClass) ?>">
    <?php if ($_faqHasGradientTop): ?>
    <!-- Top gradient overlay -->
    <div class="absolute top-0 left-0 right-0 h-24 bg-gradient-to-b from-[#f8fafc] to-transparent"></div>
    <?php endif; ?>
    <div class="relative">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($_faqTitle) ?></h2>
            <p class="text-gray-600"><?= htmlspecialchars($_faqSubtitle) ?></p>
        </div>
        <div class="space-y-3">
            <?php foreach ($_faqItems as $_faqIndex => $_faqItem): ?>
            <details class="bg-white rounded-xl shadow-sm group border border-gray-100 hover:shadow-md transition-shadow" <?= $_faqIndex === 0 ? 'open' : '' ?>>
                <summary class="flex items-center gap-3 p-4 cursor-pointer list-none">
                    <div class="w-8 h-8 bg-gradient-to-br from-<?= $_faqAccentColor ?>-100 to-<?= $_faqAccentColor ?>-200 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-<?= $_faqAccentColor ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 flex-1 text-left text-[15px]"><?= htmlspecialchars($_faqItem['question'] ?? '') ?></h3>
                    <svg class="w-5 h-5 text-<?= $_faqAccentColor ?>-500 group-open:rotate-180 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                <div class="px-4 pb-4 pl-14 text-gray-600 text-sm leading-relaxed">
                    <p><?= htmlspecialchars($_faqItem['answer'] ?? '') ?></p>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
        <?php if ($_faqShowCta): ?>
        <div class="mt-6 p-5 bg-white rounded-xl border-2 border-dashed border-<?= $_faqAccentColor ?>-200 text-center">
            <p class="text-gray-700 font-medium mb-2">Still have questions?</p>
            <?= phone_widget('faq-section', 'button') ?>
        </div>
        <?php endif; ?>
    </div>
    </div>
</section>
