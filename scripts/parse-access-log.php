#!/usr/bin/env php
<?php
/**
 * Parse nginx access.log and store visits in SQLite
 *
 * Usage: php scripts/parse-access-log.php [--reset]
 *
 * Filters out static files, stores page visits with metadata.
 * Marks bots based on User-Agent patterns.
 */

// Bootstrap is required so we can resolve SITE_STORAGE_PATH and read config.
require_once __DIR__ . '/../bootstrap.php';

$storagePath = SITE_STORAGE_PATH;
define('DB_PATH', $storagePath . '/analytics/visits.db');
define('LOG_PATH', config('logs.access_log', '/var/log/nginx/access.log'));
define('OFFSET_FILE', $storagePath . '/analytics/last_offset.txt');

// Server's own IPs to ignore (internal requests, cron jobs, etc.)
$serverIps = [
    // Configure: your servers public IP(s) to exclude from analytics
    // '192.0.2.42',
    '127.0.0.1',
    '::1',
    '172.18.0.1',       // Docker internal network (gateway)
    '172.18.0.2',       // Docker internal network (nginx proxy)
];

// Internal User-Agents to ignore
$internalUserAgents = [
    'Bird-LinkChecker',
    'Bird-SecurityScanner',
];

// Static file extensions to skip
$staticExtensions = [
    'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico',
    'woff', 'woff2', 'ttf', 'eot', 'otf',
    'map', 'json'
];

// Paths to skip (static, scanner targets, etc.)
// Paths starting with / are checked as prefix, others match anywhere in URL
$skipPaths = [
    '/robots.txt',
    '/sitemap.xml',
    '/favicon.ico',
    '/health',
    '/.well-known/',
    // Common scanner/exploit paths
    '/.git/',
    '/.env',
    '/.git',
    '/wp-',
    '/wordpress/',
    '/xmlrpc.php',
    // WordPress scanner patterns (match anywhere in URL)
    'wp-includes',
    'wp-content',
    'wp-admin',
    'wlwmanifest',
    'wp-login',
    'wp-config',
    // Other CMS scanners
    'drupal',
    'joomla',
    'magento',
    'typo3',
    // Common exploit files
    '.asp',
    '.aspx',
    '.jsp',
    '.cgi',
    '/cgi-bin/',
    '/admin/',
    '/phpmyadmin/',
    '/pma/',
    '/mysql/',
    '/owa/',
    '/geoserver/',
    '/config',
    '/webui',
    '/solr/',
    '/actuator/',
    '/.aws/',
    '/.ssh/',
    '/api/v1/',
    '/~',
    '/login',
    '/CFIDE/',
    '/nidp/',
    '/versa/',
    '/webportal',
    '/workplace/',
    '/versions',
    '/v2',
    '/user',
    '/ui/',
    '/xml/',
    '/info',
    '/portal',
    '/remote/',
    '/vendor/',
    '/telescope/',
    '/debug/',
    '/console/',
    '/manager/',
    '/shell',
    '/eval',
    '/exec',
    '/cmd',
    '/server-status',
    '/server-info',
    '/phpinfo',
    // Shell scripts (match anywhere - no leading slash)
    'c99',
    'r57',
    'wso',
    'alfa',
    'xleet',
    'b374k',
    'webshell',
    'bypass',
    'upload.php',
    'mailer.php',
    'sendmail',
    'symlink',
    'shell.php',
    '/brevo',
    '/sendgrid',
    '/mailgun',
    '/postmark',
    '/smtp',
    '/mailer',
    '/test.php',
    '/shell.php',
    '/c99.php',
    '/r57.php',
    '/.env.',
    '/backup',
    '/db',
    '/database',
    '/dump',
    '/sql',
    '/mysql',
    '/pgsql',
    '/redis',
    '/mongo',
    '/elastic',
    '/kibana',
    '/grafana',
    '/prometheus',
    '/jenkins',
    '/hudson',
    '/travis',
    '/circleci',
    '/.svn',
    '/.hg',
    '/.bzr',
    '/CVS',
    '/js',
    '/css',
];

