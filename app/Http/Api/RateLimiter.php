<?php

declare(strict_types=1);

namespace App\Http\Api;

use App\Support\RateLimit;

/**
 * Per-key sliding-window rate limit for /api/v1.
 *
 * Thin wrapper around App\Support\RateLimit: the underlying engine
 * already implements a sliding-window counter backed by SQLite (used
 * by the public form endpoints since v3.1.0-rc.2). This class scopes
 * counters to the API-key hash rather than the caller IP so callers
 * behind shared CGNAT aren't penalised by a noisy neighbour.
 *
 * Limit policy lives in config/rate-limit.php under the `api_v1` key
 * so an operator can tune it without touching code. Default: 60
 * req/min per key.
 *
 * When the limit is exceeded the response is 429 with:
 *   Retry-After:   <seconds-until-next-attempt>
 *   X-RateLimit-Remaining: 0
 * and a JSON body shaped like every other API error.
 */
final class RateLimiter
{
    private RateLimit $impl;

    public function __construct(?RateLimit $impl = null)
    {
        $this->impl = $impl ?? new RateLimit();
    }

    /**
     * Hit the limiter. Emits 429 + exit when over budget, otherwise
     * returns nothing and stamps response headers so the caller can
     * monitor its remaining budget.
     */
    public function enforce(string $keyHash): void
    {
        $verdict = $this->impl->hit('api_v1', $keyHash);

        if (!$verdict['allowed']) {
            header('Retry-After: ' . $verdict['retry_after']);
            header('X-RateLimit-Remaining: 0');
            Response::error(
                'rate_limited',
                'API key over the per-minute request budget. Retry after ' . $verdict['retry_after'] . 's.',
                429
            );
        }

        if ($verdict['remaining'] !== PHP_INT_MAX) {
            header('X-RateLimit-Remaining: ' . $verdict['remaining']);
        }
    }
}
