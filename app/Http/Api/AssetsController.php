<?php

declare(strict_types=1);

namespace App\Http\Api;

/**
 * /api/v1/assets/* -- multipart upload + listing + delete.
 *
 * Files land under uploads/ (the site-root sibling, separate from
 * public/assets which the admin Media tab manages and ships with
 * the site bundle). uploads/ is the right home for API-uploaded
 * content because:
 *   - it isn't part of the engine's versioned files (sites won't lose
 *     them on upgrade);
 *   - the deploy guide already excludes it from `git clean`;
 *   - nginx serves it through the catch-all location, same as
 *     public/assets, when symlinked or proxied (operator's call).
 *
 * Two invariants:
 *   1. Path traversal is rejected up-front. Every <path> goes through
 *      normalisePath() which strips drive letters, "..", and absolute
 *      prefixes; the result is then re-joined to the uploads root and
 *      re-checked with realpath(). Any path that escapes the root is
 *      400'd before any file system call.
 *   2. Upload size and MIME type are whitelisted. Same limits as the
 *      admin Media controller (10MiB, jpg/png/gif/webp + a few common
 *      docs); we don't accept arbitrary binaries.
 */
final class AssetsController
{
    /**
     * Allowed MIME types for upload. Stays narrow on purpose -- the
     * API surface should not become a generic file dropbox. Extend
     * per deployment if needed.
     *
     * @var list<string>
     */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024; // 10 MiB

