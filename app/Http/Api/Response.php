<?php

declare(strict_types=1);

namespace App\Http\Api;

/**
 * JSON response helpers for /api/v1 controllers.
 *
 * Two response shapes only:
 *   - success: arbitrary JSON-serialisable payload, 2xx status
 *   - error:   {"error": {"code": "...", "message": "..."}}, 4xx/5xx
 *
 * Both shapes always set Content-Type: application/json and exit(),
 * so callers can early-return from an error branch without manually
 * stopping the request.
 *
 * Testability: setting Response::$testMode = true makes both helpers
 * throw a {@see ResponseSentException} instead of calling exit() so
 * PHPUnit can drive the full controller path and assert on the status
 * + body. Production code never flips this flag.
 */
final class Response
{
    /** When true, json()/error() throw instead of calling exit(). */
    public static bool $testMode = false;

    /**
     * Last response captured under $testMode. Read after catching
     * ResponseSentException to assert on status + body.
     *
     * @var array{status:int, body:string}|null
     */
    public static ?array $lastCaptured = null;

    /**
     * @param mixed $data
     */
    public static function json($data, int $status = 200): void
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (self::$testMode) {
            self::$lastCaptured = ['status' => $status, 'body' => $body];
            throw new ResponseSentException($status, $body);
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo $body;
        exit;
    }

    public static function error(string $code, string $message, int $status = 400): void
    {
        self::json([
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
