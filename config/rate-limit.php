<?php

declare(strict_types=1);

/**
 * Rate-limit policy per endpoint.
 *
 * Each endpoint gets a list of [window_seconds => max_requests] pairs.
 * A request is allowed only when ALL configured windows still have
 * capacity. The most restrictive window drives the 429 retry-after.
 *
 * Bucket key = endpoint + sha256(clientKey). The client key is the IP
 * by default; harden with IP + User-Agent fingerprint if you face
 * residential-rotation abuse.
 *
 * Disable everything globally with `RATE_LIMIT_ENABLED=false` in .env.
 *
 * Note: /admin/login does NOT use this module — it has its own
 * lockout mechanism (see app/Admin/Auth.php) with stricter
 * fail-open-to-locked semantics. Unifying is filed as a follow-up.
 */

return [
    // /api/lead.php — Statio proxy. Aggressive on per-day to deter
    // bot lead-stuffing while still allowing reasonable form retries.
    'lead' => [
        60     => 5,    // 5 requests per minute
        86400  => 50,   // 50 requests per day
    ],

    // /api/subscribe.php — newsletter signup. Tighter than leads
    // because legitimate use is "submit once, done."
    'subscribe' => [
        60     => 3,    // 3 requests per minute
        86400  => 20,   // 20 requests per day
    ],

    // /api/search.php — site search. Higher per-minute because
    // type-ahead UIs fire per keystroke.
    'search' => [
        60     => 30,
    ],

    // /api/track-event.php — frontend pixel events. Loose because
    // legitimate event volume is high.
    'track' => [
        60     => 60,
    ],

    // /api/v1/* — public REST API. Per-key sliding window: 60 req/min.
    // Buckets are keyed on the SHA-256 hash of the API key (not the
    // caller IP) so a mobile client behind shared CGNAT isn't punished
    // by a noisy neighbour. The limiter sits inside Authenticator's
    // guard path so an unauthenticated request gets 401 long before
    // it counts against any bucket.
    'api_v1' => [
        60     => 60,   // 60 requests per minute per key
    ],
];
