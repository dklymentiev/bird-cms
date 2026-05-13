<?php

declare(strict_types=1);

namespace App\Http\Api;

/**
 * Tiny HTTP request helper for /api/v1 controllers.
 *
 * Centralises body parsing so PHPUnit can drive controllers without
 * the awkwardness of stubbing php://input through a custom stream
 * wrapper. Production reads from php://input via file_get_contents;
 * tests call Request::setBody() to inject a fixed payload before
 * invoking the controller.
 *
 * The override channel is intentionally minimal -- one string, no
 * persistence -- so a test that forgets to clear it can't poison
 * unrelated tests beyond a single setUp/tearDown pair.
 */
final class Request
{
    private static ?string $bodyOverride = null;

    /**
     * Test-only: set the body that {@see body()} will return on the
     * next call. Passing null reverts to reading php://input.
     */
    public static function setBody(?string $body): void
    {
        self::$bodyOverride = $body;
    }

    /**
     * Read the raw request body. Production reads php://input; tests
     * read whatever setBody() last installed.
     */
    public static function body(): string
    {
        if (self::$bodyOverride !== null) {
            return self::$bodyOverride;
        }
        $raw = @file_get_contents('php://input');
        return is_string($raw) ? $raw : '';
    }

    /**
     * Convenience: decode the body as JSON. Empty body is treated as
     * an empty object so a caller that forgets the body gets the
     * "missing required field" branch rather than a parse error.
     *
     * Emits 400 invalid_json + exit when the body is non-empty but
     * not a JSON object.
     *
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        $raw = self::body();
        if (trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Response::error('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $decoded;
    }
}
