<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;

/**
 * Sliding-window rate limiter backed by SQLite.
 *
 * Why sliding-window: it gives accurate counts across the configured
 * windows (e.g. 5/min and 50/day) without bursting at window edges.
 * For the small per-endpoint volumes Bird CMS sees (form submits,
 * subscriber signups), the storage cost is negligible.
 *
 * Why SQLite: keeps the zero-extra-infra promise. No Redis, no
 * external dep beyond what Bird already requires (pdo_sqlite).
 *
 * Usage:
 *
 *   $rl = new RateLimit();
 *   $verdict = $rl->hit('lead', $clientIp);
 *   if (!$verdict['allowed']) {
 *       http_response_code(429);
 *       header('Retry-After: ' . $verdict['retry_after']);
 *       echo json_encode(['error' => 'rate_limited', 'retry_after' => $verdict['retry_after']]);
 *       exit;
 *   }
 *
 * Disable globally via RATE_LIMIT_ENABLED=false (default: enabled).
 * Per-endpoint limits in config/rate-limit.php.
 */
final class RateLimit
{
    private PDO $db;
    private array $config;
    private bool $enabled;

    public function __construct(?PDO $db = null, ?array $config = null)
    {
        $this->enabled = filter_var(
            $_ENV['RATE_LIMIT_ENABLED'] ?? 'true',
            FILTER_VALIDATE_BOOLEAN
        );

        $this->config = $config ?? $this->loadConfig();
        $this->db = $db ?? $this->openDb();
        $this->ensureSchema();
    }

    /**
     * Check + record a request. Returns:
     *   ['allowed' => bool, 'retry_after' => int (seconds), 'remaining' => int]
     *
     * `retry_after` is 0 when allowed; a positive int when denied indicates
     * how long the client should wait before the next attempt has a chance.
     */
    public function hit(string $endpoint, string $clientKey): array
    {
        if (!$this->enabled) {
            return ['allowed' => true, 'retry_after' => 0, 'remaining' => PHP_INT_MAX];
        }

        $limits = $this->config[$endpoint] ?? null;
        if ($limits === null) {
            // Unknown endpoint = no limit (fail-open by design)
            return ['allowed' => true, 'retry_after' => 0, 'remaining' => PHP_INT_MAX];
        }

        $bucket = $this->bucketKey($endpoint, $clientKey);
        $now = time();
        $this->cleanup($now);

        $worstRetry = 0;
        $worstRemaining = PHP_INT_MAX;

        foreach ($limits as $windowSeconds => $maxRequests) {
            $cutoff = $now - $windowSeconds;
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) AS c, MIN(occurred_at) AS oldest
                 FROM rate_events
                 WHERE bucket_key = :k AND occurred_at >= :cutoff'
            );
            $stmt->execute([':k' => $bucket, ':cutoff' => $cutoff]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int) ($row['c'] ?? 0);
            $oldest = $row['oldest'] !== null ? (int) $row['oldest'] : $now;

            $remaining = max(0, $maxRequests - $count);
            if ($remaining < $worstRemaining) {
                $worstRemaining = $remaining;
            }

            if ($count >= $maxRequests) {
                $retry = max(1, $windowSeconds - ($now - $oldest));
                if ($retry > $worstRetry) {
                    $worstRetry = $retry;
                }
            }
        }

        if ($worstRetry > 0) {
            return ['allowed' => false, 'retry_after' => $worstRetry, 'remaining' => 0];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO rate_events (bucket_key, occurred_at) VALUES (:k, :t)'
        );
        $stmt->execute([':k' => $bucket, ':t' => $now]);

        return ['allowed' => true, 'retry_after' => 0, 'remaining' => max(0, $worstRemaining - 1)];
    }

    /**
     * Render a 429 response and exit. Convenience for endpoint files.
     */
    public function deny(int $retryAfter): void
    {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'rate_limited',
            'retry_after' => $retryAfter,
        ]);
        exit;
    }

    private function bucketKey(string $endpoint, string $clientKey): string
    {
        return $endpoint . ':' . hash('sha256', $clientKey);
    }

    private function cleanup(int $now): void
    {
        // Reap rows older than the longest configured window (default 24h)
        $maxWindow = 0;
        foreach ($this->config as $limits) {
            foreach (array_keys($limits) as $w) {
                if ($w > $maxWindow) $maxWindow = $w;
            }
        }
        if ($maxWindow === 0) $maxWindow = 86400;

        // Cheap probabilistic GC: 1% of requests trigger cleanup
        if (random_int(1, 100) === 1) {
            $this->db->prepare('DELETE FROM rate_events WHERE occurred_at < :cutoff')
                ->execute([':cutoff' => $now - $maxWindow]);
        }
    }

    private function ensureSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS rate_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bucket_key TEXT NOT NULL,
                occurred_at INTEGER NOT NULL
            )'
        );
        $this->db->exec(
            'CREATE INDEX IF NOT EXISTS idx_bucket_time
                ON rate_events (bucket_key, occurred_at)'
        );
    }

    private function openDb(): PDO
    {
        $base = defined('SITE_STORAGE_PATH') ? SITE_STORAGE_PATH : dirname(__DIR__, 2) . '/storage';
        $dir = $base . '/data';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create rate-limit storage dir: $dir");
        }
        $pdo = new PDO('sqlite:' . $dir . '/rate-limit.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        return $pdo;
    }

    private function loadConfig(): array
    {
        $path = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 2)) . '/config/rate-limit.php';
        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) return $loaded;
        }
        return self::defaults();
    }

    public static function defaults(): array
    {
        return [
            'lead'      => [60 => 5, 86400 => 50],   // 5/min, 50/day per IP
            'subscribe' => [60 => 3, 86400 => 20],   // 3/min, 20/day per IP
            'search'    => [60 => 30],               // 30/min per IP
            'track'     => [60 => 60],               // 60/min per IP (page events)
        ];
    }
}
