<?php
/** @noinspection PhpUnused — included from doctor.php */
declare(strict_types=1);

if ($jsonOut) {
    $status = $failed > 0 ? 'critical' : ($warned > 0 ? 'warn' : 'ok');
    echo json_encode([
        'site'    => $site,
        'mode'    => $mode,
        'status'  => $status,
        'passed'  => $passed,
        'warned'  => $warned,
        'failed'  => $failed,
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "=== Summary ===\n";
echo sprintf("  Passed   : %d\n", $passed);
echo sprintf("  Warnings : %d\n", $warned);
echo sprintf("  Errors   : %d\n", $failed);
if ($failed > 0) {
    echo "  Status   : CRITICAL — do NOT push destructive changes to this site\n";
} elseif ($warned > 0) {
    echo "  Status   : WARN — site is up but has structural drift\n";
} else {
    echo "  Status   : OK\n";
}
