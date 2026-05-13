<?php
/**
 * Flash Messages
 */
$flashMessages = $flash ?? [];

if (empty($flashMessages)) {
    return;
}

$typeClasses = [
    'success' => 'bg-green-50 border-green-500 text-green-800',
    'error' => 'bg-red-50 border-red-500 text-red-800',
    'warning' => 'bg-yellow-50 border-yellow-500 text-yellow-800',
    'info' => 'bg-blue-50 border-blue-500 text-blue-800',
];

$typeIcons = [
    'success' => 'ri-check-line',
    'error'   => 'ri-close-line',
    'warning' => 'ri-error-warning-line',
    'info'    => 'ri-information-line',
];
?>

<div class="mb-6 space-y-3">
    <?php foreach ($flashMessages as $msg): ?>
        <?php
        $type = $msg['type'] ?? 'info';
        $classes = $typeClasses[$type] ?? $typeClasses['info'];
        $icon = $typeIcons[$type] ?? $typeIcons['info'];
        ?>
        <div x-data="{ show: true }"
             x-show="show"
             x-transition
             class="<?= $classes ?> border-l-4 p-4 rounded-r-lg flex items-start">
            <i class="<?= $icon ?> text-lg mr-3 flex-shrink-0 leading-none"></i>
            <p class="flex-1"><?= htmlspecialchars($msg['message']) ?></p>
            <button @click="show = false" class="ml-3 flex-shrink-0 text-current opacity-50 hover:opacity-100">
                <i class="ri-close-line text-base leading-none"></i>
            </button>
        </div>
    <?php endforeach; ?>
</div>
