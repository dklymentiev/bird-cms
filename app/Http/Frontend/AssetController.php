<?php

declare(strict_types=1);

namespace App\Http\Frontend;

/**
 * Frontend asset passthrough.
 *
 * `uploads/` lives at the site root (next to content/, not under public/),
 * and `content/<category>/<slug>/` bundles can contain images alongside the
 * markdown. nginx's static-file regex location can't reach either path on
 * the current deployment shape, so the front controller dispatches them
 * through PHP. This controller exists purely to isolate that hack from the
 * rest of the routing logic; the long-term fix lives in deployment config,
 * not here.
 *
 * Behavior preserved verbatim from the procedural index.php:
 *   - matches uploads/*.{webp,jpg,jpeg,png,gif,svg,ico}
 *   - matches content/*.{webp,jpg,jpeg,png,gif,svg}  (no .ico in this set)
 *   - 200 + immutable cache header on hit, 404 on miss
 *   - filesize Content-Length, readfile() body, exit
 */
final class AssetController
{
    /** @var array<string, string> */
    private const UPLOADS_MIME = [
        'webp' => 'image/webp',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
    ];

    /** @var array<string, string> */
    private const CONTENT_MIME = [
        'webp' => 'image/webp',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
    ];

    public function __construct(private readonly string $siteRoot)
    {
    }

    /**
     * Whether the given URI looks like a static asset we should serve.
     *
     * Pure predicate so the dispatcher can check without instantiating
     * anything further if it doesn't match.
     */
    public static function matches(string $uri): bool
    {
        if (str_starts_with($uri, 'uploads/')
            && preg_match('/\.(webp|jpg|jpeg|png|gif|svg|ico)$/i', $uri)) {
            return true;
        }
        if (str_starts_with($uri, 'content/')
            && preg_match('/\.(webp|jpg|jpeg|png|gif|svg)$/i', $uri)) {
            return true;
        }
        return false;
    }

    public function handle(string $uri): void
    {
        $filePath = $this->siteRoot . '/' . $uri;
        $mimeMap = str_starts_with($uri, 'uploads/')
            ? self::UPLOADS_MIME
            : self::CONTENT_MIME;

        if (!file_exists($filePath) || !is_file($filePath)) {
            http_response_code(404);
            exit;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}
