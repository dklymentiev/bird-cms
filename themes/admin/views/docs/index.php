<?php
/**
 * Docs viewer body (planner #1844).
 *
 * Back-link sits above a centered surface card so the prose has
 * GitHub-style breathing room rather than washing into the main
 * dark-green admin background.
 *
 * Data from DocsController:
 *   - $currentDoc   string  relative path of the doc being shown
 *   - $currentTitle string  resolved heading or prettified filename
 *   - $docHtml      string  already-rendered HTML (links rewritten,
 *                           internal .md becomes /admin/docs/<urlencoded>,
 *                           assets become /admin/docs/asset/...)
 */

/** @var string $currentDoc */
/** @var string $currentTitle */
/** @var string $docHtml */
?>
<p class="docs-back"><a href="/admin/docs">&larr; Docs</a></p>
<div class="docs-page">
    <article class="prose">
        <?= $docHtml ?>
    </article>
</div>
