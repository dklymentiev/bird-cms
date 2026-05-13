<?php

declare(strict_types=1);

namespace App\Install;

/**
 * Atomically materializes the files that bootstrap.php expects on disk:
 *
 *   .env                                (env vars, secrets, hashed admin password)
 *   config/app.php                      (compiled from templates/config-app.php.example)
 *   config/categories.php               (compiled from templates/config-categories.php.example)
 *   storage/installed.lock              (install marker; also disables the wizard)
 *
 * Note: /api/lead extensionless URL routing is intentionally not added.
 * Themes call /api/lead.php directly, which works on every nginx config.
 *
 * Pure PHP, no bootstrap dependency. Writes are atomic (write to temp file
 * + rename) so a crash mid-install never leaves a half-written .env.
 */
final class ConfigWriter
{
    public function __construct(private string $siteRoot) {}

    /**
     * Write a fresh .env from .env.example, substituting wizard fields and
     * generating the secrets the user shouldn't have to think about.
     *
     * @param array{
     *   site_name: string,
     *   site_url: string,
     *   site_description?: string,
     *   admin_email: string,
     *   admin_username: string,
     *   admin_password: string,
     *   timezone: string,
     *   language: string,
     *   client_ip: ?string,
     * } $fields
     */
    public function writeEnv(array $fields): void
    {
        $template = $this->siteRoot . '/.env.example';
        if (!is_file($template)) {
            throw new \RuntimeException('.env.example missing at ' . $template);
        }
        $content = file_get_contents($template);
        if ($content === false) {
            throw new \RuntimeException('Cannot read .env.example');
        }

        // Wizard-derived secrets and metadata.
        $appKey = bin2hex(random_bytes(32));
        $passwordHash = password_hash($fields['admin_password'], PASSWORD_DEFAULT);
        $appDomain = $this->deriveDomain($fields['site_url']);
        $allowedIps = $this->deriveAllowedIps($fields['client_ip'] ?? null);
        $trustedProxies = '127.0.0.1,::1,172.16.0.0/12';

        $replacements = [
            'APP_DOMAIN'          => $appDomain,
            'APP_KEY'             => $appKey,
            'DEBUG'               => 'false',
            'TIMEZONE'            => $fields['timezone'],
            'ADMIN_USERNAME'      => $fields['admin_username'],
            'ADMIN_PASSWORD_HASH' => $passwordHash,
            'ADMIN_ALLOWED_IPS'   => $allowedIps,
            'TRUSTED_PROXIES'     => $trustedProxies,
            'SITE_NAME'           => $this->quote($fields['site_name']),
            'SITE_URL'            => $this->quote($fields['site_url']),
            'SITE_DESCRIPTION'    => $this->quote($fields['site_description'] ?? ''),
            'ACTIVE_THEME'        => 'tailwind',
        ];

        $content = $this->substitute($content, $replacements);

        // Append wizard-only fields that .env.example doesn't list yet.
        $extras = [
            'ADMIN_EMAIL'   => $fields['admin_email'],
            'SITE_LANGUAGE' => $fields['language'],
        ];
        $content .= "\n# Added by the install wizard\n";
        foreach ($extras as $key => $value) {
            $content .= $key . '=' . $this->quote($value) . "\n";
        }

        $this->atomicWrite($this->siteRoot . '/.env', $content, 0600);
    }

    /**
     * Materialize config/app.php from templates/config-app.php.example.
     * Skips if the user already has a config/app.php (don't clobber).
     */
    public function writeAppConfig(): void
    {
        $this->materializeConfig('app');
    }

    /**
     * Materialize config/categories.php from templates/config-categories.php.example.
     * Skips if the user already has a config/categories.php (don't clobber).
     */
    public function writeCategoriesConfig(): void
    {
        $this->materializeConfig('categories');
    }

    /**
     * Copy templates/config-<name>.php.example -> config/<name>.php (atomic,
     * skips if target already exists).
     */
    private function materializeConfig(string $name): void
    {
        $target = $this->siteRoot . '/config/' . $name . '.php';
        if (is_file($target)) {
            return;
        }
        $source = $this->siteRoot . '/templates/config-' . $name . '.php.example';
        if (!is_file($source)) {
            throw new \RuntimeException('Missing template: ' . $source);
        }
        $content = file_get_contents($source);
        if ($content === false) {
            throw new \RuntimeException('Cannot read ' . $source);
        }
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        $this->atomicWrite($target, $content, 0644);
    }

    /**
     * Write the install marker. Once present, public/index.php and
     * public/admin/index.php skip the wizard and run bootstrap normally.
     */
    public function markInstalled(string $version): void
    {
        $payload = [
            'version'        => $version,
            'installed_at'   => date(\DateTimeInterface::ATOM),
            'install_method' => 'wizard',
        ];
        $target = $this->siteRoot . '/storage/installed.lock';
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        $this->atomicWrite(
            $target,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            0600
        );
    }

    private function deriveDomain(string $siteUrl): string
    {
        $host = parse_url($siteUrl, PHP_URL_HOST);
        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function deriveAllowedIps(?string $clientIp): string
    {
        // Always trust loopback + the docker bridge so a fresh local install
        // can reach /admin from a host browser without further tuning.
        $base = ['127.0.0.1', '::1', '172.16.0.0/12'];

        if ($clientIp !== null && filter_var($clientIp, FILTER_VALIDATE_IP) !== false) {
            // Don't double-add loopback; otherwise include the wizard user's IP
            // so they can log in immediately on a remote install too.
            if (!in_array($clientIp, $base, true) && $clientIp !== '127.0.0.1') {
                $base[] = $clientIp;
            }
        }

        return implode(',', $base);
    }

    /**
     * Replace `KEY=...` lines (and `KEY=""` quoted forms) in a .env body.
     * Keys not listed in $values are left untouched. Comments, blank lines,
     * and unknown keys are preserved verbatim.
     */
    private function substitute(string $envBody, array $values): string
    {
        $lines = explode("\n", $envBody);
        $seen = [];

        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }
            if (!preg_match('/^(?<key>[A-Z_][A-Z0-9_]*)\s*=/', $trimmed, $m)) {
                continue;
            }
            $key = $m['key'];
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $lines[$i] = $key . '=' . $values[$key];
            $seen[$key] = true;
        }

        // Append any keys the template was missing entirely.
        $missing = array_diff_key($values, $seen);
        if ($missing !== []) {
            $lines[] = '';
            $lines[] = '# Added by the install wizard (not present in .env.example)';
            foreach ($missing as $key => $value) {
                $lines[] = $key . '=' . $value;
            }
        }

        return implode("\n", $lines);
    }

    /** Wrap value in double-quotes if it contains whitespace or special chars. */
    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/[\s"\'#]/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        return $value;
    }

    private function atomicWrite(string $path, string $content, int $mode): void
    {
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Write failed: ' . $path);
        }
        @chmod($tmp, $mode);
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Rename failed: ' . $tmp . ' -> ' . $path);
        }
    }
}
