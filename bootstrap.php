<?php
/**
 * Bird CMS Engine Bootstrap
 *
 * Path resolution:
 * - SITE_ROOT      = where this bootstrap is loaded from (or pre-defined by caller)
 * - ENGINE_ROOT    = SITE_ROOT/engine if that exists (versioned 2.0 layout),
 *                    else SITE_ROOT (legacy single-dir layout for pre-2.0 sites)
 * - SITE_*_PATH    = always SITE_ROOT/<dir> (config, content, storage)
 * - ENGINE_*_PATH  = always ENGINE_ROOT/<dir> (app, themes)
 *
 * Versioned layout: SITE_ROOT/engine -> versions/X.Y.Z/ (symlink, atomic switch).
 * Legacy layout: engine and site files share SITE_ROOT (used by 4 prod sites
 * pre-migration).
 */

declare(strict_types=1);

if (!defined('SITE_ROOT')) {
    // Walk up from this file looking for the canonical site marker:
    // a config/app.php that is required by every Bird CMS site. This
    // works for both layouts:
    //   - legacy: bootstrap.php sits in site root, config/app.php is its sibling
    //   - versioned 2.0: bootstrap.php is inside versions/X.Y.Z/ (or accessed
    //     through the engine symlink), config/app.php is at the grandparent
    $candidate = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        if (file_exists($candidate . '/config/app.php')) {
            define('SITE_ROOT', $candidate);
            break;
        }
        $parent = dirname($candidate);
        if ($parent === $candidate) {
            break;
        }
        $candidate = $parent;
    }
    if (!defined('SITE_ROOT')) {
        throw new \RuntimeException(
            'Cannot resolve SITE_ROOT: walked up from ' . __DIR__ .
            ' looking for config/app.php, found nothing.'
        );
    }
}

if (!defined('ENGINE_ROOT')) {
    $engineDir = SITE_ROOT . '/engine';
    define('ENGINE_ROOT', is_dir($engineDir) ? $engineDir : SITE_ROOT);
}

define('SITE_CONFIG_PATH',  SITE_ROOT . '/config');
define('SITE_CONTENT_PATH', SITE_ROOT . '/content');
define('SITE_STORAGE_PATH', SITE_ROOT . '/storage');
define('ENGINE_APP_PATH',   ENGINE_ROOT . '/app');
define('ENGINE_THEMES_PATH', ENGINE_ROOT . '/themes');

// Load .env file
$envFile = SITE_ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
                $value = $matches[1];
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Honor DEBUG=true for developer-friendly errors; otherwise hide internals
// from HTTP responses. Errors continue to land in PHP error_log either way.
$debug = filter_var($_ENV['DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_STRICT));

// Refuse to boot with a missing or known-insecure APP_KEY.
// APP_KEY signs HMAC preview tokens; a default value lets attackers forge them.
// Generate per-site with: php -r 'echo bin2hex(random_bytes(32));'
//
// The 'topic-wise-secret-key-change-me' sentinel is intentional: this project
// was previously called topic-wise, and any installation that still carries
// that legacy default must be refused. Do not remove on cleanup passes.
$appKey = $_ENV['APP_KEY'] ?? '';
if (in_array($appKey, ['', 'change-this-in-production', 'topic-wise-secret-key-change-me'], true)) {
    throw new \RuntimeException(
        'APP_KEY is missing or set to a known-insecure default in ' . $envFile
        . '. Generate a random value: php -r \'echo bin2hex(random_bytes(32));\''
    );
}

$autoloadPath = SITE_ROOT . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

$config = require SITE_CONFIG_PATH . '/app.php';

date_default_timezone_set($config['timezone'] ?? 'UTC');

require_once ENGINE_APP_PATH . '/Support/Config.php';
require_once ENGINE_APP_PATH . '/Support/helpers.php';
require_once ENGINE_APP_PATH . '/Support/SchemaGenerator.php';
require_once ENGINE_APP_PATH . '/helpers/authors.php';
require_once ENGINE_APP_PATH . '/helpers/schema.php';
require_once ENGINE_APP_PATH . '/Support/Markdown.php';
require_once ENGINE_APP_PATH . '/Support/ImageResolver.php';
require_once ENGINE_APP_PATH . '/Support/YamlMini.php';
require_once ENGINE_APP_PATH . '/Support/YamlHelper.php';
require_once ENGINE_APP_PATH . '/Support/TableOfContents.php';
require_once ENGINE_APP_PATH . '/Support/LinkFilter.php';
require_once ENGINE_APP_PATH . '/Support/Analytics.php';
require_once ENGINE_APP_PATH . '/Support/UrlMeta.php';
require_once ENGINE_APP_PATH . '/Support/PreviewToken.php';
require_once ENGINE_APP_PATH . '/Support/HtmlCache.php';
require_once ENGINE_APP_PATH . '/Support/EditLog.php';
require_once ENGINE_APP_PATH . '/Content/FrontMatter.php';
require_once ENGINE_APP_PATH . '/Content/ContentRepositoryInterface.php';
require_once ENGINE_APP_PATH . '/Content/AtomicMarkdownWrite.php';
require_once ENGINE_APP_PATH . '/Content/ContentCache.php';
require_once ENGINE_APP_PATH . '/Content/ArticleRepository.php';
require_once ENGINE_APP_PATH . '/Content/AreaRepository.php';
require_once ENGINE_APP_PATH . '/Content/ProjectRepository.php';
require_once ENGINE_APP_PATH . '/Content/MetricsRepository.php';
require_once ENGINE_APP_PATH . '/Content/PageRepository.php';
require_once ENGINE_APP_PATH . '/Content/ServiceRepository.php';
require_once ENGINE_APP_PATH . '/Newsletter/FileSubscriberRepository.php';
require_once ENGINE_APP_PATH . '/Theme/ThemeManager.php';
require_once ENGINE_APP_PATH . '/Http/ContentCollectors.php';
require_once ENGINE_APP_PATH . '/Http/ContentDescriptor.php';
require_once ENGINE_APP_PATH . '/Http/ContentRouter.php';
require_once ENGINE_APP_PATH . '/Http/HomeController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/AssetController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/LlmsTxtController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/SearchController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/PreviewController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/HomeController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/BlogPaginationController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/ContentTypeController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/PageController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/CategoryController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/ArticleController.php';
require_once ENGINE_APP_PATH . '/Http/Frontend/Dispatcher.php';

\App\Support\Config::boot($config);
