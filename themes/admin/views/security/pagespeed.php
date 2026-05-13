<?php
/**
 * PageSpeed Insights View
 */
$headerCrumbs = [['Audit', '/admin/sitecheck'], ['Performance', null]];
$activeTab = 'pagespeed';

function scoreColor(int $score): string {
    if ($score >= 90) return 'text-green-600 bg-green-100';
    if ($score >= 50) return 'text-yellow-600 bg-yellow-100';
    return 'text-red-600 bg-red-100';
}

function scoreBg(int $score): string {
    if ($score >= 90) return 'bg-green-500';
    if ($score >= 50) return 'bg-yellow-500';
    return 'bg-red-500';
}
?>

<?php include __DIR__ . '/../../partials/tabs-audit.php'; ?>

<div class="flex items-center justify-end mb-6">
    <div class="flex items-center gap-3">
        <input type="text" id="page-url" value="<?= htmlspecialchars($results['url'] ?? config('site_url', '')) ?>" class="px-3 py-2 border rounded w-80" placeholder="https://example.com">
        <select id="strategy" class="px-3 py-2 border rounded">
            <option value="desktop" <?= ($results['strategy'] ?? 'desktop') === 'desktop' ? 'selected' : '' ?>>Desktop</option>
            <option value="mobile" <?= ($results['strategy'] ?? '') === 'mobile' ? 'selected' : '' ?>>Mobile</option>
        </select>
        <button id="run-audit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Run Audit</button>
    </div>
</div>

<div id="loading" class="hidden mb-6 bg-white rounded-lg shadow p-6">
    <div class="flex items-center gap-4">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <div>Running PageSpeed audit... (30-60 seconds)</div>
    </div>
</div>

<div id="results">
<?php if ($results): ?>
    <!-- Scores -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <?php foreach ($results['scores'] as $category => $score): ?>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-sm text-gray-500 mb-2 capitalize"><?= str_replace('-', ' ', $category) ?></p>
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full <?= scoreColor($score) ?> text-2xl font-bold">
                <?= $score ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Core Web Vitals -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-4 py-3 border-b font-semibold">Core Web Vitals</div>
        <div class="grid grid-cols-5 gap-4 p-4">
            <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">First Contentful Paint</p>
                <p class="text-lg font-mono font-bold"><?= htmlspecialchars($results['metrics']['fcp']) ?></p>
            </div>
            <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Largest Contentful Paint</p>
                <p class="text-lg font-mono font-bold"><?= htmlspecialchars($results['metrics']['lcp']) ?></p>
            </div>
            <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Total Blocking Time</p>
                <p class="text-lg font-mono font-bold"><?= htmlspecialchars($results['metrics']['tbt']) ?></p>
            </div>
            <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Cumulative Layout Shift</p>
                <p class="text-lg font-mono font-bold"><?= htmlspecialchars($results['metrics']['cls']) ?></p>
            </div>
            <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Speed Index</p>
                <p class="text-lg font-mono font-bold"><?= htmlspecialchars($results['metrics']['si']) ?></p>
            </div>
        </div>
    </div>

    <!-- Opportunities -->
    <?php if (!empty($results['opportunities'])): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-4 py-3 border-b font-semibold text-yellow-700">Opportunities</div>
        <div class="divide-y">
            <?php foreach ($results['opportunities'] as $item): ?>
            <div class="px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full <?= scoreBg((int)(($item['score'] ?? 0) * 100)) ?>"></div>
                    <span class="font-medium"><?= htmlspecialchars($item['title']) ?></span>
                    <?php if ($item['displayValue']): ?>
                    <span class="text-sm text-gray-500"><?= htmlspecialchars($item['displayValue']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Diagnostics -->
    <?php if (!empty($results['diagnostics'])): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-4 py-3 border-b font-semibold text-gray-700">Diagnostics</div>
        <div class="divide-y">
            <?php foreach ($results['diagnostics'] as $item): ?>
            <div class="px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full <?= scoreBg((int)(($item['score'] ?? 0) * 100)) ?>"></div>
                    <span class="font-medium"><?= htmlspecialchars($item['title']) ?></span>
                    <?php if ($item['displayValue']): ?>
                    <span class="text-sm text-gray-500"><?= htmlspecialchars($item['displayValue']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <p class="text-xs text-gray-400 text-right">Last check: <?= htmlspecialchars($results['fetchTime'] ?? '-') ?></p>

<?php else: ?>
    <div class="bg-white rounded-lg shadow p-12 text-center">
        <p class="text-gray-500 mb-4">No results yet. Enter your URL and click "Run Audit".</p>
        <p class="text-sm text-gray-400">Powered by Google PageSpeed Insights API</p>
    </div>
<?php endif; ?>
</div>

<script>
document.getElementById('run-audit').addEventListener('click', async function() {
    const btn = this;
    const loading = document.getElementById('loading');
    const results = document.getElementById('results');

    btn.disabled = true;
    btn.textContent = 'Running...';
    loading.classList.remove('hidden');
    results.classList.add('opacity-50');

    try {
        const formData = new FormData();
        formData.append('url', document.getElementById('page-url').value);
        formData.append('strategy', document.getElementById('strategy').value);

        const response = await fetch('/admin/pagespeed/run', {method: 'POST', body: formData});
        const data = await response.json();

        if (data.success) {
            location.reload();
        } else {
            alert('Audit failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Run Audit';
        loading.classList.add('hidden');
        results.classList.remove('opacity-50');
    }
});
</script>