// Bot patterns in User-Agent
$botPatterns = [
    // Search engines
    'Googlebot', 'Bingbot', 'YandexBot', 'Baiduspider',
    'DuckDuckBot', 'Slurp', 'Applebot', 'Amazonbot',
    // Social
    'facebookexternalhit', 'LinkedInBot', 'Twitterbot',
    // SEO tools
    'SemrushBot', 'AhrefsBot', 'MJ12bot', 'DotBot', 'PetalBot',
    // CLI tools
    'curl/', 'curl', 'Wget', 'wget', 'httpie',
    // Libraries/scripts
    'python-requests', 'python-httpx', 'python-urllib', 'Python/',
    'Go-http-client', 'Java/', 'Apache-HttpClient', 'libwww-perl',
    'node-fetch', 'axios/', 'request/', 'http-client',
    // Generic bot patterns
    'bot', 'crawler', 'spider', 'scraper', 'scan',
    // Headless browsers
    'HeadlessChrome', 'PhantomJS', 'Puppeteer',
    // Security scanners
    'Palo Alto', 'Cortex', 'Xpanse', 'Censys', 'Shodan',
    'Nmap', 'Nikto', 'Nessus', 'OpenVAS', 'Qualys',
    'SecurityScanner', 'Nuclei', 'ZAP', 'Burp',
    // Exploit attempts (shellshock, etc)
    '() {', '/bin/bash', '/bin/sh', 'wget -', 'curl -',
    'busybox', '.sh|sh', 'eval(', 'base64',
];

// Initialize database
function initDatabase(bool $reset = false): PDO {
    $dbExists = file_exists(DB_PATH);

    if ($reset && $dbExists) {
        unlink(DB_PATH);
        if (file_exists(OFFSET_FILE)) {
            unlink(OFFSET_FILE);
        }
        echo "Database reset.\n";
        $dbExists = false;
    }

    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!$dbExists) {
        $db->exec("
            CREATE TABLE visits (
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
                session_id TEXT,
                utm_source TEXT,
                utm_medium TEXT,
                utm_campaign TEXT,
                utm_term TEXT,
                utm_content TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX idx_timestamp ON visits(timestamp);
            CREATE INDEX idx_url ON visits(url);
            CREATE INDEX idx_ip ON visits(ip);
            CREATE INDEX idx_is_bot ON visits(is_bot);
            CREATE INDEX idx_status ON visits(status);
            CREATE INDEX idx_session_id ON visits(session_id);
            CREATE INDEX idx_utm_source ON visits(utm_source);
            CREATE INDEX idx_referer_domain ON visits(referer_domain);

            CREATE TABLE sandbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fingerprint TEXT NOT NULL,
                ip TEXT NOT NULL,
                user_agent TEXT,
                first_seen DATETIME NOT NULL,
                last_seen DATETIME NOT NULL,
                total_requests INTEGER DEFAULT 1,
                count_404 INTEGER DEFAULT 0,
                urls TEXT,
                verdict TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE UNIQUE INDEX idx_sandbox_fingerprint ON sandbox(fingerprint);
            CREATE INDEX idx_sandbox_verdict ON sandbox(verdict);
        ");
        echo "Database created.\n";
    } else {
        // Migration: add new columns if they don't exist
        $columns = $db->query("PRAGMA table_info(visits)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $newColumns = [
            'referer_domain' => 'TEXT',
            'session_id' => 'TEXT',
            'utm_source' => 'TEXT',
            'utm_medium' => 'TEXT',
            'utm_campaign' => 'TEXT',
            'utm_term' => 'TEXT',
            'utm_content' => 'TEXT',
        ];
        $migrated = false;
        foreach ($newColumns as $col => $type) {
            if (!in_array($col, $columns)) {
                $db->exec("ALTER TABLE visits ADD COLUMN {$col} {$type}");
                echo "Migration: added column {$col}\n";
                $migrated = true;
            }
        }
        // Create indexes for new columns if migrated
        if ($migrated) {
            $db->exec("CREATE INDEX IF NOT EXISTS idx_utm_source ON visits(utm_source)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_referer_domain ON visits(referer_domain)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_session_id ON visits(session_id)");
        }
    }

    // Install/refresh the retention trigger (cap_visits). Idempotent.
    \App\Support\Analytics::ensureTrigger($db);

    return $db;
}

// Parse nginx log line
function parseLogLine(string $line): ?array {
    // Format with analytics cookie: IP - - [timestamp] "request" status size "referer" "user_agent" "session_id"
    // Format without cookie: IP - - [timestamp] "request" status size "referer" "user_agent" "x_forwarded_for"
    $pattern = '/^(\S+) - - \[([^\]]+)\] "([^"]*)" (\d+) (\d+) "([^"]*)" "([^"]*)"(?: "([^"]*)")?$/';

    if (!preg_match($pattern, $line, $matches)) {
        return null;
    }

    // Parse request
    $requestParts = explode(' ', $matches[3]);
    $method = $requestParts[0] ?? '';
    $url = $requestParts[1] ?? '';

    // Parse timestamp: 29/Nov/2025:21:27:10 +0000
    $timestamp = DateTime::createFromFormat('d/M/Y:H:i:s O', $matches[2]);

    // Check if 8th field is a UUID (session_id) or IP (x_forwarded_for)
    $field8 = isset($matches[8]) && $matches[8] !== '-' ? $matches[8] : null;
    $sessionId = null;
    if ($field8 && preg_match('/^[a-f0-9-]{36}$/i', $field8)) {
        $sessionId = $field8;
    }

    return [
        'ip' => $matches[1],
        'timestamp' => $timestamp ? $timestamp->format('Y-m-d H:i:s') : null,
        'method' => $method,
        'url' => $url,
        'status' => (int)$matches[4],
        'size' => (int)$matches[5],
        'referer' => $matches[6] !== '-' ? $matches[6] : null,
        'user_agent' => $matches[7] !== '-' ? $matches[7] : null,
        'session_id' => $sessionId,
    ];
}

// Check if URL should be skipped (static files)
function shouldSkip(string $url, array $staticExtensions, array $skipPaths): bool {
    // Skip absolute URLs (proxy probes)
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return true;
    }

    // Skip malformed URLs (double slashes, spaces - scanner behavior)
    if (str_starts_with($url, '//') || str_contains($url, ' /') || str_contains($url, '/ ')) {
        return true;
    }

    // Check skip paths
    foreach ($skipPaths as $path) {
        // Paths starting with / - check prefix
        // Patterns without / - check anywhere in URL (e.g., 'wp-includes', 'wlwmanifest')
        if (str_starts_with($path, '/')) {
            if (str_starts_with($url, $path)) {
                return true;
            }
        } else {
            if (str_contains($url, $path)) {
                return true;
            }
        }
    }

    // Check static extensions
    $parsedUrl = parse_url($url, PHP_URL_PATH) ?? $url;
    $extension = strtolower(pathinfo($parsedUrl, PATHINFO_EXTENSION));

    return in_array($extension, $staticExtensions);
}

