<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

/**
 * Pin SITE_ROOT to a stable sys_get_temp_dir() child for the whole
 * PHPUnit process.
 *
 * SITE_ROOT is a runtime constant; once defined it sticks. Tests that
 * use TempContent::make() can't safely point SITE_ROOT at a per-test
 * dir, because TempContent::cleanup() rmdirs the directory between
 * tests, leaving SITE_ROOT pointing at a missing path for subsequent
 * tests in the same suite.
 *
 * ensure() defines SITE_ROOT exactly once on the first call (creating
 * the directory) and is a no-op afterwards. Callers should also
 * mkdir the storage/uploads subdirs they need; this helper only
 * owns SITE_ROOT itself.
 */
final class TestSiteRoot
{
    public static function ensure(): void
    {
        if (defined('SITE_ROOT')) {
            return;
        }
        $dir = sys_get_temp_dir() . '/bird-cms-api-test-root-' . bin2hex(random_bytes(4));
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create test site root: ' . $dir);
        }
        define('SITE_ROOT', $dir);
    }
}
