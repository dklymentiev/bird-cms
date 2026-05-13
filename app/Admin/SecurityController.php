<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Security Controller
 *
 * Handles blacklist, sandbox, and site security checks
 */
final class SecurityController extends Controller
{
    private string $storagePath;
    private string $dbPath;
    private string $blacklistFile;

    public function __construct()
    {
        parent::__construct();
        $this->storagePath = defined('SITE_STORAGE_PATH') ? SITE_STORAGE_PATH : dirname(__DIR__, 2) . '/storage';
        $this->dbPath = $this->storagePath . '/analytics/visits.db';
        $this->blacklistFile = $this->storagePath . '/analytics/blacklist.txt';
    }

    /**
     * Blacklist management page
     */
    public function blacklist(): void
    {
        $this->requireAuth();

        $entries = $this->parseBlacklist();
        $autoblockRuns = $this->parseAutoblockLog();

        $this->render('security/blacklist', [
            'entries' => $entries,
            'autoblockRuns' => $autoblockRuns,
            'totalBlocked' => count($entries),
        ]);
    }

    /**
     * Remove IP from blacklist
     */
    public function unblock(): void
    {
        $this->requireAuth();

        $ip = $this->post('ip');
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->flash('error', 'Invalid IP address');
            $this->redirect('/admin/blacklist');
            return;
        }

        if (file_exists($this->blacklistFile)) {
            $lines = file($this->blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $newLines = [];
            foreach ($lines as $line) {
                $parts = explode('|', $line);
                if (trim($parts[0]) !== $ip) {
                    $newLines[] = $line;
                }
            }
            file_put_contents($this->blacklistFile, implode("\n", $newLines) . "\n");
        }

        $this->flash('success', "IP {$ip} removed from blacklist");
        $this->redirect('/admin/blacklist');
    }

