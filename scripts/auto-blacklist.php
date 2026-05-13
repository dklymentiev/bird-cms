#!/usr/bin/env php
<?php
/**
 * Auto-blacklist malicious IPs based on behavior patterns
 *
 * Runs hourly via cron. Detects and blocks:
 * - Scanners hitting sensitive paths (.env, .git, wp-*, etc)
 * - High 404 rate (>80% of requests are 404)
 * - Flood attacks (>10 req/sec)
 * - Known bad User-Agents
 *
 * Usage: php scripts/auto-blacklist.php [--dry-run]
 */

// Bootstrap is required so we can resolve SITE_STORAGE_PATH and read config.
require_once __DIR__ . '/../bootstrap.php';

$storagePath = SITE_STORAGE_PATH;
define('DB_PATH', $storagePath . '/analytics/visits.db');
define('BLACKLIST_FILE', $storagePath . '/analytics/blacklist.txt');

// Malicious URL patterns (instant block)
$maliciousUrls = [
    // Config/secrets
    '/.env', '/.aws', '/.ssh', '/.config', '/.git',
    '/config.php', '/configuration.php', '/settings.php',
    '/credentials', '/secrets', '/.htpasswd', '/.htaccess',
    '/web.config', '/application.yml', '/application.properties',

    // WordPress (we don't use it)
    '/wp-admin', '/wp-login', '/wp-content', '/wp-includes',
    '/xmlrpc.php', '/wp-config', '/wordpress',

    // Database tools
    '/phpmyadmin', '/pma', '/adminer', '/mysql', '/pgsql',
    '/database', '/dump', '/backup', '/sql',

    // Shells/exploits
    '/shell', '/c99', '/r57', '/eval', '/exec', '/cmd',
    '/system', '/passthru', '/phpinfo',

    // Dev/admin tools (shouldn't be exposed)
    '/actuator', '/solr', '/jenkins', '/grafana', '/kibana',
    '/prometheus', '/elasticsearch', '/console', '/debug',
    '/telescope', '/horizon', '/_profiler',

    // Version control
    '/.svn', '/.hg', '/.bzr', '/CVS',

    // Card fraud / payment probes
    '/3ds', '/merchant/', '/payment/', '/checkout.php',
    '/order.php', '/card/', '/pay.php', '/billing/',
    '/transaction', '/stripe/', '/paypal/',
];

// Malicious User-Agent patterns
$maliciousUAs = [
    // Exploit attempts
    '() {',           // Shellshock
    '/bin/bash',
    '/bin/sh',
    'wget -',
    'curl -',
    'base64_decode',

    // Security scanners (aggressive)
    'nuclei',
    'nikto',
    'nessus',
    'openvas',
    'qualys',
    'acunetix',
    'netsparker',
    'sqlmap',
    'wpscan',
    'dirbuster',
    'gobuster',
    'ffuf',

    // Recon/vuln scanners
    'leakix',
    'l9scan',
    'censys',
    'shodan',
    'masscan',
    'zgrab',
    'httpx',
    'cypex',
];

// Thresholds
$thresholds = [
    'min_requests' => 3,           // Minimum requests to analyze
    'high_404_ratio' => 0.8,       // 80%+ 404s = scanner
    'flood_req_per_sec' => 5,      // >5 req/sec = flood
    'lookback_hours' => 48,        // Analyze last 48 hours
];

// Whitelisted IP ranges (legitimate crawlers/services)
$whitelist = [
    // Google
    '66.249.',      // Googlebot
    '66.102.',
    '64.233.',
    '72.14.',
    '74.125.',
    '209.85.',
    '216.239.',
    '172.217.',
    // Google IPv6
    '2001:4860:',
    // Facebook
    '2a03:2880:',
    '31.13.',
    '157.240.',
    '173.252.',
    '179.60.',
    // Bing
    '40.77.',
    '157.55.',
    '207.46.',
    '13.66.',
    // Cloudflare
    '172.64.',
    '162.158.',
    '131.0.72.',
];

// Check if IP is whitelisted
function isWhitelisted(string $ip, array $whitelist): bool {
    foreach ($whitelist as $prefix) {
        if (str_starts_with($ip, $prefix)) {
            return true;
        }
    }
    return false;
}

// Parse args
$dryRun = in_array('--dry-run', $argv);
$verbose = in_array('-v', $argv) || in_array('--verbose', $argv);

if (!file_exists(DB_PATH)) {
    echo "Database not found.\n";
    exit(1);
}

$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$since = date('Y-m-d H:i:s', strtotime("-{$thresholds['lookback_hours']} hours"));

