<?php

declare(strict_types=1);

namespace App\Newsletter;

use PDO;
use PDOException;

final class SubscriberRepository
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS subscribers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                subscribed_at TEXT NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                status TEXT DEFAULT "active",
                unsubscribe_token TEXT UNIQUE,
                created_at TEXT NOT NULL
            )
        ');

        // Create index on email for faster lookups
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_email ON subscribers(email)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_status ON subscribers(status)');
    }

    public function subscribe(string $email, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        try {
            $token = bin2hex(random_bytes(16));
            $now = date('Y-m-d H:i:s');

            $stmt = $this->pdo->prepare('
                INSERT INTO subscribers (email, subscribed_at, ip_address, user_agent, unsubscribe_token, created_at)
                VALUES (:email, :subscribed_at, :ip, :ua, :token, :created_at)
            ');

            return $stmt->execute([
                ':email' => strtolower(trim($email)),
                ':subscribed_at' => $now,
                ':ip' => $ipAddress,
                ':ua' => $userAgent,
                ':token' => $token,
                ':created_at' => $now,
            ]);
        } catch (PDOException $e) {
            // Already subscribed (UNIQUE constraint violation)
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public function isSubscribed(string $email): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM subscribers
            WHERE email = :email AND status = "active"
        ');
        $stmt->execute([':email' => strtolower(trim($email))]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function unsubscribe(string $token): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE subscribers
            SET status = "unsubscribed"
            WHERE unsubscribe_token = :token
        ');
        return $stmt->execute([':token' => $token]);
    }

    public function getAll(?string $status = 'active'): array
    {
        if ($status === null) {
            $stmt = $this->pdo->query('SELECT * FROM subscribers ORDER BY subscribed_at DESC');
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM subscribers WHERE status = :status ORDER BY subscribed_at DESC');
            $stmt->execute([':status' => $status]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCount(?string $status = 'active'): int
    {
        if ($status === null) {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM subscribers');
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM subscribers WHERE status = :status');
            $stmt->execute([':status' => $status]);
        }

        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        return [
            'total' => $this->getCount(null),
            'active' => $this->getCount('active'),
            'unsubscribed' => $this->getCount('unsubscribed'),
        ];
    }
}
