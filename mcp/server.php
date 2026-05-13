<?php
/**
 * Bird CMS MCP server (stdio).
 *
 * Speaks JSON-RPC 2.0 over newline-delimited stdin/stdout per the
 * Model Context Protocol stdio transport. Lets MCP-aware clients
 * (Claude Desktop, Cursor, Continue, Zed) manage Bird CMS articles
 * directly without a custom integration layer.
 *
 * Site root is determined by:
 *   1. BIRD_SITE_DIR env var (explicit)
 *   2. The current working directory if it contains config/app.php
 *      OR content/articles/
 *   3. Walks up from $argv[0] looking for content/articles/
 *
 * v0.1 tools:
 *   - list_articles, read_article, write_article
 *   - list_categories
 *
 * Roadmap (issues filed against feat/2026-positioning):
 *   - delete_article, list_pages, read_page, write_page
 *   - publish, unpublish, search
 */

declare(strict_types=1);

// MCP runs bootstrap-free (no .env, no Config::boot, no PSR-4) so we
// require_once the one engine class we share with the rest of the
// codebase: the YAML parser. Everything else stays hand-rolled to
// keep the dependency graph minimal.
require_once __DIR__ . '/../app/Support/YamlMini.php';
require_once __DIR__ . '/../app/Support/EditLog.php';

use App\Support\EditLog;
use App\Support\YamlMini;

// --------- Site root resolution ---------
//
// Explicit `global` declaration so that `require_once`-ing this file from
// inside a function or method (PHPUnit setUpBeforeClass, in particular)
// still publishes $siteRoot/$articlesDir/$pagesDir/$contentDir/$tools into
// the real global scope. Without this, the tool_* handlers' `global ...`
// statements fetch NULL and writes land at filesystem root. (Regression
// caught by the McpServerTest / McpAdminParityTest suites in v3.1.0-rc.5.)
global $siteRoot, $articlesDir, $pagesDir, $contentDir, $tools;

$siteRoot = getenv('BIRD_SITE_DIR') ?: '';
if ($siteRoot === '') {
    $cwd = getcwd() ?: __DIR__;
    if (is_dir($cwd . '/content/articles') || is_file($cwd . '/config/app.php')) {
        $siteRoot = $cwd;
    } else {
        // Walk up from this script
        $candidate = __DIR__;
        for ($i = 0; $i < 6; $i++) {
            if (is_dir($candidate . '/content/articles')) {
                $siteRoot = $candidate;
                break;
            }
            $candidate = dirname($candidate);
        }
    }
}
if ($siteRoot === '' || !is_dir($siteRoot . '/content/articles')) {
    fwrite(STDERR, "Bird CMS MCP: cannot find site root. Set BIRD_SITE_DIR or run from a Bird CMS site directory.\n");
    exit(1);
}

$siteRoot = rtrim(realpath($siteRoot) ?: $siteRoot, '/\\');
$articlesDir = $siteRoot . '/content/articles';
$pagesDir = $siteRoot . '/content/pages';
$contentDir = $siteRoot . '/content';

// EditLog points at the site's storage/data/edits.sqlite explicitly --
// the engine bootstrap is not loaded in MCP mode, so SITE_STORAGE_PATH
// isn't defined and the fallback path (relative to this file) would
// only happen to be right for single-site repos. Setting the override
// here means MCP writes against an arbitrary BIRD_SITE_DIR also land
// in the right db.
EditLog::useDatabase($siteRoot . '/storage/data/edits.sqlite');

// --------- Tools ---------

