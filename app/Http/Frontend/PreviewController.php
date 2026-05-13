<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;
use App\Content\FrontMatter;
use App\Content\MetricsRepository;
use App\Support\Markdown;
use App\Support\PreviewToken;
use App\Support\TableOfContents;
use App\Theme\ThemeManager;

/**
 * `/preview/<slug>` — token-validated rendering of unpublished draft
 * articles staged under worklog/drafts/.
 *
 * Three failure modes, each handled distinctly so an admin debugging a
 * broken preview link gets the right signal:
 *   1. Invalid/expired token        -> 403 with a manual HTML error page
 *      (purposely not the theme's 404, because operators copy-paste these
 *      into Slack and need a self-contained message they can read at a
 *      glance).
 *   2. Malformed slug               -> 404 via theme/404.
 *   3. No draft file on disk        -> 404 via theme/404.
 *
 * On success, renders the theme's `article` view with isPreview=true so
 * templates can show a "DRAFT" banner / suppress robots meta as needed.
 *
 * Draft lookup checks `<slug>-ready.md` first, then `<slug>-draft.md` —
 * "ready" wins because that's the state the human flagged as
 * shareable. Both live under SITE_ROOT/worklog/drafts.
 */
final class PreviewController
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly MetricsRepository $metrics,
        private readonly ThemeManager $theme,
        /** @var list<string> */
        private readonly array $categoriesList,
        private readonly string $siteRoot,
    ) {
    }

    /**
     * @param list<string>          $segments Already validated as ['preview', <slug>]
     * @param array<string, mixed>  $query    $_GET-shaped (token, expires)
     */
    public function handle(array $segments, array $query): void
    {
        $previewSlug = $segments[1];

        $token   = (string) ($query['token'] ?? '');
        $expires = (int) ($query['expires'] ?? 0);

        if (!PreviewToken::verify($previewSlug, $token, $expires)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body style="font-family:sans-serif;padding:40px;">';
            echo '<h1>Preview Link Expired or Invalid</h1>';
            echo '<p>Please generate a new preview link from the <a href="/admin/pipeline">admin panel</a>.</p></body></html>';
            return;
        }

        if (!preg_match('/^[a-z0-9-]+$/', $previewSlug)) {
            $this->renderNotFound($previewSlug);
            return;
        }

        $draftsDir = $this->siteRoot . '/worklog/drafts';
        $readyPath = $draftsDir . '/' . $previewSlug . '-ready.md';
        $draftPath = $draftsDir . '/' . $previewSlug . '-draft.md';
        $filePath  = file_exists($readyPath)
            ? $readyPath
            : (file_exists($draftPath) ? $draftPath : null);

        if ($filePath === null) {
            $this->renderNotFound($previewSlug);
            return;
        }

        $contents = (string) file_get_contents($filePath);
        [$meta, $body] = FrontMatter::split($contents);

        $articleCategory = $meta['category'] ?? 'preview';
        $tags = $meta['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        $article = [
            'slug' => $previewSlug,
            'title' => $meta['title'] ?? $previewSlug,
            'description' => $meta['description'] ?? '',
            'category' => $articleCategory,
            'subcategory' => $meta['subcategory'] ?? null,
            'date' => $meta['date'] ?? date('Y-m-d'),
            'tags' => $tags,
            'type' => $meta['type'] ?? 'insight',
            'status' => 'preview',
            'hero_image' => $meta['hero_image'] ?? $meta['image'] ?? null,
            'reading_time' => $meta['reading_time'] ?? null,
            'content' => $body,
            'html' => Markdown::toHtml($body),
            'meta' => $meta,
            'is_preview' => true,
            'preview_file' => basename($filePath),
            'url' => '/preview/' . $previewSlug,
        ];

        $tocData = TableOfContents::generate($article['html']);
        $article['html'] = $tocData['html'];
        $toc = $tocData['toc'];

        $this->theme->render('article', [
            'article' => $article,
            'category' => $articleCategory,
            'toc' => $toc,
            'related' => [],
            'latest' => $this->articles->latest(6),
            'categoriesList' => $this->categoriesList,
            'metrics' => $this->metrics,
            'isPreview' => true,
        ]);
    }

    private function renderNotFound(string $slug): void
    {
        http_response_code(404);
        $this->theme->render('404', [
            'slug' => $slug,
            'categoriesList' => $this->categoriesList,
        ]);
    }
}
