<?php

declare(strict_types=1);

/**
 * Admin Panel Entry Point
 *
 * All admin routes are handled here.
 *
 * No install-guard redirect: on a fresh, unconfigured site bootstrap.php
 * fails loud (APP_KEY refuse-to-boot check). Operators are expected to
 * provision sites via scripts/install-site.sh; redirecting to /install at
 * runtime was a holdover from the in-browser wizard era and broke the
 * /admin endpoint on every site that had been migrated from earlier
 * releases without an installed.lock present.
 */

// Load bootstrap
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Load admin classes
require_once dirname(__DIR__, 2) . '/app/Admin/Router.php';
require_once dirname(__DIR__, 2) . '/app/Admin/Auth.php';
require_once dirname(__DIR__, 2) . '/app/Admin/Controller.php';
require_once dirname(__DIR__, 2) . '/app/Admin/AuthController.php';
require_once dirname(__DIR__, 2) . '/app/Admin/DashboardController.php';
require_once dirname(__DIR__, 2) . '/app/Admin/ArticleController.php';
require_once dirname(__DIR__, 2) . '/app/Admin/CategoriesController.php';
require_once dirname(__DIR__, 2) . '/app/Admin/SecurityController.php';
require_once dirname(__DIR__, 2) . '/app/Admin/MediaController.php';
require_once dirname(__DIR__, 2) . '/app/Admin/SettingsController.php';
require_once dirname(__DIR__, 2) . '/app/Admin/PagesController.php';
require_once dirname(__DIR__, 2) . '/app/Http/Api/ApiKeyStore.php';
require_once dirname(__DIR__, 2) . '/app/Http/Api/Authenticator.php';
require_once dirname(__DIR__, 2) . '/app/Admin/ApiKeysController.php';
require_once dirname(__DIR__, 2) . '/app/Admin/DocsController.php';

use App\Admin\Router;
use App\Admin\AuthController;
use App\Admin\DashboardController;
use App\Admin\ArticleController;
use App\Admin\CategoriesController;
use App\Admin\SecurityController;
use App\Admin\MediaController;
use App\Admin\SettingsController;
use App\Admin\PagesController;
use App\Admin\ApiKeysController;
use App\Admin\DocsController;

// Create router
$router = new Router('/admin');

// Auth routes (no authentication required)
$router->get('/login', AuthController::class, 'showLogin');
$router->post('/login', AuthController::class, 'login');
$router->get('/logout', AuthController::class, 'showLogout');
$router->post('/logout', AuthController::class, 'logout');

// Dashboard (authentication required - handled in controller)
$router->get('', DashboardController::class, 'index');

// Articles
$router->get('/articles', ArticleController::class, 'index');
$router->get('/articles/new', ArticleController::class, 'create');
$router->post('/articles/create', ArticleController::class, 'store');
$router->get('/articles/{category}/{slug}', ArticleController::class, 'show');
$router->get('/articles/{category}/{slug}/edit', ArticleController::class, 'edit');
$router->post('/articles/{category}/{slug}/update', ArticleController::class, 'update');
$router->post('/articles/{category}/{slug}/publish', ArticleController::class, 'publish');
$router->post('/articles/{category}/{slug}/unpublish', ArticleController::class, 'unpublish');
$router->post('/articles/{category}/{slug}/schedule', ArticleController::class, 'schedule');
$router->post('/articles/{category}/{slug}/duplicate', ArticleController::class, 'duplicate');
$router->post('/articles/{category}/{slug}/delete', ArticleController::class, 'delete');

// Categories
$router->get('/categories', CategoriesController::class, 'index');
$router->get('/categories/new', CategoriesController::class, 'create');
$router->post('/categories/create', CategoriesController::class, 'store');
$router->get('/categories/{slug}/edit', CategoriesController::class, 'edit');
$router->post('/categories/{slug}/update', CategoriesController::class, 'update');
$router->post('/categories/{slug}/delete', CategoriesController::class, 'delete');

// Security
$router->get('/blacklist', SecurityController::class, 'blacklist');
$router->post('/blacklist/unblock', SecurityController::class, 'unblock');
$router->get('/sandbox', SecurityController::class, 'sandbox');
$router->post('/sandbox/verdict', SecurityController::class, 'sandboxVerdict');
$router->post('/sandbox/bulk-bot', SecurityController::class, 'sandboxBulkBot');
$router->post('/sandbox/blacklist', SecurityController::class, 'sandboxBlacklist');
$router->get('/sitecheck', SecurityController::class, 'sitecheck');
$router->post('/sitecheck/run', SecurityController::class, 'runSitecheck');
$router->get('/links', SecurityController::class, 'links');
$router->post('/links/check', SecurityController::class, 'runLinkCheck');
$router->get('/pagespeed', SecurityController::class, 'pagespeed');
$router->post('/pagespeed/run', SecurityController::class, 'runPagespeed');


// Settings: General tab (editable identity) + read-only sub-tabs + theme switcher
$router->get('/settings', SettingsController::class, 'index');
$router->get('/settings/general', SettingsController::class, 'general');
$router->post('/settings/general/save', SettingsController::class, 'saveGeneral');
$router->post('/settings/theme', SettingsController::class, 'setTheme');

// Pages: full URL inventory + per-URL overrides (sitemap, noindex, priority,
// template) plus content/meta editing for any URL the inventory lists.
$router->get('/pages', PagesController::class, 'index');
$router->post('/pages/update', PagesController::class, 'update');
$router->post('/pages/edit-content', PagesController::class, 'editContent');
$router->post('/pages/save-content', PagesController::class, 'saveContent');
$router->post('/pages/save-template', PagesController::class, 'saveTemplate');

// Media
$router->get('/media', MediaController::class, 'index');
$router->post('/media/upload', MediaController::class, 'upload');
$router->post('/media/create-folder', MediaController::class, 'createFolder');
$router->post('/media/delete', MediaController::class, 'delete');
$router->get('/media/info', MediaController::class, 'info');

// API Keys (full mode only -- sidebar hides this link on minimal-mode
// sites, but routes always resolve so an operator who knows the URL
// can still manage keys after switching modes).
$router->get('/api-keys', ApiKeysController::class, 'index');
$router->get('/api-keys/new', ApiKeysController::class, 'create');
$router->post('/api-keys/create', ApiKeysController::class, 'store');
$router->post('/api-keys/{hash}/revoke', ApiKeysController::class, 'revoke');

// Docs viewer (planner #1844). Read-only renderer for project markdown so
// operators don't need to git clone or browse GitLab. The /asset route is
// kept narrowly scoped: docs/ subtree only, extension whitelist enforced
// inside the controller.
$router->get('/docs', DocsController::class, 'index');
$router->get('/docs/asset/{path}', DocsController::class, 'asset');
$router->get('/docs/{path}', DocsController::class, 'show');

// Dispatch request
try {
    $router->dispatch();
} catch (\Throwable $e) {
    // Log error
    error_log("Admin error: " . $e->getMessage());

    // Show error page in development
    if (($_ENV['APP_DEBUG'] ?? false) || ($_SERVER['APP_DEBUG'] ?? false)) {
        http_response_code(500);
        echo '<h1>Error</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        http_response_code(500);
        echo 'An error occurred. Please try again later.';
    }
}
