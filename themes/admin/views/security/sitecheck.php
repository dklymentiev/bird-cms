<?php
/**
 * Site Check View
 */
$headerCrumbs = [['Audit', '/admin/sitecheck'], ['SEO Audit', null]];
$activeTab = 'sitecheck';
?>

<?php include __DIR__ . '/../../partials/tabs-audit.php'; ?>

<div class="flex items-center justify-end mb-6">
    <div class="flex items-center gap-3">
        <input type="text" id="base-url" value="<?= htmlspecialchars(config('site_url')) ?>" class="px-3 py-2 border rounded w-64">
        <button id="run-scan" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Run Scan</button>
    </div>
</div>

<div id="loading" class="hidden mb-6 bg-white rounded-lg shadow p-6">
    <div class="flex items-center gap-4">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <div>Running site check... (30-60 seconds)</div>
    </div>
</div>

<div id="results">
<?php if ($results): ?>
    <!-- Summary -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Scanned</p>
            <p class="text-lg font-bold"><?= htmlspecialchars($results['base_url'] ?? '-') ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Passed</p>
            <p class="text-2xl font-bold text-green-600"><?= $results['summary']['passed'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Warnings</p>
            <p class="text-2xl font-bold text-yellow-600"><?= $results['summary']['warning'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Failed</p>
            <p class="text-2xl font-bold text-red-600"><?= $results['summary']['failed'] ?? 0 ?></p>
        </div>
    </div>

    <!-- Checks by Category -->
    <?php
    $byCategory = [];
    foreach ($results['checks'] ?? [] as $check) {
        $cat = $check['category'];
        $byCategory[$cat][] = $check;
    }
    ?>
    <?php foreach ($byCategory as $category => $checks): ?>
    <div class="bg-white rounded-lg shadow mb-4">
        <div class="px-4 py-3 border-b font-semibold"><?= htmlspecialchars($category) ?></div>
        <div class="divide-y">
            <?php foreach ($checks as $check): ?>
            <div class="px-4 py-2 flex items-center gap-3 text-sm">
                <?php if ($check['status'] === 'passed'): ?>
                <span class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs flex-shrink-0">✓</span>
                <?php elseif ($check['status'] === 'warning'): ?>
                <span class="w-5 h-5 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center text-xs flex-shrink-0">!</span>
                <?php else: ?>
                <span class="w-5 h-5 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xs flex-shrink-0">✗</span>
                <?php endif; ?>
                <span class="font-medium"><?= htmlspecialchars($check['check']) ?></span>
                <span class="text-gray-400">—</span>
                <span class="text-gray-600"><?= htmlspecialchars($check['message']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

<?php else: ?>
    <div class="bg-white rounded-lg shadow p-12 text-center">
        <p class="text-gray-500">No results yet. Click "Run Scan" to check your website.</p>
    </div>
<?php endif; ?>
</div>

<script>
document.getElementById('run-scan').addEventListener('click', async function() {
    const btn = this;
    const loading = document.getElementById('loading');
    const results = document.getElementById('results');

    btn.disabled = true;
    btn.textContent = 'Scanning...';
    loading.classList.remove('hidden');
    results.classList.add('opacity-50');

    try {
        const formData = new FormData();
        formData.append('base_url', document.getElementById('base-url').value);

        const response = await fetch('/admin/sitecheck/run', {method: 'POST', body: formData});
        const data = await response.json();

        if (data.success) {
            location.reload();
        } else {
            alert('Scan failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Run Scan';
        loading.classList.add('hidden');
        results.classList.remove('opacity-50');
    }
});
</script>
