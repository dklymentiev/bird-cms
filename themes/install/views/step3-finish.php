<?php
/**
 * Step 3 - confirm and install.
 * Provided by Wizard::renderStep3():
 *   $identity  array (validated step 2 values, no password)
 *   $csrf      string
 */
$show = static function(string $key) use ($identity) {
    return htmlspecialchars((string)($identity[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};
?>
<h2>Ready to install</h2>
<p class="lead">Review the summary, choose whether to seed demo content, and we'll write the config files.</p>

<dl class="summary">
    <dt>Site name</dt><dd><?= $show('site_name') ?></dd>
    <dt>Site URL</dt><dd><?= $show('site_url') ?></dd>
    <dt>Admin user</dt><dd><?= $show('admin_username') ?> &nbsp; (<?= $show('admin_email') ?>)</dd>
    <dt>Timezone</dt><dd><?= $show('timezone') ?></dd>
    <dt>Language</dt><dd><?= $show('language') ?></dd>
</dl>

<form method="POST" action="/install/finish">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <label class="checkbox">
        <input type="checkbox" name="seed_demo" value="1" checked>
        Install demo content (sample pages, articles, categories) -- recommended for new sites.
    </label>

    <div class="banner banner-info" style="margin-top: 18px;">
        We'll generate a fresh <code>APP_KEY</code>, hash your password with bcrypt, and set
        <code>storage/installed.lock</code>. Re-running this wizard requires deleting the lock manually.
    </div>

    <div class="actions">
        <a href="/install/identity" class="btn btn-secondary">&larr; Back</a>
        <button type="submit" class="btn btn-primary right">Install Bird CMS</button>
    </div>
</form>
