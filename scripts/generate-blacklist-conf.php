#!/usr/bin/env php
<?php
/**
 * Generate nginx blacklist.conf from blacklist.txt
 *
 * Usage: php scripts/generate-blacklist-conf.php [--apply]
 *   --apply  Also reload nginx after generating
 */

// Load bootstrap to get path constants
$bootstrap = __DIR__ . '/../bootstrap.php';
if (file_exists($bootstrap)) {
    require_once $bootstrap;
}

$storagePath = defined('SITE_STORAGE_PATH') ? SITE_STORAGE_PATH : __DIR__ . '/../storage';
define('BLACKLIST_FILE', $storagePath . '/analytics/blacklist.txt');
define('NGINX_CONF', $storagePath . '/analytics/blacklist.conf');
define('HASH_FILE', $storagePath . '/analytics/blacklist.hash');

// Parse blacklist.txt
function parseBlacklist(string $file): array {
    if (!file_exists($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $entries = [];

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        // Parse: IP | requests | pattern | date
        $parts = array_map('trim', explode('|', $line));
        $ip = $parts[0] ?? '';

        // Validate IP (v4 or v6)
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            continue;
        }

        // Build comment from remaining parts
        $comment = isset($parts[1]) ? implode(', ', array_slice($parts, 1, 2)) : '';

        $entries[] = [
            'ip' => $ip,
            'comment' => $comment,
        ];
    }

    return $entries;
}

// Generate nginx conf content
function generateNginxConf(array $entries): string {
    $lines = [
        '# Auto-generated from blacklist.txt',
        '# Generated: ' . date('Y-m-d H:i:s'),
        '# Total IPs: ' . count($entries),
        '',
    ];

    foreach ($entries as $entry) {
        $line = 'deny ' . $entry['ip'] . ';';
        if (!empty($entry['comment'])) {
            $line .= '  # ' . $entry['comment'];
        }
        $lines[] = $line;
    }

    $lines[] = '';
    return implode("\n", $lines);
}

// Check if blacklist changed
function hasChanged(string $blacklistFile, string $hashFile): bool {
    $currentHash = md5_file($blacklistFile);

    if (!file_exists($hashFile)) {
        return true;
    }

    $storedHash = trim(file_get_contents($hashFile));
    return $currentHash !== $storedHash;
}

// Save hash
function saveHash(string $blacklistFile, string $hashFile): void {
    $hash = md5_file($blacklistFile);
    file_put_contents($hashFile, $hash);
}

// Main
$apply = in_array('--apply', $argv);
$force = in_array('--force', $argv);

if (!file_exists(BLACKLIST_FILE)) {
    echo "Blacklist file not found: " . BLACKLIST_FILE . "\n";
    exit(1);
}

// Check if changed
if (!$force && !hasChanged(BLACKLIST_FILE, HASH_FILE)) {
    echo "Blacklist unchanged, skipping.\n";
    exit(0);
}

// Parse and generate
$entries = parseBlacklist(BLACKLIST_FILE);
$conf = generateNginxConf($entries);

echo "Parsed " . count($entries) . " IPs from blacklist.\n";

// Ensure directory exists
$confDir = dirname(NGINX_CONF);
if (!is_dir($confDir)) {
    mkdir($confDir, 0755, true);
}

// Write conf
if (file_put_contents(NGINX_CONF, $conf) === false) {
    echo "ERROR: Cannot write to " . NGINX_CONF . "\n";
    exit(1);
}

echo "Generated: " . NGINX_CONF . "\n";

// Save hash
saveHash(BLACKLIST_FILE, HASH_FILE);

// Reload nginx if --apply
if ($apply) {
    echo "Reloading nginx...\n";
    $output = [];
    $code = 0;
    exec('nginx -t 2>&1', $output, $code);

    if ($code !== 0) {
        echo "ERROR: nginx config test failed:\n";
        echo implode("\n", $output) . "\n";
        exit(1);
    }

    exec('nginx -s reload 2>&1', $output, $code);

    if ($code === 0) {
        echo "nginx reloaded successfully.\n";
    } else {
        echo "ERROR: nginx reload failed:\n";
        echo implode("\n", $output) . "\n";
        exit(1);
    }
}

echo "Done!\n";
