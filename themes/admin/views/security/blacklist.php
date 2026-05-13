<?php
/**
 * Blacklist Management View
 */
$headerCrumbs = [['Security', '/admin/blacklist'], ['Blocked IPs', null]];
$activeTab = 'blacklist';
?>

<?php include __DIR__ . '/../../partials/tabs-security.php'; ?>

<div class="flex items-center justify-end mb-6">
    <span class="text-slate-400"><?= $totalBlocked ?> blocked IPs</span>
</div>

<div class="grid grid-cols-2 gap-6">
    <!-- Blocked IPs -->
    <div class="bg-slate-800 border border-slate-700">
        <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
            <span class="font-semibold text-slate-200">Blocked IPs</span>
            <span class="text-xs text-slate-500"><?= count($entries) ?> <?= count($entries) === 1 ? 'entry' : 'entries' ?></span>
        </div>
        <div class="divide-y divide-slate-800 max-h-96 overflow-y-auto">
            <?php foreach ($entries as $entry): ?>
            <div class="px-4 py-3 flex items-center justify-between">
                <div>
                    <span class="font-mono text-sm text-slate-200"><?= htmlspecialchars($entry['ip']) ?></span>
                    <p class="text-xs text-slate-500"><?= htmlspecialchars($entry['reason']) ?> &middot; <?= htmlspecialchars($entry['date']) ?></p>
                </div>
                <form method="POST" action="/admin/blacklist/unblock" class="inline">
                    <input type="hidden" name="ip" value="<?= htmlspecialchars($entry['ip']) ?>">
                    <button type="submit" class="text-red-400 hover:text-red-300 text-sm">Unblock</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?>
            <div class="px-4 py-8 text-center text-slate-500 text-sm">
                <i class="ri-shield-check-line text-2xl text-emerald-400 leading-none block mb-2"></i>
                No blocked IPs.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Auto-block Log: show only runs that actually blocked something.
         The cron fires every 15 min and was logging "0 blocked" rows -- a
         heartbeat log, not activity. Now filtered to events worth reading. -->
    <?php $autoblockEvents = array_values(array_filter($autoblockRuns, fn($r) => ($r['blocked'] ?? 0) > 0)); ?>
    <div class="bg-slate-800 border border-slate-700">
        <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
            <span class="font-semibold text-slate-200">Auto-block Activity</span>
            <span class="text-xs text-slate-500">
                <?php if (count($autoblockEvents) > 0): ?>
                    <?= count($autoblockEvents) ?> recent <?= count($autoblockEvents) === 1 ? 'event' : 'events' ?>
                <?php else: ?>
                    Cron checked <?= count($autoblockRuns) ?>x recently &middot; no IPs flagged
                <?php endif; ?>
            </span>
        </div>
        <div class="divide-y divide-slate-800 max-h-96 overflow-y-auto">
            <?php if (empty($autoblockEvents)): ?>
                <div class="px-4 py-8 text-center text-slate-500 text-sm">
                    <i class="ri-shield-check-line text-2xl text-emerald-400 leading-none block mb-2"></i>
                    Quiet. The auto-blocker has not flagged any IPs in the last <?= count($autoblockRuns) ?: 'few' ?> runs.
                </div>
            <?php else: ?>
                <?php foreach ($autoblockEvents as $run): ?>
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-400"><?= htmlspecialchars($run['time'] ?? 'Unknown') ?></span>
                        <span class="px-2 py-0.5 bg-orange-900/40 text-orange-300 text-xs border border-orange-800"><?= (int) $run['blocked'] ?> blocked</span>
                    </div>
                    <?php if (!empty($run['ips'])): ?>
                    <div class="mt-2 text-xs text-slate-500 space-y-0.5">
                        <?php foreach (array_slice($run['ips'], 0, 3) as $ip): ?>
                        <div><span class="font-mono text-slate-300"><?= htmlspecialchars($ip['ip']) ?></span> &mdash; <?= htmlspecialchars($ip['reason']) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
