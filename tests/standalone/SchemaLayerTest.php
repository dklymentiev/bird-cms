<?php
/**
 * Standalone tests for the AEO/Schema layer changes from #1706.
 *
 *   php tests/standalone/SchemaLayerTest.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/SchemaGenerator.php';
require_once __DIR__ . '/../../app/helpers/schema.php';

// Stubs for engine-side helpers SchemaGenerator references.
if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return $key === 'site_url' ? 'https://example.com' : $default;
    }
}
if (!function_exists('publisher_name')) {
    function publisher_name(): string { return 'Test Publisher'; }
}
if (!function_exists('default_og_image')) {
    function default_og_image(): string { return 'https://example.com/og.png'; }
}
if (!function_exists('site_name')) {
    function site_name(): string { return 'Test Site'; }
}
if (!function_exists('site_description')) {
    function site_description(): string { return 'Test'; }
}
if (!function_exists('author_for')) {
    function author_for(string $slug): array { return ['name' => 'Test Author']; }
}
if (!function_exists('site_url')) {
    function site_url(string $path = ''): string { return 'https://example.com' . $path; }
}
if (!function_exists('asset_url')) {
    function asset_url(string $path): string { return 'https://example.com' . $path; }
}

use App\Support\SchemaGenerator;

$failures = [];
$pass = 0;

function ok(bool $cond, string $msg, array &$failures, int &$pass): void
{
    if ($cond) { $pass++; return; }
    $failures[] = $msg;
}

// E1: generate() emits BreadcrumbList when $meta['breadcrumb'] is present.
{
    $article = ['type' => 'article', 'title' => 't', 'slug' => 's'];
    $meta = [
        'schema' => 'article',
        'breadcrumb' => [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Blog', 'url' => '/blog'],
            ['label' => 't', 'url' => null],
        ],
    ];
    $schemas = SchemaGenerator::generate($article, $meta);

    $types = array_column($schemas, '@type');
    ok(in_array('BreadcrumbList', $types, true), 'E1: BreadcrumbList emitted from generate()', $failures, $pass);

    // Position must be first (prepended)
    ok(($schemas[0]['@type'] ?? '') === 'BreadcrumbList', 'E1: BreadcrumbList is first in array', $failures, $pass);
}

// E1: buildBreadcrumbSchema accepts label alias for name.
{
    $sch = SchemaGenerator::buildBreadcrumbSchema([
        ['label' => 'Home', 'url' => '/'],
    ]);
    ok(($sch['itemListElement'][0]['name'] ?? '') === 'Home', 'E1: label aliased to name', $failures, $pass);
}

// E1: relative URLs resolved against config('site_url').
{
    $sch = SchemaGenerator::buildBreadcrumbSchema([
        ['label' => 'Home', 'url' => '/'],
    ]);
    $item = $sch['itemListElement'][0]['item'] ?? '';
    ok(str_starts_with($item, 'https://example.com/'), 'E1: relative URL resolved', $failures, $pass);
}

// E1: empty url treated as "current page" (REQUEST_URI).
{
    $_SERVER['REQUEST_URI'] = '/blog/foo';
    $sch = SchemaGenerator::buildBreadcrumbSchema([
        ['label' => 'Current', 'url' => null],
    ]);
    $item = $sch['itemListElement'][0]['item'] ?? '';
    ok($item === 'https://example.com/blog/foo', "E1: null url -> current request, got '$item'", $failures, $pass);
}

// E3: schema_article wrapper returns same as static call.
{
    $article = ['type' => 'article', 'title' => 't', 'slug' => 's'];
    $a = schema_article($article);
    $b = SchemaGenerator::generate($article);
    ok($a === $b, 'E3: schema_article == SchemaGenerator::generate', $failures, $pass);
}

// E3: schema_faq wrapper.
{
    $faq = schema_faq([
        ['q' => 'Q1', 'a' => 'A1'],
    ]);
    ok(($faq['@type'] ?? '') === 'FAQPage', 'E3: schema_faq returns FAQPage', $failures, $pass);
}

// E3: schema_breadcrumb wrapper.
{
    $crumb = schema_breadcrumb([
        ['label' => 'Home', 'url' => '/'],
    ]);
    ok(($crumb['@type'] ?? '') === 'BreadcrumbList', 'E3: schema_breadcrumb returns BreadcrumbList', $failures, $pass);
}

// E3: buildFAQSchema is now public (no PHP error on direct call).
{
    $faq = SchemaGenerator::buildFAQSchema([
        ['q' => 'Q', 'a' => 'A'],
    ]);
    ok(($faq['@type'] ?? '') === 'FAQPage', 'E3: buildFAQSchema is public + works', $failures, $pass);
}

// Backward compat: generate() without breadcrumb still emits original schema set.
{
    $article = ['type' => 'article', 'title' => 't', 'slug' => 's'];
    $schemas = SchemaGenerator::generate($article, ['schema' => 'article']);
    $types = array_column($schemas, '@type');
    ok(!in_array('BreadcrumbList', $types, true), 'BC: no breadcrumb -> no BreadcrumbList', $failures, $pass);
    ok(in_array('Article', $types, true), 'BC: Article schema still emitted', $failures, $pass);
}

echo "Schema layer tests: $pass passed, " . count($failures) . " failed\n";
foreach ($failures as $msg) echo "  FAIL: $msg\n";
exit(count($failures) === 0 ? 0 : 1);
