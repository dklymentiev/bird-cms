<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;
use App\Theme\ThemeManager;

/**
 * Frontend /search route.
 *
 * Scores published articles against the user's `?q=` query and renders the
 * theme's `search` view. The scoring is intentionally simple (in-memory,
 * substring match) so it works without an external index; sites that need
 * fuzziness or stemming should swap in their own search backend at the
 * controller level.
 *
 * Scoring (preserved from the original procedural block):
 *   - title contains query        +100, +50 if title STARTS with query
 *   - description contains query  +30
 *   - any tag contains query      +20 (capped at once per article)
 *
 * Query under 2 chars => empty results, view still rendered (so the search
 * page shape is consistent whether the visitor typed nothing or typed a
 * single character).
 */
final class SearchController
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ThemeManager $theme,
        /** @var list<string> */
        private readonly array $categoriesList,
    ) {
    }

    /**
     * @param array<string, mixed> $query  Parsed query string ($_GET-shaped)
     */
    public function handle(array $query): void
    {
        $q = trim((string) ($query['q'] ?? ''));
        $results = [];

        // Byte-length (strlen) is intentional — matches the original
        // behavior, where a single multibyte character (2+ bytes) would
        // still satisfy the >= 2 gate.
        if (strlen($q) >= 2) {
            $queryLower = mb_strtolower($q);
            foreach ($this->articles->all() as $article) {
                $title = mb_strtolower((string) ($article['title'] ?? ''));
                $description = mb_strtolower((string) ($article['description'] ?? ''));
                $tags = array_map('mb_strtolower', $article['tags'] ?? []);

                $score = 0;

                if (str_contains($title, $queryLower)) {
                    $score += 100;
                    if (str_starts_with($title, $queryLower)) {
                        $score += 50;
                    }
                }
                if (str_contains($description, $queryLower)) {
                    $score += 30;
                }
                foreach ($tags as $tag) {
                    if (str_contains($tag, $queryLower)) {
                        $score += 20;
                        break;
                    }
                }

                if ($score > 0) {
                    $article['_score'] = $score;
                    $results[] = $article;
                }
            }

            usort($results, static fn($a, $b) => $b['_score'] <=> $a['_score']);
        }

        $this->theme->render('search', [
            'query' => $q,
            'results' => $results,
            'categoriesList' => $this->categoriesList,
            'latest' => $this->articles->latest(6),
        ]);
    }
}
