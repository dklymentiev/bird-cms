<?php

declare(strict_types=1);

namespace App\Http\Api;

/**
 * Bearer-token auth for /api/v1.
 *
 * Reads `Authorization: Bearer <key>` off the request, hashes the
 * presented key with SHA-256, and looks it up against
 * storage/api-keys.json via {@see ApiKeyStore}.
 *
 * Two scopes:
 *   - read:  GET only
 *   - write: GET + POST + PUT + DELETE
 *
 * Failure modes intentionally collapse to a single 401 without
 * revealing the failure cause:
 *   - no Authorization header
 *   - malformed header (not "Bearer <hex>")
 *   - key not found
 *   - key revoked
 * A scope mismatch -- caller authenticated but cannot perform the
 * requested verb -- is a separate 403 so the caller can fix the key
 * rather than retry blindly.
 *
 * Constant-time compare lives in ApiKeyStore::findActiveByHash().
 */
final class Authenticator
{
    public function __construct(private readonly ?ApiKeyStore $store = null)
    {
    }

    /**
     * Authenticate the current request and verify scope. Emits a
     * response and exits when auth fails; returns the matched record
     * (with key_hash) on success so the rate limiter can scope its
     * counters to the calling key.
     *
     * @return array<string, mixed>
     */
    public function guard(string $requiredScope): array
    {
        $header = $this->readAuthorizationHeader();
        if ($header === null) {
            // Hint clients to send a Bearer token -- standard 401 affordance.
            header('WWW-Authenticate: Bearer realm="bird-cms-api"');
            Response::error('unauthorized', 'Missing or invalid Authorization header.', 401);
        }

        $key = $this->extractBearer($header);
        if ($key === null) {
            header('WWW-Authenticate: Bearer realm="bird-cms-api", error="invalid_token"');
            Response::error('unauthorized', 'Missing or invalid Authorization header.', 401);
        }

        $hash = hash('sha256', $key);
        $store = $this->store ?? new ApiKeyStore(ApiKeyStore::defaultPath());
        $record = $store->findActiveByHash($hash);
        if ($record === null) {
            header('WWW-Authenticate: Bearer realm="bird-cms-api", error="invalid_token"');
            Response::error('unauthorized', 'Missing or invalid Authorization header.', 401);
        }

        if (!$this->scopeAllows((string) ($record['scope'] ?? ''), $requiredScope)) {
            Response::error(
                'forbidden',
                'API key scope "' . ($record['scope'] ?? '?') . '" cannot perform this action.',
                403
            );
        }

        // Stamp last_used asynchronously to the caller's perspective:
        // it's a best-effort write that never fails the request.
        $store->touch($hash);

        $record['key_hash']     = $hash; // ensure callers always see the hash
        $record['last_used_at'] = gmdate('Y-m-d\TH:i:s\Z');
        return $record;
    }

    /**
     * Extract the Authorization header. Falls back across the common
     * locations PHP exposes it; some FastCGI configurations strip the
     * header from $_SERVER unless explicitly forwarded.
     */
    private function readAuthorizationHeader(): ?string
    {
        $candidates = [
            $_SERVER['HTTP_AUTHORIZATION']        ?? null,
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
        ];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        $candidates[] = $value;
                        break;
                    }
                }
            }
        }
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '') {
                return $c;
            }
        }
        return null;
    }

    private function extractBearer(string $header): ?string
    {
        if (!preg_match('/^Bearer\s+([A-Za-z0-9._\-]+)\s*$/', $header, $m)) {
            return null;
        }
        $token = $m[1];
        // Accept 64-hex (generated via bin2hex(random_bytes(32))) and any
        // future formats that fit the loose grammar above. Length is
        // enforced loosely (16..256) to keep buffer overruns + log-spam
        // out of the matching path.
        $len = strlen($token);
        if ($len < 16 || $len > 256) {
            return null;
        }
        return $token;
    }

    private function scopeAllows(string $actual, string $required): bool
    {
        if ($required === 'read') {
            return in_array($actual, ['read', 'write'], true);
        }
        if ($required === 'write') {
            return $actual === 'write';
        }
        return false;
    }

    /**
     * Helper used by the admin UI: generate a new plaintext key
     * (64-char hex) and the sha256 hash to persist. The plaintext is
     * returned to the caller so it can be shown ONCE; nothing else
     * stores it.
     *
     * @return array{plaintext:string, hash:string}
     */
    public static function generateKey(): array
    {
        $plain = bin2hex(random_bytes(32));
        return ['plaintext' => $plain, 'hash' => hash('sha256', $plain)];
    }
}
