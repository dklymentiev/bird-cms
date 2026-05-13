<?php
/**
 * Article Edit View
 *
 * Variables:
 * - $article: array|null - Existing article data (null for new)
 * - $categories: array - Available categories
 * - $isNew: bool - Creating new article
 * - $flash: array|null - Flash message
 */

$categories = $categories ?? require dirname(__DIR__, 4) . '/config/categories.php';
$csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '');
$isNew = $isNew ?? false;

// Article defaults
$title = $article['title'] ?? '';
$slug = $article['slug'] ?? '';
$category = $article['category'] ?? '';
$type = $article['type'] ?? 'insight';
$status = $article['status'] ?? 'draft';
$description = $article['description'] ?? '';
$date = $article['date'] ?? date('Y-m-d');
$tags = $article['tags'] ?? [];
$tagsStr = is_array($tags) ? implode(', ', $tags) : $tags;
$heroImage = $article['hero_image'] ?? '';
$content = $article['content'] ?? '';
$author = $article['author'] ?? 'editorial-team';

// Types from config
$types = $config['article_types'] ?? ['insight' => 'Insight', 'guide' => 'Guide'];
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">
            <?= $isNew ? 'New Article' : 'Edit Article' ?>
        </h1>
        <?php if (!$isNew): ?>
            <p class="text-gray-600"><?= htmlspecialchars($category) ?>/<?= htmlspecialchars($slug) ?></p>
        <?php endif; ?>
    </div>
    <div class="flex items-center space-x-3">
        <?php if (!$isNew): ?>
            <a href="/<?= htmlspecialchars($category) ?>/<?= htmlspecialchars($slug) ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="px-4 py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                View on Site
            </a>
        <?php endif; ?>
        <a href="/admin/articles"
           class="px-4 py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
            Cancel
        </a>
        <!-- Split save button: primary saves whatever the Status field is set
             to (draft is the safe default); dropdown lets the operator commit
             to a Publish or Schedule path in one click without hunting for
             the Status select in the right sidebar. -->
        <div x-data="{ open: false }" class="relative inline-flex">
            <button type="submit" form="article-form"
                    class="px-4 py-2 bg-blue-900 text-blue-200 hover:bg-blue-800 transition-colors font-medium border border-blue-700 inline-flex items-center gap-2">
                <span><?= $isNew ? 'Create Article' : 'Save Changes' ?></span>
                <kbd x-data
                     x-text="navigator.platform?.startsWith('Mac') ? '⌘S' : 'Ctrl+S'"
                     class="text-xs font-mono opacity-60 px-1.5 py-0.5 border border-blue-700 leading-none">Ctrl+S</kbd>
            </button>
            <button type="button"
                    @click="open = !open"
                    class="px-2 py-2 bg-blue-900 text-blue-200 hover:bg-blue-800 border border-blue-700 border-l-0">
                <i class="ri-arrow-down-s-line text-base leading-none"></i>
            </button>
            <div x-show="open" x-cloak @click.away="open = false"
                 class="absolute right-0 top-full mt-1 w-56 bg-slate-800 border border-slate-700 shadow-lg z-30">
                <button type="button"
                        @click="$root.querySelector('select[name=status]').value='draft'; $root.requestSubmit(); open=false"
                        class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:bg-slate-700 flex items-center gap-2">
                    <i class="ri-draft-line text-base leading-none text-slate-400"></i>
                    Save as Draft
                </button>
                <button type="button"
                        @click="$root.querySelector('select[name=status]').value='published'; $root.requestSubmit(); open=false"
                        class="w-full text-left px-3 py-2 text-sm text-emerald-300 hover:bg-slate-700 flex items-center gap-2">
                    <i class="ri-checkbox-circle-line text-base leading-none"></i>
                    Save &amp; Publish now
                </button>
                <button type="button"
                        @click="$root.querySelector('select[name=status]').value='scheduled'; open=false; document.querySelector('input[name=scheduled_at]')?.focus()"
                        class="w-full text-left px-3 py-2 text-sm text-amber-300 hover:bg-slate-700 flex items-center gap-2">
                    <i class="ri-time-line text-base leading-none"></i>
                    Schedule for later…
                </button>
            </div>
        </div>
    </div>
</div>

