<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Signed preview-URL helper.
 *
 * Preview URLs let admins share unpublished/draft articles via a signed
 * link without exposing them on the public site. The signature payload
 * combines the slug (or category/slug for in-place article previews) with
 * an expiry timestamp, signed with the site's APP_KEY via HMAC-SHA256.
 *
 * Two routes consume this:
 *   - /preview/<slug>?token=...&expires=...        (drafts in worklog/)
 *   - /<category>/<slug>?preview=1&token=...&expires=...
 *
 * The signing material differs in shape (just-slug vs category/slug) but
 * the algorithm is identical, so the verification helper is shared and the
 * caller passes the exact payload string that was signed.
 *
 * `APP_KEY` is HMAC-load-bearing. The boot validator refuses to start with
 * a default or empty key (see bootstrap.php), so we can call config('app_key')
 * without a fallback here — anything reaching this code has a real key.
 */
final class PreviewToken
{
    /**
     * Verify a preview token against its signed payload + expiry.
     *
     * - `$payload` is the exact string that was signed (typically the slug
     *   or "<category>/<slug>"); callers MUST not pre-hash or transform it.
     * - `$expires` is a Unix timestamp; tokens with `expires <= time()` are
     *   rejected regardless of signature.
     * - hash_equals is used to avoid timing attacks on the token compare.
     *
     * Returns true only when the signature is valid AND the token has not
     * expired. False on any failure (no exceptions thrown — callers render
     * 403 or 404 themselves).
     */
    public static function verify(string $payload, string $token, int $expires): bool
    {
        if ($token === '' || $expires <= time()) {
            return false;
        }
        $secret = (string) config('app_key');
        if ($secret === '') {
            // bootstrap.php enforces this, but defend in depth — a missing
            // key must never accept tokens (forging would be trivial).
            return false;
        }
        $expected = hash_hmac('sha256', $payload . '|' . $expires, $secret);
        return hash_equals($expected, $token);
    }

    /**
     * Produce a signed token for the given payload + expiry. Useful for
     * admin-side preview link generation (e.g., the pipeline UI) so the
     * signing material lives in exactly one place.
     */
    public static function sign(string $payload, int $expires): string
    {
        $secret = (string) config('app_key');
        return hash_hmac('sha256', $payload . '|' . $expires, $secret);
    }
}
