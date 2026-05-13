<?php
/**
 * Wizard layout. Variables provided by Wizard::render():
 *   $viewFile     -- absolute path to themes/install/views/<step>.php
 *   $currentStep  -- 1..4 (drives progress indicator)
 *   $csrf         -- token for forms
 *   ...           -- view-specific vars (extracted via EXTR_SKIP)
 */
$siteName = htmlspecialchars($values['site_name'] ?? 'Bird CMS', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Install - Bird CMS</title>
    <link rel="stylesheet" href="/install/assets/install.css">
</head>
<body>
    <main class="install-shell">
        <header class="install-header">
            <a class="install-brand" href="/install" aria-label="Bird CMS">
                <span class="install-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" width="32" height="32"><path fill="currentColor" d="M16 4l4 6 6 0-3 5 3 5-6 0-4 6-4-6-6 0 3-5-3-5 6 0z"/></svg>
                </span>
                <span class="install-brand-text">
                    <strong>Bird CMS</strong>
                    <em>Install Wizard</em>
                </span>
            </a>
            <ol class="install-progress" aria-label="Install progress">
                <li class="<?= $currentStep >= 1 ? 'done' : '' ?> <?= $currentStep === 1 ? 'current' : '' ?>"><span>1</span><label>System</label></li>
                <li class="<?= $currentStep >= 2 ? 'done' : '' ?> <?= $currentStep === 2 ? 'current' : '' ?>"><span>2</span><label>Identity</label></li>
                <li class="<?= $currentStep >= 3 ? 'done' : '' ?> <?= $currentStep === 3 ? 'current' : '' ?>"><span>3</span><label>Finish</label></li>
            </ol>
        </header>

        <section class="install-card">
            <?php require $viewFile; ?>
        </section>

        <footer class="install-footer">
            <?php
                $versionFile = dirname(__DIR__, 2) . '/VERSION';
                $appVersion  = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';
            ?>
            <span>Bird CMS<?= $appVersion !== '' ? ' &middot; ' . htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') : '' ?></span>
        </footer>
    </main>
</body>
</html>
