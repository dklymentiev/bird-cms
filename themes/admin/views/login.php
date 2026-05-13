<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login &middot; <?= htmlspecialchars(site_name()) ?> Admin</title>
    <link rel="icon" type="image/svg+xml" href="/assets/brand/bird-logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap">
    <?= tailwind_cdn_script() ?>
    <?php
        $adminBrandCssPath = SITE_ROOT . '/public/admin/assets/brand.css';
        $adminCssPath      = SITE_ROOT . '/public/admin/assets/admin.css';
        $adminBrandV = is_file($adminBrandCssPath) ? filemtime($adminBrandCssPath) : '0';
        $adminCssV   = is_file($adminCssPath)      ? filemtime($adminCssPath)      : '0';
    ?>
    <link rel="stylesheet" href="/admin/assets/brand.css?v=<?= $adminBrandV ?>">
    <link rel="stylesheet" href="/admin/assets/admin.css?v=<?= $adminCssV ?>">
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md p-6">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="/" class="bird-logo" style="justify-content: center;">
                <img src="/assets/brand/bird-logo.svg" width="56" height="56" alt="">
            </a>
            <h1 class="text-2xl font-semibold mt-4" style="letter-spacing: -0.01em;"><?= htmlspecialchars(site_name()) ?></h1>
            <p class="text-slate-500 mt-1 uppercase tracking-wider text-sm">Admin Panel</p>
        </div>

        <!-- Login card -->
        <div class="bg-slate-800 border border-slate-700 p-8" style="box-shadow: var(--bird-shadow-lg);">
            <?php if ($lockedOut ?? false): ?>
                <div class="bg-red-100 border-l-2 border-red-500 p-4 mb-6">
                    <p class="text-red-700 font-medium">Too many failed attempts</p>
                    <p class="text-red-700 text-sm mt-1">
                        Please try again in <?= ceil(($lockoutRemaining ?? 0) / 60) ?> minutes.
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <?php foreach ($error as $msg): ?>
                    <div class="bg-red-100 border-l-2 border-red-500 p-4 mb-6">
                        <p class="text-red-700"><?= htmlspecialchars($msg['message']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" action="/admin/login" class="space-y-6">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">

                <div>
                    <label for="username" class="block text-sm font-medium text-slate-400 mb-2 uppercase tracking-wider">
                        Username
                    </label>
                    <input type="text" id="username" name="username" required
                           autocomplete="username" autofocus
                           <?= ($lockedOut ?? false) ? 'disabled' : '' ?>
                           class="w-full px-4 py-3">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-400 mb-2 uppercase tracking-wider">
                        Password
                    </label>
                    <input type="password" id="password" name="password" required
                           autocomplete="current-password"
                           <?= ($lockedOut ?? false) ? 'disabled' : '' ?>
                           class="w-full px-4 py-3">
                </div>

                <button type="submit"
                        <?= ($lockedOut ?? false) ? 'disabled' : '' ?>
                        class="w-full bg-blue-600 py-3 px-4 font-semibold uppercase tracking-wider text-sm transition-colors">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center mt-6 text-slate-600 text-sm">
            <a href="/" class="text-slate-400">Back to site</a>
        </p>
    </div>
</body>
</html>
