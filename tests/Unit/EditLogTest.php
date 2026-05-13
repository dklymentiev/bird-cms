<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\EditLog;
use PHPUnit\Framework\TestCase;

/**
 * EditLog backs the dashboard's Recent edits card; these tests pin the
 * append-only contract (schema-on-first-write, recent() ordering, silent
 * failure) so a regression can't silently start throwing on a save path
 * or stop logging.
 *
 * Every test points EditLog at a per-test SQLite file under tmp/ so they
 * don't share state with each other or with a real site's storage/.
 */
final class EditLogTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $tmpRoot = BIRD_TEST_ROOT . '/fixtures/tmp';
        if (!is_dir($tmpRoot)) {
            mkdir($tmpRoot, 0755, true);
        }
        $this->dbPath = $tmpRoot . '/edits-' . bin2hex(random_bytes(4)) . '.sqlite';
        EditLog::useDatabase($this->dbPath);
        EditLog::$context = null;
    }

    protected function tearDown(): void
    {
        EditLog::useDatabase(null);
        EditLog::$context = null;
        if (is_file($this->dbPath)) {
            @unlink($this->dbPath);
        }
    }

    public function testRecordCreatesSchemaIfMissing(): void
    {
        // db file shouldn't exist yet: the schema bootstrap is the
        // contract worth pinning (lazy / first-write).
        self::assertFileDoesNotExist($this->dbPath);

        EditLog::record('admin', 'save', '/blog/hello', 'article', 'hello');

        self::assertFileExists($this->dbPath);
        // The table also has to be queryable -- recent() reads it.
        $rows = EditLog::recent();
        self::assertCount(1, $rows);
        self::assertSame('admin', $rows[0]['source']);
        self::assertSame('save', $rows[0]['action']);
        self::assertSame('/blog/hello', $rows[0]['target_url']);
        self::assertSame('article', $rows[0]['target_type']);
        self::assertSame('hello', $rows[0]['target_slug']);
    }

    public function testRecordThenRecent(): void
    {
        EditLog::record('mcp', 'save', '/blog/a', 'article', 'a');
        EditLog::record('mcp', 'delete', '/blog/b', 'article', 'b');

        $rows = EditLog::recent();
        self::assertCount(2, $rows);
        // Newest-first ordering covered in detail by testRecentOrdersByAtDesc.
        $actions = array_column($rows, 'action');
        sort($actions);
        self::assertSame(['delete', 'save'], $actions);
    }

    public function testRecentOrdersByAtDesc(): void
    {
        // Three writes back-to-back can land on the same second on a
        // fast machine, which would make assertions about ordering
        // fragile. Insert directly via the same connection EditLog
        // uses, with explicit timestamps, to lock the ordering test
        // to data we control.
        EditLog::record('admin', 'save', '/x', 'page', 'x'); // creates schema
        $pdo = new \PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('DELETE FROM edits');
        $stmt = $pdo->prepare(
            'INSERT INTO edits (at, source, action, target_url, target_type, target_slug)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([1000, 'admin', 'save', '/first',  'page', 'first']);
        $stmt->execute([2000, 'mcp',   'save', '/second', 'page', 'second']);
        $stmt->execute([3000, 'api',   'save', '/third',  'page', 'third']);

        $rows = EditLog::recent();
        self::assertSame(['/third', '/second', '/first'], array_column($rows, 'target_url'));
        self::assertSame([3000, 2000, 1000], array_column($rows, 'at'));
    }

    public function testRecentRespectsLimit(): void
    {
        for ($i = 0; $i < 7; $i++) {
            EditLog::record('admin', 'save', '/p' . $i, 'page', 'p' . $i);
        }

        self::assertCount(3, EditLog::recent(3));
        self::assertCount(5, EditLog::recent(5));
        self::assertCount(7, EditLog::recent(100));
        // Defensive: negative / zero limit should not error out.
        self::assertSame([], EditLog::recent(0));
    }

    public function testRecordSwallowsWriteFailure(): void
    {
        // Point EditLog at a path under a file (not a dir) so opening
        // the SQLite connection fails -- mkdir() on the parent path
        // is impossible because that parent is a regular file.
        $tmpFile = BIRD_TEST_ROOT . '/fixtures/tmp/blocker-' . bin2hex(random_bytes(4));
        file_put_contents($tmpFile, 'not a directory');
        EditLog::useDatabase($tmpFile . '/edits.sqlite');

        // Must not throw. Exception bubbling up would abort the
        // repository save() that called record(), which is exactly
        // the regression this test guards.
        EditLog::record('admin', 'save', '/x', 'page', 'x');

        // recent() should also stay silent / return [].
        self::assertSame([], EditLog::recent());

        @unlink($tmpFile);
    }

    public function testSourceContextFallsBackToUnknown(): void
    {
        // The repository call sites use: EditLog::$context ?? 'unknown'.
        // When nothing has set $context, the source written to the row
        // must be exactly 'unknown' so the dashboard can render the
        // "unknown source" pill verbatim.
        self::assertNull(EditLog::$context);

        EditLog::record(EditLog::$context ?? 'unknown', 'save', '/orphan', 'page', 'orphan');
        $rows = EditLog::recent();
        self::assertCount(1, $rows);
        self::assertSame('unknown', $rows[0]['source']);
    }
}