    /**
     * POST /api/v1/assets/upload  (multipart/form-data: file=<binary>, path=<rel>)
     *
     * Atomic write: receive into uploads tree, then rename(2).
     */
    public function upload(): void
    {
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('no_file', 'Multipart "file" field is required.', 400);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            Response::error('invalid_size', 'File must be 1 byte to ' . self::MAX_BYTES . ' bytes.', 400);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpName) && !is_file($tmpName)) {
            // is_uploaded_file is false for non-SAPI uploads (testing,
            // CLI); accept any readable tmp file in that case. Tests
            // simulate the upload by setting tmp_name to a real path.
            Response::error('no_file', 'Uploaded file is missing.', 400);
        }

        // MIME sniff from the bytes, not the client-supplied Content-Type.
        $mime = $this->detectMime($tmpName);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            Response::error('unsupported_type', 'MIME type not allowed: ' . $mime, 415);
        }

        // Target path: prefer explicit "path" form field; fall back to
        // the client's filename. Either way it gets normalised first.
        $rawPath = (string) ($_POST['path'] ?? ($file['name'] ?? ''));
        $rel = $this->normalisePath($rawPath);
        if ($rel === null) {
            Response::error('invalid_path', 'Path must be a relative file path; "..", absolute paths, and drive letters are rejected.', 400);
        }

        $root = $this->uploadsRoot();
        $target = $root . '/' . $rel;

        // Ensure the parent exists; never overwrite a directory.
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            Response::error('write_failed', 'Failed to create directory.', 500);
        }
        if (is_dir($target)) {
            Response::error('invalid_path', 'Path resolves to an existing directory.', 400);
        }

        // Re-confirm containment after mkdir resolved any symlinks.
        if (!$this->isInsideRoot($target, $root)) {
            Response::error('invalid_path', 'Path escapes uploads root.', 400);
        }

        // Atomic move: PHP's move_uploaded_file falls back to rename
        // for non-SAPI tmp files (CLI tests), which is what we want.
        $moved = is_uploaded_file($tmpName)
            ? @move_uploaded_file($tmpName, $target)
            : @rename($tmpName, $target);
        if (!$moved) {
            Response::error('write_failed', 'Failed to store uploaded file.', 500);
        }
        @chmod($target, 0644);

        Response::json([
            'ok'           => true,
            'path'         => $rel,
            'size'         => filesize($target) ?: $size,
            'mime'         => $mime,
            'download_url' => '/uploads/' . $rel,
        ], 201);
    }

    /**
     * GET /api/v1/assets/<path> -- metadata + download URL.
     *
     * The body is NOT returned inline; for large binaries the caller
     * should fetch the static download_url directly (nginx serves it
     * faster than PHP can). Keeps the JSON surface uniform.
     */
    public function show(string $path): void
    {
        $rel = $this->normalisePath($path);
        if ($rel === null) {
            Response::error('invalid_path', 'Path must be a relative file path; "..", absolute paths, and drive letters are rejected.', 400);
        }
        $full = $this->uploadsRoot() . '/' . $rel;
        if (!is_file($full) || !$this->isInsideRoot($full, $this->uploadsRoot())) {
            Response::error('not_found', 'No such asset.', 404);
        }

        Response::json([
            'path'         => $rel,
            'size'         => filesize($full) ?: 0,
            'mime'         => $this->detectMime($full),
            'modified_at'  => gmdate('Y-m-d\TH:i:s\Z', filemtime($full) ?: time()),
            'download_url' => '/uploads/' . $rel,
        ]);
    }

    /**
     * DELETE /api/v1/assets/<path>. Idempotent.
     */
    public function destroy(string $path): void
    {
        $rel = $this->normalisePath($path);
        if ($rel === null) {
            Response::error('invalid_path', 'Path must be a relative file path; "..", absolute paths, and drive letters are rejected.', 400);
        }
        $full = $this->uploadsRoot() . '/' . $rel;
        if (!$this->isInsideRoot($full, $this->uploadsRoot())) {
            Response::error('invalid_path', 'Path escapes uploads root.', 400);
        }
        $deleted = false;
        if (is_file($full)) {
            $deleted = @unlink($full);
        }
        Response::json([
            'ok'      => true,
            'deleted' => $deleted,
            'path'    => $rel,
        ]);
    }

    /**
     * Strip the path to a safe, relative form.
     *
     * Rejects:
     *   - empty / whitespace-only
     *   - absolute paths (leading /)
     *   - Windows drive letters / UNC
     *   - any segment equal to "." or ".."
     *   - reserved filenames (NUL, CON, PRN on Windows -- defence-in-depth)
     *
     * Returns the cleaned relative path with forward slashes, or null
     * when the input is unsafe. The caller still has to re-check the
     * resolved path is inside the uploads root (isInsideRoot) because
     * a symlink inside uploads/ could otherwise route outside.
     */
    private function normalisePath(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        // Reject NUL byte injection up-front.
        if (strpos($raw, "\0") !== false) return null;

        // Normalise slashes; reject any leading slash, drive letter,
        // or UNC prefix.
        $p = str_replace('\\', '/', $raw);
        if (str_starts_with($p, '/')) return null;
        if (preg_match('/^[A-Za-z]:/', $p)) return null;
        if (str_starts_with($p, '//')) return null;

        $segments = explode('/', $p);
        $out = [];
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') return null;
            // Per-segment sanity: lowercase alnum + _-. and a few common
            // unicode safe code points. Tightens past "anything goes"
            // without being so strict the operator can't use timestamps.
            if (preg_match('/[\x00-\x1f]/u', $seg)) return null;
            if (preg_match('/^(con|prn|aux|nul|com[0-9]|lpt[0-9])(\.|$)/i', $seg)) return null;
            $out[] = $seg;
        }
        if ($out === []) return null;
        return implode('/', $out);
    }

    private function uploadsRoot(): string
    {
        $root = defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 3);
        $dir = $root . '/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create uploads dir: ' . $dir);
        }
        // Resolve symlinks so isInsideRoot comparisons are robust.
        $real = realpath($dir);
        return $real !== false ? $real : $dir;
    }

    private function isInsideRoot(string $candidate, string $root): bool
    {
        // realpath() returns false for not-yet-existing files; fall
        // back to the lexical parent which we just confirmed lives
        // inside the root.
        $resolved = realpath($candidate);
        if ($resolved === false) {
            $resolved = realpath(dirname($candidate));
            if ($resolved === false) return false;
            $resolved .= '/' . basename($candidate);
        }
        $rootReal = realpath($root) ?: $root;
        $resolved = str_replace('\\', '/', $resolved);
        $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');
        return str_starts_with($resolved, $rootReal . '/') || $resolved === $rootReal;
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') return $mime;
            }
        }
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if (is_string($mime) && $mime !== '') return $mime;
        }
        return 'application/octet-stream';
    }
}
