<?php
/**
 * Leads List View
 *
 * Variables: $leads, $filter, $totalLeads
 */

$pageTitle = 'Leads';
$filter = $filter ?? 'all';

// Success/Error messages
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
?>

<?php if ($success === 'deleted'): ?>
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
    Lead deleted.
</div>
<?php endif; ?>

<?php if ($error === 'not_found'): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
    Lead not found.
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900"><?= $pageTitle ?></h1>

    <div class="flex gap-4 items-center">
        <!-- Filter -->
        <div class="flex gap-1 bg-gray-200 rounded p-1">
            <a href="?filter=all" class="px-3 py-1 rounded text-sm <?= $filter === 'all' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-600 hover:text-gray-800' ?>">All</a>
            <a href="?filter=real" class="px-3 py-1 rounded text-sm <?= $filter === 'real' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-600 hover:text-gray-800' ?>">Real</a>
            <a href="?filter=spam" class="px-3 py-1 rounded text-sm <?= $filter === 'spam' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-600 hover:text-gray-800' ?>">Spam</a>
        </div>

        <!-- Export -->
        <a href="/admin/leads/export" class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 flex items-center gap-2">
            <i class="ri-download-line text-base leading-none"></i>
            Export CSV
        </a>
    </div>
</div>

<?php if (empty($leads)): ?>
<div class="bg-white rounded-lg p-8 text-center text-gray-500">
    No leads found.
</div>
<?php else: ?>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg p-4 shadow-sm">
        <p class="text-sm text-gray-500">Total Leads</p>
        <p class="text-2xl font-bold text-gray-800"><?= $totalLeads ?></p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow-sm">
        <p class="text-sm text-gray-500">This Month</p>
        <p class="text-2xl font-bold text-blue-600"><?= count(array_filter($leads, fn($l) => strtotime($l['timestamp'] ?? '') >= strtotime('first day of this month'))) ?></p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow-sm">
        <p class="text-sm text-gray-500">Today</p>
        <p class="text-2xl font-bold text-green-600"><?= count(array_filter($leads, fn($l) => date('Y-m-d', strtotime($l['timestamp'] ?? '')) === date('Y-m-d'))) ?></p>
    </div>
</div>

<!-- Leads Table -->
<div class="bg-white rounded-lg shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Date</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Name</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Contact</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Service</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Source</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Message</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($leads as $lead): ?>
                <?php
                $isSpam = false;
                $message = strtolower($lead['message'] ?? '');
                foreach (['seo', 'search engine', 'optimization', 'ranking', 'visibility'] as $pattern) {
                    if (stripos($message, $pattern) !== false) {
                        $isSpam = true;
                        break;
                    }
                }
                $isTest = stripos($lead['email'] ?? '', 'test') !== false || stripos($lead['name'] ?? '', 'test') !== false;
                ?>
                <tr class="hover:bg-gray-50 <?= $isSpam ? 'bg-red-50' : ($isTest ? 'bg-yellow-50' : '') ?>">
                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                        <?= date('M d, H:i', strtotime($lead['timestamp'] ?? '')) ?>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-800">
                        <?= htmlspecialchars($lead['name'] ?? '-') ?>
                        <?php if ($isSpam): ?>
                            <span class="ml-2 px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">SPAM</span>
                        <?php elseif ($isTest): ?>
                            <span class="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded text-xs">TEST</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-gray-800"><?= htmlspecialchars($lead['email'] ?? '-') ?></div>
                        <?php if (!empty($lead['phone'])): ?>
                        <div class="text-gray-500 text-xs"><?= htmlspecialchars($lead['phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        <?= htmlspecialchars($lead['service'] ?? $lead['service_type'] ?? '-') ?>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        <?= htmlspecialchars($lead['source'] ?? '-') ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600 max-w-xs">
                        <?php if (!empty($lead['message'])): ?>
                        <div class="truncate" title="<?= htmlspecialchars($lead['message']) ?>">
                            <?= htmlspecialchars(substr($lead['message'], 0, 80)) ?><?= strlen($lead['message']) > 80 ? '...' : '' ?>
                        </div>
                        <?php else: ?>
                        <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
