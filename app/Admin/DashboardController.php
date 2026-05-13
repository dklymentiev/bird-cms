<?php

declare(strict_types=1);

namespace App\Admin;

use App\Content\ArticleRepository;
use App\Content\PageRepository;
use App\Support\EditLog;

/**
 * Admin dashboard controller
 *
 * Renders the post-login landing page. Layout (planner #1843):
 *
 *   1. Drafts -- articles + pages with status=draft (hidden if none).
 *   2. Scheduled -- items with status=scheduled or publish_at > now
 *      (hidden if none).
 *   3. Recent edits -- last 5 EditLog rows with source attribution
 *      (admin / mcp / api / unknown). Hidden if the log is empty.
 *
 * Below those, the rc.9 supporting cards (Site info, Quick links) stay
 * unchanged so an operator can still reach common surfaces in one click
 * regardless of the content state.
 *
 * The dashboard is intentionally mode-agnostic. ADMIN_MODE=minimal hides
 * extra sidebar surfaces; the cards on this view don't need to mirror
 * that gating -- showing "you have 3 drafts" with a Pages link still
 * helps even if Pages is hidden from the sidebar.
 */
final class DashboardController extends Controller
{
    private ArticleRepository $articles;
    private PageRepository $pages;

    public function __construct()
    {
        parent::__construct();
        $contentRoot = dirname(__DIR__, 2) . '/content';
        $this->articles = new ArticleRepository($contentRoot . '/articles');
        $this->pages = new PageRepository($contentRoot . '/pages');
    }

    /**
     * Show dashboard (or login form if not authenticated).
     */
    public function index(): void
    {
        // IP check is now handled in base Controller::__construct()

        // Show login form if not authenticated
        if (!$this->auth->check()) {
            $ip = $this->auth->getClientIp();
            $lockedOut = $this->auth->isLockedOut($ip);
            $lockoutRemaining = $lockedOut ? $this->auth->getLockoutRemaining($ip) : 0;

            $this->renderWithoutLayout('login', [
                'csrf' => $this->generateCsrf(),
                'error' => $this->getFlash(),
                'lockedOut' => $lockedOut,
                'lockoutRemaining' => $lockoutRemaining,
            ]);
            return;
        }

        // Pull every article (incl. drafts) once and partition into the
        // three card buckets the view needs. Pages contribute too, since
        // an unfinished landing page is just as relevant on the dashboard
        // as an unfinished blog post.
        $articlesAll = $this->articles->all(true);

        $drafts = self::buildDrafts($articlesAll, $this->pages->all(), 10);
        $scheduled = self::buildScheduled($articlesAll, 10);
        $recentEdits = EditLog::recent(5);

        $this->render('dashboard', [
            'drafts' => $drafts,
            'scheduled' => $scheduled,
            'recentEdits' => $recentEdits,
            'lastContentUpdate' => self::lastContentMtime(
                dirname(__DIR__, 2) . '/content'
            ),
            'flash' => $this->getFlash(),
        ]);
    }

