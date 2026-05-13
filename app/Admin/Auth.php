<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Simple session-based authentication for admin panel
 */
final class Auth
{
    private array $config;
    private string $storageFile;

    public function __construct()
    {
        $this->config = \App\Support\Config::load('admin');
        $this->storageFile = dirname(__DIR__, 2) . '/storage/admin_auth.json';
        $this->ensureStorageExists();
        $this->startSession();
    }

    /**
     * Ensure storage directory and file exist
     */
    private function ensureStorageExists(): void
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!file_exists($this->storageFile)) {
            file_put_contents($this->storageFile, json_encode(['attempts' => []]));
        }
    }

    /**
     * Start session with configured name and lifetime
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = $this->config['session_lifetime'] ?? 86400;

            ini_set('session.gc_maxlifetime', (string) $lifetime);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_name($this->config['session_name']);
            session_start();
        }
    }

    /**
     * Check if user is logged in
     */
    public function check(): bool
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    /**
     * Check if current IP is allowed to access admin
     */
    public function isIpAllowed(): bool
    {
        $allowedIps = $this->config['allowed_ips'] ?? [];

        // Empty list = allow all
        if (empty($allowedIps)) {
            return true;
        }

        $clientIp = $this->getClientIp();

        foreach ($allowedIps as $allowed) {
            if (str_contains($allowed, '/')) {
                // CIDR notation
                if ($this->ipInCidr($clientIp, $allowed)) {
                    return true;
                }
            } else {
                // Exact match
                if ($clientIp === $allowed) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int)$bits);
        $subnet &= $mask;
        return ($ip & $mask) === $subnet;
    }

    /**
     * Attempt to login with credentials
     */
    public function attempt(string $username, string $password): bool
    {
        $ip = $this->getClientIp();

        // Check if locked out
        if ($this->isLockedOut($ip)) {
            return false;
        }

        // Validate credentials
        if ($username === $this->config['username'] &&
            password_verify($password, $this->config['password_hash'])) {
            $this->clearAttempts($ip);
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_login_time'] = time();
            return true;
        }

        // Record failed attempt
        $this->recordFailedAttempt($ip);
        return false;
    }

    /**
     * Logout current user
     */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Get logged in username
     */
    public function username(): ?string
    {
        return $_SESSION['admin_username'] ?? null;
    }

    /**
     * Check if IP is locked out
     */
    public function isLockedOut(string $ip): bool
    {
        $data = $this->getStorageData();
        $attempts = $data['attempts'][$ip] ?? [];

        if (empty($attempts)) {
            return false;
        }

        // Clean old attempts
        $cutoff = time() - $this->config['lockout_duration'];
        $recentAttempts = array_filter($attempts, fn($t) => $t > $cutoff);

        return count($recentAttempts) >= $this->config['max_login_attempts'];
    }

    /**
     * Get remaining lockout time in seconds
     */
    public function getLockoutRemaining(string $ip): int
    {
        $data = $this->getStorageData();
        $attempts = $data['attempts'][$ip] ?? [];

        if (empty($attempts)) {
            return 0;
        }

        $lastAttempt = max($attempts);
        $unlockTime = $lastAttempt + $this->config['lockout_duration'];
        $remaining = $unlockTime - time();

        return max(0, $remaining);
    }

    /**
     * Record a failed login attempt
     */
    private function recordFailedAttempt(string $ip): void
    {
        $data = $this->getStorageData();

        if (!isset($data['attempts'][$ip])) {
            $data['attempts'][$ip] = [];
        }

        $data['attempts'][$ip][] = time();

        // Keep only recent attempts
        $cutoff = time() - $this->config['lockout_duration'];
        $data['attempts'][$ip] = array_filter(
            $data['attempts'][$ip],
            fn($t) => $t > $cutoff
        );

        $this->saveStorageData($data);
    }

    /**
     * Clear login attempts for IP
     */
    private function clearAttempts(string $ip): void
    {
        $data = $this->getStorageData();
        unset($data['attempts'][$ip]);
        $this->saveStorageData($data);
    }

    /**
     * Get storage data
     */
    private function getStorageData(): array
    {
        $content = file_get_contents($this->storageFile);
        return json_decode($content, true) ?: ['attempts' => []];
    }

    /**
     * Save storage data
     */
    private function saveStorageData(array $data): void
    {
        file_put_contents($this->storageFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Get client IP address.
     *
     * Proxy headers (CF-Connecting-IP, X-Real-IP) are honored ONLY when the
     * TCP connection itself came from a configured trusted proxy. Otherwise
     * any client could spoof their IP by sending a header.
     *
     * Resolution order when REMOTE_ADDR is a trusted proxy:
     *   CF-Connecting-IP > X-Real-IP > REMOTE_ADDR
     * Otherwise: REMOTE_ADDR only.
     *
     * Configure trusted proxies via TRUSTED_PROXIES env (comma-separated IPs
     * and CIDRs). Default: loopback only — fail-secure.
     */
    public function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if ($this->isTrustedProxy($remoteAddr)) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP);
                if ($ip !== false) {
                    return $ip;
                }
            }
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ip = filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP);
                if ($ip !== false) {
                    return $ip;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Whether the given address is a known reverse proxy whose forwarding
     * headers we are willing to trust. Default = loopback only.
     */
    private function isTrustedProxy(string $remoteAddr): bool
    {
        foreach ($this->getTrustedProxies() as $entry) {
            if ($entry === '') {
                continue;
            }
            if (str_contains($entry, '/')) {
                if (str_contains($entry, ':')) {
                    if ($this->ipv6InCidr($remoteAddr, $entry)) {
                        return true;
                    }
                } else {
                    if ($this->ipInCidr($remoteAddr, $entry)) {
                        return true;
                    }
                }
            } elseif ($remoteAddr === $entry) {
                return true;
            }
        }
        return false;
    }

    /**
     * Trusted proxy list. Reads TRUSTED_PROXIES from environment
     * (.env-injected by bootstrap). Falls back to loopback only.
     */
    private function getTrustedProxies(): array
    {
        $raw = $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES');
        if ($raw === false || $raw === '') {
            return ['127.0.0.1', '::1'];
        }
        return array_map('trim', explode(',', $raw));
    }

    /**
     * IPv6 CIDR membership check (binary compare on inet_pton output).
     */
    private function ipv6InCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bitsStr] = explode('/', $cidr, 2);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $bits = (int) $bitsStr;
        $bytes = intdiv($bits, 8);
        $remain = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($remain > 0) {
            $mask = chr((0xff << (8 - $remain)) & 0xff);
            if ((substr($ipBin, $bytes, 1) & $mask) !== (substr($subnetBin, $bytes, 1) & $mask)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Generate a password hash (utility method)
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Require authentication, redirect to login if not authenticated
     */
    public function requireAuth(): void
    {
        if (!$this->check()) {
            header('Location: /admin/login');
            exit;
        }
    }
}
