<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Anti-bot ingest store helper.
 *
 * Owns the visits.db SQLite schema/retention used by the blacklist pipeline:
 *   nginx access.log
 *     -> scripts/parse-access-log.php (cron 5min, INSERT into visits)
 *     -> scripts/auto-blacklist.php   (cron 15min, scan + write blacklist.txt)
 *     -> scripts/generate-blacklist-conf.php (cron hourly, render nginx conf)
 *
 * NOT a user-facing analytics module. Bird CMS delegates dashboards to
 * Statio (see STATIO_* in .env). visits.db is internal anti-bot fuel.
 *
 * Required env: ANALYTICS_RETENTION_VISITS (set to 0 = unlimited, otherwise
 * an integer cap enforced by a SQLite AFTER INSERT trigger).
 */
final class Analytics
{
    private static ?array $config = null;

    /** @return array{retention_visits:?int} */
    private static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        $cfg = Config::load("analytics");
        if (!is_array($cfg)) {
            throw new \RuntimeException("config/analytics.php must return an array");
        }

        if (!array_key_exists("retention_visits", $cfg) || $cfg["retention_visits"] === null) {
            throw new \RuntimeException(
                "ANALYTICS_RETENTION_VISITS not set. Set an integer (0 = unlimited)."
            );
        }

        return self::$config = [
            "retention_visits" => (int) $cfg["retention_visits"],
        ];
    }

    /** @return int|null Number of visits to keep, or null for unlimited. */
    public static function retentionVisits(): ?int
    {
        $n = self::config()["retention_visits"];
        return $n > 0 ? $n : null;
    }

    public static function dbPath(): string
    {
        return SITE_STORAGE_PATH . "/analytics/visits.db";
    }

    /**
     * Open visits.db. Creates the directory on demand.
     * Initializes the retention trigger on each open (cheap; SQLite no-op
     * if the trigger already matches).
     */
    public static function openDb(): \PDO
    {
        $path = self::dbPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create analytics directory: $dir");
        }
        $db = new \PDO("sqlite:" . $path);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::ensureTrigger($db);
        return $db;
    }

    /**
     * Install or refresh the retention trigger. Idempotent.
     */
    public static function ensureTrigger(\PDO $db): void
    {
        $tableExists = $db->query(
            "SELECT name FROM sqlite_master WHERE type=\"table\" AND name=\"visits\""
        )->fetchColumn();
        if (!$tableExists) {
            return;
        }

        $retention = self::retentionVisits();
        if ($retention === null) {
            $db->exec("DROP TRIGGER IF EXISTS cap_visits");
            return;
        }

        $threshold = $retention + 1000;
        $keep = $retention;
        $db->exec("DROP TRIGGER IF EXISTS cap_visits");
        $db->exec(
            "CREATE TRIGGER cap_visits AFTER INSERT ON visits
             WHEN NEW.id > (SELECT MIN(id) FROM visits) + $threshold
             BEGIN
                DELETE FROM visits WHERE id < NEW.id - $keep;
             END"
        );
    }
}
