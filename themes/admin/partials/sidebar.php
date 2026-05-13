<?php
/**
 * Admin Sidebar Navigation
 *
 * Visibility honours `config/admin.php` -> `mode`:
 *   - 'minimal' (OSS default): Dashboard, Pages, Categories, Media, Settings
 *   - 'full': above plus Articles, Security, Audit
 * Routes for hidden entries still resolve -- this controls nav only.
 */

$currentPath = $currentPath ?? '/admin';
// Controller::render() injects $config (admin config) into every view.
// Fall back to a direct load when the partial is required from somewhere
// that did not extract it (defensive -- keeps the sidebar self-contained).
$adminConfig = isset($config) && is_array($config)
    ? $config
    : \App\Support\Config::load('admin');
$isFull = ($adminConfig['mode'] ?? 'minimal') === 'full';

$navItems = [
    ['path' => '/admin', 'label' => 'Dashboard', 'icon' => 'home'],
];

if ($isFull) {
    $navItems[] = ['path' => '/admin/articles', 'label' => 'Articles', 'icon' => 'document-text'];
}

$navItems[] = ['path' => '/admin/pages', 'label' => 'Pages', 'icon' => 'link'];
$navItems[] = ['path' => '/admin/categories', 'label' => 'Categories', 'icon' => 'folder'];
$navItems[] = ['path' => '/admin/media', 'label' => 'Media', 'icon' => 'photo'];
// Docs viewer (#1844). Visible in both minimal and full modes -- operators
// reach for project docs more often than per-mode features, and the route
// is read-only.
$navItems[] = ['path' => '/admin/docs', 'label' => 'Docs', 'icon' => 'book-open'];

if ($isFull) {
    // Separator before the power-user group
    $navItems[] = ['separator' => true];
    // Two grouped entry points -- each opens a tabbed page
    $navItems[] = ['path' => '/admin/blacklist', 'label' => 'Security', 'icon' => 'shield-exclamation', 'matchPaths' => ['/admin/blacklist', '/admin/sandbox']];
    $navItems[] = ['path' => '/admin/sitecheck', 'label' => 'Audit',    'icon' => 'shield-check',       'matchPaths' => ['/admin/sitecheck', '/admin/links', '/admin/pagespeed']];
    // API keys management for /api/v1. Advanced feature -- single-
    // operator deployments don't need it, so it stays gated to full
    // mode.
    $navItems[] = ['path' => '/admin/api-keys', 'label' => 'API Keys', 'icon' => 'key'];
}

// Settings always last, separated from the groups above
$navItems[] = ['separator' => true];
$navItems[] = ['path' => '/admin/settings', 'label' => 'Settings', 'icon' => 'cog'];

function isActive(string $path, string $currentPath): bool {
    // Dashboard - exact match only
    if ($path === '/admin') {
        return $currentPath === '/admin' || $currentPath === '/admin/';
    }
    // Analytics (Traffic) - exact match to not conflict with /admin/analytics/conversions
    if ($path === '/admin/analytics') {
        return $currentPath === '/admin/analytics' || $currentPath === '/admin/analytics/';
    }
    // Other paths - prefix match
    return str_starts_with($currentPath, $path);
}

function getIcon(string $icon): string {
    // Maps our internal icon names to Remix Icon classes (loaded via
    // remixicon.css in themes/admin/layout.php). Call site uses the
    // returned class on an <i> element.
    $map = [
        'home'                       => 'ri-home-5-line',
        'document-text'              => 'ri-article-line',
        'queue-list'                 => 'ri-list-check-2',
        'sparkles'                   => 'ri-magic-line',
        'folder'                     => 'ri-folder-line',
        'book-open'                  => 'ri-book-open-line',
        'document-duplicate'         => 'ri-file-copy-line',
        'squares-2x2'                => 'ri-grid-line',
        'chart-bar'                  => 'ri-bar-chart-2-line',
        'funnel'                     => 'ri-filter-3-line',
        'shield-exclamation'         => 'ri-shield-flash-line',
        'exclamation-triangle'       => 'ri-error-warning-line',
        'link'                       => 'ri-link',
        'shield-check'               => 'ri-shield-check-line',
        'envelope'                   => 'ri-mail-line',
        'magnifying-glass'           => 'ri-search-line',
        'light-bulb'                 => 'ri-lightbulb-line',
        'key'                        => 'ri-key-2-line',
        'clipboard-document-list'    => 'ri-clipboard-line',
        'user-plus'                  => 'ri-user-add-line',
        'inbox'                      => 'ri-inbox-line',
        'photo'                      => 'ri-image-line',
        'cog'                        => 'ri-settings-3-line',
        'bolt'                       => 'ri-flashlight-line',
    ];
    return $map[$icon] ?? 'ri-question-line';
}
?>

<aside data-bird-sidebar class="h-full flex flex-col">
    <!-- Logo -->
    <div class="p-4 border-b border-slate-700">
        <a href="/admin" class="bird-logo" aria-label="<?= htmlspecialchars(site_name()) ?> admin">
            <img src="/assets/brand/bird-logo.svg" width="32" height="32" alt="">
            <span x-show="!sidebarCollapsed" x-cloak>
                <span class="bird-logo-name"><?= htmlspecialchars(site_name()) ?></span><br>
                <span class="bird-logo-tag">Admin</span>
            </span>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto">
        <?php foreach ($navItems as $item): ?>
            <?php if (isset($item['separator'])): ?>
                <div class="my-3 border-t border-slate-700"></div>
            <?php else: ?>
                <?php
                    if (!empty($item['matchPaths'])) {
                        $active = false;
                        foreach ($item['matchPaths'] as $mp) {
                            if (str_starts_with($currentPath, $mp)) { $active = true; break; }
                        }
                    } else {
                        $active = isActive($item['path'], $currentPath);
                    }
                ?>
                <a href="<?= $item['path'] ?>"
                   :title="sidebarCollapsed ? '<?= htmlspecialchars($item['label'], ENT_QUOTES) ?>' : null"
                   class="flex items-center space-x-3 px-3 py-2.5 transition-colors border-l-2
                          <?= $active ? 'bg-blue-950 text-blue-300 border-blue-500' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200 border-transparent' ?>">
                    <i class="<?= getIcon($item['icon']) ?> text-lg leading-none"></i>
                    <span x-show="!sidebarCollapsed" x-cloak class="text-sm font-medium"><?= $item['label'] ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Collapse toggle (desktop only) -->
    <div class="hidden lg:block p-2 border-t border-slate-800">
        <button @click="sidebarCollapsed = !sidebarCollapsed"
                class="w-full flex items-center justify-center gap-2 px-3 py-2 text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors text-xs">
            <i :class="sidebarCollapsed ? 'ri-arrow-right-s-line' : 'ri-arrow-left-s-line'" class="text-base leading-none"></i>
            <span x-show="!sidebarCollapsed" x-cloak>Collapse</span>
        </button>
    </div>

</aside>
