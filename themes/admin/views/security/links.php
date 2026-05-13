<?php
/**
 * Link Checker View
 */
$headerCrumbs = [['Audit', '/admin/sitecheck'], ['Link Health', null]];
$activeTab = 'links';
?>

<?php include __DIR__ . '/../../partials/tabs-audit.php'; ?>

<div class="flex items-center justify-end mb-6">
    <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" id="check-images" class="rounded">
            Check images
        </label>
        <button id="run-check" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Run Check</button>
    </div>
</div>

<p class="text-sm text-gray-600 mb-6">Crawls the site, collects all links and images, checks for broken ones.</p>

<?php if ($results): ?>
<div id="results">
    <!-- Summary -->
    <div class="grid grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-xs text-gray-500 uppercase">Pages</p>
            <p class="text-2xl font-bold"><?= $results['summary']['crawled'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-xs text-gray-500 uppercase">Links</p>
            <p class="text-2xl font-bold"><?= $results['summary']['internal'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-xs text-gray-500 uppercase">External</p>
            <p class="text-2xl font-bold"><?= $results['summary']['external'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-xs text-gray-500 uppercase">Images</p>
            <p class="text-2xl font-bold"><?= $results['summary']['images'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-xs text-gray-500 uppercase">Checked</p>
            <p class="text-sm font-medium"><?= $results['timestamp'] ?? 'Never' ?></p>
        </div>
    </div>

    <!-- Broken Links -->
    <?php if (!empty($results['broken'])): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-4 py-3 border-b flex items-center gap-2">
            <span class="w-3 h-3 bg-red-500 rounded-full"></span>
            <h3 class="font-semibold">Broken Links (<?= count($results['broken']) ?>)</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2 w-16">Status</th>
                    <th class="px-4 py-2">URL</th>
                    <th class="px-4 py-2">Found on</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($results['broken'] as $link): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded"><?= htmlspecialchars($link['status']) ?></span></td>
                    <td class="px-4 py-2"><code class="text-xs"><?= htmlspecialchars($link['url']) ?></code></td>
                    <td class="px-4 py-2 text-gray-500 text-xs"><?= htmlspecialchars($link['from']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Soft 404s -->
    <?php if (!empty($results['soft404'])): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-4 py-3 border-b flex items-center gap-2">
            <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
            <h3 class="font-semibold">Soft 404s (<?= count($results['soft404']) ?>)</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2 w-16">Status</th>
                    <th class="px-4 py-2">URL</th>
                    <th class="px-4 py-2">Found on</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($results['soft404'] as $link): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded">soft404</span></td>
                    <td class="px-4 py-2"><code class="text-xs"><?= htmlspecialchars($link['url']) ?></code></td>
                    <td class="px-4 py-2 text-gray-500 text-xs"><?= htmlspecialchars($link['from']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Broken Images -->
    <?php if (!empty($results['brokenImages'])): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-4 py-3 border-b flex items-center gap-2">
            <span class="w-3 h-3 bg-orange-500 rounded-full"></span>
            <h3 class="font-semibold">Broken Images (<?= count($results['brokenImages']) ?>)</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2 w-16">Status</th>
                    <th class="px-4 py-2">URL</th>
                    <th class="px-4 py-2">Found on</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($results['brokenImages'] as $img): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><span class="px-2 py-0.5 bg-orange-100 text-orange-700 text-xs rounded"><?= $img['status'] ?></span></td>
                    <td class="px-4 py-2"><code class="text-xs"><?= htmlspecialchars($img['url']) ?></code></td>
                    <td class="px-4 py-2 text-gray-500 text-xs"><?= htmlspecialchars($img['from']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>


    <!-- All Internal Links -->
    <?php if (!empty($results['allLinks'])): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-4 py-3 border-b flex items-center gap-2">
            <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
            <h3 class="font-semibold">All Internal Links (<?= count($results['allLinks']) ?>)</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2 w-16">Pages</th>
                    <th class="px-4 py-2 w-16">Status</th>
                    <th class="px-4 py-2">URL</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($results['allLinks'] as $link):
                    $status = $link['status'] ?? '?';
                    $statusClass = match(true) {
                        $status === 200 => 'bg-green-100 text-green-700',
                        $status >= 400 => 'bg-red-100 text-red-700',
                        $status >= 300 => 'bg-yellow-100 text-yellow-700',
                        default => 'bg-gray-100 text-gray-600',
                    };
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-1.5 text-gray-500 text-xs"><?= $link['count'] ?></td>
                    <td class="px-4 py-1.5"><span class="px-2 py-0.5 rounded text-xs <?= $statusClass ?>"><?= $status ?></span></td>
                    <td class="px-4 py-1.5"><code class="text-xs"><?= htmlspecialchars($link['url']) ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- External Links -->
    <?php if (!empty($results['external'])): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-4 py-3 border-b flex items-center gap-2">
            <span class="w-3 h-3 bg-purple-500 rounded-full"></span>
            <h3 class="font-semibold">External Links (<?= count($results['external']) ?>)</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2 w-16">Pages</th>
                    <th class="px-4 py-2">URL</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($results['external'] as $link): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-1.5 text-gray-500 text-xs"><?= $link['count'] ?></td>
                    <td class="px-4 py-1.5"><a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="text-blue-600 hover:underline text-xs break-all"><?= htmlspecialchars($link['url']) ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- All Images -->
    <?php if (!empty($results['allImages'])): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-4 py-3 border-b flex items-center gap-2">
            <span class="w-3 h-3 bg-green-500 rounded-full"></span>
            <h3 class="font-semibold">All Images (<?= count($results['allImages']) ?>)</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2 w-16">Pages</th>
                    <th class="px-4 py-2 w-16">Status</th>
                    <th class="px-4 py-2">URL</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($results['allImages'] as $img):
                    $status = $img['status'] ?? '?';
                    $statusClass = match(true) {
                        $status === 200 => 'bg-green-100 text-green-700',
                        $status >= 400 => 'bg-red-100 text-red-700',
                        $status >= 300 => 'bg-yellow-100 text-yellow-700',
                        $status === '?' => 'bg-gray-100 text-gray-400',
                        default => 'bg-gray-100 text-gray-600',
                    };
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-1.5 text-gray-500 text-xs"><?= $img['count'] ?></td>
                    <td class="px-4 py-1.5"><span class="px-2 py-0.5 rounded text-xs <?= $statusClass ?>"><?= $status ?></span></td>
                    <td class="px-4 py-1.5"><code class="text-xs break-all"><?= htmlspecialchars($img['url']) ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-gray-50 border border-gray-200 rounded-lg p-12 text-center">
    <p class="text-gray-600">No check results yet.</p>
    <p class="text-gray-500 text-sm mt-1">Click "Run Check" to crawl the site and find broken links.</p>
</div>
<?php endif; ?>

<script>
document.getElementById('run-check').addEventListener('click', async function() {
    const btn = this;
    const checkImages = document.getElementById('check-images').checked;
    btn.disabled = true;
    btn.textContent = checkImages ? 'Checking links & images...' : 'Checking links...';

    try {
        const formData = new FormData();
        if (checkImages) formData.append('check_images', '1');

        const response = await fetch('/admin/links/check', {method: 'POST', body: formData});
        const data = await response.json();

        if (data.success) {
            location.reload();
        } else {
            alert('Check failed');
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Run Check';
    }
});
</script>
