<?php

declare(strict_types=1);

namespace App\Http\Api;

/**
 * Storage layer for /api/v1 keys.
 *
 * Keys live in storage/api-keys.json as a flat array of records:
 *   [
 *     {
 *       "key_hash":      "<sha256 hex>",   // 64-char hex
 *       "label":         "Mobile app v1",
 *       "scope":         "read"|"write",
 *       "created_at":    "2026-05-10T12:34:56Z",
 *       "last_used_at":  "2026-05-10T18:00:00Z" | null,
 *       "revoked_at":    "..."|null
 *     },
 *     ...
 *   ]
 *
 * Plaintext keys are NEVER persisted; only the SHA-256 hash. The
 * plaintext is shown once at creation time via the admin UI and is
 * unrecoverable afterwards.
 *
 * Concurrency: the store is small (operator-scale, not user-scale --
 * dozens of keys at most), so we read-modify-write under flock()
 * rather than introducing a sqlite dependency. The atomic temp+rename
 * keeps the file readable even mid-write.
 */
final class ApiKeyStore
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Default location: storage/api-keys.json under the site root.
     * Used by the entry point and admin controller; tests inject a
     * custom path through the constructor.
     */
    public static function defaultPath(): string
    {
        $base = defined('SITE_STORAGE_PATH') ? SITE_STORAGE_PATH : (defined('SITE_ROOT') ? SITE_ROOT . '/storage' : dirname(__DIR__, 3) . '/storage');
        return $base . '/api-keys.json';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $raw = (string) file_get_contents($this->path);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * Find the (active) record whose key_hash matches $hash.
     * Revoked keys return null even when the hash matches -- the
     * caller treats them the same as "no such key" so the API
     * never reveals which condition failed.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveByHash(string $hash): ?array
    {
        foreach ($this->all() as $row) {
            $stored = (string) ($row['key_hash'] ?? '');
            if ($stored === '') {
                continue;
            }
            // Constant-time compare even though we're picking a record:
            // the loop bound is the # of keys (tiny) but the per-record
            // compare should still resist timing analysis.
            if (hash_equals($stored, $hash)) {
                if (($row['revoked_at'] ?? null) !== null) {
                    return null;
                }
                return $row;
            }
        }
        return null;
    }

    /**
     * Persist a new key. Caller supplies the hash + label + scope; the
     * plaintext key never enters this layer. Returns the record as
     * stored so the admin controller can display it back.
     *
     * @return array<string, mixed>
     */
    public function create(string $hash, string $label, string $scope): array
    {
        if (!in_array($scope, ['read', 'write'], true)) {
            throw new \InvalidArgumentException('Scope must be "read" or "write".');
        }
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 100) {
            throw new \InvalidArgumentException('Label is required (1-100 chars).');
        }

        $row = [
            'key_hash'     => $hash,
            'label'        => $label,
            'scope'        => $scope,
            'created_at'   => gmdate('Y-m-d\TH:i:s\Z'),
            'last_used_at' => null,
            'revoked_at'   => null,
        ];
        $this->mutate(static function (array $rows) use ($row): array {
            $rows[] = $row;
            return $rows;
        });
        return $row;
    }

    /**
     * Mark a key revoked. Idempotent: revoking an already-revoked key
     * is a no-op (returns false, doesn't throw).
     */
    public function revoke(string $hash): bool
    {
        $hit = false;
        $this->mutate(static function (array $rows) use ($hash, &$hit): array {
            foreach ($rows as &$row) {
                if (!isset($row['key_hash']) || !hash_equals((string) $row['key_hash'], $hash)) {
                    continue;
                }
                if (($row['revoked_at'] ?? null) !== null) {
                    return $rows; // already revoked; signal no-op via $hit staying false
                }
                $row['revoked_at'] = gmdate('Y-m-d\TH:i:s\Z');
                $hit = true;
            }
            unset($row);
            return $rows;
        });
        return $hit;
    }

    /**
     * Stamp last_used_at on a successful auth. Best-effort: a write
     * failure here must not break an otherwise-valid request, so we
     * swallow filesystem errors. The stamp is for operator visibility
     * (admin UI lists "last used"), not for security gating.
     */
    public function touch(string $hash): void
    {
        try {
            $this->mutate(static function (array $rows) use ($hash): array {
                foreach ($rows as &$row) {
                    if (isset($row['key_hash']) && hash_equals((string) $row['key_hash'], $hash)) {
                        $row['last_used_at'] = gmdate('Y-m-d\TH:i:s\Z');
                    }
                }
                unset($row);
                return $rows;
            });
        } catch (\Throwable) {
            // Last-used is non-critical; skip silently.
        }
    }

    /**
     * Read-modify-write under exclusive flock with atomic rename.
     *
     * @param callable(list<array<string,mixed>>): list<array<string,mixed>> $fn
     */
    private function mutate(callable $fn): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create storage dir: ' . $dir);
        }

        $fp = fopen($this->path, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Cannot open ' . $this->path);
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock ' . $this->path);
            }
            $raw = stream_get_contents($fp);
            $rows = [];
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $rows = array_values(array_filter($decoded, 'is_array'));
                }
            }

            $rows = $fn($rows);

            $tmp = $this->path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
            $payload = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
                throw new \RuntimeException('Write failed: ' . $tmp);
            }
            @chmod($tmp, 0600);
            if (!@rename($tmp, $this->path)) {
                @unlink($tmp);
                throw new \RuntimeException('Rename failed: ' . $tmp . ' -> ' . $this->path);
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
