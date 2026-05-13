<?php

declare(strict_types=1);

namespace App\Admin;

use App\Content\ArticleRepository;
use App\Content\FrontMatter;
use App\Content\PageRepository;
use App\Http\ContentDescriptor;
use App\Http\ContentRouter;
use App\Support\Config;
use App\Support\UrlMeta;

/**
 * Pages / URL Inventory.
 *
 * One screen showing every URL the site exposes (homepage, every
 * registered content-type item, static pages, sitemap-only routes),
 * plus per-URL overrides backed by storage/url-meta.json:
 *
 *   - in_sitemap (bool)   exclude from sitemap.xml
 *   - noindex    (bool)   emit <meta robots="noindex,nofollow"> on render
 *   - priority   (string) sitemap <priority>
 *   - changefreq (string) sitemap <changefreq>
 *
 * Sources of URLs:
 *   - "/"                         always (homepage)
 *   - ContentRouter::allUrls()    every registered content type instance
 *                                 (articles, pages, services, areas, work, ...)
 *   - articles category indexes   /<category> for every populated category
 */
final class PagesController extends Controller
{
    private string $metaPath;

    public function __construct()
    {
        parent::__construct();
        $this->metaPath = SITE_ROOT . '/storage/url-meta.json';
    }

    public function index(): void
    {
        $this->requireAuth();

        $siteUrl = rtrim((string) config('site_url'), '/');
        $urls    = $this->collectAllUrls($siteUrl);
        $meta    = $this->loadMeta();

        // Splice url-meta overrides into each row + collect distinct sources.
        $sources = [];
        foreach ($urls as &$u) {
            $key = $u['path'];
            $u['meta'] = $meta[$key] ?? [];
            $u['in_sitemap']  = $u['meta']['in_sitemap'] ?? true;
            $u['noindex']     = $u['meta']['noindex']    ?? false;
            $u['priority']    = $u['meta']['priority']   ?? ($u['priority']   ?? '0.5');
            $u['changefreq']  = $u['meta']['changefreq'] ?? ($u['changefreq'] ?? 'weekly');
            $sources[$u['source']] = true;
        }
        unset($u);

        // Sort: source group, then path alphabetically.
        usort($urls, fn(array $a, array $b) =>
            ($a['source'] <=> $b['source']) ?: strcmp($a['path'], $b['path'])
        );

        $this->render('pages/index', [
            'pageTitle'   => 'Pages',
            'urls'        => $urls,
            'sources'     => array_keys($sources),
            'totalUrls'   => count($urls),
            'inSitemap'   => count(array_filter($urls, fn($u) => $u['in_sitemap'] && !$u['noindex'])),
            'csrf'        => $this->generateCsrf(),
            'flash'       => $this->getFlash(),
        ]);
    }

    /**
     * POST /admin/pages/update -- toggle/update one URL's overrides.
     * Accepts: path, in_sitemap (0/1), noindex (0/1), priority, changefreq.
     */
    public function update(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->setFlash('error', 'Invalid security token.');
            $this->redirect('/admin/pages');
            return;
        }

        $path = trim((string) $this->post('path', ''));
        if ($path === '' || $path[0] !== '/') {
            $this->setFlash('error', 'Path must start with /.');
            $this->redirect('/admin/pages');
            return;
        }

        $meta = $this->loadMeta();
        $row  = $meta[$path] ?? [];

        $row['in_sitemap'] = ((string) $this->post('in_sitemap', '1')) === '1';
        $row['noindex']    = ((string) $this->post('noindex', '0'))    === '1';

        $priority = trim((string) $this->post('priority', ''));
        if ($priority !== '') $row['priority'] = $priority;

        $changefreq = trim((string) $this->post('changefreq', ''));
        if ($changefreq !== '') $row['changefreq'] = $changefreq;

        // If the row is back to defaults, drop the entry to keep the JSON clean.
        if ($row['in_sitemap'] === true
            && $row['noindex']  === false
            && empty($row['priority'])
            && empty($row['changefreq'])
        ) {
            unset($meta[$path]);
        } else {
            $meta[$path] = $row;
        }

