<?php

declare(strict_types=1);

namespace Tests\Integration\Frontend;

use App\Support\PreviewToken;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestConfig;

/**
 * PreviewToken centralizes the HMAC-SHA256 algorithm used by both
 * /preview/<slug> and /<category>/<slug>?preview=1. Coverage:
 *   - sign() + verify() round-trip for a known payload
 *   - expired tokens are rejected even when the signature is valid
 *   - tampering with payload or token returns false
 *   - empty token short-circuits before hashing
 *   - missing APP_KEY refuses to verify (defense in depth)
 */
final class PreviewTokenTest extends TestCase
{
    protected function setUp(): void
    {
        TestConfig::reset();
        TestConfig::set('app_key', 'phpunit-test-key-not-for-production-use-only');
    }

    public function testValidTokenInFutureVerifies(): void
    {
        $payload = 'blog/hello-world';
        $expires = time() + 600;
        $token = PreviewToken::sign($payload, $expires);

        self::assertTrue(PreviewToken::verify($payload, $token, $expires));
    }

    public function testExpiredTokenIsRejectedEvenWithValidSignature(): void
    {
        $payload = 'blog/hello-world';
        $expires = time() - 1;
        $token = PreviewToken::sign($payload, $expires);

        // Signature is mathematically correct, but expires <= time().
        // verify() must short-circuit on expiry before any hash compare.
        self::assertFalse(PreviewToken::verify($payload, $token, $expires));
    }

    public function testTamperedPayloadIsRejected(): void
    {
        $expires = time() + 600;
        $token = PreviewToken::sign('blog/hello-world', $expires);

        // Same token, different payload at verify time.
        self::assertFalse(PreviewToken::verify('blog/other-slug', $token, $expires));
    }

    public function testTamperedExpiresIsRejected(): void
    {
        $payload = 'blog/hello-world';
        $signedExpires = time() + 600;
        $token = PreviewToken::sign($payload, $signedExpires);

        // Attacker bumps expires forward but doesn't have APP_KEY to re-sign.
        self::assertFalse(PreviewToken::verify($payload, $token, $signedExpires + 3600));
    }

    public function testEmptyTokenIsRejected(): void
    {
        self::assertFalse(PreviewToken::verify('any-payload', '', time() + 600));
    }

    public function testZeroExpiresIsRejected(): void
    {
        $token = PreviewToken::sign('any', 0);
        self::assertFalse(PreviewToken::verify('any', $token, 0));
    }

    public function testMissingAppKeyRefusesToVerify(): void
    {
        // Defense in depth: bootstrap.php is meant to refuse to start
        // without APP_KEY, but if something reaches here with an empty
        // key, verify() must not pretend the token is valid.
        TestConfig::set('app_key', '');
        $expires = time() + 600;
        // Build a token that "would" verify if APP_KEY were empty for
        // both sign and verify (the empty-secret HMAC).
        $token = hash_hmac('sha256', 'p|' . $expires, '');

        self::assertFalse(PreviewToken::verify('p', $token, $expires));
    }

    public function testDifferentKeysProduceDifferentSignatures(): void
    {
        $expires = time() + 600;
        $sigA = PreviewToken::sign('p', $expires);

        TestConfig::set('app_key', 'a-different-key-entirely');
        $sigB = PreviewToken::sign('p', $expires);

        self::assertNotSame($sigA, $sigB);
    }
}
