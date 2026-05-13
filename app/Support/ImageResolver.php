<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves image paths in content.
 *
 * Supports:
 * - Relative paths: ./image.webp → resolved from bundle directory
 * - Absolute paths: /assets/shared/... → resolved from public/
 * - Legacy paths: /content/... → resolved from base path
 */
final class ImageResolver
{
    private string $basePath;
    private string $publicPath;
    private ?string $bundlePath = null;

    public function __construct(?string $basePath = null, ?string $publicPath = null)
    {
        $this->basePath = $basePath ?? (\defined('BASE_PATH') ? BASE_PATH : getcwd());
        $this->publicPath = $publicPath ?? $this->basePath . '/public';
    }

    /**
     * Set the current bundle path for resolving relative paths
     */
    public function setBundlePath(string $bundlePath): self
    {
        $this->bundlePath = rtrim($bundlePath, '/');
        return $this;
    }

    /**
     * Get the current bundle path
     */
    public function getBundlePath(): ?string
    {
        return $this->bundlePath;
    }

    /**
     * Resolve an image source path to a URL
     *
     * @param string $src The image source from markdown
     * @return string The resolved URL path
     */
    public function resolve(string $src): string
    {
        $src = trim($src);

        // Already a full URL
        if (preg_match('#^https?://#', $src)) {
            return $src;
        }

        // Relative path: ./image.webp
        if (str_starts_with($src, './')) {
            return $this->resolveRelative($src);
        }

        // Absolute path starting with /assets/
        if (str_starts_with($src, '/assets/')) {
            return $src; // Already correct URL
        }

        // Legacy: /content/... paths - convert to URL
        if (str_starts_with($src, '/content/')) {
            return $src; // Keep as-is, will be served by nginx/php
        }

        // Unknown format - return as-is
        return $src;
    }

    /**
     * Resolve relative path from bundle directory
     */
    private function resolveRelative(string $src): string
    {
        if ($this->bundlePath === null) {
            // No bundle context - return as-is (fallback)
            return $src;
        }

        // Remove ./ prefix
        $filename = substr($src, 2);

        // Build URL path from bundle
        // Bundle: /var/www/html/content/articles/tools/my-article
        // URL: /content/articles/tools/my-article/image.webp
        $relativeBundlePath = $this->getRelativeBundlePath();

        if ($relativeBundlePath !== null) {
            return '/' . $relativeBundlePath . '/' . $filename;
        }

        return $src;
    }

    /**
     * Get bundle path relative to base path
     */
    private function getRelativeBundlePath(): ?string
    {
        if ($this->bundlePath === null) {
            return null;
        }

        // If bundle path starts with base path, make it relative
        if (str_starts_with($this->bundlePath, $this->basePath)) {
            return ltrim(substr($this->bundlePath, strlen($this->basePath)), '/');
        }

        return null;
    }

    /**
     * Check if a resolved path exists as a file
     */
    public function exists(string $src): bool
    {
        $src = trim($src);

        // Relative path
        if (str_starts_with($src, './') && $this->bundlePath !== null) {
            $filename = substr($src, 2);
            return file_exists($this->bundlePath . '/' . $filename);
        }

        // Absolute /assets/ path
        if (str_starts_with($src, '/assets/')) {
            return file_exists($this->publicPath . $src);
        }

        // Absolute /content/ path
        if (str_starts_with($src, '/content/')) {
            return file_exists($this->basePath . $src);
        }

        return false;
    }

    /**
     * Get the filesystem path for an image
     */
    public function getFilePath(string $src): ?string
    {
        $src = trim($src);

        // Relative path
        if (str_starts_with($src, './') && $this->bundlePath !== null) {
            $filename = substr($src, 2);
            $path = $this->bundlePath . '/' . $filename;
            return file_exists($path) ? $path : null;
        }

        // Absolute /assets/ path
        if (str_starts_with($src, '/assets/')) {
            $path = $this->publicPath . $src;
            return file_exists($path) ? $path : null;
        }

        // Absolute /content/ path
        if (str_starts_with($src, '/content/')) {
            $path = $this->basePath . $src;
            return file_exists($path) ? $path : null;
        }

        return null;
    }

    /**
     * Find hero image in bundle (auto-detect)
     *
     * Looks for: hero.webp, hero.jpg, hero.png in bundle directory
     */
    public function findHeroInBundle(): ?string
    {
        if ($this->bundlePath === null) {
            return null;
        }

        $extensions = ['webp', 'jpg', 'jpeg', 'png'];

        foreach ($extensions as $ext) {
            $path = $this->bundlePath . '/hero.' . $ext;
            if (file_exists($path)) {
                return './hero.' . $ext;
            }
        }

        return null;
    }

    /**
     * List all images in bundle directory
     */
    public function listBundleImages(): array
    {
        if ($this->bundlePath === null || !is_dir($this->bundlePath)) {
            return [];
        }

        $images = [];
        $extensions = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg'];

        foreach ($extensions as $ext) {
            foreach (glob($this->bundlePath . '/*.' . $ext) as $file) {
                $images[] = './' . basename($file);
            }
        }

        return $images;
    }
}
