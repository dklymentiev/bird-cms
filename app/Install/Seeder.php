<?php

declare(strict_types=1);

namespace App\Install;

/**
 * Optional demo content copier used by the wizard.
 *
 * Mirrors examples/seed/{content,uploads,config} into the live site so a
 * fresh install renders something coherent (a page, a few articles, menu,
 * categories) instead of an empty dashboard. Idempotent: existing files
 * are never overwritten.
 *
 * Phase 1 ships an empty `examples/seed/` -- the directory exists but has
 * nothing to copy, so run() is a successful no-op. Phase 3 (alpha.17) lands
 * the actual demo content; no code changes are needed here when it does.
 */
final class Seeder
{
    public function __construct(
        private string $siteRoot,
        private string $seedRoot
    ) {}

    /**
     * Copy seed content into the site.
     *
     * @return list<string> Relative paths of files actually copied (for the
     *   wizard's success summary).
     */
    public function run(): array
    {
        if (!is_dir($this->seedRoot)) {
            return [];
        }

        $copied = [];

        // Tree copies for content + uploads.
        foreach (['content', 'uploads'] as $sub) {
            $src = $this->seedRoot . '/' . $sub;
            if (!is_dir($src)) {
                continue;
            }
            $copied = array_merge(
                $copied,
                $this->copyTree($src, $this->siteRoot . '/' . $sub, $sub)
            );
        }

        // Single config files (categories.php, authors.php, menu.php). Don't
        // recurse -- the rest of config/ lives in the engine, not per-site state.
        $configSeed = $this->seedRoot . '/config';
        if (is_dir($configSeed)) {
            foreach (['categories.php', 'authors.php', 'menu.php'] as $name) {
                $src = $configSeed . '/' . $name;
                $dst = $this->siteRoot . '/config/' . $name;
                if (!is_file($src) || is_file($dst)) {
                    continue;
                }
                if (!is_dir(dirname($dst))) {
                    mkdir(dirname($dst), 0755, true);
                }
                if (copy($src, $dst)) {
                    @chmod($dst, 0644);
                    $copied[] = 'config/' . $name;
                }
            }
        }

        return $copied;
    }

    /**
     * @return list<string> Relative paths copied (under $relPrefix).
     */
    private function copyTree(string $src, string $dst, string $relPrefix): array
    {
        $copied = [];

        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $node) {
            $rel = substr($node->getPathname(), strlen($src) + 1);
            $rel = str_replace('\\', '/', $rel);
            $target = $dst . '/' . $rel;

            if ($node->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
                continue;
            }

            // Idempotency: never clobber a file the user (or a previous run)
            // already created.
            if (is_file($target)) {
                continue;
            }

            if (copy($node->getPathname(), $target)) {
                @chmod($target, 0644);
                $copied[] = $relPrefix . '/' . $rel;
            }
        }

        return $copied;
    }
}