// Check if user agent is a bot
function isBot(?string $userAgent, array $botPatterns): bool {
    if (empty($userAgent)) {
        return true; // No UA = likely bot
    }

    // Fake Chrome: has "Chrome/XXX.0.0.0" but no "Safari/" suffix (real browsers have both)
    if (preg_match('/Chrome\/\d+\.0\.0\.0$/', $userAgent) && stripos($userAgent, 'Safari/') === false) {
        return true;
    }

    $userAgentLower = strtolower($userAgent);
    foreach ($botPatterns as $pattern) {
        if (stripos($userAgent, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

// Extract UTM parameters from URL
function parseUtmParams(string $url): array {
    $result = [
        'utm_source' => null,
        'utm_medium' => null,
        'utm_campaign' => null,
        'utm_term' => null,
        'utm_content' => null,
    ];

    $query = parse_url($url, PHP_URL_QUERY);
    if (!$query) {
        return $result;
    }

    parse_str($query, $params);

    foreach ($result as $key => $value) {
        if (isset($params[$key]) && !empty($params[$key])) {
            // Sanitize: max 200 chars, no weird characters
            $val = substr($params[$key], 0, 200);
            $val = preg_replace('/[^\w\-_.@=+]/', '', $val);
            $result[$key] = $val ?: null;
        }
    }

    return $result;
}

// Extract domain from referer URL
function extractRefererDomain(?string $referer): ?string {
    if (empty($referer) || $referer === '-') {
        return null;
    }

    $host = parse_url($referer, PHP_URL_HOST);
    if (!$host) {
        return null;
    }

    // Remove www. prefix for cleaner grouping
    return preg_replace('/^www\./', '', strtolower($host));
}

// Get last processed offset
function getLastOffset(): int {
    if (file_exists(OFFSET_FILE)) {
        return (int)file_get_contents(OFFSET_FILE);
    }
    return 0;
}

// Save last processed offset
function saveLastOffset(int $offset): void {
    file_put_contents(OFFSET_FILE, (string)$offset);
}

// Main
$reset = in_array('--reset', $argv);
$db = initDatabase($reset);

if (!file_exists(LOG_PATH)) {
    echo "Log file not found: " . LOG_PATH . "\n";
    exit(1);
}

$lastOffset = $reset ? 0 : getLastOffset();
$fileSize = filesize(LOG_PATH);

// Handle log rotation (file smaller than last offset)
if ($fileSize < $lastOffset) {
    echo "Log rotation detected, starting from beginning.\n";
    $lastOffset = 0;
}

$handle = fopen(LOG_PATH, 'r');
if (!$handle) {
    echo "Cannot open log file.\n";
    exit(1);
}

// Seek to last position
fseek($handle, $lastOffset);

$stmt = $db->prepare("
    INSERT INTO visits (timestamp, ip, method, url, status, size, referer, referer_domain, user_agent, is_bot, session_id, utm_source, utm_medium, utm_campaign, utm_term, utm_content)
    VALUES (:timestamp, :ip, :method, :url, :status, :size, :referer, :referer_domain, :user_agent, :is_bot, :session_id, :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content)
");

$inserted = 0;
$skipped = 0;
$errors = 0;

$db->beginTransaction();

while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if (empty($line)) continue;

    $parsed = parseLogLine($line);
    if (!$parsed || !$parsed['timestamp']) {
        $errors++;
        continue;
    }

    // Skip server's own IPs (internal requests, cron jobs, link checker, etc.)
    if (in_array($parsed['ip'], $serverIps, true)) {
        $skipped++;
        continue;
    }

    // Skip internal tools by User-Agent
    foreach ($internalUserAgents as $internalUa) {
        if ($parsed['user_agent'] && stripos($parsed['user_agent'], $internalUa) !== false) {
            $skipped++;
            continue 2;
        }
    }

    // Skip static files
    if (shouldSkip($parsed['url'], $staticExtensions, $skipPaths)) {
        $skipped++;
        continue;
    }

    // Skip non-GET/POST or invalid methods
    if (!in_array($parsed['method'], ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])) {
        $skipped++;
        continue;
    }

    $isBot = isBot($parsed['user_agent'], $botPatterns) ? 1 : 0;

    // Extract UTM parameters and referer domain
    $utm = parseUtmParams($parsed['url']);
    $refererDomain = extractRefererDomain($parsed['referer']);

    try {
        $stmt->execute([
            ':timestamp' => $parsed['timestamp'],
            ':ip' => $parsed['ip'],
            ':method' => $parsed['method'],
            ':url' => $parsed['url'],
            ':status' => $parsed['status'],
            ':size' => $parsed['size'],
            ':referer' => $parsed['referer'],
            ':referer_domain' => $refererDomain,
            ':user_agent' => $parsed['user_agent'],
            ':is_bot' => $isBot,
            ':session_id' => $parsed['session_id'],
            ':utm_source' => $utm['utm_source'],
            ':utm_medium' => $utm['utm_medium'],
            ':utm_campaign' => $utm['utm_campaign'],
            ':utm_term' => $utm['utm_term'],
            ':utm_content' => $utm['utm_content'],
        ]);
        $inserted++;
    } catch (PDOException $e) {
        $errors++;
    }

    // Commit every 1000 records
    if ($inserted % 1000 === 0 && $inserted > 0) {
        $db->commit();
        $db->beginTransaction();
    }
}

$db->commit();

$newOffset = ftell($handle);
fclose($handle);

saveLastOffset($newOffset);

echo "Done!\n";
echo "  Inserted: $inserted\n";
echo "  Skipped:  $skipped\n";
echo "  Errors:   $errors\n";
echo "  Offset:   $lastOffset -> $newOffset\n";

// Show some stats
$total = $db->query("SELECT COUNT(*) FROM visits")->fetchColumn();
$bots = $db->query("SELECT COUNT(*) FROM visits WHERE is_bot = 1")->fetchColumn();
$humans = $total - $bots;

// Behavioral detection: mark IPs with many 403/404 as bots
// If an IP has 5+ error requests and >50% are errors, it's a scanner
$scannerIps = $db->query("
    SELECT ip,
           COUNT(*) as total,
           SUM(CASE WHEN status IN (403, 404) THEN 1 ELSE 0 END) as count_errors
    FROM visits
    WHERE is_bot = 0
    GROUP BY ip
    HAVING count_errors >= 5 AND (count_errors * 1.0 / total) > 0.5
")->fetchAll(PDO::FETCH_ASSOC);

if (count($scannerIps) > 0) {
    $markedAsBots = 0;
    foreach ($scannerIps as $row) {
        $result = $db->prepare("UPDATE visits SET is_bot = 1 WHERE ip = ? AND is_bot = 0");
        $result->execute([$row['ip']]);
        $markedAsBots += $result->rowCount();
    }
    echo "\nBehavioral detection:\n";
    echo "  Scanner IPs found: " . count($scannerIps) . "\n";
    echo "  Visits marked as bot: $markedAsBots\n";
}

echo "\nDatabase stats:\n";
echo "  Total visits: $total\n";
echo "  Humans: $humans\n";
echo "  Bots: $bots\n";
