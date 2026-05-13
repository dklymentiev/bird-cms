<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> &middot; <?= htmlspecialchars(site_name()) ?> Admin</title>

    <link rel="icon" type="image/svg+xml" href="/assets/brand/bird-logo.svg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap">
    <!-- Remix Icon (icon font for sidebar/header glyphs) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">

    <?= tailwind_cdn_script() ?>

    <?php
        $adminBrandCssPath = SITE_ROOT . '/public/admin/assets/brand.css';
        $adminCssPath      = SITE_ROOT . '/public/admin/assets/admin.css';
        $adminBrandV = is_file($adminBrandCssPath) ? filemtime($adminBrandCssPath) : '0';
        $adminCssV   = is_file($adminCssPath)      ? filemtime($adminCssPath)      : '0';
    ?>
    <link rel="stylesheet" href="/admin/assets/brand.css?v=<?= $adminBrandV ?>">
    <link rel="stylesheet" href="/admin/assets/admin.css?v=<?= $adminCssV ?>">

    <?php
        // highlight.js is only useful on the docs viewer. Load it
        // conditionally so the other admin pages stay lean.
        $isDocsView = str_starts_with($currentPath ?? '', '/admin/docs');
    ?>
    <?php if ($isDocsView): ?>
        <link rel="stylesheet"
              href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/github-dark.min.css">
        <script defer
                src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/highlight.min.js"></script>
        <script>
            // Auto-run highlight.js on every <pre><code class="language-X"> the
            // markdown renderer emits. DOMContentLoaded fires before the
            // defer'd script in some browsers, so wait for window.load.
            window.addEventListener('load', function () {
                if (window.hljs) { window.hljs.highlightAll(); }
            });
        </script>
    <?php endif; ?>
</head>
<!-- Pre-Alpine: read sidebar state from localStorage and apply class to <html>
     BEFORE first paint. Without this Alpine boots after layout, sidebar
     starts wide, then snaps to collapsed -- visible flash on every nav. -->
<script>
    if (localStorage.getItem('bird-sidebar-collapsed') === '1') {
        document.documentElement.classList.add('bird-sidebar-collapsed');
    }
</script>
<body>
    <!-- Top-level Alpine state for sidebar collapse. Persists per-browser
         so the operator's preference survives page navigation. -->
    <div class="flex h-screen overflow-hidden"
         x-data="{ sidebarOpen: window.matchMedia('(min-width: 1024px)').matches, sidebarCollapsed: localStorage.getItem('bird-sidebar-collapsed') === '1' }"
         x-init="$watch('sidebarCollapsed', v => { localStorage.setItem('bird-sidebar-collapsed', v ? '1' : '0'); document.documentElement.classList.toggle('bird-sidebar-collapsed', v); })">

        <!-- Mobile backdrop: closes sidebar on tap when open at < lg. -->
        <div x-show="sidebarOpen" x-cloak
             @click="sidebarOpen = false"
             class="fixed inset-0 bg-black/50 z-30 lg:hidden"></div>

        <!-- Sidebar wrapper. On lg+: static, in-flow, 64-or-256px wide via
             .bird-sidebar-collapsed CSS. On < lg: fixed drawer, slide via
             .is-open class (no transform string -- avoids re-creating GPU
             compositing layers on every navigation, which caused vertical
             jitter). -->
        <div class="bird-sidebar-wrapper fixed lg:static inset-y-0 left-0 z-40 bg-slate-900 border-r border-slate-800 w-64 -translate-x-full lg:translate-x-0 transition-transform lg:transition-none"
             :class="sidebarOpen ? 'translate-x-0' : ''">
            <?php require __DIR__ . '/partials/sidebar.php'; ?>
        </div>

        <div class="flex-1 flex flex-col overflow-hidden">
            <?php require __DIR__ . '/partials/header.php'; ?>

            <main class="flex-1 overflow-y-auto p-6 bg-slate-800">
                <?php require __DIR__ . '/partials/flash.php'; ?>
                <?= $content ?>
            </main>
        </div>
    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