$tools = [
    'list_articles' => [
        'description' => 'List articles in the site, optionally filtered by category and status. Returns slug, title, category, status, date for each.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'category' => ['type' => 'string', 'description' => 'Filter to a single category slug (optional).'],
                'status' => ['type' => 'string', 'description' => 'Filter by status: published or draft (optional).'],
                'limit' => ['type' => 'integer', 'description' => 'Max number of results (default 100).'],
            ],
        ],
        'handler' => 'tool_list_articles',
    ],
    'read_article' => [
        'description' => 'Read a single article by category and slug. Returns frontmatter (meta.yaml fields) and the markdown body.',
        'inputSchema' => [
            'type' => 'object',
            'required' => ['category', 'slug'],
            'properties' => [
                'category' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
            ],
        ],
        'handler' => 'tool_read_article',
    ],
    'write_article' => [
        'description' => 'Create or update an article. Writes both <slug>.md (body) and <slug>.meta.yaml (frontmatter) atomically. If category dir does not exist, it is created.',
        'inputSchema' => [
            'type' => 'object',
            'required' => ['category', 'slug', 'frontmatter', 'body'],
            'properties' => [
                'category' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'frontmatter' => [
                    'type' => 'object',
                    'description' => 'YAML frontmatter as a JSON object. Required fields: title, description, date, type, status, tags, primary. Optional: hero_image, secondary.',
                ],
                'body' => ['type' => 'string', 'description' => 'Markdown body. No frontmatter inside; that goes in the .meta.yaml.'],
            ],
        ],
        'handler' => 'tool_write_article',
    ],
    'list_categories' => [
        'description' => 'List all category slugs that have at least one article.',
        'inputSchema' => ['type' => 'object', 'properties' => new stdClass()],
        'handler' => 'tool_list_categories',
    ],
    'delete_article' => [
        'description' => 'Delete an article (removes both .md and .meta.yaml). Idempotent: returns ok=true even if files were already absent.',
        'inputSchema' => [
            'type' => 'object',
            'required' => ['category', 'slug'],
            'properties' => [
                'category' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
            ],
        ],
        'handler' => 'tool_delete_article',
    ],
    'list_pages' => [
        'description' => 'List all pages (content/pages/*.md). Returns slug, title, status, date for each.',
        'inputSchema' => ['type' => 'object', 'properties' => new stdClass()],
        'handler' => 'tool_list_pages',
    ],
    'read_page' => [
        'description' => 'Read a single page by slug. Returns frontmatter and body.',
        'inputSchema' => [
            'type' => 'object',
            'required' => ['slug'],
            'properties' => ['slug' => ['type' => 'string']],
        ],
        'handler' => 'tool_read_page',
    ],
    'write_page' => [
        'description' => 'Create or update a page. Writes both <slug>.md and <slug>.meta.yaml atomically under content/pages/.',
        'inputSchema' => [
            'type' => 'object',
            'required' => ['slug', 'frontmatter', 'body'],
            'properties' => [
                'slug' => ['type' => 'string'],
                'frontmatter' => ['type' => 'object'],
                'body' => ['type' => 'string'],
            ],
        ],
        'handler' => 'tool_write_page',
    ],
    'publish' => [
        'description' => 'Set status=published in an article\'s .meta.yaml without rewriting the body.',
        'inputSchema' => [
            'type' => 'object',
            'required' => ['category', 'slug'],
            'properties' => [
                'category' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
            ],
        ],
        'handler' => 'tool_publish',
    ],
    'unpublish' => [
        'description' => 'Set status=draft in an article\'s .meta.yaml. The article remains on disk but is hidden from indexes.',
        'inputSchema' => [
            'type' => 'object',
            'required' => ['category', 'slug'],
            'properties' => [
                'category' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
            ],
        ],
        'handler' => 'tool_unpublish',
    ],
    'search' => [
        'description' => 'Full-text search across content/ (articles + pages). Returns matched file paths with line numbers and snippets.',
        'inputSchema' => [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Search string (literal, case-insensitive).'],
                'limit' => ['type' => 'integer', 'description' => 'Max matches to return (default 50).'],
            ],
        ],
        'handler' => 'tool_search',
    ],
];

// --------- Tool handlers ---------

function tool_list_articles(array $args): array
{
    global $articlesDir;
    $catFilter = $args['category'] ?? null;
    $statusFilter = $args['status'] ?? null;
    $limit = (int)($args['limit'] ?? 100);

    $glob = $catFilter
        ? $articlesDir . '/' . basename($catFilter) . '/*.md'
        : $articlesDir . '/*/*.md';

    $files = glob($glob) ?: [];
    sort($files);

    $out = [];
    foreach ($files as $mdPath) {
        $cat = basename(dirname($mdPath));
        $slug = basename($mdPath, '.md');
        if ($slug === '.gitkeep') continue;

        $meta = read_meta($cat, $slug);
        if ($statusFilter && ($meta['status'] ?? 'published') !== $statusFilter) continue;

        $out[] = [
            'slug' => $slug,
            'category' => $cat,
            'title' => $meta['title'] ?? $slug,
            'status' => $meta['status'] ?? 'published',
            'date' => $meta['date'] ?? null,
            'type' => $meta['type'] ?? null,
        ];
        if (count($out) >= $limit) break;
    }

    return ['articles' => $out, 'total' => count($out)];
}

function tool_read_article(array $args): array
{
    global $articlesDir;
    $cat = basename($args['category']);
    $slug = basename($args['slug']);
    $mdPath = "$articlesDir/$cat/$slug.md";

    if (!is_file($mdPath)) {
        throw new RuntimeException("Article not found: $cat/$slug");
    }

    return [
        'category' => $cat,
        'slug' => $slug,
        'frontmatter' => read_meta($cat, $slug),
        'body' => file_get_contents($mdPath),
    ];
}

