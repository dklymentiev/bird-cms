#!/usr/bin/env php
<?php
/**
 * Legacy Files Audit Script
 *
 * Checks for obvious legacy/orphaned files:
 * - Engine backup directories
 * - Temp/backup files (.bak, .old, .tmp, etc.)
 * - OS junk files (.DS_Store, Thumbs.db)
 * - Orphaned images (not referenced anywhere)
 * - Empty directories
 * - Old/large log files
 *
 * Usage: php scripts/audit-legacy.php [site_path] [--fix]
 *
 * @version 1.0.0 (2025-12-14)
 */

const VERSION = '1.0.0';

$sitePath = $argv[1] ?? getcwd();
$autoFix = in_array('--fix', $argv);

if (!is_dir($sitePath)) {
    echo "Error: Directory not found: $sitePath\n";
    exit(1);
}

$sitePath = realpath($sitePath);
$siteName = basename($sitePath);

echo "=== Legacy Audit: $siteName ===\n";
echo "Path: $sitePath\n";
echo "Mode: " . ($autoFix ? "FIX (will delete)" : "REPORT ONLY") . "\n\n";

$issues = [
    'engine_backups' => [],
    'temp_files' => [],
    'junk_files' => [],
    'orphan_images' => [],
    'empty_dirs' => [],
    'large_logs' => [],
];

$stats = [
    'files_checked' => 0,
    'issues_found' => 0,
    'bytes_reclaimable' => 0,
];

// ============================================
// 1. Engine Backup Directories
// ============================================
echo "Checking engine backups...\n";

$backupDirs = glob($sitePath . '/.engine-backup-*', GLOB_ONLYDIR);
foreach ($backupDirs as $dir) {
    $size = getDirectorySize($dir);
    $issues['engine_backups'][] = [
        'path' => $dir,
        'size' => $size,
        'relative' => str_replace($sitePath . '/', '', $dir),
    ];
    $stats['bytes_reclaimable'] += $size;
}

// ============================================
// 2. Temp/Backup Files
// ============================================
echo "Checking temp/backup files...\n";

$tempPatterns = ['*.bak', '*.old', '*.tmp', '*~', '*.swp', '*.swo', '*.orig', '*.backup'];
$tempFiles = [];

foreach ($tempPatterns as $pattern) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sitePath, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
            // Skip vendor/node_modules
            if (strpos($file->getPathname(), '/vendor/') !== false) continue;
            if (strpos($file->getPathname(), '/node_modules/') !== false) continue;

            $size = $file->getSize();
            $issues['temp_files'][] = [
                'path' => $file->getPathname(),
                'size' => $size,
                'relative' => str_replace($sitePath . '/', '', $file->getPathname()),
            ];
            $stats['bytes_reclaimable'] += $size;
        }
    }
}

// ============================================
// 3. OS Junk Files
// ============================================
echo "Checking OS junk files...\n";

$junkFiles = ['.DS_Store', 'Thumbs.db', 'desktop.ini', '.Spotlight-V100', '.Trashes'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sitePath, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getFilename(), $junkFiles)) {
        $size = $file->getSize();
        $issues['junk_files'][] = [
            'path' => $file->getPathname(),
            'size' => $size,
            'relative' => str_replace($sitePath . '/', '', $file->getPathname()),
        ];
        $stats['bytes_reclaimable'] += $size;
    }
    $stats['files_checked']++;
}

// ============================================
// 4. Orphaned Images
// ============================================
echo "Checking orphaned images...\n";

$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
$imageDirs = [
    $sitePath . '/public/assets/images',
    $sitePath . '/public/assets/hero',
    $sitePath . '/content',
];

// Collect all text content to search for references
$textContent = '';
$textExtensions = ['php', 'md', 'html', 'css', 'js', 'yaml', 'yml', 'json'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sitePath, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $textExtensions)) {
            // Skip backups, vendor, node_modules, credentials
            $path = $file->getPathname();
            if (strpos($path, '.engine-backup') !== false) continue;
            if (strpos($path, '/vendor/') !== false) continue;
            if (strpos($path, '/node_modules/') !== false) continue;
            if (strpos($path, '.credentials') !== false) continue;
            if (!is_readable($path)) continue;

            $textContent .= file_get_contents($path) . "\n";
        }
    }
}

