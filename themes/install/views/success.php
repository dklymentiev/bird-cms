<?php
/**
 * Step 4 - success.
 * Provided by Wizard::renderSuccess():
 *   $version    string  (e.g. 2.0.0-alpha.15)
 *   $copied     list<string>  (relative paths of seeded files; may be empty)
 *   $admin_url  string  (where the "Go to admin" button leads)
 */
?>
<h2>You're all set</h2>
<p class="lead">Bird CMS <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?> is installed and ready.</p>

<div class="banner banner-success">
    Sign in to <strong>/admin</strong> with the credentials you just chose. Bookmark the URL --
    the admin entry is hidden by default and won't appear in any public navigation.
</div>

<?php if (!empty($copied)): ?>
    <p class="lead">Demo content seeded (<?= count($copied) ?> files):</p>
    <dl class="summary">
        <?php foreach (array_slice($copied, 0, 12) as $path): ?>
            <dt>+</dt><dd><?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?></dd>
        <?php endforeach; ?>
        <?php if (count($copied) > 12): ?>
            <dt>+</dt><dd>… and <?= count($copied) - 12 ?> more</dd>
        <?php endif; ?>
    </dl>
<?php endif; ?>

<div class="actions">
    <a href="/" class="btn btn-secondary">Visit site</a>
    <a href="<?= htmlspecialchars($admin_url, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary right">Go to admin &rarr;</a>
</div>
