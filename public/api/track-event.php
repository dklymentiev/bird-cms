<?php
/**
 * Track Event API Endpoint
 *
 * Receives conversion events from frontend and stores in SQLite
 *
 * POST /api/track-event.php
 * Body: { "event": "phone_reveal", "page": "/contact/", "meta": {} }
 */

require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

// CORS: only echo back the configured site origin. No wildcard.
$allowedOrigin = rtrim(config('site_url', ''), '/');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowedOrigin || $origin === str_replace('https://', 'http://', $allowedOrigin)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: $allowedOrigin");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event type']);
    exit;
}

// Validate event type
$allowedEvents = [
    'phone_reveal',
    'phone_click',
    'form_submit',
    'form_start',
    'cta_click',
    'quote_request',
    'email_click',
    'whatsapp_click',
    'newsletter_subscribe',
];

$eventType = $input['event'];
if (!in_array($eventType, $allowedEvents)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event type']);
    exit;
}

// Get storage path
$storagePath = defined('SITE_STORAGE_PATH')
    ? SITE_STORAGE_PATH
    : dirname(__DIR__, 2) . '/storage';

$dbPath = $storagePath . '/analytics/visits.db';

// Ensure directory exists
$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create events table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type TEXT NOT NULL,
            page_url TEXT,
            session_id TEXT,
            ip TEXT,
            user_agent TEXT,
            referer TEXT,
            metadata TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_events_type ON events(event_type)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_events_session ON events(session_id)");

    // Get client info
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    // Session ID from cookie or generate
    $sessionId = $_COOKIE['_cg_sid'] ?? bin2hex(random_bytes(16));

    // Insert event
    $stmt = $db->prepare("
        INSERT INTO events (event_type, page_url, session_id, ip, user_agent, referer, metadata, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");

    $stmt->execute([
        $eventType,
        $input['page'] ?? '',
        $sessionId,
        $ip,
        $userAgent,
        $referer,
        json_encode($input['meta'] ?? []),
    ]);

    echo json_encode([
        'success' => true,
        'event_id' => $db->lastInsertId(),
        'session_id' => $sessionId,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
