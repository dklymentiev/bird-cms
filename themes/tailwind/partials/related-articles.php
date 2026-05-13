<?php
/**
 * Related Articles Partial
 *
 * Displays related articles using pillar-cluster SEO strategy.
 * Automatically shows pillar for clusters and clusters for pillars.
 *
 * Usage in layout:
 *   <?= $this->partial('related-articles', ['article' => $article, 'limit' => 3]) ?>
 *
 * Variables:
 *   - $article: Current article array (required)
 *   - $limit: Number of articles to show (default: 3)
 *   - $title: Section title (default: "Related Articles")
 */

$article = $article ?? null;
$limit = $limit ?? 3;
$title = $title ?? 'Related Articles';

if (!$article || empty($article['slug'])) {
    return;
}

// Get related articles using pillar-aware strategy
$articlesRepo = new \App\Content\ArticleRepository(
    config('content_dir') . '/articles'
);

$related = $articlesRepo->relatedPillarAware($article, $limit);

if (empty($related)) {
    return;
}
?>

<section class="related-articles mt-12 pt-8 border-t border-gray-200 dark:border-gray-700">
    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
        <?= htmlspecialchars($title) ?>
    </h2>

    <div class="grid gap-6 md:grid-cols-<?= min(count($related), 3) ?>">
        <?php foreach ($related as $item): ?>
            <article class="related-article group">
                <a href="/<?= htmlspecialchars($item['category']) ?>/<?= htmlspecialchars($item['slug']) ?>"
                   class="block p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-400 hover:shadow-md transition-all">

                    <?php if (!empty($item['hero_image'])): ?>
                        <img src="<?= htmlspecialchars($item['hero_image']) ?>"
                             alt="<?= htmlspecialchars($item['title']) ?>"
                             class="w-full h-32 object-cover rounded-md mb-3"
                             loading="lazy">
                    <?php endif; ?>

                    <span class="inline-block text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wide mb-2">
                        <?= htmlspecialchars(ucfirst(str_replace('-', ' ', $item['category']))) ?>
                    </span>

                    <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 line-clamp-2 mb-2">
                        <?= htmlspecialchars($item['title']) ?>
                    </h3>

                    <?php if (!empty($item['description'])): ?>
                        <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                            <?= htmlspecialchars(substr($item['description'], 0, 120)) ?>...
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($item['reading_time'])): ?>
                        <span class="text-xs text-gray-500 dark:text-gray-500 mt-2 block">
                            <?= htmlspecialchars($item['reading_time']) ?>
                        </span>
                    <?php endif; ?>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
