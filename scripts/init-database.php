#!/usr/bin/env php
<?php
/**
 * Initialize SQLite databases for analytics and sandbox
 *
 * Usage: php scripts/init-database.php
 */

$storagePath = dirname(__DIR__) . '/storage';
$analyticsPath = $storagePath . '/analytics';

// Create directories
if (!is_dir($analyticsPath)) {
    mkdir($analyticsPath, 0777, true);
    echo "Created: {$analyticsPath}\n";
}

// Create visits database
$dbPath = $analyticsPath . '/visits.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec('CREATE TABLE IF NOT EXISTS visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME NOT NULL,
    ip TEXT NOT NULL,
    method TEXT,
    url TEXT NOT NULL,
    status INTEGER,
    size INTEGER,
    referer TEXT,
    referer_domain TEXT,
    user_agent TEXT,
    is_bot INTEGER DEFAULT 0,
    utm_source TEXT,
    utm_medium TEXT,
    utm_campaign TEXT,
    utm_term TEXT,
    utm_content TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Migration: add new columns if they don't exist
$columns = $db->query("PRAGMA table_info(visits)")->fetchAll(PDO::FETCH_COLUMN, 1);
$newColumns = [
    'referer_domain' => 'TEXT',
    'utm_source' => 'TEXT',
    'utm_medium' => 'TEXT',
    'utm_campaign' => 'TEXT',
    'utm_term' => 'TEXT',
    'utm_content' => 'TEXT',
];
foreach ($newColumns as $col => $type) {
    if (!in_array($col, $columns)) {
        $db->exec("ALTER TABLE visits ADD COLUMN {$col} {$type}");
        echo "Added column: {$col}\n";
    }
}

$db->exec('CREATE TABLE IF NOT EXISTS sandbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fingerprint TEXT NOT NULL,
    ip TEXT NOT NULL,
    user_agent TEXT,
    first_seen DATETIME NOT NULL,
    last_seen DATETIME NOT NULL,
    total_requests INTEGER DEFAULT 1,
    count_404 INTEGER DEFAULT 0,
    urls TEXT,
    verdict TEXT DEFAULT "pending",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Create indexes for performance
$db->exec('CREATE INDEX IF NOT EXISTS idx_visits_timestamp ON visits(timestamp)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_visits_ip ON visits(ip)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_sandbox_verdict ON sandbox(verdict)');

chmod($dbPath, 0666);

echo "Database initialized: {$dbPath}\n";
echo "Tables: visits, sandbox\n";
echo "Done.\n";