    /**
     * Build the Drafts card payload. Mixes articles + pages, sorted by
     * filesystem mtime so the most-recently-touched draft is first --
     * that's what an operator returning to the panel actually wants.
     *
     * Each entry has the same shape as scheduled() / recent edits so the
     * view can iterate one template per row.
     *
     * @param array<int, array<string, mixed>> $articles
     * @param array<int, array<string, mixed>> $pages
     * @return list<array{type:string,slug:string,title:string,url:string,edit_url:string,mtime:?int,status:string}>
     */
    public static function buildDrafts(array $articles, array $pages, int $limit = 10): array
    {
        $rows = [];

        foreach ($articles as $a) {
            $status = strtolower((string) ($a['status'] ?? 'published'));
            if ($status !== 'draft') {
                continue;
            }
            $category = (string) ($a['category'] ?? '');
            $slug = (string) ($a['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $rows[] = [
                'type' => 'article',
                'slug' => $slug,
                'title' => (string) ($a['title'] ?? $slug),
                'url'  => '/' . trim($category, '/') . '/' . $slug,
                'edit_url' => '/admin/articles/'
                    . rawurlencode($category) . '/' . rawurlencode($slug) . '/edit',
                'mtime' => self::pathMtime($a['path'] ?? null),
                'status' => 'draft',
            ];
        }

        foreach ($pages as $p) {
            $status = strtolower((string) ($p['meta']['status'] ?? $p['status'] ?? 'published'));
            if ($status !== 'draft') {
                continue;
            }
            $slug = (string) ($p['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $rows[] = [
                'type' => 'page',
                'slug' => $slug,
                'title' => (string) ($p['title'] ?? $slug),
                'url'  => '/' . $slug,
                'edit_url' => '/admin/pages#' . rawurlencode($slug),
                'mtime' => self::pathMtime($p['path'] ?? null),
                'status' => 'draft',
            ];
        }

        usort($rows, static function ($a, $b) {
            return ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0);
        });

        return array_slice($rows, 0, max(0, $limit));
    }

    /**
     * Build the Scheduled card payload. Items count as scheduled when:
     *   - status field is literally 'scheduled', OR
     *   - publish_at is set and parses to a timestamp in the future.
     *
     * Sorted by publish_at ASC -- "the next thing to go live" goes on top.
     * Items without a parseable publish_at fall to the end.
     *
     * @param array<int, array<string, mixed>> $articles
     * @return list<array{type:string,slug:string,title:string,url:string,edit_url:string,publish_at:?int,status:string}>
     */
    public static function buildScheduled(array $articles, int $limit = 10): array
    {
        $now = time();
        $rows = [];

        foreach ($articles as $a) {
            $status = strtolower((string) ($a['status'] ?? 'published'));
            $publishAt = self::parseTimestamp($a['publish_at'] ?? null);
            // Future-dated `date` also counts as scheduled when status
            // isn't explicit, matching articleStatus()'s contract.
            if ($publishAt === null && $status !== 'scheduled') {
                $dateTs = self::parseTimestamp($a['date'] ?? null);
                if ($dateTs !== null && $dateTs > $now) {
                    $publishAt = $dateTs;
                }
            }

            $isScheduled = $status === 'scheduled'
                || ($publishAt !== null && $publishAt > $now);
            if (!$isScheduled) {
                continue;
            }
            // Drafts are explicitly NOT scheduled even if they happen to
            // have a future publish_at; they belong in the Drafts card.
            if ($status === 'draft') {
                continue;
            }

            $category = (string) ($a['category'] ?? '');
            $slug = (string) ($a['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $rows[] = [
                'type' => 'article',
                'slug' => $slug,
                'title' => (string) ($a['title'] ?? $slug),
                'url'  => '/' . trim($category, '/') . '/' . $slug,
                'edit_url' => '/admin/articles/'
                    . rawurlencode($category) . '/' . rawurlencode($slug) . '/edit',
                'publish_at' => $publishAt,
                'status' => 'scheduled',
            ];
        }

        usort($rows, static function ($a, $b) {
            // Items without a publish_at sort to the end so the operator
            // sees the next concrete release first.
            $left  = $a['publish_at'] ?? PHP_INT_MAX;
            $right = $b['publish_at'] ?? PHP_INT_MAX;
            return $left <=> $right;
        });

        return array_slice($rows, 0, max(0, $limit));
    }

    private static function parseTimestamp(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);
            return $ts === false ? null : $ts;
        }
        return null;
    }

    private static function pathMtime(mixed $path): ?int
    {
        if (!is_string($path) || $path === '' || !is_file($path)) {
            return null;
        }
        $m = @filemtime($path);
        return $m === false ? null : $m;
    }

