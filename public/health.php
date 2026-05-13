<?php

declare(strict_types=1);

/**
 * Lightweight health endpoint. Reachable BEFORE bootstrap so the engine
 * can answer probes even when .env is missing or APP_KEY is invalid.
 *
 * Returns 200 + JSON when:
 *   - VERSION file is readable
 *   - Required PHP extensions are loaded
 *
 * Returns 503 + JSON listing the failures otherwise. Does not leak
 * config or paths beyond what's needed for an operator to act.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$siteRoot       = dirname(__DIR__);
$versionFile    = $siteRoot . '/VERSION';
$installedLock  = $siteRoot . '/storage/installed.lock';
$requiredExt    = ['mbstring', 'json', 'openssl', 'curl', 'intl', 'gd', 'pdo_sqlite', 'fileinfo'];

$missingExt = array_values(array_filter(
    $requiredExt,
    static fn(string $ext): bool => !extension_loaded($ext)
));

$version = is_file($versionFile)
    ? trim((string) file_get_contents($versionFile))
    : null;

$installed = file_exists($installedLock);

$ok = $version !== null && $missingExt === [];

http_response_code($ok ? 200 : 503);

echo json_encode([
    'status'             => $ok ? 'ok' : 'degraded',
    'version'            => $version,
    'php_version'        => PHP_VERSION,
    'installed'          => $installed,
    'missing_extensions' => $missingExt,
    'time'               => gmdate('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
