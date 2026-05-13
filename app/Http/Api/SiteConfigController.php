<?php

declare(strict_types=1);

namespace App\Http\Api;

use App\Admin\SettingsController;

/**
 * /api/v1/site-config -- read + update the safe subset of site config.
 *
 * Mirrors the admin Settings -> General tab. Reads / writes go through
 * the SAME whitelist (SettingsController::GENERAL_ALLOWED_FIELDS) and
 * the SAME atomic rewriter (SettingsController::writeConfigApp). One
 * source of truth for "what is the operator allowed to change" --
 * APP_KEY, ADMIN_PASSWORD_HASH, ADMIN_ALLOWED_IPS, and every other
 * .env-resident secret are NEVER reachable through this endpoint
 * because the whitelist refuses to look at them.
 *
 * GET  returns the current values for the whitelisted fields plus
 *      the option lists callers need (themes, timezones).
 * PUT  updates them; same validation as the admin form.
 */
final class SiteConfigController
{
    public function show(): void
    {
        Response::json([
            'fields'    => $this->currentValues(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'themes'    => $this->discoverThemeSlugs(),
        ]);
    }

    public function update(): void
    {
        $payload = Request::json();

        // Re-read the current values up-front; any whitelist field the
        // caller doesn't send keeps its current value. PUT semantics
        // are "merge", not "replace the whole document", because most
        // mobile clients only know one or two fields at a time.
        $current = $this->currentValues();
        $values  = [];
        foreach (SettingsController::GENERAL_ALLOWED_FIELDS as $key) {
            $values[$key] = array_key_exists($key, $payload)
                ? trim((string) $payload[$key])
                : (string) $current[$key];
        }

        // Instantiate SettingsController without invoking its parent
        // ctor: Controller::__construct() calls enforceIpRestriction()
        // which 404s any caller not in ADMIN_ALLOWED_IPS, and the
        // public API authenticates with Bearer tokens instead. We only
        // need the validator + rewriter methods which are static-shaped
        // (no instance state beyond reading $this->discoverThemes()
        // from disk).
        $settings = (new \ReflectionClass(SettingsController::class))->newInstanceWithoutConstructor();

        try {
            $settings->validateGeneralFields($values);
        } catch (\InvalidArgumentException $e) {
            Response::error('invalid_input', $e->getMessage(), 400);
        }

        try {
            $configPath = $this->configAppPath();
            $settings->writeConfigApp($configPath, $values);
        } catch (\Throwable $e) {
            Response::error('write_failed', 'Failed to write config/app.php: ' . $e->getMessage(), 500);
        }

        Response::json([
            'ok'     => true,
            'fields' => $values,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function currentValues(): array
    {
        return [
            'site_name'        => (string) \config('site_name', ''),
            'site_description' => (string) \config('site_description', ''),
            'site_url'         => (string) \config('site_url', ''),
            'active_theme'     => (string) \config('active_theme', 'tailwind'),
            'timezone'         => (string) \config('timezone', 'UTC'),
            'language'         => (string) \config('language', 'en'),
        ];
    }

    private function configAppPath(): string
    {
        if (defined('SITE_CONFIG_PATH')) {
            return SITE_CONFIG_PATH . '/app.php';
        }
        $root = defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 3);
        return $root . '/config/app.php';
    }

    /**
     * @return list<string>
     */
    private function discoverThemeSlugs(): array
    {
        $themesPath = defined('ENGINE_THEMES_PATH') ? ENGINE_THEMES_PATH : (defined('SITE_ROOT') ? SITE_ROOT . '/themes' : dirname(__DIR__, 3) . '/themes');
        if (!is_dir($themesPath)) return [];
        $excluded = ['admin', 'install'];
        $out = [];
        foreach (scandir($themesPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (in_array($entry, $excluded, true)) continue;
            if (!is_dir($themesPath . '/' . $entry)) continue;
            $out[] = $entry;
        }
        sort($out);
        return $out;
    }

}
