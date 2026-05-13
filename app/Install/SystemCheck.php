<?php

declare(strict_types=1);

namespace App\Install;

/**
 * Pre-install environment audit.
 *
 * Pure PHP, no bootstrap dependency -- this class runs while .env and
 * config/app.php still don't exist. Each check returns a row the wizard
 * renders verbatim; all-pass enables the "Continue" button on step 1.
 */
final class SystemCheck
{
    public const STATUS_PASS = 'pass';
    public const STATUS_WARN = 'warn';
    public const STATUS_FAIL = 'fail';

    private const MIN_PHP_VERSION = '8.1.0';

    /** Extensions the engine assumes at runtime. */
    private const REQUIRED_EXTENSIONS = [
        'mbstring'   => 'String handling for Markdown and YAML.',
        'json'       => 'Config files, analytics, install marker.',
        'openssl'    => 'Password hashing, HMAC preview tokens.',
        'curl'       => 'External fetches (sitemaps, IndexNow).',
        'intl'       => 'Locale-aware sorting and formatting.',
        'gd'         => 'Hero image optimization.',
        'pdo_sqlite' => 'Analytics + metrics database.',
        'fileinfo'   => 'Upload MIME detection.',
    ];

    /** @return list<array{label: string, status: string, hint: ?string}> */
    public static function run(string $siteRoot): array
    {
        return array_merge(
            [self::checkPhpVersion()],
            self::checkExtensions(),
            self::checkWritable($siteRoot)
        );
    }

    /** @return bool true if every check is pass (warn is allowed). */
    public static function allPass(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['status'] === self::STATUS_FAIL) {
                return false;
            }
        }
        return true;
    }

    /** @return array{label: string, status: string, hint: ?string} */
    private static function checkPhpVersion(): array
    {
        $current = PHP_VERSION;
        $ok = version_compare($current, self::MIN_PHP_VERSION, '>=');
        return [
            'label'  => 'PHP version >= ' . self::MIN_PHP_VERSION,
            'status' => $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            'hint'   => $ok ? 'Detected ' . $current : 'Detected ' . $current . ' -- upgrade PHP.',
        ];
    }

    /** @return list<array{label: string, status: string, hint: ?string}> */
    private static function checkExtensions(): array
    {
        $rows = [];
        foreach (self::REQUIRED_EXTENSIONS as $ext => $purpose) {
            $loaded = extension_loaded($ext);
            $rows[] = [
                'label'  => 'PHP extension: ' . $ext,
                'status' => $loaded ? self::STATUS_PASS : self::STATUS_FAIL,
                'hint'   => $loaded ? null : $purpose . ' Install via your PHP package.',
            ];
        }
        return $rows;
    }

    /** @return list<array{label: string, status: string, hint: ?string}> */
    private static function checkWritable(string $siteRoot): array
    {
        $paths = [
            'storage/'         => $siteRoot . '/storage',
            'content/articles' => $siteRoot . '/content/articles',
            'content/pages'    => $siteRoot . '/content/pages',
            'uploads/'         => $siteRoot . '/uploads',
            'config/'          => $siteRoot . '/config',
        ];
        $rows = [];
        foreach ($paths as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $writable = is_dir($path) && is_writable($path);
            $rows[] = [
                'label'  => 'Writable: ' . $label,
                'status' => $writable ? self::STATUS_PASS : self::STATUS_FAIL,
                'hint'   => $writable ? null : 'chown / chmod so the web user can write to ' . $path,
            ];
        }
        return $rows;
    }
}
