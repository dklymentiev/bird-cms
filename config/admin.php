<?php

declare(strict_types=1);

$allowedIps = $_ENV['ADMIN_ALLOWED_IPS'] ?? '';
$allowedIps = $allowedIps ? array_map('trim', explode(',', $allowedIps)) : [];

// Admin UI mode. 'minimal' (OSS default) hides sidebar entries that
// fresh installs rarely need (Articles -- redundant with Pages, the
// Security tab cluster, Audit). 'full' shows every nav item. Hidden
// sections still respond at their direct URLs; this only governs the
// sidebar.
$mode = strtolower((string) ($_ENV['ADMIN_MODE'] ?? 'minimal'));
if (!in_array($mode, ['minimal', 'full'], true)) {
    $mode = 'minimal';
}

return [
    'username' => $_ENV['ADMIN_USERNAME'] ?? 'admin',
    'password_hash' => $_ENV['ADMIN_PASSWORD_HASH'] ?? '',
    'allowed_ips' => $allowedIps, // empty = allow all
    'mode' => $mode, // minimal | full
    'session_name' => 'dim_admin',
    'session_lifetime' => 86400, // 24 hours
    'max_login_attempts' => 5,
    'lockout_duration' => 900, // 15 minutes
];
