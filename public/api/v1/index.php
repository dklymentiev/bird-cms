<?php

declare(strict_types=1);

/**
 * Bird CMS public REST API v1 -- entry point.
 *
 * Mirrors the MCP tool surface over HTTP for non-AI integrations
 * (mobile apps, third-party publishers, headless frontends). Auth is
 * Bearer-token; see app/Http/Api/Authenticator.php for the storage
 * format and constant-time compare.
 *
 * Bootstrap behaviour matches public/admin/index.php: refuse to run
 * pre-install (no .env / no installed.lock) and emit a JSON 503 with
 * a machine-readable hint so a mobile client can show a useful error
 * instead of an HTML wizard redirect.
 */

$installLock = dirname(__DIR__, 3) . '/storage/installed.lock';
if (!file_exists($installLock)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => [
            'code'    => 'not_installed',
            'message' => 'Bird CMS is not installed yet. Complete the install wizard before calling the API.',
        ],
    ]);
    return;
}

require_once dirname(__DIR__, 3) . '/bootstrap.php';

// Security headers. CORS stays off by design -- the v1 API is for
// server-to-server / native-app callers using Bearer auth, not for
// browser-side JS. Re-enable per deployment via a reverse-proxy if
// a single-page-app integration is required.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 3) . '/app/Http/Api/Router.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/Response.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/ResponseSentException.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/Request.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/ApiKeyStore.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/Authenticator.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/RateLimiter.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/HealthController.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/ContentController.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/UrlInventoryController.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/UrlMetaController.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/SiteConfigController.php';
require_once dirname(__DIR__, 3) . '/app/Http/Api/AssetsController.php';

use App\Http\Api\Router;
use App\Http\Api\Response;
use App\Http\Api\Authenticator;
use App\Http\Api\HealthController;
use App\Http\Api\ContentController;
use App\Http\Api\UrlInventoryController;
use App\Http\Api\UrlMetaController;
use App\Http\Api\SiteConfigController;
use App\Http\Api\AssetsController;

$router = new Router('/api/v1');

// Health probe -- unauthenticated, used by load balancers and CI.
$router->get('/health', static function (array $params): void {
    (new HealthController())->index();
});

// Everything below requires authentication + per-key rate limiting.
// Authenticator::guard() reads Authorization: Bearer <key>, validates
// against storage/api-keys.json, and returns the matched record. On
// any failure it emits 401/403 and exits -- no per-route boilerplate.
// RateLimiter::enforce() then scopes a 60-req/min sliding window to
// the SHA-256 of the key so multi-tenant clients behind CGNAT don't
// share a bucket with each other.
$authGuard = static function (string $requiredScope): array {
    $auth = (new Authenticator())->guard($requiredScope);
    (new RateLimiter())->enforce((string) $auth['key_hash']);
    return $auth;
};

// --- Content CRUD (mirrors MCP tools) ---
// Articles: optional category in path. The 4-segment route must be
// declared before the 3-segment one so the matcher picks the more
// specific shape when a category is present.
$router->get('/content/articles/{category}/{slug}', static function (array $p) use ($authGuard): void {
    $authGuard('read');
    (new ContentController())->show('articles', $p['slug'], $p['category']);
});
$router->post('/content/articles/{category}/{slug}', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new ContentController())->upsert('articles', $p['slug'], $p['category']);
});
$router->delete('/content/articles/{category}/{slug}', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new ContentController())->destroy('articles', $p['slug'], $p['category']);
});

// Services follow the same `<type>/<slug>` shape on disk (residential
// /commercial subfolder). Declared before the generic /content/<type>
// /<slug> route so the matcher picks the category-aware shape.
$router->get('/content/services/{category}/{slug}', static function (array $p) use ($authGuard): void {
    $authGuard('read');
    (new ContentController())->show('services', $p['slug'], $p['category']);
});
$router->post('/content/services/{category}/{slug}', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new ContentController())->upsert('services', $p['slug'], $p['category']);
});
$router->delete('/content/services/{category}/{slug}', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new ContentController())->destroy('services', $p['slug'], $p['category']);
});

$router->get('/content/{type}', static function (array $p) use ($authGuard): void {
    $authGuard('read');
    (new ContentController())->index($p['type']);
});
$router->get('/content/{type}/{slug}', static function (array $p) use ($authGuard): void {
    $authGuard('read');
    (new ContentController())->show($p['type'], $p['slug']);
});
$router->post('/content/{type}/{slug}', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new ContentController())->upsert($p['type'], $p['slug']);
});
$router->delete('/content/{type}/{slug}', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new ContentController())->destroy($p['type'], $p['slug']);
});

// --- URL inventory & per-URL meta ---
$router->get('/url-inventory', static function (array $p) use ($authGuard): void {
    $authGuard('read');
    (new UrlInventoryController())->index();
});
$router->get('/url-meta/{path:path}', static function (array $p) use ($authGuard): void {
    $authGuard('read');
    (new UrlMetaController())->show($p['path']);
});
$router->put('/url-meta/{path:path}', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new UrlMetaController())->update($p['path']);
});

// --- Site config (safe whitelist subset) ---
$router->get('/site-config', static function (array $p) use ($authGuard): void {
    $authGuard('read');
    (new SiteConfigController())->show();
});
$router->put('/site-config', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new SiteConfigController())->update();
});

// --- Assets (upload / read / delete). Upload route declared before
// the catch-all /assets/<path> so POST /assets/upload doesn't get
// mis-matched as "upload an asset named upload".
$router->post('/assets/upload', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new AssetsController())->upload();
});
$router->get('/assets/{path:path}', static function (array $p) use ($authGuard): void {
    $authGuard('read');
    (new AssetsController())->show($p['path']);
});
$router->delete('/assets/{path:path}', static function (array $p) use ($authGuard): void {
    $authGuard('write');
    (new AssetsController())->destroy($p['path']);
});

try {
    $router->dispatch(static function (): void {
        Response::error('not_found', 'No such endpoint. See docs/api.md.', 404);
    });
} catch (\Throwable $e) {
    error_log('[api/v1] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL)) {
        Response::error('internal_error', $e->getMessage(), 500);
        return;
    }
    Response::error('internal_error', 'An unexpected error occurred.', 500);
}
