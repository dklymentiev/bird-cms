<?php
/**
 * Analytics retention pruner.
 *
 * Daily cron. The actual row-cap is enforced by a SQLite AFTER INSERT trigger
 * installed by App\Support\Analytics::ensureTrigger(), so retention itself
 * cannot break — even under DDoS / viral spike. This script just reclaims disk
 * space (VACUUM) and verifies the trigger is current.
 *
 * Usage:
 *   php scripts/analytics-prune.php
 *
 * Exit codes:
 *   0 — done (or skipped because mode is not internal/hybrid)
 *   1 — error
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Support\Analytics;

$dbPath = Analytics::dbPath();
if (!file_exists($dbPath)) {
    fwrite(STDERR, "visits.db does not exist at $dbPath; nothing to prune.\n");
    exit(0);
}

$before = filesize($dbPath);
$beforeRows = 0;

try {
    $db = Analytics::openDb();
    $beforeRows = (int) $db->query('SELECT COUNT(*) FROM visits')->fetchColumn();

    // Force trigger refresh in case retention_visits changed.
    Analytics::ensureTrigger($db);

    // Reclaim disk after any deletes the trigger has accumulated.
    $db->exec('VACUUM');
    $db = null; // close before stat

    $after = filesize($dbPath);
    $afterRows = $beforeRows; // VACUUM does not change row count

    printf(
        "Analytics prune complete. Rows: %d. Disk: %.1f MB -> %.1f MB.\n",
        $afterRows,
        $before / 1024 / 1024,
        $after / 1024 / 1024
    );
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Analytics prune failed: " . $e->getMessage() . "\n");
    exit(1);
}
