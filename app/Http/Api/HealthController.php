<?php

declare(strict_types=1);

namespace App\Http\Api;

/**
 * GET /api/v1/health -- liveness probe.
 *
 * Returns {ok: true, version: "<VERSION-file-contents>"}. Intentionally
 * unauthenticated so load balancers, uptime monitors, and Docker
 * health checks can hit it without provisioning an API key.
 *
 * No I/O beyond reading the VERSION file; cheap enough to call on
 * every probe interval.
 */
final class HealthController
{
    public function index(): void
    {
        $versionFile = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 3)) . '/VERSION';
        $version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : 'unknown';

        Response::json([
            'ok'      => true,
            'version' => $version,
        ]);
    }
}
