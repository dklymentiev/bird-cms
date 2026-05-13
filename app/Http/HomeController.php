<?php

declare(strict_types=1);

namespace App\Http;

use App\Content\ArticleRepository;
use App\Content\MetricsRepository;

/**
 * Homepage data controller.
 *
 * Orchestrates content collection for homepage sections based on
 * configuration from config/home.php. Separates data collection
 * from presentation logic in index.php.
 */
class HomeController
{
    private ArticleRepository $repository;
    private ContentCollectors $collectors;

    public function __construct(ArticleRepository $repository)
    {
        $this->repository = $repository;
        $this->collectors = new ContentCollectors($repository);
    }

    /**
     * Get all data needed for homepage rendering.
     *
     * @param array $homeConfig Configuration from config/home.php
     * @param array<int, string> $categoriesList Available categories
     * @param MetricsRepository $metrics Metrics repository
     * @return array<string, mixed> View data
     */
    public function getData(array $homeConfig, array $categoriesList, MetricsRepository $metrics): array
    {
        // Hero section (single featured article)
        $heroItems = $this->collectors->collectLatest(
            $homeConfig['hero'] ?? ['limit' => 1]
        );
        $hero = $heroItems[0] ?? null;

        // Top stories (below hero)
        $topStories = $this->collectors->collectLatest(
            $homeConfig['top_stories'] ?? ['limit' => 3, 'offset' => 1]
        );

        // Trending section
        $trending = $this->collectors->collectLatest(
            $homeConfig['trending'] ?? ['limit' => 8]
        );

        // Editor's picks (from specific categories in sequence)
        $editorsPicks = $this->collectors->collectCategorySequence(
            $homeConfig['editors_picks'] ?? []
        );

        // Market pulse (multi-column layout)
        $marketPulse = $this->collectors->collectCategoryColumns(
            $homeConfig['market_pulse'] ?? []
        );

        // Playbooks section (from playbook categories)
        $playbookArticles = $this->collectors->collectCategories(
            $homeConfig['playbooks'] ?? []
        );

        // Best picks (by article type)
        $bestPicks = $this->collectors->collectByType(
            $homeConfig['best_picks'] ?? []
        );

        // Face-offs / comparisons
        $faceOffs = $this->collectors->collectCategorySingle(
            $homeConfig['face_offs'] ?? []
        );

        // How-to guides
        $howTos = $this->collectors->collectByType(
            $homeConfig['how_tos'] ?? []
        );

        // Deals strip (flagged articles)
        $dealsStrip = $this->collectors->collectByFlag(
            $homeConfig['deals_strip'] ?? []
        );

        // Most read (allows duplicates)
        $mostRead = $this->collectors->collectLatest(
            $homeConfig['most_read'] ?? ['limit' => 9, 'mark_used' => false]
        );

        // Latest analysis feed
        $latestAnalysis = $this->collectors->collectLatest(
            $homeConfig['latest_analysis'] ?? ['limit' => 6, 'mark_used' => false],
            36
        );

        // Latest feed (sidebar/footer)
        $latestFeed = $this->collectors->collectLatest(
            $homeConfig['latest_feed'] ?? ['limit' => 6, 'mark_used' => false],
            36
        );

        // Category highlights
        $categoryHighlights = $this->collectors->collectCategoryHighlights($categoriesList);

        return [
            'hero' => $hero,
            'topStories' => $topStories,
            'latest' => $latestAnalysis,
            'categoryHighlights' => $categoryHighlights,
            'categoriesList' => $categoriesList,
            'trending' => $trending,
            'editorsPicks' => $editorsPicks,
            'marketPulse' => $marketPulse,
            'playbookArticles' => $playbookArticles,
            'bestPicks' => $bestPicks,
            'faceOffs' => $faceOffs,
            'howTos' => $howTos,
            'dealsStrip' => $dealsStrip,
            'mostRead' => $mostRead,
            'latestFeed' => $latestFeed,
            'metrics' => $metrics,
        ];
    }

    /**
     * Get the content collectors instance.
     *
     * Useful for custom collection scenarios.
     *
     * @return ContentCollectors
     */
    public function getCollectors(): ContentCollectors
    {
        return $this->collectors;
    }
}
