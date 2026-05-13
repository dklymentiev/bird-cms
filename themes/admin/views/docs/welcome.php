<?php
/**
 * Docs index landing page.
 *
 * Auto-rendered from DocsController::GROUPS. Lists every shipped doc as
 * a clickable card with a one-line summary pulled from the doc's
 * leading paragraph. Replaces the old in-page sidebar -- navigation
 * between docs is now: index -> doc -> back to index.
 *
 * Data:
 *   - $sections array<string, list<array{path,title,lede}>>
 */

/** @var array<string, list<array{path:string,title:string,lede:string}>> $sections */
?>
<div class="docs-page">
    <article class="prose docs-welcome">
        <h1>Documentation</h1>
        <p>Everything shipped in this repo, grouped by what you're trying to
        do. Click into any doc; the <em>back to docs</em> link at the top of
        every page brings you here.</p>

        <?php foreach ($sections as $group => $items): ?>
            <section class="docs-index-section">
                <h2><?= htmlspecialchars((string) $group) ?></h2>
                <ul class="docs-index-list">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <a class="docs-index-title" href="/admin/docs/<?= rawurlencode($item['path']) ?>">
                                <?= htmlspecialchars($item['title']) ?>
                            </a>
                            <?php if ($item['lede'] !== ''): ?>
                                <p class="docs-index-lede"><?= htmlspecialchars($item['lede']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </article>
</div>
