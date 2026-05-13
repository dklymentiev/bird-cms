<?php

declare(strict_types=1);

/**
 * Anti-bot ingest configuration.
 *
 * The visits.db SQLite database is fed by scripts/parse-access-log.php from
 * the nginx access log and is read by scripts/auto-blacklist.php to identify
 * scanner patterns. It powers /admin/blacklist + /admin/sandbox.
 *
 * It is NOT a user-facing analytics dashboard -- those live in Statio
 * (see STATIO_* in .env). This config only controls retention so the
 * SQLite file does not grow without bound.
 */

$env = static function (string $key) {
    $v = $_ENV[$key] ?? getenv($key);
    if ($v === false || $v === "") {
        return null;
    }
    return $v;
};

return [
    // Number of rows kept in visits.db. Enforced by a SQLite trigger AFTER
    // INSERT, so the cap holds even under spike traffic. Set 0 for unlimited
    // (only on disks with room to spare). Required.
    "retention_visits" => $env("ANALYTICS_RETENTION_VISITS") !== null
        ? (int) $env("ANALYTICS_RETENTION_VISITS")
        : null,
];
