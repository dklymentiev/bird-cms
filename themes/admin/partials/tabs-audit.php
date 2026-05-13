<?php
/**
 * Tab bar for Audit pages (Site Check, Links, PageSpeed).
 * Caller sets $activeTab to one of: 'sitecheck', 'links', 'pagespeed'.
 */
$activeTab = $activeTab ?? '';

$tabs = [
    ['key' => 'sitecheck', 'path' => '/admin/sitecheck', 'label' => 'SEO Audit',    'desc' => 'Headers, robots, sitemap'],
    ['key' => 'links',     'path' => '/admin/links',     'label' => 'Link Health',  'desc' => 'Broken & external links'],
    ['key' => 'pagespeed', 'path' => '/admin/pagespeed', 'label' => 'Performance',  'desc' => 'Core Web Vitals'],
];
?>
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="px-4 py-3 border-b">
        <h2 class="text-sm font-semibold text-gray-700">Audit</h2>
        <p class="text-xs text-gray-500">Check the quality of your own site.</p>
    </div>
    <div class="flex border-b">
        <?php foreach ($tabs as $tab): ?>
            <?php $active = ($tab['key'] === $activeTab); ?>
            <a href="<?= $tab['path'] ?>"
               class="px-5 py-3 text-sm font-medium border-b-2 transition-colors
                      <?= $active
                            ? 'border-blue-500 text-blue-700 bg-blue-50'
                            : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-50' ?>">
                <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
                <span class="block text-[11px] font-normal text-gray-400"><?= htmlspecialchars($tab['desc'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