    /**
     * Sandbox review page
     */
    public function sandbox(): void
    {
        $this->requireAuth();

        if (!file_exists($this->dbPath)) {
            $this->render('security/sandbox', [
                'error' => 'Database not found.',
                'entries' => [],
                'stats' => [],
            ]);
            return;
        }

        $db = new \PDO('sqlite:' . $this->dbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $filter = $this->get('filter', 'pending');
        $page = max(1, (int)$this->get('page', 1));
        $perPage = 50;

        // Get sandbox entries
        $condition = match ($filter) {
            'bot' => "verdict = 'bot'",
            'human' => "verdict = 'human'",
            default => "verdict = 'pending'",
        };

        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare("
            SELECT id, ip, user_agent, first_seen, last_seen, total_requests as visit_count, verdict
            FROM sandbox
            WHERE {$condition}
            ORDER BY total_requests DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Count totals
        $stmt = $db->query("SELECT verdict, COUNT(*) as cnt FROM sandbox GROUP BY verdict");
        $stats = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $stats[$row['verdict']] = $row['cnt'];
        }

        $total = $stats[$filter] ?? 0;
        $totalPages = (int)ceil($total / $perPage);

        $this->render('security/sandbox', [
            'filter' => $filter,
            'entries' => $entries,
            'stats' => $stats,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * Mark sandbox entry as bot or human
     */
    public function sandboxVerdict(): void
    {
        $this->requireAuth();

        $id = $this->post('id');
        $verdict = $this->post('verdict'); // 'bot' or 'human'

        if (!$id || !in_array($verdict, ['bot', 'human'])) {
            $this->flash('error', 'Invalid request');
            $this->redirect('/admin/sandbox');
            return;
        }

        $db = new \PDO('sqlite:' . $this->dbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Update sandbox verdict
        $stmt = $db->prepare("UPDATE sandbox SET verdict = ? WHERE id = ?");
        $stmt->execute([$verdict, $id]);

        // Apply to visits
        $stmt = $db->prepare("SELECT ip, user_agent FROM sandbox WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $isBot = $verdict === 'bot' ? 1 : 0;
            if ($row['user_agent']) {
                $stmt = $db->prepare("UPDATE visits SET is_bot = ? WHERE ip = ? AND user_agent = ?");
                $stmt->execute([$isBot, $row['ip'], $row['user_agent']]);
            } else {
                $stmt = $db->prepare("UPDATE visits SET is_bot = ? WHERE ip = ? AND (user_agent IS NULL OR user_agent = '')");
                $stmt->execute([$isBot, $row['ip']]);
            }
        }

        $this->flash('success', "Marked as {$verdict}");
        $this->redirect('/admin/sandbox');
    }

    /**
     * Mark all pending as bot
     */
    public function sandboxBulkBot(): void
    {
        $this->requireAuth();

        $db = new \PDO('sqlite:' . $this->dbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $db->exec("UPDATE sandbox SET verdict = 'bot' WHERE verdict = 'pending'");

        // Apply to visits
        $stmt = $db->query("SELECT ip, user_agent FROM sandbox WHERE verdict = 'bot'");
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($row['user_agent']) {
                $upd = $db->prepare("UPDATE visits SET is_bot = 1 WHERE ip = ? AND user_agent = ?");
                $upd->execute([$row['ip'], $row['user_agent']]);
            } else {
                $upd = $db->prepare("UPDATE visits SET is_bot = 1 WHERE ip = ? AND (user_agent IS NULL OR user_agent = '')");
                $upd->execute([$row['ip']]);
            }
        }

        $this->flash('success', 'All pending marked as bots');
        $this->redirect('/admin/sandbox');
    }

    /**
     * Blacklist IP from sandbox
     */
    public function sandboxBlacklist(): void
    {
        $this->requireAuth();

        $id = $this->post('id');
        if (!$id) {
            $this->flash('error', 'Invalid request');
            $this->redirect('/admin/sandbox');
            return;
        }

        $db = new \PDO('sqlite:' . $this->dbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Get IP
        $stmt = $db->prepare("SELECT ip FROM sandbox WHERE id = ?");
        $stmt->execute([$id]);
        $ip = $stmt->fetchColumn();

        if ($ip) {
            // Add to blacklist
            $existing = file_exists($this->blacklistFile)
                ? array_filter(array_map('trim', file($this->blacklistFile)))
                : [];
            $ipList = array_map(fn($l) => explode('|', $l)[0], $existing);

            if (!in_array($ip, $ipList)) {
                $entry = "{$ip} | manual | Blacklisted from sandbox | " . date('Y-m-d');
                file_put_contents($this->blacklistFile, $entry . "\n", FILE_APPEND);
            }

            // Mark as bot and remove from sandbox
            $stmt = $db->prepare("UPDATE visits SET is_bot = 1 WHERE ip = ?");
            $stmt->execute([$ip]);
            $stmt = $db->prepare("DELETE FROM sandbox WHERE id = ?");
            $stmt->execute([$id]);

            $this->flash('success', "IP {$ip} blacklisted");
        }

        $this->redirect('/admin/sandbox');
    }

    /**
     * Site health check page
     */
    public function sitecheck(): void
    {
        $this->requireAuth();

        $cacheFile = $this->storagePath . '/cache/security-scan-results.json';
        $results = null;

        if (file_exists($cacheFile)) {
            $results = json_decode(file_get_contents($cacheFile), true);
        }

        $this->render('security/sitecheck', [
            'results' => $results,
        ]);
    }

    /**
     * Run security scan
     */
    public function runSitecheck(): void
    {
        $this->requireAuth();

        $baseUrl = $this->post('base_url', config('site_url'));

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->json(['success' => false, 'error' => 'Invalid URL'], 400);
            return;
        }

        $scriptsPath = defined('SITE_ROOT') ? SITE_ROOT . '/scripts' : dirname(__DIR__, 2) . '/scripts';
        $cmd = "php " . escapeshellarg($scriptsPath . "/security-scan.php") .
               " --base-url=" . escapeshellarg($baseUrl) . " --json 2>&1";

        $output = [];
        exec($cmd, $output);
        $jsonStr = implode("", $output);
        $results = json_decode($jsonStr, true);

        if ($results) {
            $cacheFile = $this->storagePath . '/cache/security-scan-results.json';
            file_put_contents($cacheFile, json_encode($results, JSON_PRETTY_PRINT));
            $this->json(['success' => true, 'results' => $results]);
        } else {
            $this->json(['success' => false, 'error' => 'Failed to parse results', 'raw' => $jsonStr], 500);
        }
    }

    /**
     * Link checker page
     */
    public function links(): void
    {
        $this->requireAuth();

        $cacheFile = $this->storagePath . '/cache/link-check-results.json';
        $results = null;

        if (file_exists($cacheFile)) {
            $results = json_decode(file_get_contents($cacheFile), true);
        }

        $this->render('security/links', [
            'results' => $results,
        ]);
    }

    /**
     * Run link check
     */
    public function runLinkCheck(): void
    {
        $this->requireAuth();

        $scriptsPath = defined('SITE_ROOT') ? SITE_ROOT . '/scripts' : dirname(__DIR__, 2) . '/scripts';
        $siteUrl = config('site_url') ?: throw new \RuntimeException('site_url not configured');
        $parsedUrl = parse_url($siteUrl);
        $host = $parsedUrl['host'] ?? 'localhost';
        // Use localhost to avoid external network round-trip, pass Host header
        $baseUrl = 'http://localhost';
        $checkImages = $this->post('check_images') ? ' --check-images' : '';
        $cmd = "php " . escapeshellarg($scriptsPath . "/check-links.php") .
               " --base-url=" . escapeshellarg($baseUrl) .
               " --host=" . escapeshellarg($host) .
               " --json{$checkImages} 2>&1";

        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        // Try to parse JSON output first
        $outputStr = implode("\n", $output);
        if (preg_match('/===JSON_START===\s*(.*?)\s*===JSON_END===/s', $outputStr, $matches)) {
            $results = json_decode($matches[1], true);
            if ($results) {
                $results['timestamp'] = date('Y-m-d H:i:s');
                $cacheFile = $this->storagePath . '/cache/link-check-results.json';
                file_put_contents($cacheFile, json_encode($results, JSON_PRETTY_PRINT));
                $this->json(['success' => true, 'results' => $results]);
                return;
            }
        }

        // Fallback to legacy parsing
        $results = $this->parseLinkCheckOutput($output);
        $results['timestamp'] = date('Y-m-d H:i:s');

        $cacheFile = $this->storagePath . '/cache/link-check-results.json';
        file_put_contents($cacheFile, json_encode($results, JSON_PRETTY_PRINT));

        $this->json(['success' => true, 'results' => $results]);
    }

    private function parseBlacklist(): array
    {
        if (!file_exists($this->blacklistFile)) {
            return [];
        }

        $entries = [];
        $lines = file($this->blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;

            $parts = array_map('trim', explode('|', $line));
            $ip = $parts[0] ?? '';

            if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;

            $entries[] = [
                'ip' => $ip,
                'requests' => $parts[1] ?? '',
                'reason' => $parts[2] ?? '',
                'date' => $parts[3] ?? '',
            ];
        }

        return array_reverse($entries);
    }

    private function parseAutoblockLog(int $lines = 200): array
    {
        $logFile = config('logs.autoblock_log') ?: throw new \RuntimeException('logs.autoblock_log not configured');
        if (!file_exists($logFile)) {
            return [];
        }

        $handle = @fopen($logFile, 'r');
        if (!$handle) return [];

        $allLines = [];
        while (($line = fgets($handle)) !== false) {
            $allLines[] = trim($line);
        }
        fclose($handle);

        $allLines = array_slice($allLines, -$lines);

        $runs = [];
        $currentRun = null;

        foreach ($allLines as $line) {
            if (str_starts_with($line, '=== Auto-Blacklist')) {
                if ($currentRun) {
                    $runs[] = $currentRun;
                }
                $currentRun = [
                    'header' => $line,
                    'time' => null,
                    'blocked' => 0,
                    'ips' => [],
                ];
            } elseif ($currentRun !== null) {
                if (preg_match('/Analyzing since: (.+)/', $line, $m)) {
                    $currentRun['time'] = $m[1];
                }
                if (preg_match('/Added (\d+) IPs/', $line, $m)) {
                    $currentRun['blocked'] = (int)$m[1];
                }
                if (preg_match('/^([\d.:a-f]+)\s+\|\s+(\d+)\s+\|\s+(.+)$/i', $line, $m)) {
                    $currentRun['ips'][] = [
                        'ip' => trim($m[1]),
                        'requests' => trim($m[2]),
                        'reason' => trim($m[3]),
                    ];
                }
            }
        }

        if ($currentRun) {
            $runs[] = $currentRun;
        }

        return array_reverse($runs);
    }

    private function parseLinkCheckOutput(array $output): array
    {
        $results = [
            'broken' => [],
            'soft404' => [],
            'summary' => [],
        ];

        $section = '';
        foreach ($output as $line) {
            if (str_contains($line, 'Pages crawled:')) {
                preg_match('/Pages crawled: (\d+)/', $line, $m);
                $results['summary']['crawled'] = (int)($m[1] ?? 0);
            }
            if (str_contains($line, 'Unique internal links:')) {
                preg_match('/Unique internal links: (\d+)/', $line, $m);
                $results['summary']['internal'] = (int)($m[1] ?? 0);
            }
            if (str_contains($line, 'External links:')) {
                preg_match('/External links: (\d+)/', $line, $m);
                $results['summary']['external'] = (int)($m[1] ?? 0);
            }
            if (str_contains($line, 'BROKEN LINKS')) {
                $section = 'broken';
            } elseif (str_contains($line, 'SOFT 404')) {
                $section = 'soft404';
            } elseif (str_starts_with(trim($line), '[') && $section === 'broken') {
                preg_match('/\[([^\]]+)\]\s+(.+)/', trim($line), $m);
                if ($m) {
                    $results['broken'][] = ['status' => $m[1], 'url' => $m[2], 'from' => ''];
                }
            } elseif (str_starts_with(trim($line), 'Found on:') && $section) {
                $from = trim(str_replace('Found on:', '', $line));
                if ($section === 'broken' && !empty($results['broken'])) {
                    $results['broken'][count($results['broken']) - 1]['from'] = $from;
                } elseif ($section === 'soft404' && !empty($results['soft404'])) {
                    $results['soft404'][count($results['soft404']) - 1]['from'] = $from;
                }
            } elseif (str_starts_with(trim($line), '/') && $section === 'soft404') {
                $results['soft404'][] = ['url' => trim($line), 'from' => ''];
            }
        }

        return $results;
    }

    /**
     * PageSpeed Insights page
     */
    public function pagespeed(): void
    {
        $this->requireAuth();

        $cacheFile = $this->storagePath . '/cache/pagespeed-results.json';
        $results = null;

        if (file_exists($cacheFile)) {
            $results = json_decode(file_get_contents($cacheFile), true);
        }

        $this->render('security/pagespeed', [
            'results' => $results,
        ]);
    }

    /**
     * Run PageSpeed Insights audit
     */
    public function runPagespeed(): void
    {
        $this->requireAuth();

        $url = $this->post('url', config('site_url'));
        $strategy = $this->post('strategy', 'desktop');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->json(['success' => false, 'error' => 'Invalid URL'], 400);
            return;
        }

        $apiKey = $_ENV['GOOGLE_PAGESPEED_API_KEY'] ?? $_SERVER['GOOGLE_PAGESPEED_API_KEY'] ?? '';

        $categories = ['performance', 'accessibility', 'best-practices', 'seo'];
        $categoryParams = implode('&', array_map(fn($c) => 'category=' . urlencode($c), $categories));

        $params = [
            'url' => $url,
            'strategy' => $strategy,
        ];

        if ($apiKey) {
            $params['key'] = $apiKey;
        }

        $apiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query($params) . '&' . $categoryParams;

        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            $this->json(['success' => false, 'error' => 'Failed to connect to PageSpeed API'], 500);
            return;
        }

        $data = json_decode($response, true);

        if (!$data || isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Unknown API error';
            $this->json(['success' => false, 'error' => $errorMsg], 500);
            return;
        }

        $lighthouse = $data['lighthouseResult'] ?? [];
        $categories = $lighthouse['categories'] ?? [];
        $audits = $lighthouse['audits'] ?? [];

        $results = [
            'url' => $url,
            'strategy' => $strategy,
            'fetchTime' => $data['analysisUTCTimestamp'] ?? date('c'),
            'scores' => [
                'performance' => (int)(($categories['performance']['score'] ?? 0) * 100),
                'accessibility' => (int)(($categories['accessibility']['score'] ?? 0) * 100),
                'best-practices' => (int)(($categories['best-practices']['score'] ?? 0) * 100),
                'seo' => (int)(($categories['seo']['score'] ?? 0) * 100),
            ],
            'metrics' => [
                'fcp' => $audits['first-contentful-paint']['displayValue'] ?? '-',
                'lcp' => $audits['largest-contentful-paint']['displayValue'] ?? '-',
                'tbt' => $audits['total-blocking-time']['displayValue'] ?? '-',
                'cls' => $audits['cumulative-layout-shift']['displayValue'] ?? '-',
                'si' => $audits['speed-index']['displayValue'] ?? '-',
            ],
            'opportunities' => [],
            'diagnostics' => [],
        ];

        foreach ($audits as $key => $audit) {
            if (($audit['score'] ?? 1) < 1 && isset($audit['details']['type'])) {
                $item = [
                    'id' => $key,
                    'title' => $audit['title'] ?? $key,
                    'description' => $audit['description'] ?? '',
                    'score' => $audit['score'] ?? 0,
                    'displayValue' => $audit['displayValue'] ?? '',
                ];

                if ($audit['details']['type'] === 'opportunity') {
                    $results['opportunities'][] = $item;
                } elseif ($audit['details']['type'] === 'table' || $audit['details']['type'] === 'list') {
                    $results['diagnostics'][] = $item;
                }
            }
        }

        usort($results['opportunities'], fn($a, $b) => ($a['score'] ?? 0) <=> ($b['score'] ?? 0));
        usort($results['diagnostics'], fn($a, $b) => ($a['score'] ?? 0) <=> ($b['score'] ?? 0));

        $results['opportunities'] = array_slice($results['opportunities'], 0, 10);
        $results['diagnostics'] = array_slice($results['diagnostics'], 0, 10);

        $cacheDir = $this->storagePath . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cacheDir . '/pagespeed-results.json', json_encode($results, JSON_PRETTY_PRINT));

        // Save to history
        $historyDir = $this->storagePath . '/pagespeed-history';
        if (!is_dir($historyDir)) {
            mkdir($historyDir, 0755, true);
        }
        $historyFile = $historyDir . '/' . date('Y-m-d_H-i-s') . '_' . $strategy . '.json';
        file_put_contents($historyFile, json_encode($results, JSON_PRETTY_PRINT));

        $this->json(['success' => true, 'results' => $results]);
    }
}