function tool_write_article(array $args): array
{
    global $articlesDir;
    $cat = basename($args['category']);
    $slug = basename($args['slug']);
    $fm = $args['frontmatter'];
    $body = $args['body'];

    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        throw new RuntimeException("Slug must be lowercase alphanumeric with hyphens: '$slug'");
    }
    if (!preg_match('/^[a-z0-9-]+$/', $cat)) {
        throw new RuntimeException("Category must be lowercase alphanumeric with hyphens: '$cat'");
    }

    $dir = "$articlesDir/$cat";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Atomic writes
    write_atomic("$dir/$slug.md", $body);
    write_atomic("$dir/$slug.meta.yaml", YamlMini::dump($fm));

    EditLog::record('mcp', 'save', "/$cat/$slug", 'article', $slug);

    return [
        'category' => $cat,
        'slug' => $slug,
        'paths' => [
            'body' => "content/articles/$cat/$slug.md",
            'meta' => "content/articles/$cat/$slug.meta.yaml",
        ],
        'created' => true,
    ];
}

function tool_list_categories(array $args): array
{
    global $articlesDir;
    $dirs = glob($articlesDir . '/*', GLOB_ONLYDIR) ?: [];
    $cats = array_map('basename', $dirs);

    // Filter to dirs that have at least one .md
    $cats = array_values(array_filter($cats, function ($c) use ($articlesDir) {
        $found = glob("$articlesDir/$c/*.md") ?: [];
        return count($found) > 0;
    }));

    sort($cats);
    return ['categories' => $cats];
}

function tool_delete_article(array $args): array
{
    global $articlesDir;
    $cat = basename($args['category']);
    $slug = basename($args['slug']);
    $mdPath = "$articlesDir/$cat/$slug.md";
    $yamlPath = "$articlesDir/$cat/$slug.meta.yaml";

    $deleted = [];
    foreach ([$mdPath, $yamlPath] as $p) {
        if (is_file($p) && unlink($p)) {
            $deleted[] = str_replace($GLOBALS['siteRoot'] . '/', '', $p);
        }
    }

    // Only log a delete row when something actually went away. A
    // double-delete (idempotent retry) should not duplicate the audit
    // entry the first call already wrote.
    if (!empty($deleted)) {
        EditLog::record('mcp', 'delete', "/$cat/$slug", 'article', $slug);
    }

    return ['ok' => true, 'deleted' => $deleted];
}

function tool_list_pages(array $args): array
{
    global $pagesDir;
    if (!is_dir($pagesDir)) return ['pages' => [], 'total' => 0];

    $files = glob($pagesDir . '/*.md') ?: [];
    sort($files);

    $out = [];
    foreach ($files as $mdPath) {
        $slug = basename($mdPath, '.md');
        if ($slug === '.gitkeep') continue;
        $meta = read_page_meta($slug);
        $out[] = [
            'slug' => $slug,
            'title' => $meta['title'] ?? $slug,
            'status' => $meta['status'] ?? 'published',
            'date' => $meta['date'] ?? null,
        ];
    }
    return ['pages' => $out, 'total' => count($out)];
}

function tool_read_page(array $args): array
{
    global $pagesDir;
    $slug = basename($args['slug']);
    $mdPath = "$pagesDir/$slug.md";
    if (!is_file($mdPath)) {
        throw new RuntimeException("Page not found: $slug");
    }
    return [
        'slug' => $slug,
        'frontmatter' => read_page_meta($slug),
        'body' => file_get_contents($mdPath),
    ];
}

function tool_write_page(array $args): array
{
    global $pagesDir;
    $slug = basename($args['slug']);
    $fm = $args['frontmatter'];
    $body = $args['body'];

    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        throw new RuntimeException("Slug must be lowercase alphanumeric with hyphens: '$slug'");
    }

    if (!is_dir($pagesDir)) mkdir($pagesDir, 0755, true);

    write_atomic("$pagesDir/$slug.md", $body);
    write_atomic("$pagesDir/$slug.meta.yaml", YamlMini::dump($fm));

    EditLog::record('mcp', 'save', "/$slug", 'page', $slug);

    return [
        'slug' => $slug,
        'paths' => [
            'body' => "content/pages/$slug.md",
            'meta' => "content/pages/$slug.meta.yaml",
        ],
        'created' => true,
    ];
}

function tool_publish(array $args): array
{
    return set_article_status($args['category'], $args['slug'], 'published');
}

function tool_unpublish(array $args): array
{
    return set_article_status($args['category'], $args['slug'], 'draft');
}

function set_article_status(string $cat, string $slug, string $status): array
{
    global $articlesDir;
    $cat = basename($cat);
    $slug = basename($slug);
    $yamlPath = "$articlesDir/$cat/$slug.meta.yaml";

    $meta = read_meta($cat, $slug);
    if (empty($meta) && !is_file($yamlPath)) {
        throw new RuntimeException("Article not found: $cat/$slug");
    }
    $previous = $meta['status'] ?? 'published';
    $meta['status'] = $status;

    write_atomic($yamlPath, YamlMini::dump($meta));

    EditLog::record(
        'mcp',
        $status === 'published' ? 'publish' : 'unpublish',
        "/$cat/$slug",
        'article',
        $slug
    );

    return [
        'category' => $cat,
        'slug' => $slug,
        'previous_status' => $previous,
        'new_status' => $status,
    ];
}

