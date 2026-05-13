<?php
/**
 * Settings: General tab.
 *
 * Editable site identity form. Posts to /admin/settings/general/save,
 * which validates a strict whitelist and atomically rewrites
 * config/app.php. Sensitive values (APP_KEY, admin credentials, IP
 * allowlists) intentionally do NOT live here -- they stay in .env and
 * are surfaced read-only on the Security tab.
 *
 * Provided by SettingsController::general() / index() via the parent
 * settings/index.php partial:
 *   $genValues  array<string,string>   current values for each field
 *   $genThemes  list<array{slug,name}> selectable frontend themes
 *   $genZones   list<string>           DateTimeZone::listIdentifiers()
 *   $csrf       string                 form token
 */

$val = static function (string $key) use ($genValues): string {
    return htmlspecialchars((string)($genValues[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};
?>

<div class="p-6">
    <form method="POST" action="/admin/settings/general/save" class="space-y-6">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Site name -->
        <div>
            <label for="site_name" class="block text-sm font-medium text-gray-200 mb-1">
                <i class="ri-global-line align-middle mr-1"></i>
                Site name
            </label>
            <input type="text"
                   id="site_name"
                   name="site_name"
                   value="<?= $val('site_name') ?>"
                   required
                   maxlength="100"
                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <p class="text-xs text-gray-400 mt-1">Public site title (max 100 chars).</p>
        </div>

        <!-- Site description -->
        <div>
            <label for="site_description" class="block text-sm font-medium text-gray-200 mb-1">
                <i class="ri-text-snippet align-middle mr-1"></i>
                Site description
            </label>
            <textarea id="site_description"
                      name="site_description"
                      rows="2"
                      maxlength="280"
                      class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?= $val('site_description') ?></textarea>
            <p class="text-xs text-gray-400 mt-1">Used for meta description and OG tags (max 280 chars).</p>
        </div>

        <!-- Site URL -->
        <div>
            <label for="site_url" class="block text-sm font-medium text-gray-200 mb-1">
                <i class="ri-link align-middle mr-1"></i>
                Site URL
            </label>
            <input type="url"
                   id="site_url"
                   name="site_url"
                   value="<?= $val('site_url') ?>"
                   required
                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-gray-100 rounded-md font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="https://example.com">
            <p class="text-xs text-amber-300 mt-1">
                <i class="ri-alert-line align-middle"></i>
                Changing this invalidates HMAC preview tokens. Existing draft preview links will return 403.
            </p>
        </div>

        <!-- Active theme -->
        <div>
            <label for="active_theme" class="block text-sm font-medium text-gray-200 mb-1">
                <i class="ri-palette-line align-middle mr-1"></i>
                Active theme
            </label>
            <select id="active_theme"
                    name="active_theme"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <?php foreach ($genThemes as $theme): ?>
                    <?php $slug = htmlspecialchars((string)$theme['slug'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php $name = htmlspecialchars((string)$theme['name'], ENT_QUOTES, 'UTF-8'); ?>
                    <option value="<?= $slug ?>" <?= ($genValues['active_theme'] ?? '') === $theme['slug'] ? 'selected' : '' ?>>
                        <?= $name ?> (<?= $slug ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Frontend themes only; <code class="font-mono">admin/</code> and <code class="font-mono">install/</code> are excluded.</p>
        </div>

        <!-- Timezone -->
        <div>
            <label for="timezone" class="block text-sm font-medium text-gray-200 mb-1">
                <i class="ri-time-line align-middle mr-1"></i>
                Timezone
            </label>
            <select id="timezone"
                    name="timezone"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <?php foreach ($genZones as $zone): ?>
                    <?php $z = htmlspecialchars((string)$zone, ENT_QUOTES, 'UTF-8'); ?>
                    <option value="<?= $z ?>" <?= ($genValues['timezone'] ?? '') === $zone ? 'selected' : '' ?>><?= $z ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">IANA timezone (e.g. <code class="font-mono">America/Chicago</code>).</p>
        </div>

        <!-- Language -->
        <div>
            <label for="language" class="block text-sm font-medium text-gray-200 mb-1">
                <i class="ri-translate-2 align-middle mr-1"></i>
                Language
            </label>
            <input type="text"
                   id="language"
                   name="language"
                   value="<?= $val('language') ?>"
                   required
                   pattern="[a-z]{2}(-[A-Z]{2})?"
                   maxlength="5"
                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-gray-100 rounded-md font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="en">
            <p class="text-xs text-gray-400 mt-1">Locale code: 2-letter (<code class="font-mono">en</code>) or 5-char (<code class="font-mono">en-US</code>).</p>
        </div>

        <div class="pt-2 border-t border-slate-700 flex items-center gap-3">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-500 transition-colors">
                <i class="ri-save-line align-middle mr-1"></i>
                Save general settings
            </button>
            <p class="text-xs text-gray-400">
                Writes <code class="font-mono">config/app.php</code> atomically. <code class="font-mono">.env</code> values still override at runtime.
            </p>
        </div>
    </form>
</div>

<div class="mt-4 p-4 bg-slate-900/40 border border-slate-700 rounded-lg text-sm text-gray-300">
    <p class="font-semibold text-gray-200 mb-1">What this form does not edit</p>
    <p>
        <code class="font-mono">APP_KEY</code>, <code class="font-mono">ADMIN_PASSWORD_HASH</code>,
        <code class="font-mono">ADMIN_ALLOWED_IPS</code>, and path overrides remain in
        <code class="font-mono">.env</code>. Editing them through a web form risks self-lockout
        and HMAC-token invalidation, so they are intentionally not exposed here.
    </p>
</div>
