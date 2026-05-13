<?php
/**
 * Standalone test for App\Support\RateLimit.
 *
 * No PHPUnit dep — Bird ships a shell smoke-test harness; this script
 * follows the same convention. Run:
 *
 *   php tests/standalone/RateLimitTest.php
 *
 * Exits 0 on pass, 1 on fail with a descriptive line per failure.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/RateLimit.php';

use App\Support\RateLimit;

$failures = [];
$pass = 0;

function assertTrue($cond, string $msg, array &$failures, int &$pass): void
{
    if ($cond) { $pass++; return; }
    $failures[] = $msg;
}

// In-memory PDO for isolated tests
function freshDb(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

$_ENV['RATE_LIMIT_ENABLED'] = 'true';

// Test 1: disabled flag returns allowed always
{
    $_ENV['RATE_LIMIT_ENABLED'] = 'false';
    $rl = new RateLimit(freshDb(), ['lead' => [60 => 1]]);
    $v = $rl->hit('lead', '1.2.3.4');
    assertTrue($v['allowed'] === true, 'T1: disabled flag must allow', $failures, $pass);
    $v = $rl->hit('lead', '1.2.3.4'); // would be denied if enabled
    assertTrue($v['allowed'] === true, 'T1: disabled flag must continue allowing', $failures, $pass);
    $_ENV['RATE_LIMIT_ENABLED'] = 'true';
}

// Test 2: per-minute limit blocks at N+1
{
    $rl = new RateLimit(freshDb(), ['lead' => [60 => 3]]);
    foreach ([1, 2, 3] as $i) {
        $v = $rl->hit('lead', '1.2.3.4');
        assertTrue($v['allowed'] === true, "T2: req $i should pass", $failures, $pass);
    }
    $v = $rl->hit('lead', '1.2.3.4');
    assertTrue($v['allowed'] === false, 'T2: req 4 should be denied', $failures, $pass);
    assertTrue($v['retry_after'] > 0 && $v['retry_after'] <= 60, "T2: retry_after in [1,60], got {$v['retry_after']}", $failures, $pass);
}

// Test 3: different IPs have separate buckets
{
    $rl = new RateLimit(freshDb(), ['lead' => [60 => 1]]);
    $v1 = $rl->hit('lead', 'ip-A');
    assertTrue($v1['allowed'] === true, 'T3: ip-A first hit allowed', $failures, $pass);
    $v2 = $rl->hit('lead', 'ip-B');
    assertTrue($v2['allowed'] === true, 'T3: ip-B first hit allowed (separate bucket)', $failures, $pass);
    $v3 = $rl->hit('lead', 'ip-A');
    assertTrue($v3['allowed'] === false, 'T3: ip-A second hit denied', $failures, $pass);
    $v4 = $rl->hit('lead', 'ip-B');
    assertTrue($v4['allowed'] === false, 'T3: ip-B second hit denied', $failures, $pass);
}

// Test 4: different endpoints have separate buckets
{
    $rl = new RateLimit(freshDb(), ['lead' => [60 => 1], 'subscribe' => [60 => 1]]);
    $rl->hit('lead', '1.2.3.4');
    $v = $rl->hit('subscribe', '1.2.3.4');
    assertTrue($v['allowed'] === true, 'T4: subscribe is independent of lead bucket', $failures, $pass);
}

// Test 5: unknown endpoint fails open
{
    $rl = new RateLimit(freshDb(), ['lead' => [60 => 1]]);
    $v = $rl->hit('unknown_endpoint', '1.2.3.4');
    assertTrue($v['allowed'] === true, 'T5: unknown endpoint fails open', $failures, $pass);
}

// Test 6: remaining decrements per allowed request
{
    $rl = new RateLimit(freshDb(), ['lead' => [60 => 5]]);
    $v = $rl->hit('lead', '1.2.3.4');
    assertTrue($v['remaining'] === 4, "T6: remaining=4 after first of 5, got {$v['remaining']}", $failures, $pass);
    $v = $rl->hit('lead', '1.2.3.4');
    assertTrue($v['remaining'] === 3, "T6: remaining=3 after second, got {$v['remaining']}", $failures, $pass);
}

// Test 7: dual-window — most restrictive wins
{
    // 5/min, 10/day. After 5 requests in a minute, denied even though day quota has 5 left.
    $rl = new RateLimit(freshDb(), ['lead' => [60 => 5, 86400 => 10]]);
    for ($i = 0; $i < 5; $i++) $rl->hit('lead', '1.2.3.4');
    $v = $rl->hit('lead', '1.2.3.4');
    assertTrue($v['allowed'] === false, 'T7: 6th in minute denied even with day quota left', $failures, $pass);
}

// Report
echo "RateLimit tests: $pass passed, " . count($failures) . " failed\n";
foreach ($failures as $msg) {
    echo "  FAIL: $msg\n";
}
exit(count($failures) === 0 ? 0 : 1);