function tool_search(array $args): array
{
    global $contentDir;
    $query = $args['query'] ?? '';
    $limit = (int) ($args['limit'] ?? 50);

    if ($query === '') {
        throw new RuntimeException('search query cannot be empty');
    }
    if (!is_dir($contentDir)) return ['matches' => [], 'total' => 0];

    $needle = mb_strtolower($query);
    $matches = [];
    $rootLen = strlen($GLOBALS['siteRoot']) + 1;

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($contentDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if ($ext !== 'md' && $ext !== 'yaml') continue;

        $contents = @file_get_contents($file->getPathname());
        if ($contents === false) continue;

        $lineNo = 0;
        foreach (preg_split('/\r?\n/', $contents) ?: [] as $line) {
            $lineNo++;
            if (str_contains(mb_strtolower($line), $needle)) {
                $matches[] = [
                    'file' => substr($file->getPathname(), $rootLen),
                    'line' => $lineNo,
                    'snippet' => trim($line),
                ];
                if (count($matches) >= $limit) break 2;
            }
        }
    }

    return ['matches' => $matches, 'total' => count($matches)];
}

function read_page_meta(string $slug): array
{
    global $pagesDir;
    $yamlPath = "$pagesDir/$slug.meta.yaml";
    if (is_file($yamlPath)) {
        return YamlMini::parse(file_get_contents($yamlPath));
    }
    $mdPath = "$pagesDir/$slug.md";
    if (is_file($mdPath)) {
        $body = file_get_contents($mdPath);
        if (str_starts_with(ltrim($body), '---')) {
            $end = strpos($body, "\n---", 3);
            if ($end !== false) return YamlMini::parse(substr($body, 4, $end - 4));
        }
    }
    return [];
}

// --------- Helpers ---------

function read_meta(string $cat, string $slug): array
{
    global $articlesDir;
    $yamlPath = "$articlesDir/$cat/$slug.meta.yaml";
    $mdPath = "$articlesDir/$cat/$slug.md";

    if (is_file($yamlPath)) {
        return YamlMini::parse(file_get_contents($yamlPath));
    }

    // Fallback: try frontmatter inside .md
    if (is_file($mdPath)) {
        $body = file_get_contents($mdPath);
        if (str_starts_with(ltrim($body), '---')) {
            $end = strpos($body, "\n---", 3);
            if ($end !== false) {
                return YamlMini::parse(substr($body, 4, $end - 4));
            }
        }
    }
    return [];
}

function write_atomic(string $path, string $contents): void
{
    $tmp = $path . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $contents) === false) {
        throw new RuntimeException("Failed to write temp file: $tmp");
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Failed to rename temp file to: $path");
    }
}

// --------- JSON-RPC dispatch loop ---------

function send_response($id, $result): void
{
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_SLASHES) . "\n";
    @ob_flush();
    @flush();
}

function send_error($id, int $code, string $message): void
{
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES) . "\n";
    @ob_flush();
    @flush();
}

while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    if ($line === '') continue;

    $req = json_decode($line, true);
    if (!is_array($req) || !isset($req['method'])) {
        continue;
    }

    $id = $req['id'] ?? null;
    $method = $req['method'];
    $params = $req['params'] ?? [];

    try {
        switch ($method) {
            case 'initialize':
                send_response($id, [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => ['tools' => new stdClass()],
                    'serverInfo' => ['name' => 'bird-cms', 'version' => '0.1.0'],
                ]);
                break;

            case 'notifications/initialized':
                // No response for notifications
                break;

            case 'tools/list':
                $list = [];
                foreach ($tools as $name => $t) {
                    $list[] = [
                        'name' => $name,
                        'description' => $t['description'],
                        'inputSchema' => $t['inputSchema'],
                    ];
                }
                send_response($id, ['tools' => $list]);
                break;

            case 'tools/call':
                $name = $params['name'] ?? '';
                $args = $params['arguments'] ?? [];
                if (!isset($tools[$name])) {
                    send_error($id, -32601, "Unknown tool: $name");
                    break;
                }
                $result = ($tools[$name]['handler'])($args);
                send_response($id, [
                    'content' => [
                        ['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)],
                    ],
                ]);
                break;

            default:
                send_error($id, -32601, "Method not found: $method");
        }
    } catch (Throwable $e) {
        send_error($id, -32603, $e->getMessage());
    }
}