    /**
     * Most-recent mtime across content/**\/*.md (recursive).
     *
     * Cached for 30s in a request-local static + $_SESSION. The full sweep
     * is cheap on small sites but quadratic-ish on big ones, and the value
     * only needs to be approximate -- "site changed today" reads the same
     * whether the mtime is 14 or 24 minutes old.
     *
     * Returns null when the content tree is empty or unreadable; the view
     * renders "never" in that case.
     */
    public static function lastContentMtime(string $contentRoot): ?int
    {
        static $memo = null;
        if ($memo !== null && ($memo['at'] + 30) > time() && $memo['root'] === $contentRoot) {
            return $memo['mtime'];
        }
        $sessionKey = 'dashboard_content_mtime_' . md5($contentRoot);
        if (isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey])
            && ($_SESSION[$sessionKey]['at'] + 30) > time()
        ) {
            $memo = $_SESSION[$sessionKey] + ['root' => $contentRoot];
            return $memo['mtime'];
        }

        $mtime = null;
        if (is_dir($contentRoot)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($contentRoot, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $entry) {
                if (!$entry->isFile()) {
                    continue;
                }
                if (substr($entry->getFilename(), -3) !== '.md') {
                    continue;
                }
                $m = $entry->getMTime();
                if ($mtime === null || $m > $mtime) {
                    $mtime = $m;
                }
            }
        }

        $memo = ['at' => time(), 'mtime' => $mtime, 'root' => $contentRoot];
        if (isset($_SESSION)) {
            $_SESSION[$sessionKey] = ['at' => $memo['at'], 'mtime' => $memo['mtime']];
        }
        return $mtime;
    }

    /**
     * Status label for an article row.
     *
     * The repository surfaces three states the dashboard cares about:
     *   - draft       -> status field literally "draft"
     *   - scheduled   -> publish_at / date in the future (or status=scheduled)
     *   - published   -> everything else
     *
     * Kept here (rather than on ArticleRepository) because it's a
     * presentation concern -- two consumers would mean a helper, one
     * consumer is fine inline.
     */
    public static function articleStatus(array $article): string
    {
        $raw = strtolower((string) ($article['status'] ?? 'published'));
        if ($raw === 'draft') {
            return 'draft';
        }
        if ($raw === 'scheduled') {
            return 'scheduled';
        }

        $candidate = $article['publish_at'] ?? $article['date'] ?? null;
        if (is_string($candidate) && $candidate !== '') {
            $ts = strtotime($candidate);
            if ($ts !== false && $ts > time()) {
                return 'scheduled';
            }
        }
        return 'published';
    }

    /**
     * Format a unix mtime as a coarse relative phrase ("15 min ago",
     * "yesterday", "3 days ago"). Returns "never" for null so the view
     * can call this unconditionally.
     */
    public static function relativeTime(?int $timestamp, ?int $now = null): string
    {
        if ($timestamp === null) {
            return 'never';
        }
        $now ??= time();
        $diff = $now - $timestamp;
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . ' min ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 86400 * 2) {
            return 'yesterday';
        }
        if ($diff < 86400 * 30) {
            $d = (int) floor($diff / 86400);
            return $d . ' days ago';
        }
        if ($diff < 86400 * 365) {
            $mo = (int) floor($diff / (86400 * 30));
            return $mo . ' month' . ($mo === 1 ? '' : 's') . ' ago';
        }
        $y = (int) floor($diff / (86400 * 365));
        return $y . ' year' . ($y === 1 ? '' : 's') . ' ago';
    }

    /**
     * Future-facing counterpart to relativeTime(): "in 3 days",
     * "in 2 hours", "today", or "in the past". Used by the Scheduled
     * card to display "publishes Dec 24 -- 14 days from now".
     */
    public static function relativeFuture(?int $timestamp, ?int $now = null): string
    {
        if ($timestamp === null) {
            return 'soon';
        }
        $now ??= time();
        $diff = $timestamp - $now;
        if ($diff < 0) {
            return 'in the past';
        }
        if ($diff < 60) {
            return 'in a moment';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return 'in ' . $m . ' min';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return 'in ' . $h . ' hour' . ($h === 1 ? '' : 's');
        }
        if ($diff < 86400 * 2) {
            return 'tomorrow';
        }
        if ($diff < 86400 * 30) {
            $d = (int) floor($diff / 86400);
            return 'in ' . $d . ' days';
        }
        if ($diff < 86400 * 365) {
            $mo = (int) floor($diff / (86400 * 30));
            return 'in ' . $mo . ' month' . ($mo === 1 ? '' : 's');
        }
        $y = (int) floor($diff / (86400 * 365));
        return 'in ' . $y . ' year' . ($y === 1 ? '' : 's');
    }

    /**
     * Pretty-printed source label for the Recent edits card.
     * Anything we don't recognise renders verbatim so a future source
     * type added to EditLog::record() doesn't need a code edit here
     * to show up sensibly.
     */
    public static function sourceLabel(string $source): string
    {
        switch ($source) {
            case 'admin':
                return 'via admin';
            case 'mcp':
                return 'via Claude (MCP)';
            case 'api':
                return 'via api';
            case 'unknown':
                return 'unknown source';
        }
        return 'via ' . $source;
    }
}