echo "=== Auto-Blacklist Scanner ===\n";
echo "Analyzing since: {$since}\n";
echo $dryRun ? "MODE: Dry run (no changes)\n" : "MODE: Live\n";
echo "\n";

// Load existing blacklist IPs
$existingIps = [];
if (file_exists(BLACKLIST_FILE)) {
    $lines = file(BLACKLIST_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (empty($line) || $line[0] === '#') continue;
        $parts = explode('|', $line);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $existingIps[$ip] = true;
        }
    }
}
echo "Existing blacklist: " . count($existingIps) . " IPs\n\n";

$toBlock = [];

// === Rule 1: Malicious URL patterns ===
echo "Checking malicious URL patterns...\n";

$placeholders = implode(',', array_fill(0, count($maliciousUrls), '?'));
// Build LIKE conditions for each pattern
$likeConditions = [];
$params = [$since];
foreach ($maliciousUrls as $pattern) {
    $likeConditions[] = "url LIKE ?";
    $params[] = '%' . $pattern . '%';
}
$likeWhere = implode(' OR ', $likeConditions);

$stmt = $db->prepare("
    SELECT ip, COUNT(*) as cnt, GROUP_CONCAT(DISTINCT url) as urls
    FROM visits
    WHERE timestamp >= ? AND ({$likeWhere})
    GROUP BY ip
");
$stmt->execute($params);
$maliciousHits = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($maliciousHits as $hit) {
    if (isset($existingIps[$hit['ip']])) continue;
    if (isWhitelisted($hit['ip'], $whitelist)) continue;

    // Find which pattern matched
    $matchedPattern = 'scanner';
    $urls = explode(',', $hit['urls']);
    foreach ($urls as $url) {
        foreach ($maliciousUrls as $pattern) {
            if (stripos($url, $pattern) !== false) {
                $matchedPattern = trim($pattern, '/.');
                break 2;
            }
        }
    }

    $toBlock[$hit['ip']] = [
        'requests' => $hit['cnt'],
        'reason' => "{$matchedPattern} scanner",
        'urls' => $hit['urls'],
    ];

    if ($verbose) {
        echo "  {$hit['ip']}: {$hit['cnt']} hits to {$matchedPattern}\n";
    }
}
echo "  Found: " . count($toBlock) . " IPs\n\n";

// === Rule 2: High 404 ratio ===
echo "Checking high 404 ratio...\n";

// Note: SQLite PDO doesn't work with placeholders in HAVING, must interpolate
$minReq = (int)$thresholds['min_requests'];
$stmt = $db->prepare("
    SELECT
        ip,
        COUNT(*) as total,
        SUM(CASE WHEN status = 404 THEN 1 ELSE 0 END) as cnt_404,
        SUM(CASE WHEN status = 200 THEN 1 ELSE 0 END) as cnt_200,
        GROUP_CONCAT(DISTINCT url) as urls
    FROM visits
    WHERE timestamp >= ?
    GROUP BY ip
    HAVING total >= {$minReq} AND cnt_404 > 0
");
$stmt->execute([$since]);
$highErrorIps = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($highErrorIps as $row) {
    if (isset($existingIps[$row['ip']]) || isset($toBlock[$row['ip']])) continue;
    if (isWhitelisted($row['ip'], $whitelist)) continue;

    $ratio = $row['cnt_404'] / $row['total'];

    // 100% 404 with multiple requests = definite scanner
    if ($row['cnt_200'] == 0 && $row['total'] >= $thresholds['min_requests']) {
        $toBlock[$row['ip']] = [
            'requests' => $row['total'],
            'reason' => "100% 404 ({$row['cnt_404']} fails)",
            'urls' => $row['urls'],
        ];
        if ($verbose) {
            echo "  {$row['ip']}: 100% 404 ({$row['total']} requests)\n";
        }
    }
    // 80%+ 404 with decent sample
    elseif ($ratio >= $thresholds['high_404_ratio'] && $row['total'] >= 5) {
        $pct = round($ratio * 100);
        $toBlock[$row['ip']] = [
            'requests' => $row['total'],
            'reason' => "{$pct}% 404 rate",
            'urls' => $row['urls'],
        ];
        if ($verbose) {
            echo "  {$row['ip']}: {$pct}% 404 ({$row['total']} requests)\n";
        }
    }
}
$rule2Count = count($toBlock) - count(array_filter($maliciousHits, fn($h) => isset($toBlock[$h['ip']])));
echo "  Found: {$rule2Count} IPs\n\n";

// === Rule 3: Flood detection ===
echo "Checking flood attacks...\n";

$stmt = $db->prepare("
    SELECT
        ip,
        COUNT(*) as total,
        MIN(timestamp) as first_seen,
        MAX(timestamp) as last_seen
    FROM visits
    WHERE timestamp >= ?
    GROUP BY ip
    HAVING total >= 10
");
$stmt->execute([$since]);
$possibleFloods = $stmt->fetchAll(PDO::FETCH_ASSOC);

$floodCount = 0;
foreach ($possibleFloods as $row) {
    if (isset($existingIps[$row['ip']]) || isset($toBlock[$row['ip']])) continue;
    if (isWhitelisted($row['ip'], $whitelist)) continue;

    $duration = max(1, strtotime($row['last_seen']) - strtotime($row['first_seen']));
    $reqPerSec = $row['total'] / $duration;

    if ($reqPerSec >= $thresholds['flood_req_per_sec']) {
        $toBlock[$row['ip']] = [
            'requests' => $row['total'],
            'reason' => "flood " . round($reqPerSec, 1) . " req/sec",
            'urls' => '/',
        ];
        $floodCount++;
        if ($verbose) {
            echo "  {$row['ip']}: {$row['total']} requests in {$duration}s\n";
        }
    }
}
echo "  Found: {$floodCount} IPs\n\n";

// === Rule 4: Malicious User-Agents ===
echo "Checking malicious User-Agents...\n";

$uaConditions = [];
$params = [$since];
foreach ($maliciousUAs as $ua) {
    $uaConditions[] = "LOWER(user_agent) LIKE ?";
    $params[] = '%' . strtolower($ua) . '%';
}
$uaWhere = implode(' OR ', $uaConditions);

$stmt = $db->prepare("
    SELECT ip, COUNT(*) as cnt, MAX(user_agent) as ua
    FROM visits
    WHERE timestamp >= ? AND ({$uaWhere})
    GROUP BY ip
");
$stmt->execute($params);
$badUaIps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$uaCount = 0;
foreach ($badUaIps as $row) {
    if (isset($existingIps[$row['ip']]) || isset($toBlock[$row['ip']])) continue;
    if (isWhitelisted($row['ip'], $whitelist)) continue;

    // Find which UA pattern matched
    $matchedUa = 'bad UA';
    foreach ($maliciousUAs as $pattern) {
        if (stripos($row['ua'], $pattern) !== false) {
            $matchedUa = $pattern;
            break;
        }
    }

    $toBlock[$row['ip']] = [
        'requests' => $row['cnt'],
        'reason' => "malicious UA: {$matchedUa}",
        'urls' => '',
    ];
    $uaCount++;
    if ($verbose) {
        echo "  {$row['ip']}: {$matchedUa}\n";
    }
}
echo "  Found: {$uaCount} IPs\n\n";

// === Summary and apply ===
echo str_repeat('=', 60) . "\n";
echo "TOTAL TO BLOCK: " . count($toBlock) . " IPs\n";
echo str_repeat('=', 60) . "\n\n";

if (empty($toBlock)) {
    echo "No new malicious IPs detected.\n";
    exit(0);
}

// Show what will be blocked
foreach ($toBlock as $ip => $info) {
    printf("%-39s | %4d | %s\n", $ip, $info['requests'], $info['reason']);
}
echo "\n";

if ($dryRun) {
    echo "Dry run - no changes made.\n";
    exit(0);
}

// Add to blacklist
$date = date('Y-m-d H:i');
$added = 0;
$handle = fopen(BLACKLIST_FILE, 'a');

foreach ($toBlock as $ip => $info) {
    $line = "{$ip} | {$info['requests']} | {$info['reason']} | {$date}\n";
    fwrite($handle, $line);
    $added++;
}
fclose($handle);

echo "Added {$added} IPs to blacklist.\n";

// Delete their visits from database
$ips = array_keys($toBlock);
$placeholders = implode(',', array_fill(0, count($ips), '?'));
$stmt = $db->prepare("DELETE FROM visits WHERE ip IN ({$placeholders})");
$stmt->execute($ips);
$deleted = $stmt->rowCount();

echo "Deleted {$deleted} visits from database.\n";

// Trigger blacklist.conf regeneration
echo "Regenerating nginx blacklist...\n";
$output = [];
$code = 0;
exec('php ' . __DIR__ . '/generate-blacklist-conf.php --apply 2>&1', $output, $code);

if ($code === 0) {
    echo "Nginx blacklist updated and reloaded.\n";
} else {
    echo "WARNING: Failed to reload nginx:\n";
    echo implode("\n", $output) . "\n";
}

echo "\nDone!\n";
