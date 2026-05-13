<?php

declare(strict_types=1);

namespace App\Http\Api;

/**
 * Thrown by {@see Response::json()} / {@see Response::error()} when
 * {@see Response::$testMode} is true.
 *
 * Production code path always calls exit(); tests flip the flag and
 * catch this exception so they can assert on the status + body
 * without the suite terminating mid-test.
 *
 * Carries the same payload the wire response would have carried:
 *   - status: HTTP status code
 *   - body:   raw JSON body that was about to be echoed
 *
 * Inspect via Response::$lastCaptured for the same data; the
 * exception form is convenient for try/catch-driven assertions.
 */
final class ResponseSentException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $body
    ) {
        parent::__construct(sprintf('API response %d: %s', $status, $body));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decoded(): ?array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
