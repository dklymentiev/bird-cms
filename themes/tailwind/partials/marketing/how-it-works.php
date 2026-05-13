<?php
/**
 * How It Works - 3-Step Process Partial
 *
 * Universal partial for service businesses.
 * Can be moved to bird-cms/partials/marketing/
 *
 * @var array $howItWorks Configuration array:
 *   - title: string (default: "How It Works")
 *   - subtitle: string (default: "Book your service in 3 simple steps")
 *   - accentColor: string (default: "primary") - Tailwind color name
 *   - steps: array of [icon, title, description]
 *   - bgClass: string (default: "bg-gray-50")
 */
$howItWorks = $howItWorks ?? [];
$_hiwTitle = $howItWorks['title'] ?? 'How It Works';
$_hiwSubtitle = $howItWorks['subtitle'] ?? 'Book your service in 3 simple steps';
$_hiwAccentColor = $howItWorks['accentColor'] ?? 'primary';
$_hiwBgClass = $howItWorks['bgClass'] ?? 'bg-gray-50';

// Default steps if none provided
$defaultSteps = [
    [
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        'title' => 'Book Online',
        'description' => 'Choose your service and pick a time that works for you'
    ],
    [
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>',
        'title' => 'We Deliver',
        'description' => 'Our professionals arrive on time and ready to work'
    ],
    [
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>',
        'title' => 'Relax & Enjoy',
        'description' => 'Sit back and enjoy the results. 100% satisfaction guaranteed'
    ]
];
$_hiwSteps = $howItWorks['steps'] ?? $defaultSteps;
?>
<section class="py-16 <?= htmlspecialchars($_hiwBgClass) ?>">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($_hiwTitle) ?></h2>
            <p class="text-gray-600"><?= htmlspecialchars($_hiwSubtitle) ?></p>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            <?php foreach ($_hiwSteps as $_hiwIndex => $_hiwStep): ?>
            <div class="group text-center">
                <div class="relative inline-block mb-5">
                    <div class="w-20 h-20 bg-gradient-to-br from-<?= $_hiwAccentColor ?>-100 to-<?= $_hiwAccentColor ?>-200 rounded-2xl flex items-center justify-center mx-auto shadow-lg group-hover:shadow-xl group-hover:scale-105 transition-all duration-300">
                        <svg class="w-10 h-10 text-<?= $_hiwAccentColor ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <?= $_hiwStep['icon'] ?>
                        </svg>
                    </div>
                    <span class="absolute -top-2 -right-2 w-7 h-7 bg-<?= $_hiwAccentColor ?>-600 text-white text-sm font-bold rounded-full flex items-center justify-center shadow-md"><?= $_hiwIndex + 1 ?></span>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($_hiwStep['title']) ?></h3>
                <p class="text-gray-600"><?= htmlspecialchars($_hiwStep['description']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