// Check each image
foreach ($imageDirs as $imageDir) {
    if (!is_dir($imageDir)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($imageDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $ext = strtolower($file->getExtension());
            if (in_array($ext, $imageExtensions)) {
                $filename = $file->getFilename();
                $relativePath = str_replace($sitePath . '/public', '', $file->getPathname());
                $relativePath2 = str_replace($sitePath . '/', '', $file->getPathname());

                // Check if image is referenced
                $isReferenced =
                    strpos($textContent, $filename) !== false ||
                    strpos($textContent, $relativePath) !== false ||
                    strpos($textContent, ltrim($relativePath, '/')) !== false;

                if (!$isReferenced) {
                    $size = $file->getSize();
                    $issues['orphan_images'][] = [
                        'path' => $file->getPathname(),
                        'size' => $size,
                        'relative' => $relativePath2,
                    ];
                    $stats['bytes_reclaimable'] += $size;
                }
            }
        }
    }
}

// ============================================
// 5. Empty Directories
// ============================================
echo "Checking empty directories...\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sitePath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $file) {
    if ($file->isDir()) {
        $path = $file->getPathname();

        // Skip special directories
        if (strpos($path, '/.git') !== false) continue;
        if (strpos($path, '/vendor/') !== false) continue;
        if (strpos($path, '/node_modules/') !== false) continue;

        // Check if empty (only . and ..)
        $files = array_diff(scandir($path), ['.', '..']);
        if (empty($files)) {
            $issues['empty_dirs'][] = [
                'path' => $path,
                'size' => 0,
                'relative' => str_replace($sitePath . '/', '', $path),
            ];
        }
    }
}

// ============================================
// 6. Large/Old Log Files
// ============================================
echo "Checking log files...\n";

$logDirs = [
    $sitePath . '/storage/logs',
    $sitePath . '/logs',
];

foreach ($logDirs as $logDir) {
    if (!is_dir($logDir)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($logDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size = $file->getSize();
            $age = time() - $file->getMTime();

            // Flag if > 10MB or > 30 days old
            if ($size > 10 * 1024 * 1024 || $age > 30 * 24 * 3600) {
                $issues['large_logs'][] = [
                    'path' => $file->getPathname(),
                    'size' => $size,
                    'age_days' => round($age / 86400),
                    'relative' => str_replace($sitePath . '/', '', $file->getPathname()),
                ];
                $stats['bytes_reclaimable'] += $size;
            }
        }
    }
}

// ============================================
// Report
// ============================================
echo "\n" . str_repeat("=", 60) . "\n";
echo "AUDIT RESULTS\n";
echo str_repeat("=", 60) . "\n\n";

$totalIssues = 0;

// Engine Backups
if (!empty($issues['engine_backups'])) {
    $count = count($issues['engine_backups']);
    $totalIssues += $count;
    echo "📦 ENGINE BACKUPS ($count)\n";
    foreach ($issues['engine_backups'] as $item) {
        echo "   " . $item['relative'] . " (" . formatBytes($item['size']) . ")\n";
    }
    echo "\n";
}

// Temp Files
if (!empty($issues['temp_files'])) {
    $count = count($issues['temp_files']);
    $totalIssues += $count;
    echo "📄 TEMP/BACKUP FILES ($count)\n";
    foreach ($issues['temp_files'] as $item) {
        echo "   " . $item['relative'] . " (" . formatBytes($item['size']) . ")\n";
    }
    echo "\n";
}

// Junk Files
if (!empty($issues['junk_files'])) {
    $count = count($issues['junk_files']);
    $totalIssues += $count;
    echo "🗑️  OS JUNK FILES ($count)\n";
    foreach ($issues['junk_files'] as $item) {
        echo "   " . $item['relative'] . "\n";
    }
    echo "\n";
}

