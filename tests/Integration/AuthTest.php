<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Admin\Auth;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\TempContent;

/**
 * Integration coverage for App\Admin\Auth.
 *
 * Auth is the security choke-point: login attempts, IP allowlist, lockout,
 * trusted-proxy header acceptance. All four are exercised here against the
 * real on-disk attempt store; we just point storageFile at a temp file via
 * reflection so the suite doesn't pollute a real install's storage/.
 *
 * The tests deliberately drive Auth through its public methods (attempt,
 * isIpAllowed, getClientIp, isLockedOut, logout) so the assertions track
 * the contract a controller actually depends on.
 */
final class AuthTest extends TestCase
{
    private string $storageFile;
    private string $tmpDir;

    protected function setUp(): void
    {
        // Auth's constructor calls Config::load('admin') which requires the
        // engine bootstrap chain. Stub the loaded config in a static property
        // before constructing Auth -- mirrors how tests/bootstrap.php avoids
        // the full SITE_ROOT walk.
        $this->primeAdminConfig([
            'username' => 'admin',
            // Hash for "secret"
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'allowed_ips' => ['127.0.0.1', '10.0.0.0/24'],
            'session_name' => 'phpunit_admin',
            'session_lifetime' => 3600,
            'max_login_attempts' => 3,
            'lockout_duration' => 900,
        ]);

        $this->tmpDir = TempContent::make('auth');
        $this->storageFile = $this->tmpDir . '/admin_auth.json';

        // Sessions can't actually start in CLI without an SAPI hook; many
        // tests here only need the IP/lockout surface, so suppress.
        @session_id() === '' ?: session_destroy();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_ENV['TRUSTED_PROXIES'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testLoginAttemptWithGoodCredentialsSucceeds(): void
    {
        $auth = $this->makeAuth();
        self::assertTrue($auth->attempt('admin', 'secret'));
    }

    public function testLoginAttemptWithBadPasswordFails(): void
    {
        $auth = $this->makeAuth();
        self::assertFalse($auth->attempt('admin', 'wrong'));
    }

    public function testLoginAttemptWithBadUsernameFails(): void
    {
        $auth = $this->makeAuth();
        self::assertFalse($auth->attempt('hacker', 'secret'));
    }

    public function testIpAllowlistExactMatch(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $auth = $this->makeAuth();
        self::assertTrue($auth->isIpAllowed());
    }

    public function testIpAllowlistCidrMatch(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.50';
        $auth = $this->makeAuth();
        self::assertTrue($auth->isIpAllowed());
    }

    public function testIpAllowlistRejectsOutsider(): void
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $auth = $this->makeAuth();
        self::assertFalse($auth->isIpAllowed());
    }

    public function testProxyHeadersIgnoredFromUntrustedRemote(): void
    {
        // Connection from outside trusted-proxies list. Even if the request
        // claims X-Real-IP: 127.0.0.1, getClientIp() must NOT honour it.
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_X_REAL_IP'] = '127.0.0.1';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '127.0.0.1';
        $_ENV['TRUSTED_PROXIES'] = '127.0.0.1';

        $auth = $this->makeAuth();
        self::assertSame('8.8.8.8', $auth->getClientIp());

        unset($_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    public function testProxyHeadersHonoredFromTrustedRemote(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.42';
        $_ENV['TRUSTED_PROXIES'] = '127.0.0.1';

        $auth = $this->makeAuth();
        self::assertSame('203.0.113.42', $auth->getClientIp());

        unset($_SERVER['HTTP_X_REAL_IP']);
    }

    public function testLockoutAfterMaxFailedAttempts(): void
    {
        $auth = $this->makeAuth();
        $ip = '127.0.0.1';

        // 3 failed attempts (max_login_attempts=3 in primed config).
        $auth->attempt('admin', 'wrong-1');
        $auth->attempt('admin', 'wrong-2');
        $auth->attempt('admin', 'wrong-3');

        self::assertTrue($auth->isLockedOut($ip));
        self::assertGreaterThan(0, $auth->getLockoutRemaining($ip));

        // Even good credentials fail while locked out.
        self::assertFalse($auth->attempt('admin', 'secret'));
    }

    public function testHashPasswordRoundTrips(): void
    {
        $hash = Auth::hashPassword('hunter2');
        self::assertTrue(password_verify('hunter2', $hash));
        self::assertFalse(password_verify('wrong', $hash));
    }

    private function makeAuth(): Auth
    {
        $auth = new Auth();
        // Redirect storage to our tmp dir; the constructor already created
        // a default file we overwrite here.
        $r = new ReflectionClass($auth);
        $prop = $r->getProperty('storageFile');
        $prop->setAccessible(true);
        $prop->setValue($auth, $this->storageFile);
        if (!file_exists($this->storageFile)) {
            file_put_contents($this->storageFile, json_encode(['attempts' => []]));
        }
        return $auth;
    }

    /**
     * Inject an admin config into App\Support\Config's static cache so
     * Auth's constructor finds it without touching disk.
     */
    private function primeAdminConfig(array $cfg): void
    {
        $r = new ReflectionClass(\App\Support\Config::class);
        $cache = $r->getProperty('items');
        $cache->setAccessible(true);
        $cache->setValue(null, []);

        // Config::load() uses a separate static-in-method cache; we can't
        // poke at that directly, so write the admin config to a tmp file
        // and define SITE_CONFIG_PATH/ENGINE_ROOT to point at it.
        if (!defined('SITE_CONFIG_PATH')) {
            $tmpCfgDir = sys_get_temp_dir() . '/bird-cms-cfg-' . bin2hex(random_bytes(4));
            mkdir($tmpCfgDir, 0755, true);
            define('SITE_CONFIG_PATH', $tmpCfgDir);
            define('ENGINE_ROOT', $tmpCfgDir);
        }
        file_put_contents(
            SITE_CONFIG_PATH . '/admin.php',
            '<?php return ' . var_export($cfg, true) . ';'
        );
    }
}
