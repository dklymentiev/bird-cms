<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Atomic write helpers shared by content repositories.
 *
 * Every content repository ends up doing the same dance: emit a body
 * (markdown) and a sidecar (YAML or frontmatter), write each one to a
 * temp file, then rename(2) into place so a crashed PHP process can't
 * leave a half-written .md/.meta.yaml pair on disk.
 *
 * Mirrors the pattern in {@see \App\Install\ConfigWriter::atomicWrite()}
 * and the MCP server's write_atomic(), but lives next to the
 * repositories that actually call it so the read and write paths sit
 * in the same file.
 */
trait AtomicMarkdownWrite
{
    /**
     * Write $contents to $path atomically: temp file in the same
     * directory, fsync-by-rename to the target. Caller is responsible
     * for ensuring the parent directory exists.
     *
     * @throws \RuntimeException on any IO failure -- never returns false.
     */
    protected function atomicWrite(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Failed to create directory: ' . $dir);
        }

        // Suffix temp filename with PID + 8 random hex so two concurrent
        // saves to the same path don't trample each other's temp files.
        $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));

        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write temp file: ' . $tmp);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to rename temp file to: ' . $path);
        }
    }

    /**
     * Slug guard shared by every save path. Repositories accept input
     * from the admin UI and from MCP; this is the last gate before we
     * touch the filesystem with a user-supplied path segment.
     */
    protected function assertValidSlug(string $value, string $label = 'slug'): void
    {
        if ($value === '' || preg_match('/^[a-z0-9-]+$/', $value) !== 1) {
            throw new \RuntimeException(
                $label . ' must be lowercase alphanumeric with hyphens; got "' . $value . '"'
            );
        }
    }
}
