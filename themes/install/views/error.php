<?php
/**
 * Fallback error view used when Wizard::fatal() is called (CSRF expired,
 * write failure, rate limit hit). Variables in scope:
 *   $message  string  set by the caller via $errors / fatal()
 */
$msg = $message ?? 'An unexpected error stopped the install.';
?>
<h2>Something went wrong</h2>
<p class="lead">The install didn't finish. Details below - fix the underlying issue and start over at <code>/install</code>.</p>

<div class="banner banner-fail">
    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="actions">
    <a href="/install" class="btn btn-primary right">Restart install</a>
</div>
