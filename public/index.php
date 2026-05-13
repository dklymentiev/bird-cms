<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Install guard. bootstrap.php requires .env + config/app.php and refuses to
// boot without an APP_KEY; on a fresh install neither file exists yet. The
// guard has three responsibilities:
//   1. Pre-install (no lock): divert to /install or render the wizard.
//   2. Post-install: pass through to bootstrap normally.
//   3. /install/* paths always go to install.php, even after install — that
//      script handles its own idempotency (e.g. lets /install/success render
//      once, then redirects everything else to /admin).
// ---------------------------------------------------------------------------
$installLock = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__, 2) . '/storage/installed.lock';
$installUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isInstallPath = $installUri === '/install' || str_starts_with($installUri, '/install/');

// Health probe: bootstrap-free, available before and after install.
if ($installUri === '/health') {
    require __DIR__ . '/health.php';
    return;
}

if (!file_exists($installLock)) {
    if ($isInstallPath) {
        require __DIR__ . '/install.php';
        return;
    }
    header('Location: /install', true, 302);
    return;
}

if ($isInstallPath) {
    require __DIR__ . '/install.php';
    return;
}

require __DIR__ . '/../bootstrap.php';

// Security headers — set before any controller writes a body so an
// early-exiting controller (e.g. AssetController) still has them attached.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

\App\Http\Frontend\Dispatcher::fromSiteConfig(__DIR__)
    ->dispatch($_SERVER['REQUEST_URI'] ?? '/', $_GET);
