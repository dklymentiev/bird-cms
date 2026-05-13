<?php
/**
 * Guarantees Strip Partial
 *
 * Universal partial for trust signals.
 * Can be moved to bird-cms/partials/marketing/
 *
 * @var array $guaranteesConfig Configuration array:
 *   - guarantees: array of [icon, iconColor, title, subtitle]
 *   - bgClass: string (default gradient)
 */
$guaranteesConfig = $guaranteesConfig ?? [];
$bgClass = $guaranteesConfig['bgClass'] ?? 'bg-gradient-to-r from-[#f0fdf4] via-[#ecfdf5] to-[#f0fdf4]';

// Default guarantees - common for service businesses
$defaultGuarantees = [
    [
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
        'iconColor' => 'green',
        'title' => '100% Satisfaction',
        'subtitle' => 'Or we re-do for free'
    ],
    [
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
        'iconColor' => 'blue',
        'title' => 'Always On Time',
        'subtitle' => 'Reliable & punctual'
    ],
    [
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
        'iconColor' => 'purple',
        'title' => 'Vetted Staff',
        'subtitle' => 'Background checked'
    ]
];
$guarantees = $guaranteesConfig['guarantees'] ?? $defaultGuarantees;
?>
<section class="py-10 <?= htmlspecialchars($bgClass) ?>">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-<?= count($guarantees) ?> gap-6">
            <?php foreach ($guarantees as $guarantee): ?>
            <div class="flex items-center gap-4 bg-white p-4 rounded-xl shadow-sm">
                <div class="w-12 h-12 bg-<?= htmlspecialchars($guarantee['iconColor']) ?>-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-<?= htmlspecialchars($guarantee['iconColor']) ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?= $guarantee['icon'] ?>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($guarantee['title']) ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($guarantee['subtitle']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