// Orphan Images
if (!empty($issues['orphan_images'])) {
    $count = count($issues['orphan_images']);
    $totalIssues += $count;
    echo "🖼️  ORPHANED IMAGES ($count)\n";
    $shown = 0;
    foreach ($issues['orphan_images'] as $item) {
        if ($shown < 20) {
            echo "   " . $item['relative'] . " (" . formatBytes($item['size']) . ")\n";
            $shown++;
        }
    }
    if ($count > 20) {
        echo "   ... and " . ($count - 20) . " more\n";
    }
    echo "\n";
}

// Empty Directories
if (!empty($issues['empty_dirs'])) {
    $count = count($issues['empty_dirs']);
    $totalIssues += $count;
    echo "📁 EMPTY DIRECTORIES ($count)\n";
    foreach ($issues['empty_dirs'] as $item) {
        echo "   " . $item['relative'] . "/\n";
    }
    echo "\n";
}

// Large Logs
if (!empty($issues['large_logs'])) {
    $count = count($issues['large_logs']);
    $totalIssues += $count;
    echo "📋 LARGE/OLD LOG FILES ($count)\n";
    foreach ($issues['large_logs'] as $item) {
        echo "   " . $item['relative'] . " (" . formatBytes($item['size']) . ", " . $item['age_days'] . " days old)\n";
    }
    echo "\n";
}

// Summary
echo str_repeat("-", 60) . "\n";
echo "SUMMARY\n";
echo str_repeat("-", 60) . "\n";
echo "Files checked:      " . number_format($stats['files_checked']) . "\n";
echo "Issues found:       " . $totalIssues . "\n";
echo "Space reclaimable:  " . formatBytes($stats['bytes_reclaimable']) . "\n";

if ($totalIssues === 0) {
    echo "\n✅ No legacy issues found!\n";
} else {
    echo "\nRun with --fix to auto-delete (except orphan images)\n";
}

// ============================================
// Auto-fix
// ============================================
if ($autoFix && $totalIssues > 0) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "AUTO-FIX\n";
    echo str_repeat("=", 60) . "\n\n";

    $deleted = 0;
    $bytesFreed = 0;

    // Delete engine backups
    foreach ($issues['engine_backups'] as $item) {
        echo "Deleting: " . $item['relative'] . "... ";
        if (deleteDirectory($item['path'])) {
            echo "OK\n";
            $deleted++;
            $bytesFreed += $item['size'];
        } else {
            echo "FAILED\n";
        }
    }

    // Delete temp files
    foreach ($issues['temp_files'] as $item) {
        echo "Deleting: " . $item['relative'] . "... ";
        if (unlink($item['path'])) {
            echo "OK\n";
            $deleted++;
            $bytesFreed += $item['size'];
        } else {
            echo "FAILED\n";
        }
    }

    // Delete junk files
    foreach ($issues['junk_files'] as $item) {
        echo "Deleting: " . $item['relative'] . "... ";
        if (unlink($item['path'])) {
            echo "OK\n";
            $deleted++;
            $bytesFreed += $item['size'];
        } else {
            echo "FAILED\n";
        }
    }

    // Delete empty directories
    foreach ($issues['empty_dirs'] as $item) {
        echo "Removing: " . $item['relative'] . "/... ";
        if (rmdir($item['path'])) {
            echo "OK\n";
            $deleted++;
        } else {
            echo "FAILED\n";
        }
    }

    // Note: NOT auto-deleting orphan images (too risky)
    if (!empty($issues['orphan_images'])) {
        echo "\n⚠️  Orphan images NOT deleted (review manually)\n";
    }

    // Note: NOT auto-deleting logs (might be needed)
    if (!empty($issues['large_logs'])) {
        echo "⚠️  Log files NOT deleted (review manually)\n";
    }

    echo "\nDeleted: $deleted items\n";
    echo "Space freed: " . formatBytes($bytesFreed) . "\n";
}

// ============================================
// Helper Functions
// ============================================

function formatBytes(int $bytes): string {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function getDirectorySize(string $path): int {
    $size = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

function deleteDirectory(string $dir): bool {
    if (!is_dir($dir)) return false;

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    return rmdir($dir);
}
