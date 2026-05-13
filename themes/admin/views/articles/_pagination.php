<?php if ($pagination['totalPages'] > 1): ?>
<div class="flex items-center justify-between <?= $paginationClass ?? '' ?>">
    <p class="text-sm text-gray-500">
        Showing <?= (($pagination['page'] - 1) * $pagination['perPage']) + 1 ?>–<?= min($pagination['page'] * $pagination['perPage'], $pagination['total']) ?>
        of <?= $pagination['total'] ?>
    </p>

    <div class="flex items-center space-x-1">
        <?php if ($pagination['page'] > 1): ?>
            <a href="<?= htmlspecialchars(\App\Admin\ArticleController::buildFilterUrl($filters, ['page' => $pagination['page'] - 1])) ?>"
               class="px-2 py-1 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                ←
            </a>
        <?php endif; ?>

        <?php
        $start = max(1, $pagination['page'] - 2);
        $end = min($pagination['totalPages'], $pagination['page'] + 2);
        ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $pagination['page']): ?>
                <span class="px-2 py-1 text-sm font-medium text-white bg-blue-500 rounded">
                    <?= $i ?>
                </span>
            <?php else: ?>
                <a href="<?= htmlspecialchars(\App\Admin\ArticleController::buildFilterUrl($filters, ['page' => $i])) ?>"
                   class="px-2 py-1 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                    <?= $i ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($pagination['page'] < $pagination['totalPages']): ?>
            <a href="<?= htmlspecialchars(\App\Admin\ArticleController::buildFilterUrl($filters, ['page' => $pagination['page'] + 1])) ?>"
               class="px-2 py-1 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                →
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
