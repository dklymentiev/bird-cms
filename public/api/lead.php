<?php
/**
 * Lead capture API endpoint -- Statio proxy.
 *
 * Forwards POST submissions to STATIO_LEADS_ENDPOINT with bearer auth.
 * No local storage, no SMTP -- Statio handles persistence, attribution,
 * notification routing.
 *
 * Required env (set during install or via Statio site provisioning):
 *   STATIO_LEADS_ENDPOINT  https://statio.example/api/leads
 *   STATIO_API_SECRET      per-site bearer secret from Statio dashboard
 *   STATIO_SITE_GUID       optional, helps Statio multi-tenant routing
 *
 * If STATIO_LEADS_ENDPOINT is unset, the endpoint returns 503 with a
 * machine-readable hint -- the form on the page should disable itself.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$rl = new \App\Support\RateLimit();
$verdict = $rl->hit('lead', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!$verdict['allowed']) {
    $rl->deny($verdict['retry_after']);
}

$endpoint = trim((string) ($_ENV['STATIO_LEADS_ENDPOINT'] ?? ''));
$secret   = trim((string) ($_ENV['STATIO_API_SECRET'] ?? ''));

if ($endpoint === '' || $secret === '') {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'tracking_not_configured',
        'hint'  => 'Set STATIO_LEADS_ENDPOINT and STATIO_API_SECRET in .env. See docs/recipes/integrate-statio.md.',
    ]);
    exit;
}

$payload = $_POST;

// Always include site identity so Statio routes correctly when one
// instance fronts multiple Bird CMS sites.
$payload['site_url']  = $payload['site_url']  ?? rtrim((string) config('site_url'), '/');
$payload['site_name'] = $payload['site_name'] ?? (string) config('site_name');
$siteGuid = trim((string) ($_ENV['STATIO_SITE_GUID'] ?? ''));
if ($siteGuid !== '') {
    $payload['site_guid'] = $siteGuid;
}

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $secret,
        'Accept: application/json',
    ],
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false || $code === 0) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'tracking_unreachable',
        'detail' => $err ?: 'no_response',
    ]);
    exit;
}

http_response_code($code);
echo $body;