<form id="article-form"
      method="POST"
      action="<?= $isNew ? '/admin/articles/create' : '/admin/articles/' . htmlspecialchars($category) . '/' . htmlspecialchars($slug) . '/update' ?>"
      class="space-y-6"
      x-data="articleEditor()"
      x-init="init()"
      @input.debounce.500ms="autosave()"
      @change.debounce.500ms="autosave()"
      @submit="clearDraft()"
      @keydown.ctrl.s.window.prevent="$el.requestSubmit()"
      @keydown.meta.s.window.prevent="$el.requestSubmit()">
    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
    <?php if (!$isNew): ?>
        <input type="hidden" name="_original_category" value="<?= htmlspecialchars($category) ?>">
        <input type="hidden" name="_original_slug" value="<?= htmlspecialchars($slug) ?>">
    <?php endif; ?>

    <!-- Draft recovery banner: shown when localStorage holds an unsaved
         snapshot newer than the loaded form data. User chooses to apply
         or discard; either way clears the snapshot key. -->
    <div x-show="hasDraft" x-cloak
         class="bg-amber-900/40 border border-amber-700 text-amber-200 px-4 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <i class="ri-history-line text-lg leading-none"></i>
            <span class="text-sm">
                Unsaved draft from <span x-text="draftAge"></span>.
            </span>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="restoreDraft()"
                    class="px-3 py-1.5 text-sm bg-amber-700 hover:bg-amber-600 text-amber-50 border border-amber-600">
                Restore
            </button>
            <button type="button" @click="discardDraft()"
                    class="px-3 py-1.5 text-sm text-amber-300 hover:text-amber-100">
                Discard
            </button>
        </div>
    </div>

    <!-- Autosave indicator (subtle, top-right of form) -->
    <div x-show="autosaveStatus" x-cloak
         class="text-xs text-slate-500 -mt-2"
         x-text="autosaveStatus"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Title -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                <input type="text"
                       name="title"
                       x-ref="titleInput"
                       value="<?= htmlspecialchars($title) ?>"
                       required
                       class="w-full px-4 py-2 border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Article title..."
                       @input="if (isNew) generateSlug($event.target.value)">
            </div>

            <!-- Content Editor -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Content (Markdown)</label>
                    <?php if (!empty($previewUrl)): ?>
                        <!-- Open the actual rendered page in a new tab. Drafts/scheduled
                             use a signed preview token; published articles open the live URL.
                             Replaces the previous in-editor markdown-only preview, which lacked
                             theme + hero + layout and didn't tell you what readers would see. -->
                        <a href="<?= htmlspecialchars($previewUrl) ?>"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 px-3 py-1 text-sm text-slate-300 hover:text-blue-300 hover:bg-slate-700 transition-colors border border-slate-700">
                            <i class="ri-external-link-line text-base leading-none"></i>
                            Open preview in new tab
                        </a>
                    <?php else: ?>
                        <span class="text-xs text-slate-500 italic">Save once to enable preview</span>
                    <?php endif; ?>
                </div>

                <!-- Editor -->
                <div>
                    <textarea name="content"
                              x-ref="contentEditor"
                              rows="20"
                              style="overflow: hidden; resize: none;"
                              x-init="$nextTick(() => { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; })"
                              @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                              class="w-full px-4 py-3 border border-gray-300 font-mono text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Write your article in Markdown..."><?= htmlspecialchars($content) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Publish Settings -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Publish Settings</h3>

                <!-- Status -->
                <div class="mb-4" x-data="{ status: '<?= htmlspecialchars($status) ?>' }">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select name="status"
                            x-model="status"
                            class="w-full px-3 py-2 border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="scheduled">Scheduled</option>
                    </select>

                    <!-- Schedule datetime: only when status === scheduled. Posts as
                         scheduled_at; the controller picks it up alongside date/status. -->
                    <div x-show="status === 'scheduled'" x-cloak class="mt-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Publish at</label>
                        <input type="datetime-local"
                               name="scheduled_at"
                               value="<?= htmlspecialchars($scheduledAt ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-slate-500 mt-1">Article goes live automatically at the chosen time.</p>
                    </div>
                </div>

                <!-- Date -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                    <input type="date"
                           name="date"
                           value="<?= htmlspecialchars($date) ?>"
                           class="w-full px-3 py-2 border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Category -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
                    <select name="category"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select category...</option>
                        <?php foreach ($categories as $catKey => $cat): ?>
                            <?php if ($catKey !== 'latest'): ?>
                                <option value="<?= htmlspecialchars($catKey) ?>" <?= $category === $catKey ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['title']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Type -->
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                    <select name="type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($types as $typeKey => $typeLabel): ?>
                            <option value="<?= htmlspecialchars($typeKey) ?>" <?= $type === $typeKey ? 'selected' : '' ?>>
                                <?= htmlspecialchars($typeLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- URL & SEO -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <h3 class="text-sm font-medium text-gray-900 mb-4">URL & SEO</h3>

                <!-- Slug -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Slug</label>
                    <div class="flex items-center gap-2">
                        <input type="text"
                               name="slug"
                               x-ref="slugInput"
                               value="<?= htmlspecialchars($slug) ?>"
                               required
                               pattern="[a-z0-9-]+"
                               class="flex-1 px-3 py-2 border border-gray-300 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="article-slug">
                        <!-- Regenerate from current title -- works on both create and edit, since
                             the auto-generation in @input only fires on isNew. -->
                        <button type="button"
                                @click="regenerateSlug()"
                                title="Regenerate slug from title"
                                class="px-2.5 py-2 text-slate-400 hover:text-blue-400 border border-slate-700 hover:border-blue-500 transition-colors">
                            <i class="ri-refresh-line text-base leading-none"></i>
                        </button>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                    <textarea name="description"
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Brief description for SEO..."><?= htmlspecialchars($description) ?></textarea>
                </div>

                <!-- Tags with autocomplete from existing corpus -->
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tags</label>
                    <input type="text"
                           name="tags"
                           list="bird-tag-list"
                           value="<?= htmlspecialchars($tagsStr) ?>"
                           class="w-full px-3 py-2 border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Comma-separated. Start typing for suggestions.">
                    <?php if (!empty($allTags)): ?>
                    <!-- Browser-native autocomplete: matches the last comma-separated
                         token. Lo-fi but requires zero JS and works on all browsers. -->
                    <datalist id="bird-tag-list">
                        <?php foreach (array_slice($allTags, 0, 200) as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <p class="text-xs text-slate-500 mt-1"><?= count($allTags) ?> tags in use across all articles.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Media -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Media</h3>

                <!-- Hero Image with Media Library picker -->
                <div class="mb-4" x-data="{ pickerOpen: false }">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hero Image URL</label>
                    <div class="flex items-center gap-2">
                        <input type="text"
                               name="hero_image"
                               x-ref="heroInput"
                               value="<?= htmlspecialchars($heroImage) ?>"
                               class="flex-1 px-3 py-2 border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="/assets/hero/...">
                        <button type="button"
                                @click="pickerOpen = true"
                                title="Pick from media library"
                                class="px-2.5 py-2 text-slate-400 hover:text-blue-400 border border-slate-700 hover:border-blue-500 transition-colors">
                            <i class="ri-image-line text-base leading-none"></i>
                        </button>
                    </div>

                    <!-- Media picker modal: iframes /admin/media?picker=1; the
                         picker view sends postMessage with the chosen URL when
                         user clicks a file. Parent sets the input + closes. -->
                    <div x-show="pickerOpen" x-cloak
                         class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
                         @keydown.escape.window="pickerOpen = false">
                        <div class="bg-slate-800 border border-slate-700 w-full max-w-4xl h-[80vh] flex flex-col"
                             @click.away="pickerOpen = false">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700">
                                <h3 class="text-sm font-medium text-slate-200">Pick hero image</h3>
                                <button type="button" @click="pickerOpen = false"
                                        class="text-slate-400 hover:text-slate-200">
                                    <i class="ri-close-line text-lg leading-none"></i>
                                </button>
                            </div>
                            <iframe x-show="pickerOpen" x-cloak
                                    src="/admin/media?picker=1"
                                    class="flex-1 w-full bg-slate-900"
                                    @load="window.addEventListener('message', (e) => {
                                        if (e.data?.bird === 'media-pick' && e.data.url) {
                                            $refs.heroInput.value = e.data.url;
                                            $refs.heroInput.dispatchEvent(new Event('input', { bubbles: true }));
                                            pickerOpen = false;
                                        }
                                    }, { once: true })"></iframe>
                        </div>
                    </div>
                </div>

                <!-- Hero Preview -->
                <?php if ($heroImage): ?>
                <div class="mb-4 rounded-lg overflow-hidden bg-gray-100">
                    <img src="<?= htmlspecialchars($heroImage) ?>"
                         alt="Hero preview"
                         class="w-full h-auto"
                         loading="lazy">
                </div>
                <?php endif; ?>

                <!-- Author -->
                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Author</label>
                    <input type="text"
                           name="author"
                           value="<?= htmlspecialchars($author) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="editorial-team">
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function articleEditor() {
    return {
        isNew: <?= $isNew ? 'true' : 'false' ?>,
        // ---- Draft autosave state ----
        storageKey: '',
        hasDraft: false,
        draftAge: '',
        autosaveStatus: '',
        dirty: false,
        autosaveTimer: null,

        init() {
            // Storage key is per-article (or "new") so multiple drafts
            // don't collide across browser tabs / articles.
            const orig = this.$el.querySelector('input[name="_original_slug"]');
            const cat  = this.$el.querySelector('input[name="_original_category"]');
            this.storageKey = this.isNew
                ? 'bird-cms-draft-new'
                : 'bird-cms-draft-' + (cat?.value || '_') + '-' + (orig?.value || '_');

            // Check for stored draft -- only flag if it actually has user
            // input (skip empty restore on first visit).
            const raw = localStorage.getItem(this.storageKey);
            if (raw) {
                try {
                    const draft = JSON.parse(raw);
                    if (draft.savedAt) {
                        this.hasDraft = true;
                        this.draftAge = this.formatAge(draft.savedAt);
                    }
                } catch (e) { /* corrupt JSON, ignore */ }
            }

            // beforeunload guard -- warn before tab close if dirty.
            window.addEventListener('beforeunload', (e) => {
                if (this.dirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        autosave() {
            this.dirty = true;
            // Snapshot every named input/textarea/select in the form.
            const data = { savedAt: new Date().toISOString(), fields: {} };
            this.$el.querySelectorAll('[name]').forEach(el => {
                if (el.name.startsWith('_')) return; // skip csrf + original_*
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.checked) data.fields[el.name] = el.value;
                } else {
                    data.fields[el.name] = el.value;
                }
            });
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(data));
                this.autosaveStatus = 'Saved locally · ' + new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            } catch (e) {
                this.autosaveStatus = 'Local save failed (storage full?)';
            }
        },

        restoreDraft() {
            const raw = localStorage.getItem(this.storageKey);
            if (!raw) { this.hasDraft = false; return; }
            try {
                const draft = JSON.parse(raw);
                Object.entries(draft.fields || {}).forEach(([name, value]) => {
                    const el = this.$el.querySelector('[name="' + name + '"]');
                    if (el) el.value = value;
                });
                this.hasDraft = false;
                this.dirty = true;
                this.autosaveStatus = 'Draft restored from ' + this.draftAge;
            } catch (e) { this.hasDraft = false; }
        },

        discardDraft() {
            localStorage.removeItem(this.storageKey);
            this.hasDraft = false;
        },

        clearDraft() {
            // Called on form submit -- if the server accepted the save,
            // we don't need the local copy any more.
            localStorage.removeItem(this.storageKey);
            this.dirty = false;
        },

        formatAge(iso) {
            const then = new Date(iso);
            const now = new Date();
            const sec = Math.floor((now - then) / 1000);
            if (sec < 60)        return 'just now';
            if (sec < 3600)      return Math.floor(sec/60) + ' min ago';
            if (sec < 86400)     return Math.floor(sec/3600) + ' h ago';
            return then.toLocaleString();
        },

        // ---- Slug helpers ----
        generateSlug(title) {
            // Auto-generated as user types title -- only on create, so we
            // never silently overwrite an established slug.
            if (!this.isNew) return;
            this.$refs.slugInput.value = this.slugify(title);
        },

        regenerateSlug() {
            // Triggered explicitly by the refresh button -- works on edit
            // pages too since the user is asking for it.
            const title = this.$refs.titleInput?.value || '';
            this.$refs.slugInput.value = this.slugify(title);
        },

        slugify(value) {
            return value
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/[\s_]+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '')
                .substring(0, 60);
        }
    }
}

</script>
