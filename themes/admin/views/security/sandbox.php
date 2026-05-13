<?php
/**
 * Sandbox Review View
 */
$headerCrumbs = [['Security', '/admin/blacklist'], ['IP Review', null]];
$activeTab = 'sandbox';
$filter = $filter ?? 'pending';
$pendingCount = $stats['pending'] ?? 0;
$botCount = $stats['bot'] ?? 0;
$humanCount = $stats['human'] ?? 0;
?>

<?php include __DIR__ . '/../../partials/tabs-security.php'; ?>

<div class="flex items-center justify-end mb-6">
    <div class="flex gap-2">
        <a href="?filter=pending" class="px-3 py-1 rounded <?= $filter === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-200' ?>">Pending (<?= $pendingCount ?>)</a>
        <a href="?filter=bot" class="px-3 py-1 rounded <?= $filter === 'bot' ? 'bg-orange-500 text-white' : 'bg-gray-200' ?>">Bots (<?= $botCount ?>)</a>
        <a href="?filter=human" class="px-3 py-1 rounded <?= $filter === 'human' ? 'bg-blue-500 text-white' : 'bg-gray-200' ?>">Humans (<?= $humanCount ?>)</a>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded"><?= htmlspecialchars($error) ?></div>
<?php else: ?>

<?php if ($filter === 'pending' && $pendingCount > 0): ?>
<!-- Bulk action toolbar: separated from per-row actions to make the
     destructive nature obvious; confirm dialog before commit. -->
<div x-data="{ open: false }" class="mb-4 flex items-center justify-between bg-slate-800 border border-slate-700 px-4 py-3">
    <div class="text-sm text-slate-300">
        <i class="ri-error-warning-line text-amber-400 mr-1 leading-none"></i>
        Bulk actions affect <span class="font-semibold text-amber-300"><?= $pendingCount ?></span> pending IPs at once.
    </div>
    <div class="relative">
        <button type="button"
                @click="open = !open"
                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-slate-900 border border-slate-600 hover:border-slate-500 text-slate-200">
            <i class="ri-more-2-line text-base leading-none"></i>
            Bulk
        </button>
        <div x-show="open" x-cloak @click.away="open = false"
             class="absolute right-0 mt-1 w-56 bg-slate-800 border border-slate-700 shadow-lg z-30">
            <form method="POST" action="/admin/sandbox/bulk-bot"
                  onsubmit="return confirm('Mark all <?= $pendingCount ?> pending IPs as Bot? This cannot be undone in bulk.');">
                <button type="submit" class="w-full text-left px-3 py-2 text-sm text-orange-300 hover:bg-slate-700 flex items-center gap-2">
                    <i class="ri-robot-line text-base leading-none"></i>
                    Mark all pending as Bot
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">IP</th>
                <th class="px-4 py-3 text-left">User Agent</th>
                <th class="px-4 py-3 text-left">Visits</th>
                <th class="px-4 py-3 text-left">First Seen</th>
                <th class="px-4 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($entries as $entry): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs"><?= htmlspecialchars($entry['ip']) ?></td>
                <td class="px-4 py-2 text-xs max-w-xs truncate" title="<?= htmlspecialchars($entry['user_agent']) ?>"><?= htmlspecialchars($entry['user_agent'] ?: '(none)') ?></td>
                <td class="px-4 py-2"><?= $entry['visit_count'] ?></td>
                <td class="px-4 py-2 text-gray-500"><?= $entry['first_seen'] ?></td>
                <td class="px-4 py-2">
                    <?php if ($entry['verdict'] === 'pending'): ?>
                    <form method="POST" action="/admin/sandbox/verdict" class="inline">
                        <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                        <input type="hidden" name="verdict" value="bot">
                        <button class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs hover:bg-orange-200">Bot</button>
                    </form>
                    <form method="POST" action="/admin/sandbox/verdict" class="inline">
                        <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                        <input type="hidden" name="verdict" value="human">
                        <button class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200">Human</button>
                    </form>
                    <form method="POST" action="/admin/sandbox/blacklist" class="inline">
                        <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                        <button class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">Blacklist</button>
                    </form>
                    <?php else: ?>
                    <span class="px-2 py-1 <?= $entry['verdict'] === 'bot' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700' ?> rounded text-xs"><?= ucfirst($entry['verdict']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t flex justify-between">
        <span>Page <?= $page ?> of <?= $totalPages ?></span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?><a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-100 rounded">Prev</a><?php endif; ?>
            <?php if ($page < $totalPages): ?><a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-100 rounded">Next</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
