<?php
/** @var string $url */
/** @var string $title */
/** @var string $size - 'small' or 'large' */

$url = $url ?? '';
$title = $title ?? '';
$size = $size ?? 'small';
$encodedUrl = urlencode($url);
$encodedTitle = urlencode($title);

$iconSize = $size === 'large' ? 'h-5 w-5' : 'h-4 w-4';
$buttonSize = $size === 'large' ? 'p-2.5' : 'p-2';
$gapSize = $size === 'large' ? 'gap-3' : 'gap-2';
?>

<div class="flex flex-wrap items-center <?= $gapSize ?> gap-y-2 sm:flex-nowrap">
    <span class="w-full text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-slate-400 sm:w-auto">Share:</span>

    <!-- Twitter/X -->
    <a href="https://twitter.com/intent/tweet?url=<?= $encodedUrl ?>&text=<?= $encodedTitle ?>"
       target="_blank"
       rel="noopener noreferrer"
       class="inline-flex items-center justify-center <?= $buttonSize ?> rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-slate-900 hover:bg-slate-900 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:bg-slate-700"
       aria-label="Share on X">
        <svg class="<?= $iconSize ?>" fill="currentColor" viewBox="0 0 24 24">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
        </svg>
    </a>

    <!-- LinkedIn -->
    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $encodedUrl ?>"
       target="_blank"
       rel="noopener noreferrer"
       class="inline-flex items-center justify-center <?= $buttonSize ?> rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-600 hover:bg-blue-600 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-blue-600 dark:hover:bg-blue-600"
       aria-label="Share on LinkedIn">
        <svg class="<?= $iconSize ?>" fill="currentColor" viewBox="0 0 24 24">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
        </svg>
    </a>

    <!-- Facebook -->
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $encodedUrl ?>"
       target="_blank"
       rel="noopener noreferrer"
       class="inline-flex items-center justify-center <?= $buttonSize ?> rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-500 hover:bg-blue-500 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-blue-500 dark:hover:bg-blue-500"
       aria-label="Share on Facebook">
        <svg class="<?= $iconSize ?>" fill="currentColor" viewBox="0 0 24 24">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
    </a>

    <!-- Telegram -->
    <a href="https://t.me/share/url?url=<?= $encodedUrl ?>&text=<?= $encodedTitle ?>"
       target="_blank"
       rel="noopener noreferrer"
       class="inline-flex items-center justify-center <?= $buttonSize ?> rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-400 hover:bg-blue-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-blue-400 dark:hover:bg-blue-400"
       aria-label="Share on Telegram">
        <svg class="<?= $iconSize ?>" fill="currentColor" viewBox="0 0 24 24">
            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
        </svg>
    </a>

    <!-- Email -->
    <a href="mailto:?subject=<?= $encodedTitle ?>&body=<?= $encodedUrl ?>"
       class="inline-flex items-center justify-center <?= $buttonSize ?> rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-slate-900 hover:bg-slate-900 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:bg-slate-700"
       aria-label="Share via Email">
        <svg class="<?= $iconSize ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
    </a>

    <!-- Copy Link -->
    <button type="button"
            onclick="navigator.clipboard.writeText('<?= htmlspecialchars($url, ENT_QUOTES) ?>').then(() => { const btn = this; const orig = btn.innerHTML; btn.innerHTML = '<svg class=&quot;<?= $iconSize ?>&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; viewBox=&quot;0 0 24 24&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M5 13l4 4L19 7&quot;/></svg>'; setTimeout(() => btn.innerHTML = orig, 2000); })"
            class="inline-flex items-center justify-center <?= $buttonSize ?> rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-brand-500 hover:bg-brand-500 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-brand-500 dark:hover:bg-brand-500"
            aria-label="Copy link">
        <svg class="<?= $iconSize ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
        </svg>
    </button>
</div>
