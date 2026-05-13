<?php
/**
 * Admin settings page.
 * Provided by SettingsController::index() / general():
 *   $tab       string  active tab (general|site|appearance|security|email|about)
 *   $general   array   {values, themes, timezones} for the General tab form
 *   $site      list<array>
 *   $security  list<array>
 *   $email     list<array>
 *   $about     list<array>
 *   $csrf      string  CSRF token
 *   $flash     list<array{type:string,message:string}>
 */
$pageTitle = 'Settings';

$tabs = [
    'general'    => ['label' => 'General',    'desc' => 'Edit site identity'],
    'site'       => ['label' => 'Site',       'desc' => 'Read-only view'],
    'appearance' => ['label' => 'Appearance', 'desc' => 'Active theme'],
    'security'   => ['label' => 'Security',   'desc' => 'IPs, debug, secrets'],
    'email'      => ['label' => 'Email',      'desc' => 'SMTP credentials'],
    'about'      => ['label' => 'About',      'desc' => 'Version, paths, runtime'],
];

$row = static function(array $item): string {
    $rawValue = (string) $item['value'];
    $value    = htmlspecialchars($rawValue, ENT_QUOTES, 'UTF-8');
    $label    = htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8');
    $source   = isset($item['source']) ? htmlspecialchars((string)$item['source'], ENT_QUOTES, 'UTF-8') : '';
    $mono     = !empty($item['mono']);
    $danger   = !empty($item['danger']);

    $valueClass = 'text-sm break-all '
        . ($mono ? 'font-mono ' : '')
        . ($danger ? 'text-amber-700' : 'text-gray-900');

    // Auto-link http(s) URLs and file paths so an operator can hop to the
    // live site / open a docs page in one click instead of selecting +
    // copying the value out of the read-only field.
    if (preg_match('#^https?://#', $rawValue)) {
        $value = sprintf(
            '<a href="%s" target="_blank" rel="noopener" class="text-blue-500 hover:underline">%s</a>',
            $value, $value
        );
    }

    $sourceHtml = $source !== ''
        ? '<p class="text-xs text-gray-500 mt-1">edit on server: <code class="font-mono">' . $source . '</code></p>'
        : '';

    return sprintf(
        '<div class="px-4 py-3 grid grid-cols-3 gap-4 items-start">'
            . '<div class="text-sm text-gray-700 font-medium">%s</div>'
            . '<div class="col-span-2"><div class="%s">%s</div>%s</div>'
        . '</div>',
        $label, $valueClass, $value, $sourceHtml
    );
};
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-100"><?= $pageTitle ?></h1>
        <p class="text-sm text-gray-400 mt-1">Read-only view of site config. Edit values directly on the server, then reload this page.</p>
    </div>
</div>

<!-- Tab nav -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="flex border-b">
        <?php foreach ($tabs as $key => $meta): ?>
            <?php $active = ($tab === $key); ?>
            <a href="/admin/settings?tab=<?= $key ?>"
               class="px-5 py-3 text-sm font-medium border-b-2 transition-colors
                      <?= $active
                            ? 'border-blue-500 text-blue-700 bg-blue-50'
                            : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-50' ?>">
                <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                <span class="block text-[11px] font-normal text-gray-400"><?= htmlspecialchars($meta['desc'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tab content -->
    <?php if ($tab === 'general'): ?>
        <?php
        $genValues = $general['values'] ?? [];
        $genThemes = $general['themes'] ?? [];
        $genZones  = $general['timezones'] ?? [];
        require __DIR__ . '/general.php';
        ?>
    <?php elseif ($tab === 'appearance'): ?>
        <div class="p-6">
            <?php if (empty($appearance['themes'])): ?>
                <p class="text-sm text-gray-500">No themes found in <code class="font-mono">themes/</code>.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($appearance['themes'] as $theme): ?>
                        <div class="border <?= $theme['active'] ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white' ?> rounded-lg p-4 flex flex-col">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900"><?= htmlspecialchars($theme['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p class="text-xs text-gray-500 font-mono mt-0.5"><?= htmlspecialchars($theme['slug'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <?php if ($theme['active']): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Active</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($theme['description'])): ?>
                                <p class="text-sm text-gray-600 mb-3 flex-1"><?= htmlspecialchars($theme['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php else: ?>
                                <p class="text-sm text-gray-400 italic mb-3 flex-1">No description.</p>
                            <?php endif; ?>
                            <?php if (!$theme['active']): ?>
                                <form method="POST" action="/admin/settings/theme">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="theme" value="<?= htmlspecialchars($theme['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit"
                                            class="w-full px-3 py-2 bg-gray-900 text-white text-sm font-medium rounded hover:bg-gray-800 transition-colors">
                                        Activate
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    Switching writes <code class="font-mono">ACTIVE_THEME=&lt;slug&gt;</code> to <code class="font-mono">.env</code>. Public site picks it up on the next page load.
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="divide-y">
            <?php
            $rows = match ($tab) {
                'site'     => $site,
                'security' => $security,
                'email'    => $email,
                'about'    => $about,
            };
            ?>
            <?php foreach ($rows as $item): ?>
                <?= $row($item) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($tab === 'security'): ?>
<div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700">
    <p class="font-semibold text-gray-800 mb-1">Why is this read-only?</p>
    <p>
        These values live in <code class="font-mono">.env</code> and are HMAC- or auth-load-bearing.
        Editing them through a web form would risk self-lockout (wrong IP CIDR), credential leaks,
        or invalidated session tokens. Edit <code class="font-mono">.env</code> on the server, then
        restart PHP-FPM or reload this page.
    </p>
</div>
<?php endif; ?>

<?php if ($tab === 'about'): ?>
<div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700">
    <p>Bird CMS &middot; markdown-first PHP CMS. <a href="https://gitlab.com/codimcc/bird-cms" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Source</a></p>
</div>
<?php endif; ?>
