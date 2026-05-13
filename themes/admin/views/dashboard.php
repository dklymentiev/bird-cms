<?php
/**
 * Admin Dashboard View (planner #1843).
 *
 * Up to four operational cards in an auto-fit grid:
 *
 *   1. Drafts       -- articles + pages with status=draft (hidden when empty).
 *   2. Scheduled    -- articles with status=scheduled or publish_at>now (hidden when empty).
 *   3. Recent edits -- the last 5 EditLog rows, with source attribution.
 *   4. Quick links  -- one-click access to the most common admin surfaces.
 *
 * The previous "Site info" card (URL/theme/last-update) was dropped --
 * Site URL is already in the header's View-site button, and theme +
 * last-update are accessible from Settings.
 *
 * Data passed in by DashboardController::index():
 *   - $drafts      list<row>  each: type, slug, title, url, edit_url, mtime, status
 *   - $scheduled   list<row>  each: type, slug, title, url, edit_url, publish_at, status
 *   - $recentEdits list<row>  each: at, source, action, target_url, target_type, target_slug
 *   - $flash       array      flash messages
 */

use App\Admin\DashboardController;

$pageTitle = 'Dashboard';

$drafts      = $drafts      ?? [];
$scheduled   = $scheduled   ?? [];
$recentEdits = $recentEdits ?? [];

$hasDrafts    = !empty($drafts);
$hasScheduled = !empty($scheduled);
$hasRecent    = !empty($recentEdits);
?>

<div class="bird-dashboard">
    <?php if ($hasDrafts): ?>
    <!-- Drafts card. Sorted by mtime desc; up to 10 rows. -->
    <section class="bird-card">
        <header class="bird-card-header">
            <i class="ri-draft-line bird-card-icon" aria-hidden="true"></i>
            <h2>Drafts</h2>
            <span class="bird-card-count"><?= count($drafts) ?></span>
        </header>
        <ul class="bird-card-list">
            <?php foreach ($drafts as $row): ?>
                <li>
                    <div class="bird-card-row-main">
                        <a class="bird-card-row-title" href="<?= htmlspecialchars($row['edit_url']) ?>">
                            <?= htmlspecialchars($row['title']) ?>
                        </a>
                        <div class="bird-card-row-meta">
                            <span class="bird-card-row-url"><?= htmlspecialchars($row['url']) ?></span>
                            <?php if (!empty($row['mtime'])): ?>
                                <span class="bird-card-row-sep">&middot;</span>
                                <span>edited <?= htmlspecialchars(DashboardController::relativeTime($row['mtime'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="bird-pill bird-pill-mute">draft</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($hasScheduled): ?>
    <!-- Scheduled card. Sorted by publish_at asc; future-only. -->
    <section class="bird-card">
        <header class="bird-card-header">
            <i class="ri-calendar-schedule-line bird-card-icon" aria-hidden="true"></i>
            <h2>Scheduled</h2>
            <span class="bird-card-count"><?= count($scheduled) ?></span>
        </header>
        <ul class="bird-card-list">
            <?php foreach ($scheduled as $row): ?>
                <?php
                    $when    = $row['publish_at'] ?? null;
                    $whenAbs = is_int($when) ? date('M j', $when) : 'soon';
                    $whenRel = DashboardController::relativeFuture(is_int($when) ? $when : null);
                ?>
                <li>
                    <div class="bird-card-row-main">
                        <a class="bird-card-row-title" href="<?= htmlspecialchars($row['edit_url']) ?>">
                            <?= htmlspecialchars($row['title']) ?>
                        </a>
                        <div class="bird-card-row-meta">
                            <span class="bird-card-row-url"><?= htmlspecialchars($row['url']) ?></span>
                            <span class="bird-card-row-sep">&middot;</span>
                            <span>publishes <?= htmlspecialchars($whenAbs) ?></span>
                            <?php if ($whenRel !== ''): ?>
                                <span class="bird-card-row-sep">&middot;</span>
                                <span><?= htmlspecialchars($whenRel) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="bird-pill bird-pill-accent">scheduled</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($hasRecent): ?>
    <!-- Recent edits card. Last 5 EditLog rows, newest first. -->
    <section class="bird-card">
        <header class="bird-card-header">
            <i class="ri-history-line bird-card-icon" aria-hidden="true"></i>
            <h2>Recent edits</h2>
            <span class="bird-card-count"><?= count($recentEdits) ?></span>
        </header>
        <ul class="bird-card-list">
            <?php foreach ($recentEdits as $edit): ?>
                <?php
                    $source = (string) ($edit['source'] ?? 'unknown');
                    // 'unknown' rows come from CLI / test runs that didn't
                    // set EditLog::$context. They aren't meaningful to an
                    // operator, so we render no pill in that case rather
                    // than a "unknown" badge that looks like a bug.
                    $sourceClass = match ($source) {
                        'admin' => 'bird-pill-success',
                        'mcp'   => 'bird-pill-violet',
                        'api'   => 'bird-pill-warning',
                        default => '',
                    };
                    $targetUrl  = (string) ($edit['target_url']  ?? '');
                    $targetType = (string) ($edit['target_type'] ?? '');
                    $targetSlug = (string) ($edit['target_slug'] ?? '');
                    $action     = (string) ($edit['action']      ?? 'save');
                    $editHref = $targetUrl;
                    if ($targetType === 'page' && $targetSlug !== '') {
                        $editHref = '/admin/pages#' . rawurlencode($targetSlug);
                    } elseif ($targetType === 'article' && $targetUrl !== '') {
                        $trimmed = trim($targetUrl, '/');
                        if ($trimmed !== '') {
                            $parts = explode('/', $trimmed);
                            if (count($parts) === 2) {
                                $editHref = '/admin/articles/' . rawurlencode($parts[0])
                                    . '/' . rawurlencode($parts[1]) . '/edit';
                            }
                        }
                    }
                ?>
                <li>
                    <a class="bird-card-row-inline" href="<?= htmlspecialchars($editHref) ?>">
                        <span class="bird-card-row-url"><?= htmlspecialchars($targetUrl) ?></span>
                        <span class="bird-card-row-sep">&middot;</span>
                        <span class="bird-card-row-meta">
                            <?= htmlspecialchars($action) ?>d
                            <?= htmlspecialchars(DashboardController::relativeTime((int) $edit['at'])) ?>
                        </span>
                    </a>
                    <?php if ($sourceClass !== ''): ?>
                        <span class="bird-pill <?= $sourceClass ?>"
                              title="<?= htmlspecialchars(DashboardController::sourceLabel($source)) ?>">
                            <?= htmlspecialchars($source) ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- Quick links: same grid as the operational cards. -->
    <section class="bird-card">
        <header class="bird-card-header">
            <i class="ri-rocket-line bird-card-icon" aria-hidden="true"></i>
            <h2>Quick links</h2>
        </header>
        <ul class="bird-quicklinks">
            <li>
                <a href="/admin/articles/new">
                    <i class="ri-article-line" aria-hidden="true"></i>
                    <span>New article</span>
                </a>
            </li>
            <li>
                <a href="/admin/pages">
                    <i class="ri-link" aria-hidden="true"></i>
                    <span>Manage pages</span>
                </a>
            </li>
            <li>
                <a href="/admin/media">
                    <i class="ri-image-line" aria-hidden="true"></i>
                    <span>Upload media</span>
                </a>
            </li>
            <li>
                <a href="/admin/settings/general">
                    <i class="ri-settings-3-line" aria-hidden="true"></i>
                    <span>Site settings</span>
                </a>
            </li>
        </ul>
    </section>
</div>
