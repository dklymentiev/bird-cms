<?php
/**
 * Article Card Partial (Compact)
 */

$status = $article['status'] ?? 'published';
$publishAt = $article['publish_at'] ?? null;
$isDraft = $status === 'draft';
// Scheduled only if status=scheduled AND publish_at is in the future
$isScheduled = $status === 'scheduled' && $publishAt && strtotime($publishAt) > time();
$category = $article['category'] ?? 'unknown';
$heroImage = $article['hero_image'] ?? null;
$editUrl = "/admin/articles/" . htmlspecialchars($category) . "/" . htmlspecialchars($article['slug']) . "/edit";
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '');

// Generate view URL - with token for drafts and scheduled
if ($isDraft || $isScheduled) {
    $expires = time() + 3600;
    $secretKey = config('app_key');
    $previewSlug = $category . '/' . $article['slug'];
    $token = hash_hmac('sha256', $previewSlug . '|' . $expires, $secretKey);
    $viewUrl = "/" . htmlspecialchars($category) . "/" . htmlspecialchars($article['slug']) . "?preview=1&token=" . $token . "&expires=" . $expires;
} else {
    $viewUrl = "/" . htmlspecialchars($category) . "/" . htmlspecialchars($article['slug']);
}
?>

<div class="<?= $isDraft ? 'bg-gray-50 opacity-60' : ($isScheduled ? 'bg-blue-50/50' : 'bg-white') ?> rounded-lg border border-gray-200 overflow-hidden hover:border-gray-300 hover:opacity-100 transition-all group">
    <!-- Title - fixed 2 lines height -->
    <a href="<?= $editUrl ?>" class="block px-2 py-2 border-b border-gray-100">
        <h3 class="text-base font-medium text-gray-900 line-clamp-2 h-12 leading-6 group-hover:text-blue-600 transition-colors">
            <?= htmlspecialchars($article['title'] ?? 'Untitled') ?>
        </h3>
    </a>

    <!-- Image -->
    <a href="<?= $editUrl ?>" class="block aspect-video bg-gray-50 overflow-hidden">
        <?php if ($heroImage): ?>
            <img src="<?= htmlspecialchars($heroImage) ?>?t=<?= time() ?>" alt="" class="w-full h-full object-cover" loading="lazy">
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center text-gray-300">
                <i class="ri-image-line text-xl leading-none"></i>
            </div>
        <?php endif; ?>
    </a>

    <!-- Icon Buttons -->
    <div class="px-2 py-2 flex items-center justify-between bg-gray-50" x-data="{ showDatePicker: false }">
        <div class="flex items-center gap-1">
            <?php if ($isDraft): ?>
                <span class="text-sm text-yellow-600 font-medium">Draft</span>
            <?php elseif ($isScheduled): ?>
                <span class="text-sm text-blue-600 font-medium"><?= date('M j, H:i', strtotime($publishAt)) ?></span>
            <?php else: ?>
                <span class="text-sm text-green-600 font-medium"><?= !empty($article['date']) ? htmlspecialchars($article['date']) : 'Live' ?></span>
            <?php endif; ?>
        </div>
        <div class="flex items-center">
            <!-- View -->
            <a href="<?= $viewUrl ?>" target="_blank" class="p-2 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded" title="View">
                <i class="ri-eye-line text-lg leading-none"></i>
            </a>
            <!-- Publish/Unpublish -->
            <?php if ($isDraft || $isScheduled): ?>
                <form method="POST" action="/admin/articles/<?= htmlspecialchars($category) ?>/<?= htmlspecialchars($article['slug']) ?>/publish">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <button type="submit" class="p-2 text-gray-400 hover:text-green-500 hover:bg-green-50 rounded" title="Publish now">
                        <i class="ri-checkbox-circle-line text-lg leading-none"></i>
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="/admin/articles/<?= htmlspecialchars($category) ?>/<?= htmlspecialchars($article['slug']) ?>/unpublish">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <button type="submit" class="p-2 text-gray-400 hover:text-yellow-500 hover:bg-yellow-50 rounded" title="Unpublish">
                        <i class="ri-forbid-2-line text-lg leading-none"></i>
                    </button>
                </form>
            <?php endif; ?>
            <!-- Schedule/Date -->
            <div class="relative">
                <button @click="showDatePicker = !showDatePicker" class="p-2 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded" title="Set date">
                    <i class="ri-calendar-line text-lg leading-none"></i>
                </button>
                <div x-show="showDatePicker" @click.away="showDatePicker = false" x-cloak
                     class="absolute right-0 bottom-full mb-1 bg-white rounded-lg shadow-lg border border-gray-200 p-3 z-10">
                    <form method="POST" action="/admin/articles/<?= htmlspecialchars($category) ?>/<?= htmlspecialchars($article['slug']) ?>/schedule">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="datetime-local" name="publish_at" required
                               value="<?= $isScheduled && $publishAt ? date('Y-m-d\TH:i', strtotime($publishAt)) : date('Y-m-d\TH:i') ?>"
                               class="text-sm border border-gray-300 rounded px-2 py-1 mb-2 w-full">
                        <button type="submit" class="w-full text-sm bg-blue-600 text-white rounded px-3 py-1.5 hover:bg-blue-700">
                            Set Date
                        </button>
                    </form>
                </div>
            </div>
            <!-- More menu -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded" title="More">
                    <i class="ri-more-2-line text-lg leading-none"></i>
                </button>
                <div x-show="open" @click.away="open = false" x-cloak
                     class="absolute right-0 bottom-full mb-1 w-32 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-10">
                    <form method="POST" action="/admin/articles/<?= htmlspecialchars($category) ?>/<?= htmlspecialchars($article['slug']) ?>/delete"
                          onsubmit="return confirm('Delete this article?')">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <button type="submit" class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
