<?php

declare(strict_types=1);

namespace App\Http\Api;

use App\Support\UrlMeta;

/**
 * /api/v1/url-meta/<path> -- per-URL sitemap + template overrides.
 *
 * Backed by storage/url-meta.json (the same file the admin URL
 * Inventory writes). Operations:
 *   - GET  returns the full override row for a path (or defaults)
 *   - PUT  merges the supplied fields into the row, atomically
 *
 * <path> in the URL is the path portion of the URL the override
 * applies to, URL-encoded. The Router's {name:path} capture decodes
 * it so the controller sees e.g. "/blog/launch-notes".
 *
 * Atomic write mirrors PagesController::saveMeta: temp file + rename
 * + UrlMeta::reset() so a subsequent GET in the same request sees
 * the just-written row.
 */
final class UrlMetaController
{
    private const ALLOWED_FIELDS = ['in_sitemap', 'noindex', 'priority', 'changefreq', 'template'];

    public function show(string $path): void
    {
        $normalized = $this->normalisePath($path);
        if ($normalized === null) {
            Response::error('invalid_path', 'Path must be a non-empty URL path starting with /.', 400);
        }

        $all = UrlMeta::all();
        $row = is_array($all[$normalized] ?? null) ? $all[$normalized] : [];

        Response::json([
            'path'       => $normalized,
            'in_sitemap' => $row['in_sitemap'] ?? true,
            'noindex'    => $row['noindex']    ?? false,
            'priority'   => $row['priority']   ?? null,
            'changefreq' => $row['changefreq'] ?? null,
            'template'   => $row['template']   ?? null,
        ]);
    }

    public function update(string $path): void
    {
        $normalized = $this->normalisePath($path);
        if ($normalized === null) {
            Response::error('invalid_path', 'Path must be a non-empty URL path starting with /.', 400);
        }

        $payload = Request::json();
        $meta = $this->loadMetaFile();
        $row  = is_array($meta[$normalized] ?? null) ? $meta[$normalized] : [];

        if (array_key_exists('in_sitemap', $payload)) {
            $row['in_sitemap'] = (bool) $payload['in_sitemap'];
        }
        if (array_key_exists('noindex', $payload)) {
            $row['noindex'] = (bool) $payload['noindex'];
        }
        if (array_key_exists('priority', $payload)) {
            $v = (string) $payload['priority'];
            if ($v === '') {
                unset($row['priority']);
            } elseif (preg_match('/^(0(\.\d+)?|1(\.0+)?)$/', $v) === 1) {
                $row['priority'] = $v;
            } else {
                Response::error('invalid_priority', 'priority must be a decimal between 0.0 and 1.0.', 400);
            }
        }
        if (array_key_exists('changefreq', $payload)) {
            $v = (string) $payload['changefreq'];
            $valid = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
            if ($v === '') {
                unset($row['changefreq']);
            } elseif (in_array($v, $valid, true)) {
                $row['changefreq'] = $v;
            } else {
                Response::error('invalid_changefreq', 'changefreq must be one of: ' . implode(', ', $valid), 400);
            }
        }
        if (array_key_exists('template', $payload)) {
            $v = (string) $payload['template'];
            if ($v === '') {
                unset($row['template']);
            } else {
                $row['template'] = $v;
            }
        }

        // Drop the entry if it equals defaults so the JSON stays clean
        // (matches PagesController::update behaviour).
        if (($row['in_sitemap'] ?? true) === true
            && ($row['noindex']  ?? false) === false
            && empty($row['priority'])
            && empty($row['changefreq'])
            && empty($row['template'])
        ) {
            unset($meta[$normalized]);
        } else {
            $meta[$normalized] = $row;
        }

        $this->saveMetaFile($meta);

        Response::json([
            'ok'   => true,
            'path' => $normalized,
            'meta' => $meta[$normalized] ?? null,
        ]);
    }

    private function normalisePath(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        // The router captures everything after /url-meta/, which is
        // already URL-decoded. Add the leading slash back so callers
        // pass "blog/foo" or "/blog/foo" interchangeably.
        if ($raw[0] !== '/') {
            $raw = '/' . $raw;
        }
        // Block path traversal in the meta key itself: a malicious
        // value like /a/../b would re-normalise to /b on the client,
        // creating ambiguous keys.
        if (strpos($raw, '..') !== false) {
            return null;
        }
        return $raw;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadMetaFile(): array
    {
        $path = $this->metaPath();
        if (!is_file($path)) return [];
        $raw = (string) file_get_contents($path);
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, array<string, mixed>> $meta
     */
    private function saveMetaFile(array $meta): void
    {
        $path = $this->metaPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create storage dir: ' . $dir);
        }
        ksort($meta);
        $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $bytes = file_put_contents($tmp, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        if ($bytes === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to write ' . $path);
        }
        UrlMeta::reset();
    }

    private function metaPath(): string
    {
        $root = defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 3);
        return $root . '/storage/url-meta.json';
    }

}
