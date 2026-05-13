<?php
/**
 * URL Inventory view — single content browser/editor.
 *
 * Variables:
 * - $urls       list<array{path, loc, source, category, lastmod, priority,
 *                          changefreq, in_sitemap, noindex}>
 * - $sources    list<string> distinct source values for the filter dropdown
 * - $totalUrls  int
 * - $inSitemap  int (urls that will actually appear in sitemap.xml)
 * - $csrf       string
 *
 * The pencil per row opens a four-tab modal:
 *   Content   – body + title + description + hero (only when editable)
 *   Meta      – raw YAML extras (everything beyond the dedicated inputs)
 *   Template  – per-URL view template override (saved to url-meta.json)
 *   Sitemap   – existing in_sitemap / noindex / priority / changefreq
 *
 * Save flow on submit:
 *   1. POST /admin/pages/save-content       (body + meta) — only if editable_body
 *   2. POST /admin/pages/save-template      (template override)
 *   3. POST /admin/pages/update             (sitemap row, classic form)
 * Steps 1 + 2 run as fetch() with CSRF; step 3 is the existing form submit.
 * If step 1 fails the modal stays open with the error so the operator can
 * fix the YAML.
 */
$headerCrumbs = [['Pages', null]];
?>

<div x-data="urlInventory(<?= htmlspecialchars(json_encode([
        'csrf' => $csrf,
    ]), ENT_QUOTES) ?>)">

    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-4 flex-1">
            <span class="text-sm text-slate-400">
                <?= $totalUrls ?> total &middot; <?= $inSitemap ?> in sitemap
            </span>
            <div class="flex-1 max-w-md relative">
                <i class="ri-search-line text-base text-slate-500 absolute left-3 top-1/2 -translate-y-1/2 leading-none"></i>
                <input type="text" x-model="q" placeholder="Filter by URL or category..."
                       class="w-full pl-9 pr-3 py-1.5 bg-slate-900 border border-slate-700 text-sm text-slate-200 placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <select x-model="source"
                    class="px-3 py-1.5 bg-slate-900 border border-slate-700 text-sm text-slate-200">
                <option value="">All sources</option>
                <?php foreach ($sources as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="/sitemap.xml" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-slate-300 hover:text-blue-300 hover:bg-slate-700 transition-colors border border-slate-700">
            <i class="ri-external-link-line text-base leading-none"></i>
            View sitemap.xml
        </a>
    </div>

    <div class="bg-slate-800 border border-slate-700 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800 text-slate-400 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">URL</th>
                    <th class="px-4 py-3 text-left">Source</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-center">Sitemap</th>
                    <th class="px-4 py-3 text-center">Index</th>
                    <th class="px-4 py-3 text-left">Priority</th>
                    <th class="px-4 py-3 text-left">Last mod</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php foreach ($urls as $u): ?>
                    <?php $rowJson = htmlspecialchars(json_encode($u, JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>
                    <tr class="hover:bg-slate-800"
                        x-show="(q === '' || '<?= htmlspecialchars(strtolower($u['path'].' '.$u['category']), ENT_QUOTES) ?>'.includes(q.toLowerCase())) && (source === '' || source === '<?= htmlspecialchars($u['source'], ENT_QUOTES) ?>')">
                        <td class="px-4 py-2.5 text-slate-100 font-mono text-xs">
                            <a href="<?= htmlspecialchars($u['path']) ?>" target="_blank" rel="noopener"
                               class="hover:text-blue-400 inline-flex items-center gap-1">
                                <?= htmlspecialchars($u['path']) ?>
                                <i class="ri-external-link-line text-xs leading-none opacity-50"></i>
                            </a>
                        </td>
                        <td class="px-4 py-2.5 text-slate-400 text-xs">
                            <span class="px-1.5 py-0.5 bg-slate-800 border border-slate-700"><?= htmlspecialchars($u['source']) ?></span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-400 text-xs"><?= htmlspecialchars($u['category']) ?></td>
                        <td class="px-4 py-2.5 text-center">
                            <?php if ($u['noindex']): ?>
                                <span class="text-amber-500" title="Forced out by noindex">—</span>
                            <?php elseif ($u['in_sitemap']): ?>
                                <i class="ri-checkbox-circle-line text-emerald-400 text-base leading-none"></i>
                            <?php else: ?>
                                <i class="ri-close-circle-line text-slate-600 text-base leading-none"></i>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <?php if ($u['noindex']): ?>
                                <span class="text-xs text-amber-400">noindex</span>
                            <?php else: ?>
                                <i class="ri-checkbox-circle-line text-emerald-400 text-base leading-none"></i>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-slate-400 text-xs"><?= htmlspecialchars($u['priority']) ?></td>
                        <td class="px-4 py-2.5 text-slate-500 text-xs"><?= htmlspecialchars($u['lastmod']) ?></td>
                        <td class="px-4 py-2.5 text-right">
                            <button @click='openEdit(<?= $rowJson ?>)'
                                    class="text-slate-500 hover:text-blue-400 inline-block px-1.5"
                                    title="Edit URL">
                                <i class="ri-edit-line text-base leading-none"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Tabbed edit modal: Content + Meta + Template + Sitemap. -->
    <div x-show="edit.open" x-cloak
         class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
         @keydown.escape.window="closeEdit()">
        <div class="bg-slate-800 border border-slate-700 w-full max-w-4xl max-h-[90vh] flex flex-col"
             @click.away="closeEdit()">

            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700">
                <div class="flex items-center gap-3 min-w-0">
                    <h3 class="text-sm font-medium text-slate-200 shrink-0">Edit URL</h3>
                    <code class="px-2 py-0.5 bg-slate-900 text-slate-300 text-xs font-mono border border-slate-700 truncate"
                          x-text="edit.path"></code>
                    <span class="px-1.5 py-0.5 bg-slate-900 border border-slate-700 text-xs text-slate-400 shrink-0"
                          x-text="edit.source"></span>
                </div>
                <button type="button" @click="closeEdit()"
                        class="text-slate-400 hover:text-slate-200">
                    <i class="ri-close-line text-lg leading-none"></i>
                </button>
            </div>

            <!-- Loading + error banner -->
            <div x-show="edit.loading" class="px-4 py-2 bg-slate-900 text-slate-400 text-xs border-b border-slate-700">
                Loading content...
            </div>
            <div x-show="edit.error" class="px-4 py-2 bg-red-900/40 text-red-200 text-xs border-b border-red-700"
                 x-text="edit.error"></div>
            <div x-show="edit.notice" class="px-4 py-2 bg-amber-900/40 text-amber-200 text-xs border-b border-amber-700"
                 x-text="edit.notice"></div>

            <!-- Tab strip -->
            <div class="flex border-b border-slate-700 bg-slate-900">
                <template x-for="t in tabs" :key="t.id">
                    <button type="button" @click="tab = t.id"
                            :class="tab === t.id
                                ? 'border-blue-500 text-slate-100'
                                : 'border-transparent text-slate-400 hover:text-slate-200'"
                            class="px-4 py-2 text-xs uppercase tracking-wider border-b-2 transition-colors">
                        <span x-text="t.label"></span>
                    </button>
                </template>
            </div>

            <!-- Tab bodies -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4">

                <!-- Content tab -->
                <div x-show="tab === 'content'" class="space-y-3">
                    <template x-if="!edit.editable_body && edit.source === 'static'">
                        <div class="p-4 bg-slate-900 border border-slate-700 text-sm text-slate-300 space-y-3">
                            <p>
                                This URL is rendered programmatically. To add an
                                intro / override, create
                                <code class="text-xs bg-slate-800 px-1.5 py-0.5"
                                      x-text="'content/pages/' + edit.slug + '.md'"></code>
                                and the engine will render it inside the chosen
                                template as <code class="text-xs">$intro</code>.
                            </p>
                            <button type="button" @click="createOverride()"
                                    class="px-3 py-1.5 text-sm bg-blue-900 text-blue-200 hover:bg-blue-800 border border-blue-700">
                                Create override file
                            </button>
                        </div>
                    </template>

                    <template x-if="edit.editable_body">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Title</label>
                                <input type="text" x-model="edit.title"
                                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Description</label>
                                <textarea x-model="edit.description" rows="2"
                                          class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Hero image</label>
                                <div class="flex gap-2">
                                    <input type="text" x-model="edit.hero_image"
                                           placeholder="/uploads/... or ./hero.webp"
                                           class="flex-1 px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200 font-mono">
                                    <button type="button" @click="pickMedia()"
                                            class="px-3 py-2 text-sm bg-slate-700 text-slate-200 hover:bg-slate-600 border border-slate-600">
                                        Browse
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Body (Markdown)</label>
                                <textarea x-model="edit.body" rows="22" spellcheck="false" wrap="off"
                                          class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-xs text-slate-200 font-mono"></textarea>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Meta tab -->
                <div x-show="tab === 'meta'" class="space-y-4">
                    <p class="text-xs text-slate-500">
                        Common publish controls live here. Custom or theme-specific keys
                        go in the advanced YAML below; structured fields always win over
                        duplicates there.
                    </p>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                            <select x-model="edit.meta_fields.status"
                                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200">
                                <option value="">— inherit —</option>
                                <option value="draft">draft</option>
                                <option value="published">published</option>
                                <option value="scheduled">scheduled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Date</label>
                            <input type="date" x-model="edit.meta_fields.date"
                                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200">
                        </div>
                    </div>

                    <div x-show="edit.meta_fields.status === 'scheduled'">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Scheduled at</label>
                        <input type="datetime-local" x-model="edit.meta_fields.scheduled_at"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200">
                        <p class="text-xs text-slate-500 mt-1">
                            Required when status is <code class="text-xs">scheduled</code>.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Hero image</label>
                        <div class="flex gap-2">
                            <!-- Bound to the same edit.hero_image as the Content tab
                                 so the two stay in lockstep -- one source of truth,
                                 one media picker, no merge logic on save. -->
                            <input type="text" x-model="edit.hero_image"
                                   placeholder="/uploads/... or ./hero.webp"
                                   class="flex-1 px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200 font-mono">
                            <button type="button" @click="pickMedia()"
                                    class="px-3 py-2 text-sm bg-slate-700 text-slate-200 hover:bg-slate-600 border border-slate-600">
                                Browse
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Shared with the Content tab's hero image input.
                        </p>
                    </div>

                    <details class="border border-slate-700 bg-slate-900/50">
                        <summary class="px-3 py-2 text-xs uppercase tracking-wider text-slate-400 cursor-pointer select-none hover:text-slate-200">
                            Advanced: raw YAML
                        </summary>
                        <div class="p-3 space-y-2">
                            <p class="text-xs text-slate-500">
                                Custom keys beyond the structured fields above. Anything
                                this form doesn't know about is preserved as-is on save.
                            </p>
                            <textarea x-model="edit.meta_yaml" rows="14" spellcheck="false" wrap="off"
                                      placeholder="tags:&#10;  - example"
                                      class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-xs text-slate-200 font-mono"></textarea>
                        </div>
                    </details>
                </div>

                <!-- Template tab -->
                <div x-show="tab === 'template'" class="space-y-3">
                    <p class="text-xs text-slate-500">
                        Override the view template for this URL. Empty = use the
                        default for this content type.
                    </p>
                    <select x-model="edit.template"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200">
                        <option value="">Use default for this content type</option>
                        <template x-for="tpl in edit.available_templates" :key="tpl">
                            <option :value="tpl" x-text="tpl"></option>
                        </template>
                    </select>
                </div>

                <!-- Sitemap tab -->
                <div x-show="tab === 'sitemap'" class="space-y-4">
                    <label class="flex items-center gap-2 text-sm text-slate-200 cursor-pointer">
                        <input type="checkbox" x-model="edit.in_sitemap" class="w-4 h-4">
                        <span>Include in <code class="text-xs">sitemap.xml</code></span>
                    </label>

                    <label class="flex items-center gap-2 text-sm text-slate-200 cursor-pointer">
                        <input type="checkbox" x-model="edit.noindex" class="w-4 h-4">
                        <span>Send <code class="text-xs">noindex,nofollow</code> robots meta (also drops from sitemap)</span>
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Priority</label>
                            <select x-model="edit.priority"
                                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200">
                                <?php foreach (['1.0','0.9','0.8','0.7','0.6','0.5','0.4','0.3','0.2','0.1'] as $p): ?>
                                    <option value="<?= $p ?>"><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Changefreq</label>
                            <select x-model="edit.changefreq"
                                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-sm text-slate-200">
                                <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $f): ?>
                                    <option value="<?= $f ?>"><?= $f ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer with single Save -->
            <div class="flex items-center justify-between gap-2 px-4 py-3 border-t border-slate-700 bg-slate-900">
                <div class="text-xs text-slate-500" x-text="saveStatus"></div>
                <div class="flex gap-2">
                    <button type="button" @click="closeEdit()"
                            class="px-3 py-1.5 text-sm text-slate-400 hover:text-slate-200">Cancel</button>
                    <button type="button" @click="saveAll()" :disabled="edit.saving"
                            class="px-3 py-1.5 text-sm bg-blue-900 text-blue-200 hover:bg-blue-800 border border-blue-700 disabled:opacity-50">
                        Save all
                    </button>
                </div>
            </div>

            <!-- Hidden form used for the legacy /admin/pages/update sitemap save.
                 Keeps the existing handler unchanged; saveAll() submits this
                 after the AJAX content + template writes succeed. -->
            <form x-ref="sitemapForm" method="POST" action="/admin/pages/update" class="hidden">
                <input type="hidden" name="_csrf" :value="csrf">
                <input type="hidden" name="path" :value="edit.path">
                <input type="hidden" name="in_sitemap" :value="edit.in_sitemap ? '1' : '0'">
                <input type="hidden" name="noindex" :value="edit.noindex ? '1' : '0'">
                <input type="hidden" name="priority" :value="edit.priority">
                <input type="hidden" name="changefreq" :value="edit.changefreq">
            </form>
        </div>
    </div>

    <!-- Media picker iframe modal: same /admin/media UI articles editor uses.
         Listens for window 'media:selected' message to drop the URL into
         the hero image field. -->
    <div x-show="media.open" x-cloak
         class="fixed inset-0 z-[60] bg-black/70 flex items-center justify-center p-4"
         @keydown.escape.window="media.open = false">
        <div class="bg-slate-800 border border-slate-700 w-full max-w-4xl h-[80vh] flex flex-col"
             @click.away="media.open = false">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700">
                <h3 class="text-sm font-medium text-slate-200">Select media</h3>
                <button type="button" @click="media.open = false"
                        class="text-slate-400 hover:text-slate-200">
                    <i class="ri-close-line text-lg leading-none"></i>
                </button>
            </div>
            <iframe src="/admin/media?picker=1" class="flex-1 w-full bg-white"></iframe>
        </div>
    </div>
</div>

<script>
function urlInventory(opts) {
    return {
        csrf: opts.csrf,
        q: '',
        source: '',
        tab: 'content',
        tabs: [
            { id: 'content',  label: 'Content'  },
            { id: 'meta',     label: 'Meta'     },
            { id: 'template', label: 'Template' },
            { id: 'sitemap',  label: 'Sitemap'  },
        ],
        media: { open: false },
        saveStatus: '',
        edit: {
            open: false,
            loading: false,
            saving: false,
            error: '',
            notice: '',
            path: '',
            source: '',
            slug: '',
            category: '',
            editable_body: false,
            editable_template: true,
            available_templates: [],
            template: '',
            title: '',
            description: '',
            hero_image: '',
            body: '',
            meta_yaml: '',
            meta_fields: {
                status: '',
                date: '',
                scheduled_at: '',
                hero_image: '',
            },
            in_sitemap: true,
            noindex: false,
            priority: '0.5',
            changefreq: 'weekly',
        },

        // localStorage key per URL so concurrent unsaved drafts don't
        // clobber each other. Mirrors the article editor pattern.
        storageKey() {
            return 'bird-url-edit-' + this.edit.path;
        },

        openEdit(row) {
            this.tab = 'content';
            this.saveStatus = '';
            // Carry the sitemap row in immediately; the AJAX call only
            // backfills content + template, so the user sees the sitemap
            // tab populated even before the network round-trip lands.
            this.edit = Object.assign({}, this.edit, {
                open: true,
                loading: true,
                saving: false,
                error: '',
                notice: '',
                path: row.path,
                source: row.source,
                in_sitemap: !!row.in_sitemap,
                noindex: !!row.noindex,
                priority: row.priority || '0.5',
                changefreq: row.changefreq || 'weekly',
                title: '',
                description: '',
                hero_image: '',
                body: '',
                meta_yaml: '',
                meta_fields: { status: '', date: '', scheduled_at: '', hero_image: '' },
                template: '',
                slug: '',
                category: '',
                editable_body: false,
                editable_template: true,
                available_templates: [],
            });

            this.fetchContent();
        },

        closeEdit() {
            this.persistDraft();
            this.edit.open = false;
        },

        async fetchContent() {
            const fd = new FormData();
            fd.append('_csrf', this.csrf);
            fd.append('path', this.edit.path);

            try {
                const res = await fetch('/admin/pages/edit-content', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                });
                const j = await res.json();
                if (!j.ok) {
                    this.edit.error = j.error || 'Failed to load content.';
                    this.edit.loading = false;
                    return;
                }

                this.edit.source             = j.source;
                this.edit.slug               = j.slug;
                this.edit.category           = j.category || '';
                this.edit.editable_body      = !!j.editable_body;
                this.edit.editable_template  = !!j.editable_template;
                this.edit.available_templates = j.available_templates || [];
                this.edit.template           = j.template || '';
                this.edit.title              = j.title || '';
                this.edit.description        = j.description || '';
                this.edit.hero_image         = j.hero_image || '';
                this.edit.body               = j.body || '';
                this.edit.meta_yaml          = j.meta_yaml || '';
                this.edit.meta_fields        = Object.assign(
                    { status: '', date: '', scheduled_at: '', hero_image: '' },
                    j.meta_fields || {}
                );
                this.edit.loading            = false;

                if (j.source === 'static' && !j.editable_body) {
                    this.edit.notice = 'No content/pages/' + j.slug + '.md yet — the body field is read-only until you create the override.';
                }

                this.restoreDraft();
            } catch (err) {
                this.edit.error = 'Network error: ' + (err.message || err);
                this.edit.loading = false;
            }
        },

        // Cache the in-flight edit so a refresh / accidental close doesn't
        // lose work. Same key shape as the articles editor.
        persistDraft() {
            try {
                if (!this.edit.path) return;
                localStorage.setItem(this.storageKey(), JSON.stringify({
                    title:       this.edit.title,
                    description: this.edit.description,
                    hero_image:  this.edit.hero_image,
                    body:        this.edit.body,
                    meta_yaml:   this.edit.meta_yaml,
                    meta_fields: this.edit.meta_fields,
                    template:    this.edit.template,
                    saved_at:    Date.now(),
                }));
            } catch (e) { /* localStorage may be full / disabled */ }
        },

        restoreDraft() {
            try {
                const raw = localStorage.getItem(this.storageKey());
                if (!raw) return;
                const d = JSON.parse(raw);
                // Only override the server payload if the local draft has any
                // body/title actually set; an empty draft would just blank
                // freshly loaded content.
                if (d.body || d.title || d.meta_yaml) {
                    if (confirm('Unsaved draft found in this browser for ' + this.edit.path + '. Restore it?')) {
                        Object.assign(this.edit, d);
                        // Ensure meta_fields has all known keys even if the
                        // draft was saved before this field set existed.
                        this.edit.meta_fields = Object.assign(
                            { status: '', date: '', scheduled_at: '', hero_image: '' },
                            this.edit.meta_fields || {}
                        );
                    } else {
                        localStorage.removeItem(this.storageKey());
                    }
                }
            } catch (e) { /* ignore parse errors, treat as no draft */ }
        },

        clearDraft() {
            try { localStorage.removeItem(this.storageKey()); } catch (e) {}
        },

        async saveAll() {
            this.edit.saving = true;
            this.edit.error  = '';
            this.saveStatus  = 'Saving...';

            try {
                if (this.edit.editable_body) {
                    const ok = await this.saveContentTab();
                    if (!ok) { this.edit.saving = false; return; }
                }
                await this.saveTemplateTab();
                this.clearDraft();
                // Sitemap tab uses the legacy form POST so the existing
                // /admin/pages/update handler re-renders the inventory with
                // its flash message intact. This navigates the page.
                this.$refs.sitemapForm.submit();
            } catch (err) {
                this.edit.error  = 'Save failed: ' + (err.message || err);
                this.edit.saving = false;
                this.saveStatus  = '';
            }
        },

        async saveContentTab() {
            const mf = this.edit.meta_fields || {};

            const fd = new FormData();
            fd.append('_csrf', this.csrf);
            fd.append('path', this.edit.path);
            fd.append('slug', this.edit.slug);
            fd.append('title', this.edit.title);
            fd.append('description', this.edit.description);
            fd.append('hero_image', this.edit.hero_image);
            fd.append('body', this.edit.body);
            fd.append('meta_yaml', this.edit.meta_yaml);
            fd.append('status', mf.status || '');
            fd.append('date', mf.date || '');
            // Only send scheduled_at when status=scheduled; otherwise the
            // backend would persist a stale datetime the user can't see.
            fd.append('scheduled_at', mf.status === 'scheduled' ? (mf.scheduled_at || '') : '');

            const res = await fetch('/admin/pages/save-content', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            });
            const j = await res.json();
            if (!j.ok) {
                this.edit.error = j.error || 'Save failed.';
                this.saveStatus = '';
                return false;
            }
            return true;
        },

        async saveTemplateTab() {
            const fd = new FormData();
            fd.append('_csrf', this.csrf);
            fd.append('path', this.edit.path);
            fd.append('template', this.edit.template);

            const res = await fetch('/admin/pages/save-template', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            });
            const j = await res.json();
            if (!j.ok) {
                throw new Error(j.error || 'Template save failed');
            }
        },

        // Fall-through page creation: write a minimal scaffold so the
        // body field becomes editable on the next open. Done via the same
        // save-content endpoint so we don't need a separate route.
        async createOverride() {
            const fd = new FormData();
            fd.append('_csrf', this.csrf);
            fd.append('path', this.edit.path);
            fd.append('slug', this.edit.slug);
            fd.append('title', this.edit.slug.charAt(0).toUpperCase() + this.edit.slug.slice(1));
            fd.append('description', '');
            fd.append('hero_image', '');
            fd.append('body', '<!-- Intro for ' + this.edit.path + ' -->\n');
            fd.append('meta_yaml', '');
            // Seed via the structured fields the new saveContent owns;
            // pushing `status: draft` into meta_yaml would get stripped
            // because status is a structured key now.
            fd.append('status', 'draft');
            fd.append('date', '');
            fd.append('scheduled_at', '');

            const res = await fetch('/admin/pages/save-content', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            });
            const j = await res.json();
            if (!j.ok) {
                this.edit.error = j.error || 'Could not create override.';
                return;
            }
            // Re-fetch so editable_body flips true on the next render.
            this.fetchContent();
        },

        pickMedia() {
            this.media.open = true;
            // The /admin/media iframe posts a window message back when a
            // file is picked. Wire a one-shot listener so the URL lands
            // in the shared hero field (Meta tab binds to the same value).
            const handler = (ev) => {
                if (!ev.data || ev.data.kind !== 'media:selected') return;
                this.edit.hero_image = ev.data.url || ev.data.path || '';
                this.media.open = false;
                window.removeEventListener('message', handler);
            };
            window.addEventListener('message', handler);
        },
    };
}
</script>
