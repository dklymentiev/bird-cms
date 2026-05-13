<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;
use App\Content\MetricsRepository;
use App\Support\LinkFilter;
use App\Support\PreviewToken;
use App\Support\TableOfContents;
use App\Theme\ThemeManager;

/**
 * `/<category>/<slug>` (or `/<articles_prefix>/<category>/<slug>` when a
 * prefix is configured) — article detail page.
 *
 * Three responsibilities, each ported verbatim from the procedural block
 * in the pre-refactor index.php:
 *
 *   1. Resolve the article via ArticleRepository. The `?preview=1&token=...`
 *      query opts in to draft visibility; verification routes through
 *      `App\Support\PreviewToken` so the HMAC algorithm lives in one place.
 *
 *   2. Build the sidebar pool in three tiers, preferring quality over
 *      quantity:
 *        a. articles sharing tags with the current one (capped at 8)
 *        b. fill from the same category if fewer than 5
 *        c. fill from global latest if still fewer than 5
 *      Each tier de-duplicates against prior tiers; the final slice is
 *      always exactly the first 5 (or fewer if the site is small).
 *
 *   3. Decorate the article HTML: TOC heading IDs (TableOfContents),
 *      then LinkFilter rewrites internal links to unpublished/scheduled
 *      articles as `unlink` (text, not anchor) so the rendered HTML
 *      doesn't leak draft URLs to readers or search engines.
 *
 * `globalLatest` and the sidebar `latest` are two different feeds —
 * sidebar is the tag/category/global mix above, `globalLatest` is the
 * raw "latest 8 minus this one, take 5" used by the article template's
 * footer rail. Themes that don't render the rail just ignore the key.
 */
final class ArticleController
{
    /** @param callable(string): string $templateResolver */
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly MetricsRepository $metrics,
        private readonly ThemeManager $theme,
        /** @var list<string> */
        private readonly array $categoriesList,
        private $templateResolver,
    ) {
    }

    /**
     * Resolve the article segments from the full URI segments, honoring
     * the optional `articles_prefix` config (e.g. /blog/<category>/<slug>).
     *
     * Returns null when the segment shape doesn't match an article URL
     * for this site's configuration — caller falls through to 404.
     *
     * @param list<string> $segments
     * @return list<string>|null
     */
    public static function resolveSegments(array $segments, string $articlesPrefix): ?array
    {
        if ($articlesPrefix !== '') {
            // Prefix required: only /<prefix>/<category>/<slug> matches.
            if (count($segments) >= 3 && $segments[0] === $articlesPrefix) {
                return array_slice($segments, 1);
            }
            return null;
        }
        return $segments;
    }

    /**
     * @param list<string>          $articleSegments  [category, slug]
     * @param array<string, mixed>  $query  $_GET-shaped (for ?preview=1)
     */
    public function handle(array $articleSegments, array $query): void
    {
        $category = $articleSegments[0];
        $slug = $articleSegments[1];
        if (preg_match('/^[a-z0-9-]+$/', $category) !== 1
            || preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
            $this->renderNotFound($category, $slug);
            return;
        }

        // Draft visibility: payload is "<category>/<slug>".
        $isPreview = false;
        if (isset($query['preview']) && (string) $query['preview'] === '1') {
            $isPreview = PreviewToken::verify(
                $category . '/' . $slug,
                (string) ($query['token'] ?? ''),
                (int) ($query['expires'] ?? 0),
            );
        }

        $article = $this->articles->find($category, $slug, $isPreview);
        if ($article === null) {
            $this->renderNotFound($category, $slug);
            return;
        }

        $related = $this->articles->relatedPillarAware($article, 3);
        $sidebar = $this->buildSidebarPool($article);

        // Decorate HTML: TOC heading IDs, then unlink rewrites for any
        // internal links pointing at unpublished/scheduled articles.
        $tocData = TableOfContents::generate($article['html']);
        $article['html'] = $tocData['html'];
        $article['toc'] = $tocData['toc'];

        $linkFilter = new LinkFilter($this->articles);
        $article['html'] = $linkFilter->filter($article['html'], 'unlink');

        $currentSlug = $article['slug'];
        $globalLatest = array_values(array_filter(
            $this->articles->latest(8),
            static fn(array $item) => ($item['slug'] ?? null) !== $currentSlug,
        ));

        $resolve = $this->templateResolver;
        $this->theme->render($resolve('article'), [
            'article' => $article,
            'related' => $related,
            'latest' => $sidebar,
            'globalLatest' => array_slice($globalLatest, 0, 5),
            'categoriesList' => $this->categoriesList,
            'adjacent' => $this->articles->adjacent($category, $slug),
            'metrics' => $this->metrics,
        ]);
    }

    /**
     * Build the per-article sidebar pool (max 5) in three tiers:
     *  - tag matches (cap 8)
     *  - same-category fill (cap 5 total)
     *  - global-latest fill (cap 5 total)
     *
     * @param array<string, mixed> $article
     * @return list<array<string, mixed>>
     */
    private function buildSidebarPool(array $article): array
    {
        $currentSlug = $article['slug'];
        $currentCategory = $article['category'] ?? null;
        $currentTags = array_map('strtolower', $article['tags'] ?? []);

        $tagMatches = [];
        if (!empty($currentTags)) {
            foreach ($this->articles->all() as $candidate) {
                $candidateSlug = $candidate['slug'] ?? null;
                if ($candidateSlug === null || $candidateSlug === $currentSlug) {
                    continue;
                }
                $candidateTags = array_map('strtolower', $candidate['tags'] ?? []);
                if (!empty(array_intersect($currentTags, $candidateTags))) {
                    $tagMatches[$candidateSlug] = $candidate;
                }
                if (count($tagMatches) >= 8) {
                    break;
                }
            }
        }

        $sidebarPool = array_values($tagMatches);

        if (count($sidebarPool) < 5 && $currentCategory) {
            foreach ($this->articles->inCategory((string) $currentCategory, 8) as $candidate) {
                $candidateSlug = $candidate['slug'] ?? null;
                if ($candidateSlug === null || $candidateSlug === $currentSlug) {
                    continue;
                }
                if (isset($tagMatches[$candidateSlug])) {
                    continue;
                }
                $sidebarPool[] = $candidate;
                if (count($sidebarPool) >= 5) {
                    break;
                }
            }
        }

        if (count($sidebarPool) < 5) {
            foreach ($this->articles->latest(8) as $candidate) {
                $candidateSlug = $candidate['slug'] ?? null;
                if ($candidateSlug === null || $candidateSlug === $currentSlug) {
                    continue;
                }
                if (isset($tagMatches[$candidateSlug])) {
                    continue;
                }
                $exists = false;
                foreach ($sidebarPool as $existing) {
                    if (($existing['slug'] ?? null) === $candidateSlug) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists) {
                    continue;
                }
                $sidebarPool[] = $candidate;
                if (count($sidebarPool) >= 5) {
                    break;
                }
            }
        }

        return array_slice($sidebarPool, 0, 5);
    }

    private function renderNotFound(string $category, string $slug): void
    {
        http_response_code(404);
        $this->theme->render('404', [
            'category' => $category,
            'slug' => $slug,
            'categoriesList' => $this->categoriesList,
        ]);
    }
}