        $this->saveMeta($meta);
        $this->setFlash('success', 'Saved overrides for ' . $path);
        $this->redirect('/admin/pages');
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function loadMeta(): array
    {
        if (!is_file($this->metaPath)) return [];
        $json = (string) file_get_contents($this->metaPath);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Atomic write: encode to a tmp file then rename. Keeps the JSON readable
     * if the operator wants to inspect / commit.
     *
     * @param array<string,array<string,mixed>> $meta
     */
    private function saveMeta(array $meta): void
    {
        ksort($meta);
        $tmp = $this->metaPath . '.tmp.' . getmypid();
        $bytes = file_put_contents($tmp, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        if ($bytes === false || !rename($tmp, $this->metaPath)) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to write ' . $this->metaPath);
        }
        // Invalidate the request-scope cache so /admin/pages re-renders see
        // the just-saved override on the same request (the modal flow does
        // a synchronous form POST to /update right after this).
        UrlMeta::reset();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function collectAllUrls(string $siteUrl): array
    {
        $rows = [];

        // Homepage.
        $rows[] = [
            'path'       => '/',
            'loc'        => $siteUrl . '/',
            'source'     => 'static',
            'category'   => '',
            'lastmod'    => date('Y-m-d'),
            'priority'   => '1.0',
            'changefreq' => 'daily',
        ];

        // ContentRouter handles every registered content type uniformly.
        try {
            $contentConfig = Config::load('content');
            $router = new ContentRouter($contentConfig);
            foreach ($router->allUrls($siteUrl) as $u) {
                $path = (string) parse_url($u['loc'], PHP_URL_PATH) ?: '/';
                $rows[] = [
                    'path'       => $path,
                    'loc'        => $u['loc'],
                    'source'     => $u['type'] ?? 'content',
                    'category'   => $this->extractCategory($path, $u['type'] ?? ''),
                    'lastmod'    => $u['lastmod']    ?? date('Y-m-d'),
                    'priority'   => $u['priority']   ?? '0.5',
                    'changefreq' => $u['changefreq'] ?? 'weekly',
                ];
            }
        } catch (\Throwable $e) {
            // No content.php -- skip silently; just shows "/".
        }

        // Article category index pages (e.g. /blog, /tips). ContentRouter
        // doesn't emit these, but they're real URLs the user navigates.
        if (is_dir(SITE_ROOT . '/content/articles')) {
            $repo = new ArticleRepository(SITE_ROOT . '/content/articles');
            foreach ($repo->categories() as $cat) {
                if (empty($repo->inCategory($cat, 1))) continue;
                $rows[] = [
                    'path'       => '/' . $cat,
                    'loc'        => $siteUrl . '/' . $cat,
                    'source'     => 'category-index',
                    'category'   => $cat,
                    'lastmod'    => date('Y-m-d'),
                    'priority'   => '0.9',
                    'changefreq' => 'daily',
                ];
            }
        }

        return $rows;
    }

    /**
     * Best-effort category extraction from URL path.
     * Articles: /<cat>/<slug>           -> <cat>
     * Services (cleaninggta): /<type>/<slug>  -> <type>
     * Anything else: empty string
     */
    private function extractCategory(string $path, string $source): string
    {
        $segments = array_values(array_filter(explode('/', $path)));
        if (count($segments) >= 2 && $source === 'articles') return $segments[0];
        if (count($segments) >= 2 && $source === 'services') return $segments[0];
        return '';
    }

    /**
     * POST /admin/pages/edit-content
     *
     * AJAX endpoint that backs the URL Inventory pencil action. Given a URL,
     * resolves which content type owns it via ContentRouter::resolve(),
     * loads the underlying record, and returns the shape the modal needs to
     * render its Content / Meta / Template / Sitemap tabs in one shot.
     *
     * Returns JSON. Always responds with HTTP 200 and an `ok` flag so the
     * Alpine modal can show a friendly inline error without parsing status
     * codes; misuse (missing path, unknown URL, internal load failure) just
     * flips ok=false with a message.
     */
    public function editContent(): void
    {
        $this->requireAuth();

        $path = trim((string) $this->post('path', ''));
        if ($path === '' || $path[0] !== '/') {
            $this->json(['ok' => false, 'error' => 'Path must start with /.']);
            return;
        }

        $descriptor = $this->resolveDescriptor($path);
        if ($descriptor === null) {
            $this->json(['ok' => false, 'error' => 'No content found for ' . $path]);
            return;
        }

        $payload = [
            'ok'                 => true,
            'path'               => $path,
            'source'             => $descriptor->source,
            'slug'               => $descriptor->slug,
            'category'           => $descriptor->category,
            'editable_body'      => $descriptor->editableBody,
            'editable_template'  => $descriptor->editableTemplate,
            'available_templates' => $this->availableTemplates(),
            'template'           => $this->currentTemplateOverride($path),
            // Meta defaults; filled in below from the loaded record when present.
            'title'              => '',
            'description'        => '',
            'body'               => '',
            'meta_yaml'          => '',
            'hero_image'         => '',
        ];

        // Defaults for structured meta fields surfaced as their own inputs
        // in the modal's Meta tab. The frontend reads `meta_fields`; the
        // advanced textarea reads `meta_yaml`. Keep the two disjoint so a
        // round-trip doesn't double-write known keys.
        $payload['meta_fields'] = self::defaultMetaFields();

        // 'static' descriptors (homepage, category indexes) only carry an
        // editable body if a content/pages/<slug>.md fall-through exists.
        // Either way we still surface template + sitemap so the user can
        // override those without authoring an intro page first.
        if ($descriptor->source === 'static') {
            $page = $this->loadStaticOverride($descriptor->slug);
            if ($page !== null) {
                $meta = is_array($page['meta'] ?? null) ? $page['meta'] : [];
                $payload['title']       = (string) ($page['title'] ?? '');
                $payload['description'] = (string) ($page['description'] ?? '');
                $payload['body']        = (string) ($page['content'] ?? '');
                $payload['hero_image']  = (string) ($meta['hero_image'] ?? '');
                $payload['meta_fields'] = self::splitMetaFields($meta);
                $payload['meta_yaml']   = self::extrasYaml($meta);
            }
            $this->json($payload);
            return;
        }

        $record = $this->loadRecord($descriptor);
        if ($record === null) {
            $this->json(['ok' => false, 'error' => 'Could not load content for ' . $path]);
            return;
        }

        $meta = is_array($record['meta'] ?? null) ? $record['meta'] : [];
        $payload['title']       = (string) ($record['title'] ?? '');
        $payload['description'] = (string) ($record['description'] ?? '');
        $payload['body']        = (string) ($record['content'] ?? '');
        $payload['hero_image']  = (string) ($meta['hero_image'] ?? $record['hero_image'] ?? '');
        $payload['meta_fields'] = self::splitMetaFields($meta);
        $payload['meta_yaml']   = self::extrasYaml($meta);

        $this->json($payload);
    }

    /**
     * POST /admin/pages/save-content
     *
     * Writes a single URL's body + meta back to its owning repository.
     *
     * The slug must be unchanged from what editContent returned (we look it
     * up by URL again and compare). Slug renaming via the inventory is a
     * separate concern; allowing it here would silently break inbound
     * links and sitemap entries.
     *
     * meta_yaml is parsed via FrontMatter::parse and merged with the
     * dedicated title / description / hero_image inputs, with the inputs
     * winning over duplicates.
     */
    public function saveContent(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->json(['ok' => false, 'error' => 'Invalid security token.'], 400);
            return;
        }

        $path = trim((string) $this->post('path', ''));
        if ($path === '' || $path[0] !== '/') {
            $this->json(['ok' => false, 'error' => 'Path must start with /.'], 400);
            return;
        }

        $descriptor = $this->resolveDescriptor($path);
        if ($descriptor === null) {
            $this->json(['ok' => false, 'error' => 'No content found for ' . $path], 404);
            return;
        }

        $postedSlug = trim((string) $this->post('slug', ''));
        if ($postedSlug !== '' && $postedSlug !== $descriptor->slug) {
            $this->json(['ok' => false, 'error' => 'Slug rename is not supported here.'], 400);
            return;
        }

        $fields = [
            'title'        => (string) $this->post('title', ''),
            'description'  => (string) $this->post('description', ''),
            'hero_image'   => (string) $this->post('hero_image', ''),
            'status'       => (string) $this->post('status', ''),
            'date'         => (string) $this->post('date', ''),
            'scheduled_at' => (string) $this->post('scheduled_at', ''),
        ];
        $body    = (string) $this->post('body', '');
        $rawYaml = (string) $this->post('meta_yaml', '');

        $error = self::validateMetaFields($fields);
        if ($error !== null) {
            $this->json(['ok' => false, 'error' => $error], 400);
            return;
        }

        $rawMeta = [];
        if (trim($rawYaml) !== '') {
            try {
                $parsed = FrontMatter::parse($rawYaml);
            } catch (\Throwable $e) {
                $this->json(['ok' => false, 'error' => 'Invalid meta YAML: ' . $e->getMessage()], 400);
                return;
            }
            if (!is_array($parsed)) {
                $this->json(['ok' => false, 'error' => 'meta_yaml must parse to a mapping.'], 400);
                return;
            }
            $rawMeta = $parsed;
        }

        $meta = self::mergeStructuredMeta($rawMeta, $fields);

        try {
            $this->writeViaRepository($descriptor, $meta, $body);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Save failed: ' . $e->getMessage()], 500);
            return;
        }

        $this->json([
            'ok'     => true,
            'path'   => $path,
            'source' => $descriptor->source,
            'slug'   => $descriptor->slug,
        ]);
    }

    /**
     * POST /admin/pages/save-template
     *
     * Per-URL template override stored alongside sitemap meta in
     * storage/url-meta.json. Empty value clears the override and falls
     * back to the content type's default template.
     */
    public function saveTemplate(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->json(['ok' => false, 'error' => 'Invalid security token.'], 400);
            return;
        }

        $path = trim((string) $this->post('path', ''));
        if ($path === '' || $path[0] !== '/') {
            $this->json(['ok' => false, 'error' => 'Path must start with /.'], 400);
            return;
        }

        $template = trim((string) $this->post('template', ''));
        if ($template !== '' && !in_array($template, $this->availableTemplates(), true)) {
            $this->json(['ok' => false, 'error' => 'Unknown template: ' . $template], 400);
            return;
        }

        $meta = $this->loadMeta();
        $row  = $meta[$path] ?? [];

        if ($template === '') {
            unset($row['template']);
        } else {
            $row['template'] = $template;
        }

        if ($row === []) {
            unset($meta[$path]);
        } else {
            $meta[$path] = $row;
        }

        $this->saveMeta($meta);

        $this->json(['ok' => true, 'path' => $path, 'template' => $template]);
    }

