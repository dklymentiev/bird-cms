<?php
/**
 * Testimonials Partial - Customer Reviews with Verified Badges
 *
 * Universal partial for any business.
 * Can be moved to bird-cms/partials/marketing/
 *
 * @var array $testimonialsConfig Configuration array:
 *   - title: string (default: "What Our Customers Say")
 *   - rating: float (default: 4.9)
 *   - review_count: string (default: "127+")
 *   - reviews: array of review objects
 *   - trust_logos: array of [icon_color, label]
 *   - bgClass: string (default: "bg-[#e2e8f0]")
 *
 * Each review: [text, name, location, note, avatar, verified]
 */
$testimonialsConfig = $testimonialsConfig ?? [];
$_tmTitle = $testimonialsConfig['title'] ?? 'What Our Customers Say';
$_tmRating = $testimonialsConfig['rating'] ?? 4.9;
$_tmReviewCount = $testimonialsConfig['review_count'] ?? '127+';
$_tmBgClass = $testimonialsConfig['bgClass'] ?? 'bg-[#e2e8f0]';

// Default reviews
$_tmDefaultReviews = [
    [
        'text' => 'Best service I\'ve ever used! They were thorough, professional, and the results exceeded my expectations.',
        'name' => 'Sarah M.',
        'location' => 'Toronto',
        'note' => 'Regular customer',
        'avatar' => '/assets/images/bank/avatar-sarah.webp',
        'verified' => true
    ],
    [
        'text' => 'Excellent experience from start to finish. Would highly recommend to anyone looking for quality service.',
        'name' => 'James L.',
        'location' => 'Mississauga',
        'note' => 'First-time customer',
        'avatar' => '/assets/images/bank/avatar-james.webp',
        'verified' => true
    ],
    [
        'text' => 'Been using them for 6 months now. Always on time, always thorough. Couldn\'t be happier!',
        'name' => 'Jennifer K.',
        'location' => 'Markham',
        'note' => '6 months customer',
        'avatar' => '/assets/images/bank/avatar-jennifer.webp',
        'verified' => true
    ]
];
$_tmReviews = $testimonialsConfig['reviews'] ?? $_tmDefaultReviews;

// Default trust logos
$_tmDefaultTrustLogos = [
    ['color' => 'green', 'label' => 'Google Reviews'],
    ['color' => 'blue', 'label' => 'HomeStars'],
    ['color' => 'amber', 'label' => 'BBB Accredited']
];
$_tmTrustLogos = $testimonialsConfig['trust_logos'] ?? $_tmDefaultTrustLogos;
?>
<section class="py-14 <?= htmlspecialchars($_tmBgClass) ?>">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold text-gray-900 mb-3"><?= htmlspecialchars($_tmTitle) ?></h2>
            <div class="flex items-center justify-center gap-2">
                <div class="flex text-yellow-400">
                    <?php for ($_tmI = 0; $_tmI < 5; $_tmI++): ?>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php endfor; ?>
                </div>
                <span class="text-gray-600 font-medium"><?= htmlspecialchars($_tmRating) ?>/5 from <?= htmlspecialchars($_tmReviewCount) ?> reviews</span>
            </div>
        </div>
        <div class="grid md:grid-cols-3 gap-5">
            <?php foreach ($_tmReviews as $_tmReview): ?>
            <div class="bg-white rounded-xl p-5 shadow-lg border border-gray-100 hover:shadow-xl transition-shadow">
                <div class="flex text-yellow-400 mb-3">
                    <?php for ($_tmJ = 0; $_tmJ < 5; $_tmJ++): ?>
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php endfor; ?>
                </div>
                <div class="relative">
                    <svg class="absolute -top-1 -left-1 w-6 h-6 text-primary-200" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
                    <p class="text-gray-700 mb-4 leading-relaxed pl-4">"<?= htmlspecialchars($_tmReview['text']) ?>"</p>
                </div>
                <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                    <?php if (!empty($_tmReview['avatar'])): ?>
                    <div class="relative">
                        <div class="absolute inset-0 bg-primary-400/30 rounded-full blur-md"></div>
                        <img src="<?= htmlspecialchars($_tmReview['avatar']) ?>" alt="<?= htmlspecialchars($_tmReview['name']) ?>" class="relative w-12 h-12 rounded-full object-cover ring-2 ring-white shadow-md">
                    </div>
                    <?php endif; ?>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-bold text-gray-900"><?= htmlspecialchars($_tmReview['name']) ?></p>
                            <?php if (!empty($_tmReview['verified'])): ?>
                            <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($_tmReview['location'] ?? '') ?><?= !empty($_tmReview['note']) ? ' • ' . htmlspecialchars($_tmReview['note']) : '' ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Trust logos -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <div class="flex flex-wrap justify-center items-center gap-8 text-gray-400">
                <?php foreach ($_tmTrustLogos as $_tmLogo): ?>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-<?= htmlspecialchars($_tmLogo['color']) ?>-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    <span class="text-sm font-medium text-gray-600"><?= htmlspecialchars($_tmLogo['label']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
