<?php

declare(strict_types=1);

namespace App\Content;

use Throwable;
use SQLite3;

final class MetricsRepository
{
    private ?SQLite3 $database = null;
    private bool $writable = true;
    /** @var array<string,int> */
    private array $memoryStore = [];

    public function __construct(private readonly string $databasePath)
    {
        if (!class_exists(SQLite3::class)) {
            $this->writable = false;
            return;
        }

        $directory = dirname($this->databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            $this->database = new SQLite3(':memory:');
            $this->writable = false;
        } else {
            try {
                $this->database = new SQLite3($this->databasePath);
            } catch (Throwable $exception) {
                $this->database = new SQLite3(':memory:');
                $this->writable = false;
            }
        }

        $this->database->exec('PRAGMA journal_mode = WAL;');
        $this->database->exec('CREATE TABLE IF NOT EXISTS views (slug TEXT PRIMARY KEY, count INTEGER NOT NULL)');
    }

    public function getViews(string $slug): int
    {
        if ($this->database === null) {
            return $this->memoryStore[$slug] ??= random_int(120, 480);
        }

        $statement = $this->database->prepare('SELECT count FROM views WHERE slug = :slug');
        $statement->bindValue(':slug', $slug, SQLITE3_TEXT);
        $result = $statement->execute();

        $row = $result?->fetchArray(SQLITE3_ASSOC);
        if ($row !== false) {
            return (int) $row['count'];
        }

        $initial = random_int(120, 480);
        $this->setViews($slug, $initial);
        return $initial;
    }

    public function setViews(string $slug, int $count): void
    {
        if ($this->database === null) {
            $this->memoryStore[$slug] = $count;
            return;
        }

        $statement = $this->database->prepare('INSERT INTO views (slug, count) VALUES (:slug, :count) ON CONFLICT(slug) DO UPDATE SET count = excluded.count');
        $statement->bindValue(':slug', $slug, SQLITE3_TEXT);
        $statement->bindValue(':count', $count, SQLITE3_INTEGER);
        $statement->execute();
    }

    public function increment(string $slug, int $step = 1): void
    {
        $current = $this->getViews($slug);
        $this->setViews($slug, $current + max(1, $step));
    }

    public function __destruct()
    {
        $this->database?->close();
    }
}
