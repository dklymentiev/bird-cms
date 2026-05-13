<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

// CORS headers - restrict to same origin
$allowedOrigin = rtrim(config('site_url', ''), '/');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowedOrigin || $origin === str_replace('https://', 'http://', $allowedOrigin)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: $allowedOrigin");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

use App\Newsletter\FileSubscriberRepository;

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $rl = new \App\Support\RateLimit();
    $verdict = $rl->hit('subscribe', $ipAddress !== '' ? $ipAddress : 'unknown');
    if (!$verdict['allowed']) {
        error_log(sprintf('Newsletter subscribe rate limit triggered for %s', $ipAddress !== '' ? $ipAddress : 'unknown'));
        $rl->deny($verdict['retry_after']);
    }

    // Get email from request
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? $_POST['email'] ?? null;

    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        exit;
    }

    // Validate email
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);
    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }

    // Initialize repository
    $dataDir = defined('SITE_STORAGE_PATH') ? SITE_STORAGE_PATH . '/data' : __DIR__ . '/../../storage/data';
    $repository = new FileSubscriberRepository($dataDir);

    // Check if already subscribed
    if ($repository->isSubscribed($email)) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'You are already subscribed!',
            'already_subscribed' => true,
        ]);
        exit;
    }

    // Subscribe
    $success = $repository->subscribe(
        $email,
        $ipAddress !== '' ? $ipAddress : null,
        $userAgent !== '' ? $userAgent : null
    );

    if ($success) {
        // Track subscription event for analytics
        $storagePath = defined('SITE_STORAGE_PATH') ? SITE_STORAGE_PATH : __DIR__ . '/../../storage';
        $dbPath = $storagePath . '/analytics/visits.db';
        if (file_exists($dbPath)) {
            try {
                $db = new PDO('sqlite:' . $dbPath);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $db->prepare("INSERT INTO events (event_type, page_url, session_id, ip, user_agent, referer, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))");
                $stmt->execute([
                    'newsletter_subscribe',
                    $_SERVER['HTTP_REFERER'] ?? '',
                    $_COOKIE['_cg_sid'] ?? bin2hex(random_bytes(8)),
                    $ipAddress,
                    $userAgent,
                    $_SERVER['HTTP_REFERER'] ?? '',
                    json_encode(['email_domain' => substr($email, strpos($email, '@') + 1)]),
                ]);
            } catch (Exception $e) {
                // Ignore tracking errors
            }
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Successfully subscribed!',
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to subscribe. Please try again.',
        ]);
    }

} catch (Exception $e) {
    error_log('Newsletter subscription error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred. Please try again later.',
    ]);
}
