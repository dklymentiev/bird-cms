<?php

declare(strict_types=1);

namespace App\Newsletter;

final class FileSubscriberRepository
{
    private const MAX_SUBSCRIBERS = 5000;

    private string $dataFile;
    private string $lockFile;

    public function __construct(string $dataDir)
    {
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $this->dataFile = $dataDir . '/subscribers.json';
        $this->lockFile = $dataDir . '/subscribers.lock';

        // Initialize file if it doesn't exist
        if (!file_exists($this->dataFile)) {
            file_put_contents($this->dataFile, json_encode([]));
        }
    }

    private function lock(): void
    {
        $fp = fopen($this->lockFile, 'c');
        if ($fp === false) {
            throw new \RuntimeException('Failed to create lock file');
        }
        flock($fp, LOCK_EX);
    }

    private function unlock(): void
    {
        if (file_exists($this->lockFile)) {
            $fp = fopen($this->lockFile, 'r');
            if ($fp) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }

    private function readData(): array
    {
        $content = file_get_contents($this->dataFile);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function writeData(array $data): void
    {
        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function subscribe(string $email, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        $this->lock();

        try {
            $data = $this->readData();
            $email = strtolower(trim($email));

            if (count($data) >= self::MAX_SUBSCRIBERS) {
                throw new \RuntimeException('Subscriber storage limit reached');
            }

            // Check if already subscribed
            if (isset($data[$email])) {
                return false;
            }

            // Add new subscriber
            $data[$email] = [
                'email' => $email,
                'subscribed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'status' => 'active',
                'unsubscribe_token' => bin2hex(random_bytes(16)),
            ];

            $this->writeData($data);
            return true;

        } finally {
            $this->unlock();
        }
    }

    public function isSubscribed(string $email): bool
    {
        $data = $this->readData();
        $email = strtolower(trim($email));

        return isset($data[$email]) && $data[$email]['status'] === 'active';
    }

    public function unsubscribe(string $token): bool
    {
        $this->lock();

        try {
            $data = $this->readData();

            foreach ($data as $email => $subscriber) {
                if ($subscriber['unsubscribe_token'] === $token) {
                    $data[$email]['status'] = 'unsubscribed';
                    $this->writeData($data);
                    return true;
                }
            }

            return false;

        } finally {
            $this->unlock();
        }
    }

    public function getAll(?string $status = 'active'): array
    {
        $data = $this->readData();

        if ($status === null) {
            return array_values($data);
        }

        return array_values(array_filter($data, fn($sub) => $sub['status'] === $status));
    }

    public function getCount(?string $status = 'active'): int
    {
        return count($this->getAll($status));
    }

    public function getStats(): array
    {
        $data = $this->readData();

        $stats = [
            'total' => count($data),
            'active' => 0,
            'unsubscribed' => 0,
        ];

        foreach ($data as $subscriber) {
            if ($subscriber['status'] === 'active') {
                $stats['active']++;
            } elseif ($subscriber['status'] === 'unsubscribed') {
                $stats['unsubscribed']++;
            }
        }

        return $stats;
    }

    public function exportToCsv(string $outputPath): int
    {
        $subscribers = $this->getAll('active');

        $fp = fopen($outputPath, 'w');
        if ($fp === false) {
            throw new \RuntimeException("Failed to create file: {$outputPath}");
        }

        // Write CSV header
        fputcsv($fp, ['Email', 'Subscribed At', 'Status', 'IP Address']);

        // Write data
        foreach ($subscribers as $sub) {
            fputcsv($fp, [
                $sub['email'],
                $sub['subscribed_at'],
                $sub['status'],
                $sub['ip_address'] ?? '',
            ]);
        }

        fclose($fp);
        return count($subscribers);
    }
}
