<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Temp content directory factory.
 *
 * Each test that needs a content tree calls TempContent::make('articles')
 * in setUp() and TempContent::cleanup() in tearDown(). Directories live
 * under tests/fixtures/tmp/ so a failed test leaves debuggable artifacts
 * inside the repo rather than scattered around %TEMP%.
 */
final class TempContent
{
    /** @var list<string> */
    private static array $created = [];

    public static function make(string $label = 'content'): string
    {
        $base = BIRD_TEST_ROOT . '/fixtures/tmp';
        if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) {
            throw new \RuntimeException('Failed to create tmp dir: ' . $base);
        }
        $dir = $base . '/' . $label . '-' . bin2hex(random_bytes(4));
        if (!mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Failed to create temp content dir: ' . $dir);
        }
        self::$created[] = $dir;
        return $dir;
    }

    public static function cleanup(): void
    {
        foreach (self::$created as $dir) {
            self::rrmdir($dir);
        }
        self::$created = [];
    }

    private static function rrmdir(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        $items = @scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::rrmdir($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}
