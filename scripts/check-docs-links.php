<?php

declare(strict_types=1);

/**
 * Walk every doc reachable from the admin docs viewer, render through
 * the same Markdown -> rewriteLinks pipeline, and verify each rewritten
 * link still resolves on disk. Reports broken links per file.
 *
 * Run from the project root:
 *   php scripts/check-docs-links.php
 *
 * Exit 0 = clean. Exit 1 = at least one broken link.
 */

require __DIR__ . '/../bootstrap.php';

use App\Admin\DocsController;
use App\Support\Markdown;

$projectRoot = dirname(__DIR__);

$visited = [];
$broken  = [];

// Seed from every entry in GROUPS plus the recipes README (linked from a
// few docs but not in GROUPS itself).
$queue = [];
foreach (DocsController::GROUPS as $items) {
    foreach ($items as $path) {
        $queue[] = $path;
    }
}
$queue[] = 'docs/recipes/README.md';
$queue[] = 'docs/perf/html-cache.md';
$queue[] = 'docs/perf/benchmarks/README.md';
$queue[] = 'docs/screenshots/README.md';
$queue[] = 'docs/brand/README.md';

while ($queue) {
    $rel = array_shift($queue);
    if (isset($visited[$rel])) {
        continue;
    }
    $visited[$rel] = true;

    $absolute = DocsController::safeResolve($rel, $projectRoot);
    if ($absolute === null || !is_file($absolute)) {
        // safeResolve refused -- can't audit further from here.
        continue;
    }

    $markdown = (string) file_get_contents($absolute);
    $html     = Markdown::toHtml($markdown);
    $html     = DocsController::rewriteLinks($html, $rel);

    // Extract every /admin/docs/... href and src and check it resolves.
    preg_match_all('#(?:href|src)="(/admin/docs/(?:asset/)?[^"#]+)#i', $html, $m);
    foreach ($m[1] as $url) {
        // Strip /admin/docs/ or /admin/docs/asset/, urldecode -> rel path.
        $asset = false;
        if (str_starts_with($url, '/admin/docs/asset/')) {
            $encoded = substr($url, strlen('/admin/docs/asset/'));
            $asset   = true;
        } else {
            $encoded = substr($url, strlen('/admin/docs/'));
        }
        $target = rawurldecode($encoded);
        if ($target === '') {
            continue;
        }

        $resolved = DocsController::safeResolve($target, $projectRoot);
        if ($resolved === null || !is_file($resolved)) {
            $broken[] = [
                'from'   => $rel,
                'target' => $target,
                'url'    => $url,
                'asset'  => $asset,
            ];
            continue;
        }

        // Asset route also enforces a mime whitelist (jpg/png/gif/webp/svg/pdf).
        if ($asset) {
            $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
            $okExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'];
            if (!in_array($ext, $okExts, true)) {
                $broken[] = [
                    'from'   => $rel,
                    'target' => $target,
                    'url'    => $url,
                    'asset'  => true,
                    'note'   => 'asset mime whitelist excludes .' . $ext,
                ];
                continue;
            }
        }

        // Crawl onward only into markdown docs.
        if (!$asset && str_ends_with($resolved, '.md')) {
            $queue[] = $target;
        }
    }
}

if (empty($broken)) {
    echo "OK: " . count($visited) . " docs checked, no broken links.\n";
    exit(0);
}

echo "BROKEN LINKS in admin docs viewer:\n\n";
$grouped = [];
foreach ($broken as $b) {
    $grouped[$b['from']][] = $b;
}
foreach ($grouped as $from => $items) {
    echo "  {$from}\n";
    foreach ($items as $b) {
        $note = isset($b['note']) ? " ({$b['note']})" : '';
        echo "    -> {$b['target']}{$note}\n";
    }
    echo "\n";
}
echo "Total: " . count($broken) . " broken across " . count($grouped) . " files.\n";
exit(1);
