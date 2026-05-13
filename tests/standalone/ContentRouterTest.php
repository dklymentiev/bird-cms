<?php
/**
 * Standalone test for App\Http\ContentRouter URL pattern grammar.
 *
 * Specifically covers the optional-placeholder syntax `{name?}` added in #1720.
 * Run:
 *
 *   php tests/standalone/ContentRouterTest.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/Content/ContentRepositoryInterface.php';
require_once __DIR__ . '/../../app/Http/ContentRouter.php';

use App\Http\ContentRouter;
use App\Content\ContentRepositoryInterface;

// Minimal in-memory repository
final class FakeRepo implements ContentRepositoryInterface
{
    public function __construct(private array $items = []) {}
    public function all(): array { return $this->items; }
    public function findByParams(array $params): ?array
    {
        foreach ($this->items as $item) {
            if (($item['slug'] ?? '') !== ($params['slug'] ?? '__')) continue;
            if (($params['category'] ?? null) !== null && ($item['category'] ?? null) !== $params['category']) continue;
            if (($params['subcategory'] ?? null) !== null && ($item['subcategory'] ?? null) !== $params['subcategory']) continue;
            // If pattern had subcategory required but item lacks one -> mismatch
            // If subcategory absent in URL params, item.subcategory must also be empty
            if (array_key_exists('subcategory', $params) && $params['subcategory'] === null && !empty($item['subcategory'])) continue;
            return $item;
        }
        return null;
    }
    public function find(string $slug): ?array { return $this->findByParams(['slug' => $slug]); }
}

$failures = [];
$pass = 0;

function ok(bool $cond, string $msg, array &$failures, int &$pass): void
{
    if ($cond) { $pass++; return; }
    $failures[] = $msg;
}

// Build router with one type using the new optional-placeholder pattern
$repo = new FakeRepo([
    ['slug' => 'flat',     'category' => 'blog', 'subcategory' => null],
    ['slug' => 'nested',   'category' => 'blog', 'subcategory' => 'resources'],
]);

$config = [
    'types' => [
        'articles' => [
            'url' => '/{category}/{subcategory?}/{slug}',
            'repository' => FakeRepo::class,
            'source' => 'unused',
        ],
    ],
];

// Inject the fake repo via reflection (router otherwise news up the class)
$router = new ContentRouter($config);
$rc = new ReflectionClass($router);
$prop = $rc->getProperty('repositories');
$prop->setAccessible(true);
$prop->setValue($router, [FakeRepo::class => $repo]);

// Test 1: short URL still matches when subcategory absent
{
    $result = $router->match('/blog/flat');
    ok($result !== null, 'T1: /blog/flat matches', $failures, $pass);
    ok(($result['item']['slug'] ?? '') === 'flat', 'T1: resolved to flat article', $failures, $pass);
}

// Test 2: long URL matches when subcategory present
{
    $result = $router->match('/blog/resources/nested');
    ok($result !== null, 'T2: /blog/resources/nested matches', $failures, $pass);
    ok(($result['item']['slug'] ?? '') === 'nested', 'T2: resolved to nested article', $failures, $pass);
    ok(($result['params']['subcategory'] ?? null) === 'resources', 'T2: subcategory captured', $failures, $pass);
}

// Test 3: long URL doesn't match flat article
{
    $result = $router->match('/blog/wrong/flat');
    ok($result === null, 'T3: /blog/wrong/flat does not match (no such subcategory for flat)', $failures, $pass);
}

// Test 4: short URL doesn't accidentally match nested article
{
    $result = $router->match('/blog/nested');
    ok($result === null || ($result['item']['slug'] ?? '') !== 'nested',
        'T4: nested article not reachable via /blog/nested (subcategory required by item)',
        $failures, $pass);
}

// Test 5: allUrls() emits long form for nested + short form for flat
{
    $urls = $router->allUrls('https://example.com');
    $locs = array_column($urls, 'loc');
    ok(in_array('https://example.com/blog/flat', $locs, true), 'T5: short URL emitted for flat', $failures, $pass);
    ok(in_array('https://example.com/blog/resources/nested', $locs, true), 'T5: long URL emitted for nested', $failures, $pass);
    ok(!in_array('https://example.com/blog/nested', $locs, true), 'T5: short URL NOT emitted for nested', $failures, $pass);
}

// Test 6: backward compat — pattern without optional placeholder still works
{
    $repo2 = new FakeRepo([['slug' => 'about', 'category' => 'pages']]);
    $router2 = new ContentRouter([
        'types' => ['pages' => [
            'url' => '/{category}/{slug}',
            'repository' => FakeRepo::class,
            'source' => 'unused',
        ]],
    ]);
    $rc2 = new ReflectionClass($router2);
    $p2 = $rc2->getProperty('repositories');
    $p2->setAccessible(true);
    $p2->setValue($router2, [FakeRepo::class => $repo2]);

    $r = $router2->match('/pages/about');
    ok($r !== null && ($r['item']['slug'] ?? '') === 'about', 'T6: legacy pattern unchanged', $failures, $pass);

    $urls = $router2->allUrls('https://example.com');
    ok(in_array('https://example.com/pages/about', array_column($urls, 'loc'), true),
        'T6: legacy allUrls unchanged', $failures, $pass);
}

echo "ContentRouter tests: $pass passed, " . count($failures) . " failed\n";
foreach ($failures as $msg) echo "  FAIL: $msg\n";
exit(count($failures) === 0 ? 0 : 1);
