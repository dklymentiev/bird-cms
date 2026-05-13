<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Http\Api\ApiKeyStore;
use App\Http\Api\Authenticator;
use App\Http\Api\RateLimiter;
use App\Http\Api\Response;
use App\Http\Api\ResponseSentException;
use App\Support\RateLimit;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempContent;

/**
 * Bearer auth + rate limit coverage for /api/v1.
 *
 * The HTTP entry point (public/api/v1/index.php) calls Authenticator
 * ::guard() before any route handler runs, so this test class exercises
 * guard() directly with the same Authorization header it would receive
 * over the wire. Response::$testMode flips exit() into a catchable
 * exception so we can assert on status + body without the test runner
 * dying.
 */
final class ApiAuthTest extends TestCase
{
    private string $keysPath;
    private ApiKeyStore $store;

    protected function setUp(): void
    {
        Response::$testMode = true;
        Response::$lastCaptured = null;

        $dir = TempContent::make('api-auth');
        $this->keysPath = $dir . '/api-keys.json';
        $this->store = new ApiKeyStore($this->keysPath);

        // Clear out any Authorization header from previous tests.
        unset(
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        );
    }

    protected function tearDown(): void
    {
        Response::$testMode = false;
        TempContent::cleanup();
    }

    public function testMissingHeaderReturns401(): void
    {
        $auth = new Authenticator($this->store);
        $this->expectException(ResponseSentException::class);
        try {
            $auth->guard('read');
        } catch (ResponseSentException $e) {
            self::assertSame(401, $e->status);
            $decoded = $e->decoded();
            self::assertSame('unauthorized', $decoded['error']['code']);
            throw $e;
        }
    }

    public function testInvalidBearerReturns401(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer not-hex-and-too-short';
        $auth = new Authenticator($this->store);
        $this->expectException(ResponseSentException::class);
        try {
            $auth->guard('read');
        } catch (ResponseSentException $e) {
            self::assertSame(401, $e->status);
            throw $e;
        }
    }

    public function testUnknownKeyReturns401(): void
    {
        $unknown = bin2hex(random_bytes(32));
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $unknown;
        $auth = new Authenticator($this->store);
        $this->expectException(ResponseSentException::class);
        try {
            $auth->guard('read');
        } catch (ResponseSentException $e) {
            self::assertSame(401, $e->status);
            throw $e;
        }
    }

    public function testRevokedKeyReturns401EvenWhenHashMatches(): void
    {
        $gen = Authenticator::generateKey();
        $this->store->create($gen['hash'], 'mobile', 'read');
        $this->store->revoke($gen['hash']);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $gen['plaintext'];
        $auth = new Authenticator($this->store);
        $this->expectException(ResponseSentException::class);
        try {
            $auth->guard('read');
        } catch (ResponseSentException $e) {
            self::assertSame(401, $e->status, 'Revoked keys must collapse to 401 alongside missing/wrong keys.');
            throw $e;
        }
    }

    public function testValidReadKeyAllowsRead(): void
    {
        $gen = Authenticator::generateKey();
        $this->store->create($gen['hash'], 'mobile', 'read');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $gen['plaintext'];
        $auth = new Authenticator($this->store);
        $record = $auth->guard('read');

        self::assertSame($gen['hash'], $record['key_hash']);
        self::assertSame('read', $record['scope']);
        self::assertNotNull($record['last_used_at'], 'guard() must stamp last_used_at on success.');
    }

    public function testReadScopeCannotWrite(): void
    {
        $gen = Authenticator::generateKey();
        $this->store->create($gen['hash'], 'mobile', 'read');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $gen['plaintext'];

        $auth = new Authenticator($this->store);
        $this->expectException(ResponseSentException::class);
        try {
            $auth->guard('write');
        } catch (ResponseSentException $e) {
            self::assertSame(403, $e->status, 'Scope mismatch is 403, distinct from 401, so callers know to rotate the key.');
            $decoded = $e->decoded();
            self::assertSame('forbidden', $decoded['error']['code']);
            throw $e;
        }
    }

    public function testWriteScopeAllowsBothVerbs(): void
    {
        $gen = Authenticator::generateKey();
        $this->store->create($gen['hash'], 'ci', 'write');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $gen['plaintext'];

        $auth = new Authenticator($this->store);
        $r = $auth->guard('read');
        self::assertSame('write', $r['scope']);

        $w = $auth->guard('write');
        self::assertSame('write', $w['scope']);
    }

    public function testRateLimitReturns429AfterNRequests(): void
    {
        // Stand up a fresh in-memory limiter with a tiny budget so the
        // test doesn't have to fire 60 requests to trip it.
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $limiter = new RateLimit($pdo, ['api_v1' => [60 => 2]]);
        $bridge = new RateLimiter($limiter);

        $hash = hash('sha256', 'fake');
        // First two requests pass.
        $bridge->enforce($hash);
        $bridge->enforce($hash);

        $this->expectException(ResponseSentException::class);
        try {
            $bridge->enforce($hash);
        } catch (ResponseSentException $e) {
            self::assertSame(429, $e->status);
            $decoded = $e->decoded();
            self::assertSame('rate_limited', $decoded['error']['code']);
            throw $e;
        }
    }

    public function testKeyHashStorageNeverHoldsPlaintext(): void
    {
        // Defense-in-depth assertion: the plaintext key must not appear
        // anywhere in the on-disk JSON, regardless of how the operator
        // gets there (label, accident, copy-paste).
        $gen = Authenticator::generateKey();
        $this->store->create($gen['hash'], 'a sensitive label', 'read');

        $raw = (string) file_get_contents($this->keysPath);
        self::assertStringContainsString($gen['hash'], $raw);
        self::assertStringNotContainsString($gen['plaintext'], $raw);
    }
}
