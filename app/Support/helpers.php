<?php

declare(strict_types=1);

use App\Support\Config;
use App\Theme\ThemeManager;

function config(?string $key = null, mixed $default = null): mixed
{
    if ($key === null) {
        return Config::all();
    }

    return Config::get($key, $default);
}

function content_path(string $path = ''): string
{
    $base = rtrim(config('content_dir'), '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function article_path(string $path = ''): string
{
    $base = rtrim(config('articles_dir'), '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function image_path(string $path = ''): string
{
    $base = rtrim(config('images_dir'), '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function cache_path(string $path = ''): string
{
    $base = rtrim(config('cache_dir'), '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function theme_manager(): ThemeManager
{
    static $manager = null;
    if ($manager === null) {
        $manager = new ThemeManager(config('themes_path'), config('active_theme'));
    }

    return $manager;
}

// === Site Identity Helpers ===

function site_name(): string
{
    return config('site_name', 'My Site');
}

function site_tagline(): string
{
    return config('site_tagline', '');
}

function site_description(): string
{
    return config('site_description', '');
}

function site_url(string $path = ''): string
{
    $base = rtrim(config('site_url', ''), '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function site_logo(): string
{
    return config('site_logo', '/assets/images/logo.svg');
}

// === Contact Helpers ===

function contact_email(string $type = 'general'): string
{
    $contacts = config('contacts', []);
    return $contacts[$type] ?? $contacts['general'] ?? '';
}

function social_link(string $network): ?string
{
    $social = config('social', []);
    return $social[$network] ?? null;
}

// === Branding Helpers ===

function publisher_name(): string
{
    return config('branding.publisher_name', config('site_name', 'Publisher'));
}

function default_author(): string
{
    return config('branding.default_author', 'Editorial Team');
}

function copyright_text(): string
{
    $holder = config('branding.copyright_holder', config('site_name'));
    $startYear = config('branding.copyright_year_start', date('Y'));
    $currentYear = date('Y');

    if ($startYear == $currentYear) {
        return "© {$currentYear} {$holder}";
    }
    return "© {$startYear}–{$currentYear} {$holder}";
}

// === SEO Helpers ===

function page_title(string $title): string
{
    $separator = config('seo.title_separator', '·');
    $suffix = config('seo.title_suffix') ?? config('site_name');
    $title = trim($title);
    if ($title === '') {
        return (string) $suffix;
    }
    return "{$title} {$separator} {$suffix}";
}

function search_prefix(): string
{
    return config('seo.google_search_prefix', '');
}

function default_og_image(): ?string
{
    $img = config('seo.default_og_image');
    return $img ? (string) $img : null;
}

function tailwind_cdn_script(): string
{
    $url = config('theme.tailwind_cdn_url');
    if (!$url) {
        return '';
    }
    $safe = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
    return '<script src="' . $safe . '"></script>';
}

/**
 * Robots meta tag for the current request, honouring per-URL overrides
 * stored in storage/url-meta.json by /admin/pages.
 *
 * Returns the full <meta name="robots" content="noindex,nofollow"> tag
 * when the operator has flagged the URL noindex, otherwise empty string.
 * Themes call this in <head> alongside other meta.
 */
function robots_meta_for_current_url(): string
{
    static $meta = null;
    if ($meta === null) {
        $path = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__, 2)) . '/storage/url-meta.json';
        $meta = is_file($path)
            ? (json_decode((string) file_get_contents($path), true) ?: [])
            : [];
    }
    $reqPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (!empty($meta[$reqPath]['noindex'])) {
        return '<meta name="robots" content="noindex,nofollow">';
    }
    return '';
}

// === Feature Helpers ===

function feature_enabled(string $feature): bool
{
    $features = config('features', []);
    return (bool) ($features[$feature] ?? false);
}

// === Schema.org Helpers ===

/**
 * Render LocalBusiness schema from config
 */
function render_local_business_schema(): string
{
    $schema = \App\Support\SchemaGenerator::generateLocalBusiness();
    return $schema ? \App\Support\SchemaGenerator::render([$schema]) : '';
}

/**
 * Render schema for a service page
 */
function render_service_schema(array $serviceData): string
{
    return \App\Support\SchemaGenerator::generateForPageAndRender($serviceData, 'service');
}

/**
 * Render schema for an area/location page
 */
function render_area_schema(array $areaData): string
{
    return \App\Support\SchemaGenerator::generateForPageAndRender($areaData, 'area');
}

/**
 * Render FAQ schema from array
 */
function render_faq_schema(array $faqs): string
{
    $schemas = \App\Support\SchemaGenerator::generateForPage(['faqs' => $faqs], 'page');
    return \App\Support\SchemaGenerator::render($schemas);
}

/**
 * Render article/blog schema
 */
function render_article_schema(array $article): string
{
    return \App\Support\SchemaGenerator::generateAndRender($article, ['schema' => 'article']);
}

// === Phone Widget ===

/**
 * Render click-to-reveal phone widget with location tracking
 *
 * Requires tracker.js to be loaded for tracking and reveal functionality.
 * Phone number is configured in config/app.php under 'phone' key.
 *
 * @param string $location Location identifier for tracking (e.g., 'header', 'hero', 'footer')
 * @param string $style Widget style: button, link, minimal, header, hero, hero-dark, footer, footer-button, contact, floating, inline, cta-text
 * @param string $class Additional CSS classes
 * @return string HTML output
 */
function phone_widget(string $location = 'unknown', string $style = 'button', string $class = ''): string
{
    $phoneConfig = config('phone', []);
    $phone = $phoneConfig['display'] ?? '(000) 000-0000';
    $phoneFull = $phoneConfig['full'] ?? '+10000000000';
    $phoneMasked = $phoneConfig['masked'] ?? preg_replace('/\d{4}$/', '••••', $phone);

    $phoneIcon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>';

    $attrs = sprintf(
        'onclick="revealPhone(this)" data-phone="%s" data-display="%s" data-location="%s"',
        htmlspecialchars($phoneFull),
        htmlspecialchars($phone),
        htmlspecialchars($location)
    );

    // All styles now hide the actual number - show "Call us" instead
    return match($style) {
        'button' => sprintf(
            '<button type="button" %s class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition cursor-pointer %s">%s<span>Call us</span></button>',
            $attrs, $class, $phoneIcon
        ),
        'link' => sprintf(
            '<span %s class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 font-semibold cursor-pointer %s">%s Call us</span>',
            $attrs, $class, $phoneIcon
        ),
        'minimal' => sprintf(
            '<span %s class="cursor-pointer hover:text-primary-600 transition underline %s">Call us</span>',
            $attrs, $class
        ),
        'header' => sprintf(
            '<span %s class="inline-flex items-center gap-1 text-primary-600 font-bold cursor-pointer hover:text-primary-700 transition %s">%s Call us</span>',
            $attrs, $class, $phoneIcon
        ),
        'hero', 'hero-commercial' => sprintf(
            '<button type="button" %s class="inline-flex items-center justify-center gap-2 border-2 border-white/80 text-white hover:bg-white hover:text-primary-700 px-8 py-4 rounded-xl font-bold text-lg whitespace-nowrap transition cursor-pointer %s">%s<span>Call us</span></button>',
            $attrs, $class, $phoneIcon
        ),
        'hero-dark' => sprintf(
            '<button type="button" %s class="inline-flex items-center justify-center gap-2 border-2 border-white/80 text-white hover:bg-white hover:text-gray-900 px-8 py-4 rounded-xl font-bold text-lg whitespace-nowrap transition cursor-pointer %s">%s<span>Call us</span></button>',
            $attrs, $class, $phoneIcon
        ),
        'footer', 'footer-hidden' => sprintf(
            '<span %s class="text-gray-300 cursor-pointer hover:text-primary-400 transition underline underline-offset-2 %s">Call us</span>',
            $attrs, $class
        ),
        'footer-button' => sprintf(
            '<button type="button" %s class="flex-1 flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-800 py-3 rounded-lg font-semibold transition cursor-pointer %s">%s<span>Call us</span></button>',
            $attrs, $class, $phoneIcon
        ),
        'contact' => sprintf(
            '<div %s class="flex items-center gap-4 p-4 bg-primary-50 rounded-xl hover:bg-primary-100 transition cursor-pointer %s"><div class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center"><svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></div><div><div class="text-sm text-gray-500">Phone</div><div class="font-semibold text-gray-900">Tap to reveal</div></div></div>',
            $attrs, $class
        ),
        'floating' => sprintf(
            '<button type="button" %s class="w-14 h-14 bg-primary-600 hover:bg-primary-700 text-white rounded-full shadow-lg flex items-center justify-center transition cursor-pointer %s" title="Call us">%s</button>',
            $attrs, $class, $phoneIcon
        ),
        'inline' => sprintf(
            '<span %s class="text-primary-600 hover:text-primary-700 font-semibold cursor-pointer underline %s">call us</span>',
            $attrs, $class
        ),
        'cta-text' => sprintf(
            '<span %s class="text-blue-400 hover:text-blue-300 font-semibold cursor-pointer underline %s">call us</span>',
            $attrs, $class
        ),
        default => sprintf(
            '<span %s class="cursor-pointer underline %s">Call us</span>',
            $attrs, $class
        ),
    };
}

/**
 * Generate email reveal widget HTML
 *
 * @param string $location Location identifier for tracking
 * @param string $style Widget style: link, minimal, footer, contact, inline
 * @param string $class Additional CSS classes
 * @return string HTML output
 */
function email_widget(string $location = 'unknown', string $style = 'link', string $class = ''): string
{
    $emailConfig = config('email', []);
    $email = $emailConfig['address'] ?? 'info@example.com';
    $emailMasked = $emailConfig['masked'] ?? preg_replace('/@.*/', '@•••', $email);

    $emailIcon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';

    $attrs = sprintf(
        'onclick="revealEmail(this)" data-email="%s" data-location="%s"',
        htmlspecialchars($email),
        htmlspecialchars($location)
    );

    // All styles now hide the actual email - show "Email us" instead
    return match($style) {
        'link' => sprintf(
            '<span %s class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 font-semibold cursor-pointer %s">%s Email us</span>',
            $attrs, $class, $emailIcon
        ),
        'minimal' => sprintf(
            '<span %s class="cursor-pointer hover:text-primary-600 transition underline %s">Email us</span>',
            $attrs, $class
        ),
        'footer', 'footer-hidden' => sprintf(
            '<span %s class="text-gray-300 cursor-pointer hover:text-primary-400 transition underline underline-offset-2 %s">Email us</span>',
            $attrs, $class
        ),
        'contact' => sprintf(
            '<div %s class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition cursor-pointer %s"><div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center"><svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div><div><div class="text-sm text-gray-500">Email</div><div class="font-semibold text-gray-900">Tap to reveal</div></div></div>',
            $attrs, $class
        ),
        'inline' => sprintf(
            '<span %s class="text-primary-600 hover:text-primary-700 cursor-pointer underline %s">email us</span>',
            $attrs, $class
        ),
        default => sprintf(
            '<span %s class="cursor-pointer underline %s">Email us</span>',
            $attrs, $class
        ),
    };
}