    /**
     * Discover renderable view templates in the active theme.
     *
     * Returns slug names (without .php). Filters out partials, error
     * pages, and any view starting with "_" -- those aren't reachable
     * as a top-level page template.
     *
     * @return list<string>
     */
    private function availableTemplates(): array
    {
        $activeTheme = (string) (config('active_theme') ?: 'tailwind');
        $themesPath  = defined('ENGINE_THEMES_PATH') ? ENGINE_THEMES_PATH : (SITE_ROOT . '/themes');
        $viewsDir    = $themesPath . '/' . $activeTheme . '/views';

        if (!is_dir($viewsDir)) {
            return [];
        }

        $exclude = [
            '404', 'search', 'breadcrumbs',
            // Internal layout fragments commonly shipped under views/.
            'header', 'footer', 'sidebar', 'meta-tags', 'pagination',
        ];

        $out = [];
        foreach (glob($viewsDir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if ($name === '' || $name[0] === '_') continue;
            if (in_array($name, $exclude, true)) continue;
            $out[] = $name;
        }
        sort($out);
        return $out;
    }

    /**
     * Read the per-URL template override (if any) from url-meta.json.
     */
    private function currentTemplateOverride(string $path): string
    {
        $meta = $this->loadMeta();
        return (string) ($meta[$path]['template'] ?? '');
    }

    /**
     * Wrap a fresh ContentRouter (config may have changed since last
     * request) and reverse-lookup the descriptor for $path.
     */
    private function resolveDescriptor(string $path): ?ContentDescriptor
    {
        try {
            $contentConfig = Config::load('content');
        } catch (\Throwable $e) {
            return null;
        }
        $router = new ContentRouter($contentConfig);
        return $router->resolve($path);
    }

    /**
     * Spin up the descriptor's repository and load the matching record.
     * Returns null on any lookup miss; callers translate that into a JSON
     * error.
     */
    private function loadRecord(ContentDescriptor $descriptor): ?array
    {
        $sourcePath = $this->sourcePathFor($descriptor->source);
        if ($sourcePath === null) {
            return null;
        }

        $repoClass = $descriptor->repositoryClass;
        $repo = new $repoClass($sourcePath);

        // Per-type lookup: each repository's find() takes the params it
        // already understands. Mirrors ContentRouter::findItem() but
        // stays inside the controller so the descriptor stays small.
        switch ($descriptor->source) {
            case 'articles':
                return $repo->find((string) $descriptor->category, $descriptor->slug);
            case 'services':
                return $repo->find((string) $descriptor->category, $descriptor->slug);
            case 'areas':
                if ($descriptor->category !== null && $descriptor->category !== '') {
                    return $repo->findSubarea($descriptor->category, $descriptor->slug);
                }
                return $repo->find($descriptor->slug);
            case 'pages':
            case 'projects':
                return $repo->find($descriptor->slug);
        }
        return null;
    }

    /**
     * Static-source URLs (homepage, category indexes) optionally have a
     * content/pages/<slug>.md fall-through. We load it through
     * PageRepository so the same write path works on save.
     */
    private function loadStaticOverride(string $slug): ?array
    {
        $repo = new PageRepository($this->sourcePathFor('pages') ?? '');
        return $repo->find($slug);
    }

    /**
     * Pick the right repository write entry point based on the
     * descriptor source. Centralised so saveContent stays linear.
     *
     * @param array<string, mixed> $meta
     */
    private function writeViaRepository(ContentDescriptor $descriptor, array $meta, string $body): void
    {
        $sourcePath = $this->sourcePathFor($descriptor->source === 'static' ? 'pages' : $descriptor->source);
        if ($sourcePath === null) {
            throw new \RuntimeException('No source path for ' . $descriptor->source);
        }

        switch ($descriptor->source) {
            case 'articles':
                (new ArticleRepository($sourcePath))->save(
                    (string) $descriptor->category,
                    $descriptor->slug,
                    $meta,
                    $body
                );
                return;
            case 'services':
                (new \App\Content\ServiceRepository($sourcePath))->save(
                    (string) $descriptor->category,
                    $descriptor->slug,
                    $meta,
                    $body
                );
                return;
            case 'areas':
                (new \App\Content\AreaRepository($sourcePath))->save($descriptor->slug, $meta, $body);
                return;
            case 'projects':
                (new \App\Content\ProjectRepository($sourcePath))->save($descriptor->slug, $meta, $body);
                return;
            case 'pages':
            case 'static':
                // 'static' writes go through PageRepository as a fall-through
                // override page (content/pages/<slug>.md). After this write,
                // the descriptor's editable_body flag flips to true on the
                // next request because the file now exists.
                (new PageRepository($sourcePath))->save($descriptor->slug, $meta, $body);
                return;
        }

        throw new \RuntimeException('Unsupported source: ' . $descriptor->source);
    }

    /**
     * Resolve the on-disk content/<bucket> path for a descriptor source.
     * Reuses config/content.php so the engine + URL Inventory agree on
     * where each type lives.
     */
    private function sourcePathFor(string $source): ?string
    {
        try {
            $contentConfig = Config::load('content');
        } catch (\Throwable $e) {
            return null;
        }
        $sub = $contentConfig['types'][$source]['source'] ?? ('content/' . $source);
        return SITE_ROOT . '/' . ltrim((string) $sub, '/');
    }

    /**
     * Keys the modal's structured inputs own. Anything in this list lives
     * in `meta_fields`, never in the advanced raw-YAML textarea, so the
     * round-trip can't accidentally double-write them.
     *
     * @var list<string>
     */
    private const STRUCTURED_META_KEYS = ['status', 'date', 'scheduled_at', 'hero_image'];

    /**
     * Valid values for the `status` field. Anything else is rejected by
     * saveContent() so we don't write garbage states that the frontend
     * filters won't understand.
     *
     * @var list<string>
     */
    private const VALID_STATUSES = ['draft', 'published', 'scheduled'];

    /**
     * Empty defaults for the structured meta block. Used when the underlying
     * record has no meta at all (fresh static override) so the frontend can
     * still bind to a well-defined shape.
     *
     * @return array{status:string, date:string, scheduled_at:string, hero_image:string}
     */
    public static function defaultMetaFields(): array
    {
        return [
            'status'       => '',
            'date'         => '',
            'scheduled_at' => '',
            'hero_image'   => '',
        ];
    }

    /**
     * Split a stored meta array into the structured fields the modal
     * binds discrete inputs to.
     *
     * The structured block always has the four known keys (empty strings
     * when missing); the caller pairs this with extrasYaml() to get a
     * disjoint advanced-YAML blob for unknown keys.
     *
     * @param array<string, mixed> $meta
     * @return array{status:string, date:string, scheduled_at:string, hero_image:string}
     */
    public static function splitMetaFields(array $meta): array
    {
        $out = self::defaultMetaFields();
        foreach (self::STRUCTURED_META_KEYS as $k) {
            if (isset($meta[$k])) {
                $out[$k] = (string) $meta[$k];
            }
        }
        return $out;
    }

    /**
     * Merge raw-YAML extras with the modal's structured fields.
     *
     * Rules:
     *   - title / description / hero_image come from their dedicated inputs.
     *     Empty inputs delete the key, non-empty inputs always win over
     *     anything the raw YAML might say. The structured block strips
     *     these from extrasYaml() before the textarea ever sees them, but
     *     a paranoid operator could still paste them back; structured wins.
     *   - status / date / scheduled_at follow the same rule: empty input =
     *     delete key, non-empty input = write through.
     *   - Any key the raw YAML carries that the form doesn't know about is
     *     preserved untouched.
     *
     * @param array<string, mixed> $rawYamlMeta  parsed advanced textarea
     * @param array<string, string> $fields      structured POST fields
     *                                           (title, description, hero_image,
     *                                            status, date, scheduled_at)
     * @return array<string, mixed>
     */
    public static function mergeStructuredMeta(array $rawYamlMeta, array $fields): array
    {
        // Strip every key the structured block owns from the raw YAML so
        // an empty form field deletes it -- structured wins.
        $merged = $rawYamlMeta;
        foreach (array_merge(['title', 'description'], self::STRUCTURED_META_KEYS) as $k) {
            unset($merged[$k]);
        }

        // Replay the structured fields. Trim text inputs; empty after trim
        // means "delete this key", non-empty means "write through".
        foreach (['title', 'description', 'hero_image', 'status', 'date', 'scheduled_at'] as $k) {
            $v = trim((string) ($fields[$k] ?? ''));
            if ($v !== '') {
                $merged[$k] = $v;
            }
        }
        return $merged;
    }

    /**
     * Validate the structured POST fields before we let them anywhere near
     * the merge. Returns null on success or a human-readable error string
     * the caller can surface as JSON.
     *
     * @param array<string, string> $fields
     */
    public static function validateMetaFields(array $fields): ?string
    {
        $status      = trim((string) ($fields['status'] ?? ''));
        $date        = trim((string) ($fields['date'] ?? ''));
        $scheduledAt = trim((string) ($fields['scheduled_at'] ?? ''));

        if ($status !== '' && !in_array($status, self::VALID_STATUSES, true)) {
            return 'Invalid status: ' . $status . ' (expected one of '
                . implode(', ', self::VALID_STATUSES) . ').';
        }
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return 'Invalid date: must be YYYY-MM-DD.';
        }
        if ($status === 'scheduled' && $scheduledAt === '') {
            return 'scheduled_at is required when status=scheduled.';
        }
        return null;
    }

    /**
     * Encode every meta key the dedicated inputs DON'T already cover into
     * raw YAML for the modal's "Advanced: raw YAML" disclosure.
     *
     * Title / description / hero_image come from the Content tab; status /
     * date / scheduled_at come from the structured Meta inputs. Internal
     * cache fields (canonical, parsed-only mirrors) are stripped so the
     * operator only sees what they can edit.
     *
     * @param array<string, mixed> $meta
     */
    public static function extrasYaml(array $meta): string
    {
        unset(
            $meta['title'],
            $meta['description'],
            $meta['canonical'], // recomputed by ArticleRepository on each load
        );
        foreach (self::STRUCTURED_META_KEYS as $k) {
            unset($meta[$k]);
        }
        if ($meta === []) {
            return '';
        }
        return FrontMatter::encode($meta) . "\n";
    }
}
