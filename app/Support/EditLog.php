<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use Throwable;

/**
 * EditLog -- append-only audit log of content saves/deletes.
 *
 * Records who wrote what so the admin dashboard's "Recent edits" card can
 * show "saved 15 min ago via Claude (MCP)" alongside admin-driven changes
 * and external API calls. The backing store is a small SQLite file at
 * storage/data/edits.sqlite (same dir as views.sqlite), schema-on-first-
 * write, ordered by `at DESC` for the read path.
 *
 * Failure mode: every write is best-effort. The log lives next to the
 * primary save path; a failure to record an edit must NOT abort the save
 * itself. All write paths catch Throwable, dump to error_log, and return.
 *
 * Source attribution: callers set ::$context to a string before invoking
 * a repository save (e.g. Admin\Controller sets it to 'admin'). Direct
 * callers can also pass an explicit source to record(); ::$context is a
 * convenience for the common admin path where the same Controller request
 * fans out into multiple repository saves.
 */
final class EditLog
{
    /**
     * Caller-supplied source label that repository saves should pick up
     * when they don't pass one explicitly. Admin controllers set this to
     * 'admin' in their constructor; the API ContentController sets 'api'
     * before delegating to a repository. MCP handlers pass 'mcp'
     * directly to record() since they don't share a base class.
     */
    public static ?string $context = null;

    /**
     * Cached PDO connection per absolute db path. Static rather than a
     * field so the call sites (static record()/recent()) can share it
     * without threading an instance through every repository.
     *
     * @var array<string, PDO>
     */
    private static array $connections = [];

    /**
     * Absolute path override for tests. When null, record()/recent() use
     * storage/data/edits.sqlite under SITE_STORAGE_PATH (or a guessed
     * fallback). Tests call ::useDatabase($tmpPath) to point at a fresh
     * file per test.
     */
    private static ?string $dbPathOverride = null;

    /**
     * Append a single edit row. Never throws; failures land in error_log.
     *
     * @param string $source 'admin'|'mcp'|'api'|'unknown'
     * @param string $action 'save'|'delete'|'publish'|'unpublish'
     * @param string $url     Canonical or constructed URL of the target
     * @param string|null $type   'article'|'page'|'service'|'area'|'project'
     * @param string|null $slug   The slug being edited (nullable for safety)
     */
    public static function record(
        string $source,
        string $action,
        string $url,
        ?string $type,
        ?string $slug
    ): void {
        try {
            $pdo = self::pdo();
            if ($pdo === null) {
                return;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO edits (at, source, action, target_url, target_type, target_slug)
                 VALUES (:at, :source, :action, :url, :type, :slug)'
            );
            $stmt->execute([
                ':at'     => time(),
                ':source' => $source,
                ':action' => $action,
                ':url'    => $url,
                ':type'   => $type,
                ':slug'   => $slug,
            ]);
        } catch (Throwable $e) {
            // Storage failure must not block the save itself. We log to
            // PHP's error_log so an operator hunting "why no Recent edits?"
            // has a breadcrumb without having to wire up a logger.
            @error_log('[EditLog] record failed: ' . $e->getMessage());
        }
    }

    /**
     * Return the last $limit edits, newest first. Returns [] on any
     * failure so the dashboard view can render the "hide card" branch
     * without special-casing missing-database situations.
     *
     * @return array<int, array{at:int, source:string, action:string, target_url:?string, target_type:?string, target_slug:?string}>
     */
    public static function recent(int $limit = 5): array
    {
        if ($limit < 1) {
            return [];
        }
        try {
            $pdo = self::pdo();
            if ($pdo === null) {
                return [];
            }

            $stmt = $pdo->prepare(
                'SELECT at, source, action, target_url, target_type, target_slug
                 FROM edits
                 ORDER BY at DESC
                 LIMIT :lim'
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            // Normalise types: SQLite returns ints/strings mixed.
            foreach ($rows as &$row) {
                $row['at'] = (int) $row['at'];
            }
            unset($row);

            return $rows;
        } catch (Throwable $e) {
            @error_log('[EditLog] recent failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Test hook: point the log at a specific SQLite file. Call with null
     * to reset to the default storage/data/edits.sqlite resolution. Drops
     * the cached connection so a stale handle to the previous path can't
     * leak across tests.
     */
    public static function useDatabase(?string $path): void
    {
        self::$dbPathOverride = $path;
        self::$connections = [];
    }

    /**
     * Lazily open the SQLite connection and ensure the schema exists.
     * Returns null when:
     *   - pdo_sqlite extension is missing (no log, the engine boot already
     *     surfaces that via SystemCheck)
     *   - the storage directory cannot be created/written (silent: a
     *     read-only storage/data/ should not break content saves)
     */
    private static function pdo(): ?PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            return null;
        }

        $path = self::resolveDbPath();
        if ($path === null) {
            return null;
        }

        if (isset(self::$connections[$path])) {
            return self::$connections[$path];
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::ensureSchema($pdo);
            self::$connections[$path] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            @error_log('[EditLog] open failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve the on-disk db path. Order of precedence:
     *   1. ::useDatabase($path) test override
     *   2. SITE_STORAGE_PATH constant (defined by engine bootstrap)
     *   3. A guessed location two levels above app/Support/. Only used
     *      when neither of the above is in scope, which in practice
     *      means a unit test that forgot to override -- still safe
     *      because the file gets written under the repo, not the user's
     *      home, and the test suite scrubs storage/ between runs.
     */
    private static function resolveDbPath(): ?string
    {
        if (self::$dbPathOverride !== null) {
            return self::$dbPathOverride;
        }
        if (defined('SITE_STORAGE_PATH')) {
            return SITE_STORAGE_PATH . '/data/edits.sqlite';
        }
        return dirname(__DIR__, 2) . '/storage/data/edits.sqlite';
    }

    /**
     * Idempotent schema bootstrap. The CREATE statements are guarded by
     * IF NOT EXISTS so re-running them on every connection open is a
     * no-op after the first write.
     */
    private static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS edits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                at INTEGER NOT NULL,
                source TEXT NOT NULL,
                action TEXT NOT NULL,
                target_url TEXT,
                target_type TEXT,
                target_slug TEXT
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_edits_at_desc ON edits(at DESC)'
        );
    }
}
