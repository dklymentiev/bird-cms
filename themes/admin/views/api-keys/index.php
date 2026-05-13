<?php
/**
 * /admin/api-keys -- list active + revoked keys.
 *
 * Provided by ApiKeysController::index():
 *   $pageTitle  string
 *   $keys       list<array> -- already presented (hash_short, is_active, ...)
 *   $newKey     ?string     -- plaintext to display ONCE (set right after create)
 *   $csrf       string
 *   $flash      list<{type,message}>
 */
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-100">API Keys</h1>
        <p class="text-sm text-gray-400 mt-1">
            Bearer tokens for <code class="font-mono text-gray-300">/api/v1</code>. Only the SHA-256 hash is stored.
        </p>
    </div>
    <a href="/admin/api-keys/new"
       class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded inline-flex items-center gap-2">
        <i class="ri-add-line"></i> New key
    </a>
</div>

<?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>

<?php if ($newKey !== null && $newKey !== ''): ?>
    <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 text-amber-900 p-4 rounded-r-lg">
        <p class="font-semibold mb-2">Copy this key now -- it will not be shown again.</p>
        <div class="flex items-center gap-2">
            <code class="font-mono text-sm bg-white border border-amber-200 rounded px-3 py-2 flex-1 break-all"><?= htmlspecialchars($newKey, ENT_QUOTES, 'UTF-8') ?></code>
            <button type="button"
                    class="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm rounded"
                    onclick="navigator.clipboard.writeText('<?= htmlspecialchars($newKey, ENT_QUOTES, 'UTF-8') ?>'); this.textContent='Copied';">
                Copy
            </button>
        </div>
        <p class="text-xs mt-2">Send it as <code class="font-mono">Authorization: Bearer &lt;key&gt;</code> on every API request.</p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($keys)): ?>
        <div class="p-8 text-center text-gray-500">
            <p class="text-sm">No keys yet.</p>
            <p class="text-xs mt-2">Create one to start calling <code class="font-mono">/api/v1</code> from a mobile app or third-party integration.</p>
        </div>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                <tr>
                    <th class="px-4 py-2 text-left">Label</th>
                    <th class="px-4 py-2 text-left">Scope</th>
                    <th class="px-4 py-2 text-left">Hash</th>
                    <th class="px-4 py-2 text-left">Created</th>
                    <th class="px-4 py-2 text-left">Last used</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($keys as $k): ?>
                    <tr class="<?= $k['is_active'] ? '' : 'bg-gray-50 text-gray-500' ?>">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <?= htmlspecialchars($k['label'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                <?= $k['scope'] === 'write' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                <?= htmlspecialchars($k['scope'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">
                            <?= htmlspecialchars($k['hash_short'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            <?= htmlspecialchars($k['created_at'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            <?= htmlspecialchars((string) ($k['last_used_at'] ?? '— never —'), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($k['is_active']): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Active</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700">
                                    Revoked <?= htmlspecialchars((string) $k['revoked_at'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <?php if ($k['is_active']): ?>
                                <form method="POST"
                                      action="/admin/api-keys/<?= htmlspecialchars($k['hash'], ENT_QUOTES, 'UTF-8') ?>/revoke"
                                      onsubmit="return confirm('Revoke key &quot;<?= htmlspecialchars($k['label'], ENT_QUOTES, 'UTF-8') ?>&quot;? This cannot be undone.');"
                                      class="inline-block">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">
                                        Revoke
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
