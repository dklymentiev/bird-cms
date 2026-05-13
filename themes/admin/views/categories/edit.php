<?php
/**
 * Category Edit View
 *
 * Variables:
 * - $slug: string - Category slug
 * - $category: array - Category data
 * - $isNew: bool - Creating new category
 * - $flash: array|null - Flash message
 */

$csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '');

$title = $category['title'] ?? '';
$description = $category['description'] ?? '';
$icon = $category['icon'] ?? 'folder';
$subcategories = $category['subcategories'] ?? [];

// Available icons
$availableIcons = [
    'sparkles' => 'Sparkles',
    'chart-bar' => 'Chart Bar',
    'compass' => 'Compass',
    'cpu-chip' => 'CPU Chip',
    'wrench' => 'Wrench',
    'banknotes' => 'Banknotes',
    'heart' => 'Heart',
    'star' => 'Star',
    'server-stack' => 'Server Stack',
    'shield-check' => 'Shield Check',
    'tag' => 'Tag',
    'book-open' => 'Book Open',
    'scale' => 'Scale',
    'clock' => 'Clock',
    'folder' => 'Folder',
    'document-text' => 'Document Text',
    'globe-alt' => 'Globe',
    'light-bulb' => 'Light Bulb',
];
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">
            <?= $isNew ? 'New Category' : 'Edit Category' ?>
        </h1>
        <?php if (!$isNew): ?>
            <p class="text-gray-600">/<?= htmlspecialchars($slug) ?></p>
        <?php endif; ?>
    </div>
    <div class="flex items-center space-x-3">
        <?php if (!$isNew): ?>
            <a href="/<?= htmlspecialchars($slug) ?>"
               target="_blank"
               class="px-4 py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                View on Site
            </a>
        <?php endif; ?>
        <a href="/admin/categories"
           class="px-4 py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
            Cancel
        </a>
    </div>
</div>

<form method="POST"
      action="<?= $isNew ? '/admin/categories/create' : '/admin/categories/' . htmlspecialchars($slug) . '/update' ?>"
      class="space-y-6"
      x-data="categoryEditor()">
    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Info -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>

                <!-- Title -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text"
                           name="title"
                           value="<?= htmlspecialchars($title) ?>"
                           required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Category title..."
                           @input="if (isNew) generateSlug($event.target.value)">
                </div>

                <!-- Slug -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                    <div class="flex items-center">
                        <span class="px-3 py-2 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg text-gray-500">/</span>
                        <input type="text"
                               name="slug"
                               x-ref="slugInput"
                               value="<?= htmlspecialchars($slug) ?>"
                               required
                               pattern="[a-z0-9-]+"
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="category-slug">
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description"
                              rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Brief description of this category..."><?= htmlspecialchars($description) ?></textarea>
                </div>

                <!-- Icon -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Icon</label>
                    <select name="icon"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($availableIcons as $iconKey => $iconLabel): ?>
                            <option value="<?= $iconKey ?>" <?= $icon === $iconKey ? 'selected' : '' ?>>
                                <?= $iconLabel ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Subcategories -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Subcategories</h2>
                    <button type="button"
                            @click="addSubcategory()"
                            class="px-3 py-1.5 text-sm bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                        + Add Subcategory
                    </button>
                </div>

                <div class="space-y-3" x-ref="subcategoriesContainer">
                    <template x-for="(sub, index) in subcategories" :key="index">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <input type="text"
                                       :name="'subcat_keys[' + index + ']'"
                                       x-model="sub.key"
                                       placeholder="slug"
                                       class="w-full px-3 py-2 text-sm font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex-1">
                                <input type="text"
                                       :name="'subcat_titles[' + index + ']'"
                                       x-model="sub.title"
                                       placeholder="Title"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <button type="button"
                                    @click="removeSubcategory(index)"
                                    :disabled="sub.key === 'general'"
                                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                    title="Remove">
                                <i class="ri-delete-bin-line text-base leading-none"></i>
                            </button>
                        </div>
                    </template>
                </div>

                <p class="mt-3 text-xs text-gray-500">
                    The "general" subcategory cannot be removed. Add subcategories to organize articles within this category.
                </p>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Actions</h3>

                <button type="submit"
                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium mb-3">
                    <?= $isNew ? 'Create Category' : 'Save Changes' ?>
                </button>

                <?php if (!$isNew): ?>
                    <a href="/admin/articles?category=<?= htmlspecialchars($slug) ?>"
                       class="block w-full px-4 py-2 text-center text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        View Articles
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!$isNew): ?>
                <!-- Danger Zone -->
                <div class="bg-white rounded-lg shadow-sm border border-red-200 p-4">
                    <h3 class="text-sm font-medium text-red-900 mb-4">Danger Zone</h3>

                    <form method="POST"
                          action="/admin/categories/<?= htmlspecialchars($slug) ?>/delete"
                          onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.')">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                        <button type="submit"
                                class="w-full px-4 py-2 text-red-600 border border-red-300 rounded-lg hover:bg-red-50 transition-colors">
                            Delete Category
                        </button>
                    </form>

                    <p class="mt-2 text-xs text-gray-500">
                        Category can only be deleted if it contains no articles.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
function categoryEditor() {
    return {
        isNew: <?= $isNew ? 'true' : 'false' ?>,
        subcategories: <?= json_encode(array_map(function($key, $sub) {
            return ['key' => $key, 'title' => $sub['title'] ?? ''];
        }, array_keys($subcategories), array_values($subcategories))) ?>,

        generateSlug(title) {
            if (!this.isNew) return;

            const slug = title
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/[\s_]+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '')
                .substring(0, 30);

            this.$refs.slugInput.value = slug;
        },

        addSubcategory() {
            this.subcategories.push({ key: '', title: '' });
        },

        removeSubcategory(index) {
            if (this.subcategories[index].key !== 'general') {
                this.subcategories.splice(index, 1);
            }
        }
    }
}
</script>
