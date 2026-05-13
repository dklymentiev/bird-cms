<?php
/**
 * Step 1 - system check.
 * Provided by Wizard::renderStep1():
 *   $checks   list<array{label,status,hint}>
 *   $allPass  bool
 */
?>
<h2>Welcome to Bird CMS</h2>
<p class="lead">A quick environment audit before we set up your site. This takes about a second.</p>

<?php if (!$allPass): ?>
    <div class="banner banner-fail">
        Fix the highlighted items below, then reload this page. Most failures are missing PHP extensions or non-writable directories.
    </div>
<?php endif; ?>

<ul class="check-list">
    <?php foreach ($checks as $row): ?>
        <li class="<?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="icon" aria-hidden="true">
                <?= $row['status'] === 'pass' ? '&#10003;' : ($row['status'] === 'warn' ? '!' : '&times;') ?>
            </span>
            <span>
                <span class="label"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($row['hint'])): ?>
                    <span class="hint"><?= htmlspecialchars($row['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>

<div class="actions">
    <a href="/install"
       class="btn btn-secondary"
       onclick="window.location.reload(); return false;">Re-check</a>
    <a href="/install/identity"
       class="btn btn-primary right<?= $allPass ? '' : ' is-disabled' ?>"
       <?= $allPass ? '' : 'aria-disabled="true" tabindex="-1"' ?>
       <?= $allPass ? '' : 'onclick="return false;"' ?>>Continue &rarr;</a>
</div>
