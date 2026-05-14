# Changelog

All notable changes to Bird CMS are documented here. Format is loosely based on
[Keep a Changelog](https://keepachangelog.com/), versioning follows
[SemVer](https://semver.org/).

## [3.2.0] - 2026-05-14

Finish the two-level article URL grammar the router scaffolded back in
#1720. Articles can now physically live at
`content/articles/<category>/<subcategory>/<slug>/index.md` and be served
at `/<category>/<subcategory>/<slug>`. Flat layout
(`content/articles/<category>/<slug>.md` → `/<category>/<slug>`) is
unchanged; the new behaviour is purely additive.

### Added

- `ArticleRepository::getCategoryArticles()` scans a third layout —
  `category/subcategory/slug/index.md` — in addition to the existing
  flat and one-level-bundle forms. Capped at one level of subcategory:
  `/{category}/{subcategory?}/{slug}` is the router's URL grammar and
  deeper layouts have nowhere to be addressed.
- `ArticleRepository::find()` gained an optional fourth parameter
  `?string $subcategory = null`. When `null`, a nested article is NOT
  returned for a flat lookup (canonical URL discipline -- there is only
  one URL per article). When non-null, the lookup is constrained to
  that subcategory.
- `ArticleRepository::findByParams()` honours `subcategory` in the
  param array (router already passes it from
  `/{category}/{subcategory?}/{slug}` matches).
- `ArticleController::resolveSegments()` accepts the 3-segment shape
  `[category, subcategory, slug]` in addition to the legacy
  `[category, slug]`. `Dispatcher` step 13 widened to dispatch both.
- Four new unit tests in `tests/Unit/ArticleRepositoryTest.php` cover
  nested load, subcategory-from-path derivation, no-flat-fallback for
  nested articles, and invalid-subcategory-slug rejection.

### Changed

- `config/content.php`: `articles.url` pattern is now
  `/{category}/{subcategory?}/{slug}` (was `/{category}/{slug}`). The
  `{subcategory?}` optional placeholder was already supported by
  `ContentRouter` (#1720) -- this just opts articles into using it.
- `ArticleRepository::buildUrl()` accepts `?string $subcategory = null`
  as a third parameter and inserts the segment when non-null.

### Notes

- No site.yaml flag. The change is backwards-compatible: sites without
  nested article directories see zero behavioural difference (the new
  glob returns empty, the optional URL segment is omitted, and `find()`
  without a subcategory behaves as before).
- Filesystem is source of truth for nested articles: a `meta.subcategory`
  inside a nested bundle is overridden by the directory name so the URL
  cannot silently desync from the on-disk path.
- `save()` is unchanged in this release. Writing into a nested
  subcategory directory is a manual filesystem operation for now (move
  the bundle dir into `category/subcategory/`); admin-UI write support
  is deferred to a future release.

## [3.1.10] - 2026-05-13

Retire dead deploy path; doctor v1.1 surfaces retired-feature drift.

### Removed

- `scripts/update-engine.sh` reduced to a retirement stub. The script
  implemented the pre-versioned, copy-based deploy (file-by-file
  `cp -r` of `app/`, `bootstrap.php`, `themes/admin/`, etc. into the
  site dir). The current path uses `scripts/update.sh` which extracts
  a release archive into `versions/X.Y.Z/` and atomically flips the
  `engine` symlink. The old script also referenced engine directories
  that no longer exist (`monitoring/` retired in `2e69a8b` 2026-04-28,
  `content-optimization/` retired in `29eaae1` 2026-05-02); its `cp`
  lines had been silent no-ops for several releases. The stub still
  exits non-zero with a pointer to `update.sh` / `deploy-all.sh` so
  muscle-memory users get a clear redirect instead of "command not
  found".

### Changed

- `DOCTOR_VERSION` bumped 1.0 -> 1.1.
- New section 6 in `scripts/doctor.php`: **retired engine features**.
  Warns when `monitoring/` or `content-optimization/` exist at the
  site root, or when `docs/MIGRATION-PLAN-2025-12.md` is present
  (alpha-era doc orphan marker). HTTP smoke (was section 6) is now
  section 7 under `--deep`.

### Validator findings on remaining "fat" sites

After running v1.1 doctor across all six prod sites:
- klymentiev.com: section 6 clean (Agent #2 quarantined those dirs in
  Task #1858, 2026-05-13).
- cleaninggta.com, klim.expert, topic-wise.com: still carrying
  `monitoring/` (616K-856K) and `content-optimization/` (32K-740K)
  plus alpha-era `docs/` orphans. WARN level — no impact on uptime,
  but a clear quarantine target for site-specific hygiene work.
- husky-cleaning.biz, bird-cms.com: clean (predate the retired
  features).

## [3.1.9] - 2026-05-13

Add a structural integrity validator (`scripts/doctor.php`) plus a small
parser hardening that surfaced while building it.

### Added

- `scripts/doctor.php` — site integrity validator. Three modes:
  - `--quick` (~1s): directory skeleton, .env file present, engine
    symlink resolves, `config/app.php` parses, required config keys set.
  - default (~10s): adds theme directory check, frontmatter parse of
    every `.md` and `.meta.yaml` under `content/`, categories/dirs
    cross-reference.
  - `--deep` (~60s): adds HTTP smoke on `site_url` and `/admin/`.
- Exit codes: 0 OK, 1 warnings, 2 critical. Use these to gate destructive
  agent operations (`mv`, `rm`, mass migrations) — refuse to proceed
  if doctor exits 2.
- `--json` flag for machine-readable output. Includes per-check
  `section`, `status`, `name`, and `detail`.
- `DOCTOR_VERSION` constant (currently `1.0`) shown in the report header
  alongside the engine version, so a stored report tells you exactly
  which check-set applied. Bump when adding/removing/changing checks.
- Built-in minimal `.env` reader so `$env(...)` lookups inside
  `config/app.php` resolve correctly without booting the full engine.

### Validator findings on existing 6 prod sites (baseline)

- topic-wise / klymentiev / klim.expert / cleaninggta:
  `public/index.php` is a real file, not a symlink. WARN-level drift —
  engine updates do NOT auto-propagate to that entrypoint. Re-symlink
  to `../engine/public/index.php` to fix.
- klymentiev: orphan `content/articles/resources/` not in categories.php.
- cleaninggta: 13 categories registered in `config/categories.php` have
  no matching `content/articles/<slug>/` dir — content lives under
  `content/services/`, `content/content/` instead. Site-specific layout
  drift; not engine-breakage.
- husky-cleaning.biz, bird-cms.com: landing-only sites, no
  `config/categories.php` (expected).

### Notes

Doctor lives in the engine bundle; each site runs the doctor matching
its own engine version. To gate cross-site operations on the same
spec, run doctor from the latest engine source explicitly.

## [3.1.8] - 2026-05-13

Hotfix: quoted list-items with embedded `: ` no longer corrupt on parse.

### Fixed

- `FrontMatter::parseLines` previously treated any list-item value
  containing `:` (other than URLs with `http`) as an inline map. A line
  like
  ```
  - "Everyone has seen the demo: ask an AI agent to book a flight."
  ```
  was parsed as `{"Everyone has seen the demo": "ask an AI agent..."}`,
  silently turning a string list into a list of one-element maps. v3.1.7
  encoded this content correctly, but READ-side parsing lost the shape
  on the next save. New guard via `isQuotedListItem` skips the colon-as-
  map interpretation when the value is wrapped in matching `"..."` or
  `'...'` quotes. Found by klim.expert services migration round-trip
  test (4 of 7 services were silently corrupting paragraph arrays).

### Added

- `tests/Unit/FrontMatterTest::testQuotedListItemWithColonStaysString`
  — covers four shapes: colon-mid-string, colon-at-start, plain string
  without colon, and inline URL.

### Notes

Sites already on v3.1.7 inherit the fix via the engine symlink swap.
This bug never corrupted existing YAML files: parsing was wrong on
v3.1.7, but until a SAVE round-trip went through the admin path the
file on disk was untouched. After this fix, klim.expert services
migration (Task #1855) is safe to complete.

## [3.1.7] - 2026-05-13

Hotfix wave: close the FrontMatter encoder bugs that v3.1.6's READ-side fix
exposed, restore themed 404 on sites with local themes, and ship a saner
installer template.

### Fixed

- `FrontMatter::escapeScalar` no longer uses `addslashes`; YAML
  double-quoted strings now escape only `\` and `"`. Single quotes pass
  through unchanged, ending the per-save accumulation of `\` on any
  string with an apostrophe + structural char (e.g. `Don't: read`).
- `FrontMatter::escapeScalar` quotes numeric-looking strings (`"01"`,
  `"007"`, `"1e10"`), YAML keyword strings (`"true"`, `"null"`, `"yes"`),
  strings starting with whitespace or YAML indicators, and strings with
  leading/trailing whitespace. Previously these emitted bare and were
  re-cast on the next read.
- `FrontMatter::castValue` preserves YAML-quoted scalars as strings (no
  coercion to int/bool/null). `step: "01"` now round-trips as the string
  `"01"`, not int `1`.
- `FrontMatter::encodeLevel` handles nested arrays / maps inside list-of-
  objects entries. Previously a structure like
  ```
  stack:
    - name: backend
      items: ["PHP", "MySQL"]
  ```
  fell through to `(string) $array`, emitting the literal word `Array`
  and silently destroying the nested data. The nested-array branch now
  renders the sub-list at one extra indent so the parser can disambiguate.
- `Admin\Controller::enforceIpRestriction` now resolves the themed 404
  view via `config('themes_path')` rather than `dirname(__DIR__, 2)`.
  Sites whose theme lives under `<site>/themes/<name>/` (cleaninggta,
  klymentiev, klim.expert, topic-wise) now serve their stylised 404
  page to unauthorized admin visitors instead of a giveaway plain-text
  "404 Not Found".

### Changed

- `scripts/install-site.sh` defaults `themes_path` in the generated
  `config/app.php` to `__DIR__ . '/../themes'` (site-local) instead of
  `ENGINE_THEMES_PATH`. The previous default coupled every new site's
  visual identity to the engine's bundled tailwind theme, surfacing as
  a surprise redesign on every engine upgrade.
- `scripts/install-site.sh` symlinks
  `public/assets/brand/{bird-logo.svg,hero-glow.webp}` from the engine
  bundle and copies the default tailwind theme into `themes/tailwind/`.
  Fresh sites render the admin panel and a working default theme out
  of the box without manual scaffolding.

### Added

- `tests/Unit/FrontMatterTest.php` — round-trip coverage for the four
  encoder bug classes above, plus a flat-document smoke test.

### Notes for site operators

Existing sites already on v3.1.6 inherit the fixes via the engine
symlink swap; no `.env` or `config/` changes required. Sites that were
manually patched between v3.1.0 and v3.1.7 (themes_path overrides,
brand asset symlinks) keep their overrides — the installer change only
affects newly created sites.

## [3.1.6] - 2026-05-12

Hotfix: close silent-data-loss on nested fields in article meta.yaml.

### Fixed

- `ArticleRepository::parseMetaYaml` now reads via `FrontMatter::parse`
  instead of `YamlHelper::parse` (which delegated to `YamlMini`, the
  flat-only minimal parser). The save path already used
  `FrontMatter::encode`, so nested mappings persisted correctly but were
  lost on the next read — an asymmetric pipeline that silently truncated
  `features:`, `faq:`, and similar block-arrays of objects to a list of
  the outer keys only.

  Concrete symptom: a meta.yaml with
  ```
  features:
    - title: X
      text:  Y
  ```
  came back as `['title: X', ...]` with `text` dropped. After this fix
  it round-trips intact.

  `YamlHelper` / `YamlMini` are unchanged — they remain in use by
  `mcp/server.php` (which bootstraps without the autoloader). The fix
  is a one-line parser swap in `ArticleRepository::parseMetaYaml`; no
  schema change, no admin contract change, no client-visible API change.

  Found by the klim.expert maintainer while testing whether services
  authored as a category inside `content/articles/services/` could
  carry nested block arrays. They couldn't — same root cause.

## [3.1.5] - 2026-05-12

Hotfix: remove install-guard auto-redirects.

### Fixed

- `public/admin/index.php` and `public/index.php` no longer redirect to
  `/install` when `storage/installed.lock` is missing. On sites migrated
  from earlier releases the lock was never created, so every `/admin`
  visit produced a 302 to `/install` — which on production installs
  resolves to 404 because the in-browser wizard is intentionally not
  part of those installs. `bootstrap.php`'s APP_KEY refuse-to-boot check
  remains as the fail-loud safety net for genuinely unconfigured installs.

## [3.1.4] - 2026-05-12

Tooling and docs cleanup; no engine behaviour change.

### Changed

- Renamed `tests/legacy/` → `tests/standalone/`. The three files in there
  (`ContentRouterTest`, `RateLimitTest`, `SchemaLayerTest`) are the only
  coverage for `App\Http\ContentRouter`, `App\Support\RateLimit`, and
  the AEO/Schema layer (#1706) — `tests/Unit/` covers none of them. The
  "legacy" label was misleading; they're intentional standalone tests
  following the shell smoke-test convention, not deletable tech debt.
- Documented in `bootstrap.php` why the `topic-wise-secret-key-change-me`
  sentinel is intentional in the APP_KEY refuse-list (project was
  previously named topic-wise; the sentinel catches old installs that
  carried that default).

## [3.1.3] - 2026-05-12

De-Bird the default tailwind theme footer.

### Fixed

- Restored slate-950 footer baseline in `themes/tailwind/partials/footer.php`.
  The lean-3.0 cycle (commits `ac0fab9` and `f5fca0d`) had baked Bird CMS
  brand hex codes directly into the default footer: `#0a2520`
  forest-green background, `#f3c33b` sun-gold link-hover, `#2dbb98` mint
  category dots, `#1c5a52` teal border, `#7cdcc4` mint headings, `#f8f6f3`
  cream text, `#94a89f` muted green. Every tenant inherited the palette
  regardless of their own brand. Replaced with `bg-slate-950 text-slate-200`
  + Tailwind brand-utility gradient that picks up whatever indigo `brand-*`
  tokens are configured per site. Logo image-mode opt-in
  (`config('site.branding.logo_image')`) from v3.1.1 preserved.

## [3.1.2] - 2026-05-12

Remove Bird marketing hero from default home page.

### Fixed

- The default `themes/tailwind/views/home.php` opened with a full-screen
  Bird CMS brand-intro hero: animated polygonal hummingbird (via
  `marketing/bird-animation` partial), rainbow brand-shimmer wordmark,
  `/welcome` eyebrow, fallback tagline "Polygonal hummingbird,
  forest-deep, sun-warm." That was authored as `bird-cms-brand.html` for
  README/screen-capture material and didn't belong in the default theme
  that tenants inherit. Replaced with a brand-agnostic intro:
  `<h1>site_name</h1>` + tagline (no fake-bird fallback) + single
  Subscribe CTA. Sites can override the entire top by adding
  `content/pages/home.md` (rendered as `$intro` just below).
- `themes/tailwind/partials/marketing/bird-animation.php` and the
  `brand-shimmer` / `hero-bird-*` / `hero-cta-*` CSS classes remain in
  the codebase as opt-in marketing primitives.

## [3.1.1] - 2026-05-12

De-Bird the default tailwind theme header, footer logo, and brand css.

### Fixed

- `themes/tailwind/partials/header.php` and `footer.php`: hardcoded
  `<img src="/assets/brand/bird-logo.svg">` replaced with a config-driven
  fallback. If `config('site.branding.logo_image')` is set, render the
  image; otherwise fall back to the pre-rebrand text-initials block
  (which has no asset dependency and therefore cannot 404).
- `themes/tailwind/layouts/base.php`: dropped the hardcoded
  `<link rel="icon" type="image/svg+xml" href="/assets/brand/bird-logo.svg" />`.
  Sites already declare per-site favicons via `/favicon.svg` and
  `/favicon.ico`. Also: emit `<link rel="stylesheet">` for
  `/assets/frontend/brand.css` and `site.css` only when the files
  actually exist on disk; emitting broken links produced 404s on every
  tenant that never shipped its own brand stylesheet.

### Changed

- `themes/tailwind/theme.json`: renamed `name` from `"Bird"` to
  `"Default"`; clarified that the default theme is brand-agnostic and
  per-site branding is wired through
  `config('site.branding.logo_image')` and
  `public/assets/frontend/brand.css`.

### Background

Commit `5bf0488` (lean-3.0 cycle, 2026-04-28) baked Bird CMS brand
assets straight into the default tailwind theme, so every tenant on
`ACTIVE_THEME=tailwind` got a broken `<img src="/assets/brand/bird-logo.svg">`
in the header and broken `brand.css` / `site.css` link references.
v3.1.1, v3.1.2, and v3.1.3 together restore brand-neutral defaults.
Bird CMS's own marketing site is a static landing page and is unaffected.

## [3.1.0-rc.10] - 2026-05-11

Dashboard becomes useful, in-admin docs viewer ships.

### Added

- **Dashboard v2** — three operational cards at the top, each hidden
  when empty so the page never looks stuffed:
  - **Drafts** — articles + pages with `status = draft`, last edited
    relative time, edit shortcut. Useful first-look every time you sit
    down at the admin.
  - **Scheduled** — items with `status = scheduled` or future
    `publish_at`, sorted by publish time ascending, "publishes Dec 24 ·
    14 days" rendering.
  - **Recent edits** — last 5 entries from the new `EditLog` with
    source attribution: "saved 15 min ago · via admin" / "via Claude (MCP)" /
    "via api". Lets you see whether your AI assistant actually saved
    that change.
  Below the three cards, the rc.9 Site info + Quick links blocks stay
  for orientation.
- **`App\Support\EditLog`** — SQLite-backed activity log
  (`storage/data/edits.sqlite`). Records source/action/target_url
  on every save and delete from admin, MCP, and the REST API. Schema
  initialises on first write. Never throws — write failures and
  missing `pdo_sqlite` short-circuit to a silent no-op. Reads cap at
  the most recent 5 by default; ordering via index on `at DESC`.
- **Admin docs viewer at `/admin/docs`.** Read-only two-column page
  with a grouped tree of `docs/*.md` + recipes + README. Internal
  `[X](other.md)` links rewritten so navigation stays inside the
  viewer. Images served via `/admin/docs/asset/<path>` with strict
  whitelist (jpg/png/webp/svg/pdf), realpath confinement to `docs/`,
  and `X-Content-Type-Options: nosniff`. New "Docs" sidebar item
  (RemixIcon `book-open-line`) visible in both `minimal` and `full`
  admin modes. 18 integration tests cover the viewer, link rewriting,
  asset traversal-rejection.

### Changed

- Source attribution on saves now flows through:
  `App\Admin\Controller::__construct` sets `EditLog::$context = 'admin'`,
  `mcp/server.php` records `source='mcp'` directly per tool, and
  `app/Http/Api/ContentController` sets `source='api'`. One log,
  three real surfaces, no drift.

### Repo hygiene

- Suite: 148 → 177 tests (+29), 549 → 677 assertions, all green.

## [3.1.0-rc.9] - 2026-05-11

Admin polish from a fresh-install dogfood pass.

### Added

- **Useful dashboard.** Three sections — `Recent articles` (latest 5 with
  edit links + status badge, empty-state CTA when none), `Site` info
  (URL, theme, last-content-update relative time), `Quick links` (New
  article / Manage pages / Upload media / Site settings). Drops the old
  Pages/Media count tiles that just sat there meaning nothing. Same
  dashboard for minimal and full admin modes. 6 new integration tests.

### Fixed

- **Sidebar no longer appears to "jump left" when navigating between
  admin pages.** Root cause was body scroll-gutter reflow: short pages
  had no scrollbar, long pages did, gutter reserved/released changed
  main-content width by ~17 px on every navigation. One CSS line
  (`html { scrollbar-gutter: stable; }` in `public/admin/assets/admin.css`)
  reserves the gutter unconditionally. Frontend themes untouched.
  Browser support: Chrome 94+, Firefox 97+, Safari 17+; older browsers
  silently ignore and degrade to pre-fix behavior.

### Repo hygiene

- `landing/` removed (was 784-line design prototype duplicating live
  bird-cms.com; engine clone no longer ships dead weight).
- Suite: 142 -> 148 tests, 515 -> 549 assertions, all green.

## [3.1.0-rc.8] - 2026-05-11

Stage-2 perf: full HTML response cache with simple invalidation. Closes
most of the Bird-vs-Hugo gap for read-heavy content sites.

### Added

- **`App\Support\HtmlCache`** — opt-in static-HTML cache layer. Each
  stable-URL response (homepage, article, page, category index,
  llms.txt, content-type detail) is captured via `ob_start()`,
  persisted to `storage/cache/html/<key>.html` via atomic temp+rename,
  and served on subsequent hits without re-rendering. Five-minute TTL
  safety net guards against orphan staleness.
- **Cascading invalidation on save.** Every `ContentRepository::save()`
  drops the entity's own cache file plus `home.html`, `category/<cat>.html`,
  and `llms.txt` (the three pages most likely to reference it). The
  Settings General save calls `HtmlCache::flushAll()` because changing
  `site_name` / nav / theme touches every rendered page.
- **Opt-in via `.env` `HTML_CACHE=true`.** Default off for backward compat.
  Skips automatically when query string is non-empty, request method is
  not GET, or the path is under `/admin` / `/api` / `/install` / `/health`.
- 22 new tests (16 unit + 6 integration). Strict path-traversal rejection
  in `HtmlCache::sanitizeKey` covered by 8 traversal variants. Suite is
  142 tests / 515 assertions, all green.

### Notes

- This is stage 2 of the perf track (#1840). Stage 1 (rc.5) cached the
  meta-array layer (`ContentCache` trait). Stage 3 would extend this to
  serve straight from nginx without entering PHP at all; not in rc.8.
- `.env.example` also gained the missing `CONTENT_CACHE=false` line
  (rc.5 shipped without documenting that switch).

## [3.1.0-rc.7] - 2026-05-11

HTTP REST API v1 and three walkthrough recipe posts. Closes the "code that
makes the CMS usable from outside Claude/Cursor" gap and the
"interested-developer-becomes-running-install" content gap simultaneously.

### Added

- **HTTP REST API v1** at `/api/v1/*`. Mirrors the MCP server's tool
  surface for non-AI integrations (mobile apps, third-party publishers,
  headless frontends). Endpoints: content CRUD across all 5 content types,
  URL inventory + per-URL meta override, site-config GET/PUT through the
  same whitelist the Settings UI uses, asset upload/list/delete. Bearer
  auth via API keys stored as SHA-256 hashes in `storage/api-keys.json`;
  per-key rate limit of 60 req/min sliding window; scopes (`read` / `write`)
  enforce method-level access. New "API Keys" admin section (visible
  under `ADMIN_MODE=full`) for create / list / revoke. 29 integration
  tests cover auth, content, url-inventory, site-config, and assets.
- **Three walkthrough recipe posts** in `docs/recipes/`:
  - `small-business-cafe.md` (1509 words) — photo of a storefront → 5-page
    site in 90 minutes via Claude + MCP, with concrete config diffs.
  - `personal-blog-import.md` (1447 words) — 12 markdown files from a
    legacy folder imported via Claude + MCP `write_article`.
  - `hugo-migration.md` (1489 words) — Hugo TOML → Bird YAML conversion
    walkthrough, including honest what's-gained / what's-lost.
  Each has a "what broke" section, real config diffs, and synthesized
  Claude transcripts using only real v0.2 MCP tool names.

### Fixed

- **PHPUnit theme-discovery test pollution.** `SettingsControllerTest` and
  the new `Api/SiteConfigApiTest` both `define('ENGINE_THEMES_PATH', ...)`
  on first run; whichever class loaded first claimed the constant. The
  Settings test now (re)creates its required theme dirs inside whatever
  path is active rather than skipping setup on cached state.

### Repo hygiene

- 14 commits across two agent branches merged cleanly into main
  (feat/rest-api-v1, feat/walkthrough-recipes). Test suite: 91 -> 120
  tests, 332 -> 423 assertions. All green.

## [3.1.0-rc.6] - 2026-05-11

Test suite goes green. Fixes three real bugs the parity tests caught
when first wired through CI.

### Fixed

- **MCP server published its state to the wrong scope.** `mcp/server.php`
  declared `$siteRoot`, `$articlesDir`, `$pagesDir`, `$contentDir`, and
  `$tools` at top-level. When the file is loaded normally (CLI stdio
  entry) PHP's top-level IS global scope, so the tool handlers' `global
  $articlesDir` statements worked. When PHPUnit `require_once`'d the file
  from inside `setUpBeforeClass()`, those identifiers became locals of
  the calling method and the tool handlers fetched NULL. Writes ended
  up at `/blog/<slug>.md` (filesystem root), not the configured tree.
  Added explicit `global` declarations and a `$GLOBALS[]` rebind hook in
  the parity test setUp so order-dependent class load ordering doesn't
  leak state across test classes.
- **`ArticleRepository::slugFromFilename` truncated any slug containing a
  dash.** The pre-3.x dating convention (`2026-05-10-my-post.md`) had a
  matching shift-first-segment heuristic baked in; new writes (MCP, admin
  URL Inventory) use bare slugs like `mcp-to-admin.md` and the heuristic
  silently turned them into `to-admin`. Repository `find()` then returned
  null for the original slug — the exact "MCP wrote it, admin can't read
  it" drift the parity tests were built to catch. Now only the literal
  `YYYY-MM-DD-` prefix is stripped.
- **`LlmsTxtController` ignored the `articles_prefix` config.** The repo's
  canonical `$article['url']` builds without the prefix; the llms.txt
  controller fell back to that value and produced `/blog/<slug>` even on
  sites configured with `/articles` as the URL prefix. Now always
  composes from `siteUrl + prefix + cat/slug`.

### Tests

- Suite goes from 6 errors + 4 failures (`v3.1.0-rc.5`) to 0/0 on all 91
  tests / 332 assertions. The MCP/admin parity suite — the drift canary —
  is now actually canary-ing.

## [3.1.0-rc.5] - 2026-05-11

Code-quality + admin-coverage polish. Four parallel agent branches merged.

### Added

- **Settings -> General tab.** Edit `site_name`, `site_description`,
  `site_url`, `active_theme`, `timezone`, `language` from the admin
  without SSH'ing into `config/app.php`. Strict field whitelist refuses
  `app_key`, `admin_password_hash`, `admin_allowed_ips`, path overrides,
  and theme values outside `themes/`. Atomic write to `config/app.php`
  via temp + rename. `site_url` change flashes a warning that HMAC
  preview tokens are invalidated.
- **Structured meta inputs in the URL Inventory edit modal.** The Meta
  tab now has dedicated controls for `status` (draft/published/scheduled),
  `date`, `scheduled_at` (conditional on status), and `hero_image`. Raw
  YAML textarea remains as the "Advanced" fallback for any field the
  form doesn't cover. Unknown YAML keys survive a save byte-for-byte.
- **ContentCache trait.** Two-tier cache for all five content
  repositories (Article/Page/Service/Area/Project). Tier 1 = per-instance
  memo (always on). Tier 2 = filesystem cache as `storage/cache/<key>.php`
  via `var_export` (opcache-friendly), opt-in via `CONTENT_CACHE=true`,
  mtime invalidation walks watched paths, atomic writes. Falls back
  gracefully when `storage/cache/` is not writable. Bench script at
  `docs/perf/benchmarks/cache.sh` seeds a 500-article site for measuring.

### Changed

- **`public/index.php` is now 49 lines.** Was 822 lines of procedural
  request dispatch mixing asset serving, llms.txt, search, preview-token
  validation, blog pagination, and per-content-type rendering. Extracted
  into `app/Http/Frontend/{Asset,LlmsTxt,Search,Preview,Home,
  BlogPagination,ContentType,Page,Category,Article}Controller.php` plus a
  small `Frontend\Dispatcher` with a route table. New `App\Support\PreviewToken`
  helper shares HMAC sign/verify between PreviewController and per-content
  controllers. No URL, header, or rendered-HTML changes — refactor only.

### Fixed

- (none new — rc.4 hotfixes carry forward)

### Repo hygiene

- 23 commits across four branches merged cleanly into main (feat/settings-general-tab,
  feat/pages-structured-meta, feat/repository-caching, feat/refactor-public-index).
- Each agent worked in its own git worktree to avoid checkout thrashing.

## [3.1.0-rc.4] - 2026-05-11

Engine polish + first real test suite + repositioning. The shipped admin
already covered the URL Inventory editor (rc.2) and the Topic Wise persona
leak fix (rc.3); rc.4 closes five more pieces in one drop.

### Added

- **PHPUnit baseline (58 tests across 4 suites).** Unit coverage for every
  ContentRepository (save/find/delete/atomic write, 28 tests), HTTP
  integration tests for the admin controllers (17), JSON-RPC handler tests
  for the MCP server with golden fixtures (8), and round-trip parity tests
  that exercise MCP -> admin -> MCP to catch drift between the two write
  surfaces (5). `make test` is now wired through `vendor/bin/phpunit`; CI
  runs the suite ahead of the HTTP smoke. Closes a real gap — pre-rc.4
  there was no automated detection of the "MCP wrote it, admin can't read
  it" class of bug.
- **`ADMIN_MODE=minimal` flag (default for new installs).** Sidebar drops
  to five items (Dashboard / Pages / Categories / Media / Settings) for
  small-site operators who don't need the Articles section duplicate, the
  five Security sub-tabs, or the Audit panels. `ADMIN_MODE=full` restores
  the previous nine-item layout. Hidden sections remain reachable by
  direct URL — only navigation rendering is affected.
- **`App\Support\YamlMini`.** Dependency-free YAML parser/dumper. The
  engine and the MCP server (which is bootstrap-free) now share one
  parser instead of maintaining two that quietly drifted apart.

### Changed

- **README leads with MCP, not "WordPress alternative."** Hero is now
  "AI-first markdown CMS with native MCP support — your AI agent edits
  the site directly. No copy-paste, no API token, no plugin." Comparison
  section calls out where Bird wins and loses vs WordPress, Decap CMS,
  and Strapi/Payload. Quick-start ships with a real Claude Desktop
  transcript that creates an article through `mcp/server.php`.
- **Repo root is leaner.** `CLAUDE.md`, `RELEASING.md`, `RELEASES.md`
  moved into `docs/`; `benchmarks/` moved under `docs/perf/`. Root went
  from 37 tracked items to 33; closer to WordPress-tier density.
- **`composer.json` description and keywords now lead with MCP.** Search
  surfaces (Packagist, GitHub topic pages) reflect the actual differentiator.

### Fixed

- **Topic Wise persona leaked into `public/index.php` `llms.txt` route.**
  Hardcoded "honest hands-on reviews of AI tools" copy and "Product
  Reviews / Comparisons / Guides" taxonomy replaced with generic content
  listing grouped by `article.type`. (Already shipped in rc.3; called out
  here for completeness.)
- **`ArticleRepository::isPillarKeyword` hardcoded English regexes.**
  Patterns like `/^best\s/i` and `/\s202[4-9]$/i` were baked into the
  engine. Moved to `config('seo.pillar_patterns', [])`; default empty,
  sites opt in. (Already in rc.3.)

### Repo hygiene

- 17 commits across five branches merged cleanly into main (`chore/root-cleanup`,
  `chore/yaml-parser-consolidation`, `feat/minimal-admin-mode`,
  `feat/readme-repositioning`, `feat/test-suite`).
- Branches built in parallel via separate git worktrees to avoid checkout
  thrashing during simultaneous agent work.

## [3.1.0-rc.1] - 2026-05-10

Admin overhaul plus the 2026-positioning surface that landed on top of
the lean-3.0 base. Roughly a month of UX, security, and DX work on the
admin panel; a new public landing posture with MCP server, AEO schema
layer, optional URL-grammar segments, and a unified rate limiter; plus
the bug-fix tail from the lean-3.0 cycle. No content-model changes.

### Added

- **Admin button system, collapsible sidebar, tag autocomplete, table
  view.** Single source of truth for primary/secondary/danger styles
  across every admin form, sidebar collapses to icon strip with state
  persisted across navigation, tag inputs autocomplete from the corpus,
  and list pages got a denser table-mode toggle.
- **Autosave + draft restore in the editor.** Open a draft, walk away,
  come back: your in-progress changes survive a tab close. A datetime
  picker on the schedule field replaces the raw ISO input.
- **Hero-image picker via a media iframe.** The hero field opens the
  full media library inline instead of asking you to copy a URL by
  hand. Save button now splits Save / Save & Publish.
- **Settings admin page.** Consolidates Security and Audit tabs that
  used to live in scattered sub-pages. The legacy AI-hero placeholder
  in the dashboard is gone.
- **URL Inventory page.** Walks every routable URL on the site,
  surfaces sitemap-inclusion status, and offers a per-URL `noindex`
  override without touching meta files by hand.
- **MCP server (v0.1 + v0.2).** `bird-cms-mcp` stdio server exposes
  pages and articles to Claude / Cursor / any MCP client: list, read,
  create, update, search, publish-toggle, delete. Eleven tools across
  the two minor revs.
- **AEO schema layer (E1+E2+E3).** Auto-emitted Breadcrumb JSON-LD,
  OpenGraph article tags (`article:published_time`, `article:author`,
  `article:section`), and SchemaGenerator helpers for downstream
  themes that want to enrich their own pages.
- **Optional URL-grammar segments.** Router now supports
  `/{category}/{subcategory?}/{slug}` so a single content type can
  serve both flat and nested URLs without two route definitions.
- **Unified sliding-window SQLite rate limiter.** One implementation
  shared by `/api/lead`, `/api/subscribe`, and `/api/track-event`,
  configurable per route.
- **Benchmark suite + methodology doc.** Reproducible perf numbers
  for the cold-cache and warm-path render under
  `docs/benchmarks/`.
- **AI-content workflow recipe.** `docs/recipes/` entry showing the
  end-to-end "draft via MCP, edit in admin, publish" loop.
- **Install wizard: password generator on step 2.** One click instead
  of inventing a secret in your head.
- **Auto-update lean-3.0 patch path.** `update.sh` detects an
  alpha.12-era site config on first lean-3.0 swap and patches it in
  place; no manual `config/app.php` surgery on upgrade.

### Changed

- **Landing page rewritten for v3.0.0 + 2026 positioning.** New copy,
  new hero composition, new screenshots. Drops the AI-factory
  language that no longer matches what the engine ships.
- **RemixIcon migration.** All inline SVG nav and label icons in the
  admin panel are now RemixIcon classes (44 swaps across 10
  templates) so themes can restyle the icon set without editing PHP.
- **Admin palette consistency.** Panel backgrounds standardised on
  `slate-800`; the Blocked IPs panel and a handful of secondary
  panels that drifted to lighter slate during the UX sweep are now
  back in line.
- **Article edit screen handles bundle-format articles.** The new
  bundle layout (article + assets in one folder) edits and counts
  the same as a flat-file article.
- **Editor preview moved out of the in-editor iframe** to a clean
  "Open in new tab" button so the preview no longer fights the
  editor for viewport space.
- **Media library: flat image-only mode with live filter.** Hides
  folders and non-image entries when the picker is invoked from a
  hero/inline-image field.
- **Dashboard cleanup.** Removed the Articles-by-Category panel;
  toned down folder/file tile colors on the dark admin theme.

### Fixed

- **Auto-block heartbeat noise hidden from Security log.** The
  security view now surfaces only real block events, not the
  per-minute "still healthy" heartbeats that buried them.
- **Sidebar collapse FOUC.** Collapse-state flash on every page load
  is gone; an inline pre-Alpine script restores the state before
  paint instead of after.
- **Sidebar layer-flicker on bundle-format article edits.**
- **`SettingsController` no longer chokes on array `$_ENV` entries.**
  Pre-filters to scalars before the snapshot diff so a stray array
  in the env can't 500 the settings page.
- **`collectAllTags()` walks `$this->articles`, not `$this->repository`.**
  Tag dropdowns were silently empty in some controller contexts.
- **`ContentRouter` no longer dispatches on routes already owned by
  the legacy article/page handlers.** Preview tokens, draft restore,
  and the hero picker all rely on the public dispatch landing in the
  right place; this hardens the item-route branch and wires the
  router into `public/index.php`.
- **`CategoriesController` counts bundle-format articles** the same
  as flat-file ones.
- **Content meta surfaces full field set; `noSidebar` propagates;
  null YAML values no longer trip the parser.**
- **Docker entrypoint: symlink `public/content` and serve a static
  `index.html` before handing off to PHP.**
- **Quick-win UX fixes:** breadcrumbs on every admin page, a real
  Filter button on listings, slug regen from title, save-keyboard
  shortcut, sandbox bulk actions, settings-page CSS scope, dashboard
  copy.

### Repo hygiene

- Local-testing scratch files now ignored at the root.
- `hero-glow.png` (1.3 MB) replaced with a 11 KB WebP; the file was
  no longer referenced by any theme since the lean-3.0 CSS-gradient
  hero, but resurfaced via a sweep commit.

## [3.0.0-rc.2] - 2026-05-02

Install-wizard polish on top of rc.1. Two pre-existing config bugs
fixed; no other behavioral changes.

### Fixed

- **Wizard now writes `config/categories.php`.** ConfigWriter gained
  `writeCategoriesConfig()` (copies a new
  `templates/config-categories.php.example` containing one default
  `general` category). Without this, /admin/articles and
  /admin/categories returned HTTP 500 on any fresh install.
- **`logs.autoblock_log` shipped with a default.**
  `templates/config-app.php.example` now exposes
  `logs.autoblock_log = SITE_STORAGE_PATH . '/logs/cron-autoblock.log'`,
  matching the path `scripts/auto-blacklist.php` redirects to. Without
  it, /admin/blacklist returned HTTP 500.

### Changed

- **Refactor.** `ConfigWriter::writeAppConfig()` and
  `writeCategoriesConfig()` share a private
  `materializeConfig(string $name)` helper. Adding the next
  `config-X.php.example` is a one-line addition.

### Known limitation

- Extensionless `/api/lead` URL is not routed; themes call
  `/api/lead.php` directly. Adding `try_files $uri $uri.php` inside a
  prefix `/api/` location does not transfer control to the php-fpm
  regex location -- a proper rewrite-based fix is disproportionate to
  the cosmetic gain. `/api/lead.php` (and `/api/subscribe.php`) work
  on every nginx config.

## [3.0.0-rc.1] - 2026-05-02

**BREAKING.** Lean-down release that aligns the OSS distribution with what
the README promises (a markdown-first PHP CMS). Modules duplicated by
Statio (analytics + leads), abandoned by usage data (AI factory), or
out of scope (audit toolkit, dead scripts) have been removed. Total
delta: ~80 files removed, ~14,000 LOC dropped, admin sidebar shrunk
from 14 menu items to 9.

### Removed

- **AI content factory.** `factory/`, GeneratorController (1252 LOC god
  class), PipelineController (Kanban Trends->Drafts->Ready->Published),
  InstructionsController, TemplatesController, WorklogController,
  OpenRouterService, DraftRepository, TrendsRepository,
  `themes/admin/views/{generator,pipeline,instructions,templates,worklog}/`,
  `scripts/publish-article.php`. Material preserved at
  `/server/sites/content-factory-seed/` on the maintainer's server for
  a future multi-tenant `bird-factory` service.
- **Analytics admin.** `AnalyticsController`,
  `themes/admin/views/analytics/` (4 dashboards),
  `scripts/{analytics-stats,analytics-48h,clean-analytics,backfill-analytics}.php`.
  Statio (https://statio.click) replaces them with multi-tenant dashboards.
- **Local Leads admin.** `LeadsController`, `themes/admin/views/leads/`,
  per-site CSV/JSON storage UI. `/api/lead` is now a thin proxy that
  forwards to `STATIO_LEADS_ENDPOINT` with bearer auth.
- **Widgets admin.** `WidgetsController`, `themes/admin/views/widgets/`.
  Marketing partials in `themes/tailwind/partials/marketing/` are
  always-on; per-site overrides happen by editing the theme directly.
- **Audit toolkit.** `audit/`, `content-optimization/`, `scripts/audit*.php`.
  Personal SEO/security audit scripts that never ran in the request
  path; belong in a separate maintainer toolchain.
- **3 dead/dangerous scripts.**
  `scripts/analyze-scanners.php` (called Anthropic Claude API without
  `ANTHROPIC_API_KEY` documented anywhere -- mine for OSS users),
  `scripts/security-scan.php` (pentest-time tool, not a runtime feature),
  `scripts/generate-nginx-blacklist.php` (dead duplicate; the active
  pipeline writes via `generate-blacklist-conf.php`).
- **Cruft.** `scripts;S/` Windows path artifact, stale session-snapshot
  doc.

### Changed

- **`/api/lead.php` rewritten as Statio proxy** (~80 LOC, was ~250 LOC
  with custom SMTP TLS client and disk persistence). Returns 503 with a
  machine-readable hint when `STATIO_LEADS_ENDPOINT` is unset; lead-form
  partials in themes are expected to self-disable.
- **`App\Support\Analytics` simplified** from a 4-mode pixel/dashboard
  abstraction to anti-bot ingest only. Owns visits.db schema +
  retention trigger; pixel rendering and external-tracker config are
  removed (themes inject the Statio script tag directly). Surface drops
  from 7 public methods to 4.
- **`config/analytics.php`** trimmed to `retention_visits` only.
- **`.env.example`** dropped 17 keys (AI keys, Generator config, SEO
  research APIs, screenshot service, MAIL_*, EXTERNAL_TRACKER_*,
  ANALYTICS_MODE) and added `STATIO_LEADS_ENDPOINT`,
  `STATIO_API_SECRET`, `STATIO_SITE_GUID`. Reorganized to make it clear
  the visits.db is anti-bot fuel, not a user-facing dashboard.
- **`public/admin/index.php`** dropped 8 controller require/use blocks
  and ~50 route registrations. Shrinks from 206 to 100 lines.
- **Admin sidebar** trimmed to: Dashboard, Articles, Categories, Media |
  Blacklist, Sandbox, Links, Site Check, PageSpeed.
- **`docs/02-spec.md`** updated F-35..F-41 to match shipped surface;
  removed F-38..F-40 (DataForSEO monitoring, Generator panel, Pipeline
  panel) and downgraded F-41 to the Statio-proxy behavior.
- **`README.md`** drops the "Pillar-cluster interlinking" claim,
  rewrites the SEO section, adds a "Tracking via Statio" feature
  bullet, adds an "Anti-bot ingest" feature bullet documenting the
  nginx-only nature, and links the new Statio recipe + 2.x->3.0
  migration doc. Version badge bumps to 3.0.0-rc.1.
- **`SECURITY.md`** supported-versions table: 3.x supported, 2.0.0-beta.x
  unsupported.

### Migration

Per-site upgrade checklist (export local leads, configure Statio,
disable container cron entries that point at removed scripts, archive
`storage/factory/` and `storage/leads/`) -- if you need it, the
`docs/migration-2.x-to-3.0.md` file lives in git history before this
release.

### Kept (intentionally)

- Anti-bot pipeline: `scripts/parse-access-log.php`,
  `scripts/auto-blacklist.php`, `scripts/generate-blacklist-conf.php`,
  `scripts/analytics-prune.php`, visits.db schema. Proven on prod
  sites.
- Site quality tools: `/admin/sitecheck`, `/admin/links`,
  `/admin/pagespeed` (curl-based, portable across deploys).
- Newsletter: `/api/subscribe.php` + `FileSubscriberRepository`. Used
  on prod (~31 subscribers across 4 sites). Admin UI dashboards were
  removed with Analytics; subscribers persist as a JSON file ready for
  CSV export when traffic justifies a real ESP.

## [2.0.0-beta.3] - 2026-04-29

UX-professionalization iteration 1, plus one-line installers for
Windows / macOS / Linux. No behavioral changes to content rendering or
the engine bootstrap. Project UX maturity score moves from 65% (beta.2)
to ~80% (this release), crossing the public-beta gate.

### Added

- **One-line installers** -- `scripts/install.sh` (bash) and
  `scripts/install.ps1` (PowerShell). Curl-pipe-bash and iwr-pipe-iex
  one-liners take a fresh machine to a running install wizard with a
  single command. Each installer checks Docker, falls back from `git`
  to a tarball/zip download, **auto-picks the first free port in
  8080..8099** so it never collides with whatever else is on the host,
  waits for `/health`, and opens the wizard in the default browser.
  Override the port range via `BIRD_CMS_PORT` (specific port) or
  `BIRD_CMS_PORT_RANGE_{START,END}` (different range). README now
  leads with the one-liner; the manual three-line flow remains as
  fallback.
- **`/health` endpoint** -- bootstrap-free, returns 200 + JSON when the
  VERSION file is readable and required PHP extensions are loaded; 503
  with a list of missing extensions otherwise. Reachable before the
  install wizard finishes, suitable for load balancer probes.
- **Empty-state on the admin dashboard** -- when no articles, drafts,
  or ready-to-publish items exist, the dashboard now renders a single
  "Create your first article" CTA card instead of four zero-count stat
  tiles. New operators see direction, not blank dashboards.
- **`Ctrl+S` / `Cmd+S` save shortcut** in the article editor.
- **`scripts/backup.sh`** -- bundles `.env`, `config/`, `content/`,
  `storage/` (minus logs+cache), and `uploads/` into a timestamped
  tarball. Defaults to `./backups/` next to the site path.
- **`docs/troubleshooting.md`** -- top install/runtime/operations
  errors with copy-paste fixes (port in use, missing extensions, lock
  file, .env permissions, 502 startup, 404 on admin, APP_KEY missing,
  /health degraded, two-tab edit overwrite).
- **`docs/screenshots/`** placeholder directory with capture
  instructions; README links resolve.
- **GitHub `.github/`** issue templates (bug, feature, question +
  config.yml) and a PR template.
- **GitLab `.gitlab/`** equivalent issue + merge-request templates.
- **README badges** -- version, license, public-beta status, PHP, Docker.

### Changed

- **Single source of truth for version string.** Wizard
  (`app/Install/Wizard.php`) and install layout (`themes/install/layout.php`)
  now read `VERSION` at runtime instead of hardcoding `2.0.0-alpha.15`.
- **`SECURITY.md` supported-versions table** updated to list `2.0.0-beta.x`
  as the supported line; alpha series now explicitly unsupported.
- **`README.md`** drops the stale `Version: 2.0.0-beta.1` header in
  favor of badges, replaces the `open` (macOS-only) quick-start command
  with a portable instruction, points to `docs/brand/index.html` as a
  visual demo until live screenshots land, and links the new
  `docs/troubleshooting.md`.
- **`docker-compose.yml`** header comment now describes the wizard flow
  as the primary path; the alpha.14 manual flow is no longer documented
  in the file as if it were current.
- **Install-site versioned-symlink target** in the README example
  bumped from `versions/2.0.0-beta.1` to `versions/2.0.0-beta.2`.

### Fixed

- **Brand color coverage on the admin dashboard.** `admin.css` now
  remaps the full 50-700 scale of yellow/green/purple/violet utilities
  into Bird brand tokens (was 700/800 only), plus the `bg-blue-50/100`
  / `bg-purple-50/100` / `bg-indigo-50/100` informational tints. Stat
  tiles and chips on the dashboard read on-brand instead of generic
  Tailwind blues, yellows, greens, purples.

## [2.0.0-beta.2] - 2026-04-29

Brand-polish iteration over beta.1. 19 commits resolving every visual
issue surfaced by walking the live site against the brand spec, plus a
cleaner home hero that mirrors `docs/brand/index.html`.

### Added

- **`docs/brand/index.html`** - the canonical visual brand spec moved
  into the repo (was in a personal workspace). Single-file palette,
  typography, animated logo marks, voice samples, AI image prompt,
  asset manifest. Includes a teal-spotlight + sun-gold-bounce radial
  backdrop ready for video capture.
- **`docs/brand/README.md`** - meta: this is canonical, edit here first,
  sync `brand.css` and `bird-logo.svg` to match.
- **Brand-spec home hero** - 280px centered animated hummingbird,
  `/ welcome` eyebrow in Geist Mono teal, site name in the brand
  rainbow-shimmer gradient (teal -> sun-gold -> sunset orange ->
  ember red -> teal), tagline, Subscribe + Learn-more CTAs. Mirrors
  `docs/brand/index.html` so the homepage and the spec read as one
  design system.
- **Stage-light hero backdrop** - one focused teal spotlight from upper
  center with sun-gold ambient bounce and corner vignette. Slow 8s
  pulse on a separate `::before` layer (respects
  `prefers-reduced-motion`).
- **Top-stories grid** below the hero (3 cards) -- replaces the legacy
  right-column sidebar.
- **Animated bird partial** at `themes/tailwind/partials/marketing/bird-animation.php` -
  the polygonal hummingbird from the brand spec. Self-contained
  (scoped CSS + inline SVG, no external deps).

### Fixed

- **Tailwind CDN url** ships with a working default
  (`https://cdn.tailwindcss.com`). Previously empty default broke every
  `class="bg-* text-* h-* w-*"` utility on a fresh wizard install --
  layout collapsed, SVGs ballooned to viewport size.
- **Cache-busting** on `brand.css` + `admin.css` + `site.css` via
  `?v=<filemtime>` in `<link>` tags. Browsers no longer serve a stale
  palette after edits.
- **Footer** in brand colors -- was `bg-slate-950 + indigo gradient`,
  now `forest-deep + teal/sun-gold strip`. Column headers, links,
  bullets, hover states all on brand-hex inline styles.
- **Modal backdrops** (search, newsletter signup, newsletter status)
  use forest-deep at 85% alpha instead of slate-blue at 80%.
- **Header** uses `color-mix(in oklab, var(--bg) 80%, transparent)` so
  it inherits whatever brand bg is active (cream in light, forest-mid
  in dark) rather than the previous slate-blue translucency.
- **Card shadows** unified at 1-2px subtle elevation across all
  `.shadow-*` Tailwind variants. Newsletter form no longer floats over
  flat sibling cards.
- **Dark-mode moon icon** centered in theme-toggle. Was 20x20 viewBox
  with shape offset to upper-right; now 24x24 with the standard
  heroicons solid moon.
- **Site.css coverage swept** for every Tailwind utility variant the
  default theme uses: `bg-slate-*\/N` opacity (40, 60, 70, 80, 95),
  `text-brand-*` (100..700, all dark: variants), gradient stops
  (from/via/to-{slate,white,gray,brand}), shadow scale.
- **Page titles** strip leading separator on empty prefix
  (`<title>Bird CMS</title>` instead of `<title> · Bird CMS</title>`).
- **Default `seo.title_separator`** changed em-dash (`-`) to middot
  (`·`) - reads more design-friendly in browser tabs.

### Changed

- **Frontend default theme is now dark** (forest-mid bg, ink-white
  text, teal accent). Light mode remains opt-in via
  `[data-theme="light"]` and the theme-toggle button.
- **Em-dashes purged** from every user-visible string -- 25+ markdown
  files (titles, body, meta.yaml descriptions), tailwind partials
  (footer + base + home + install views), the title-separator default.
  Body em-dashes in dev docs (`docs/0X-*.md`) and PHP comments left
  intact (stylistically valid mid-prose, not visible to end users).
- **Default site name** is `Bird CMS` (no longer `Bird CMS Demo`).
- **Hero glow** removed PNG dependency - now pure CSS layered radials,
  zero extra request, zero kB.
- **Animated bird logo halo** removed from `.bird-logo::before` (the
  small halo around the wordmark icon). Was added in error.

### Migration

No-op for existing installs. Edit `.env`:
- Optionally bump `SITE_NAME` if you ran the wizard with `Bird CMS Demo`.
- Verify `TAILWIND_CDN_URL=https://cdn.tailwindcss.com` is set (the
  default hot-fix was added; existing `.env` written by the alpha.15
  wizard had it empty).

Hard-reload the site (`Ctrl+Shift+R`) once after pulling beta.2 to clear
any cached CSS.

## [2.0.0-beta.1] - 2026-04-29

First public-evaluation beta. Consolidates the alpha.14 → alpha.17 work
into a single tagged release with full operator documentation. The
quick-start is now `git clone && docker compose up && open localhost:8080`
— the install wizard handles APP_KEY generation, password hashing, demo
content, and brand defaults. No `cp .env.example .env`, no shell session
required.

### Highlights since alpha.13

- **Three-screen install wizard** (alpha.15) that writes `.env`,
  `config/app.php`, `storage/installed.lock` atomically. CSRF-protected,
  rate-limited (5 finish/min/IP), idempotent.
- **Bird brand identity** (alpha.16 + alpha.17) — forest-deep + teal +
  sun-gold palette, polygonal hummingbird logo, Geist typography.
  Wizard, admin, frontend (light + dark) share one set of CSS-variable
  tokens an operator can override without touching engine code.
- **Demo content** (alpha.17) — three articles, two pages, three
  categories, four polygonal SVG hero illustrations, default
  `authors.php`. Optional checkbox in the wizard.
- **Stable docker stack** (alpha.14) — six P0 fixes that previously
  broke `docker compose up` on a fresh clone. nginx vhost rendered from
  template at container start, php-fpm unix socket, port-aware
  redirects, blacklist path matched between nginx and entrypoint, GD
  installed, .gitattributes enforces LF line endings.

### Added in beta.1

- **`docs/onboarding.md`** — operator walkthrough of the install wizard.
- **`docs/branding.md`** — change colors / logo / fonts via CSS-variable
  overrides. Worked example for a violet brand.
- **`docs/theming.md`** — build a custom theme from scratch. Render
  lifecycle, view-context variables, partial conventions, asset helper,
  packaging guide.
- **`scripts/migrate-alpha13-to-beta1.sh`** — idempotent migration helper
  for existing alpha sites. Writes `storage/installed.lock`, ensures
  `config/authors.php` exists, backs up `themes/tailwind/` if
  customized, reports remaining manual steps.
- **`docs/01-vision.md` refresh** — positions the project as
  WordPress-class out-of-the-box instead of "comfortable on a Linux
  box". Adds three-screen install and brand-aware-by-default to the
  value list.
- **README rewrite** — quick-start at the top, customization layers,
  migration pointer, documentation index. Drops the now-stale
  install-site.sh-only flow as the primary onboarding path.

### Fixed in beta.1

- **Admin dashboard "Articles by Category"** no longer divides by zero
  when every category is set up but has no articles yet
  (`max([0,0,0]) === 0` slipped past the previous `!empty()` guard).

### Migration

Existing alpha.13 / alpha.14 sites: run
`scripts/migrate-alpha13-to-beta1.sh` from the site root before pulling
the new tag. The script writes `storage/installed.lock` so the wizard
treats the site as already-installed; without it, the install guard in
`public/index.php` would redirect every request to `/install` after the
upgrade.

Sites with custom `themes/tailwind/` layouts: the script backs up the
directory before the upgrade. Restore the backup over the shipped
`themes/tailwind/` if you want to preserve your customizations.

### Not in this beta

- **Public screenshots and a wizard demo gif** for the README — added
  alongside the public announcement, not on a tagged release.
- **GitHub mirror** — beta lives on GitLab only; mirror created when
  the project moves to public release.
- **AI-generated cover art** — demo content ships hand-coded polygonal
  SVG illustrations instead. Real art is post-beta polish.
- **Self-hosted Geist woff2** — Google Fonts CDN is the current loader.
  Self-hosting deferred to post-beta.

## [2.0.0-alpha.17] - 2026-04-29

Frontend rebrand + demo content. Picking the "install demo content" checkbox
in the wizard now lands you on a working three-article, two-page site
themed in Bird brand colors instead of a blank dashboard.

### Added

- **Light + dark frontend palette.** `public/assets/frontend/brand.css`
  defines two CSS-variable sets scoped to `[data-theme="light"]` (default —
  warm `surface-light` cream + `eye-navy` text + `teal` accents) and
  `[data-theme="dark"]` (forest-deep + ink-white, matching admin). The
  existing theme toggle already syncs `data-theme` with Tailwind's
  `dark` class so a single inline script flips both at once.
- **`public/assets/frontend/site.css`** — frontend equivalent of admin.css.
  Maps Tailwind `slate-*`, `brand-*`, status colors onto brand tokens;
  styles article typography on Geist + Geist Mono with sun-gold inline
  code tint.
- **Bird logo** in frontend header (36px) and footer (40px), replaces the
  initial-circle generic logo block. Same SVG already used by admin and
  the install wizard.
- **Demo content** under `examples/seed/`, copied into the live site by
  the wizard's "install demo content" checkbox:
  - 3 articles (welcome, customizing-your-theme, writing-your-first-post)
  - 2 pages (about, contact)
  - 4 polygonal SVG illustrations as article covers
  - `config/categories.php` with three categories
  - `config/authors.php` with the editorial-team default
- **`/uploads/*` static handler** in `public/index.php` and a
  `public/uploads -> ../uploads` symlink at container start (entrypoint.sh).
  Uploaded media now serves with one-year cache headers from the site root.

### Changed

- **`themes/tailwind/layouts/base.php`** loads Geist instead of Inter
  (matches admin), links brand.css + site.css, and syncs `data-theme`
  on every theme-toggle click so brand variables flip in step.
- **`Seeder`** also copies `authors.php` (was previously only copying
  `categories.php` and `menu.php`).

### Migration

Sites with custom `themes/tailwind` views: `site.css` only overrides
Tailwind utility classes; raw colors in your custom views are untouched.
If you want to opt out of the Bird palette, remove the two `<link>` tags
that base.php adds for brand.css + site.css.

## [2.0.0-alpha.16] - 2026-04-29

Admin rebrand. The dashboard, login screen, and every view that renders
through `themes/admin/layout.php` now use the Bird palette (forest-deep
backdrop, teal primary, sun-gold accents) instead of the generic dark
slate/blue look. No view template was rewritten — the change applies as
a CSS cascade so the 21 admin sections inherit the new look in one shot.

### Added

- **Bird logo** as a shipped asset: `public/assets/brand/bird-logo.svg`
  (4.1 KB, polygonal hummingbird, viewBox 811×811, source of truth in
  the brand HTML). Used as favicon, sidebar header, login screen, and
  install wizard logo.
- **`public/admin/assets/brand.css`** — all Bird design tokens as CSS
  custom properties. Single source of truth for admin palette.
- **`public/admin/assets/admin.css`** — ~250 lines of targeted Tailwind
  utility overrides that remap every slate/gray/blue/indigo class onto
  brand surfaces. Loaded after Tailwind CDN so its rules win.
- **Geist + Geist Mono** fonts via Google Fonts CDN in admin layout and
  login. Self-hosted woff2 deferred to post-beta polish.

### Changed

- **`themes/admin/layout.php`** — dropped 90 lines of inline `<style>`
  and the Tailwind config extend block; links brand.css + admin.css and
  Geist instead. Final file: 35 lines (down from 145).
- **`themes/admin/partials/sidebar.php`** — header now shows the
  polygonal Bird logo next to the site name with an "Admin" tag.
  `data-bird-sidebar` attribute drives styling declaratively from
  admin.css.
- **`themes/admin/views/login.php`** — full rebrand. Forest-deep page
  background, forest-mid card, Bird logo above the form. Form inputs
  inherit the brand styling automatically.

### Migration

- Sites that customized `themes/admin/layout.php` need to merge their
  changes on top of the new (much shorter) layout.
- Sites that overrode admin colors via custom CSS should now override
  CSS custom properties in `public/admin/assets/brand.css` instead of
  hunting for `!important` declarations in the layout's inline style.
- Tailwind utility classes used in admin views continue to work
  unchanged; they pick up the brand colors automatically.

## [2.0.0-alpha.15] - 2026-04-29

Onboarding release. `git clone && docker compose up && open localhost:8080`
now lands you on a three-screen install wizard that writes `.env`,
`config/app.php`, and `storage/installed.lock` for you — no manual config
copy, no `php -r 'echo bin2hex(random_bytes(32))'`, no `password_hash` line
in your shell history. First step toward the WordPress/Joomla-class
out-of-the-box experience the productization plan calls for.

### Added

- **Install wizard.** Three brand-styled screens: system check, site +
  admin identity, finish. Generates `APP_KEY`, bcrypts the admin password,
  derives `APP_DOMAIN` and `ADMIN_ALLOWED_IPS` from the request context,
  and writes everything atomically. CSRF-protected, rate-limited (5
  finish attempts/min/IP), idempotent (refuses to re-run once
  `storage/installed.lock` exists). Optional "install demo content"
  checkbox calls `App\Install\Seeder` to copy `examples/seed/*` into the
  live site.
  - `app/Install/{SystemCheck,ConfigWriter,Seeder,Wizard}.php`
  - `public/install.php` — bootstrap-free entry
  - `themes/install/` — pure-CSS theme on Bird brand tokens
  - `public/install/assets/install.css`

- **Pre-bootstrap install guard** in `public/index.php` and
  `public/admin/index.php`. Diverts to `/install` when the lock is
  missing; passes through to bootstrap normally once installed.
  `/install/*` paths are always handled by the wizard entry, even
  post-install, so the success page can render once after the lock
  is written.

- **GD extension** in the unified Docker image (`libpng-dev`,
  `libjpeg-turbo-dev`, `freetype-dev`, plus `docker-php-ext-configure gd`).
  The engine assumes it for hero image optimization; the wizard's system
  check flagged its absence.

- **`templates/config-app.php.example`** and `examples/seed/README.md`
  scaffolds, used by the wizard.

### Changed

- **`docker-compose.yml`** marks `env_file` optional (`required: false`,
  Compose v2.24+ syntax). The container now boots without `.env`; the
  wizard writes one on first run.
- **`templates/docker/unified/nginx.conf.template`** adds
  `location ^~ /install` with `try_files $uri /index.php`. Static
  wizard assets (`/install/assets/*`) keep their direct-from-disk fast
  path; everything else under `/install` falls through to the install
  guard. Fixes the 403 that nginx returned when looking for an index
  file inside `/install/`.

### Migration

Existing alpha.14 (and earlier) sites: create the install marker so
the wizard treats the site as already-installed. One-liner:

```bash
mkdir -p storage
printf '{"version":"2.0.0-alpha.14","installed_at":"%s","install_method":"manual"}\n' \
  "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > storage/installed.lock
chmod 600 storage/installed.lock
```

A `scripts/migrate-alpha14-to-alpha15.sh` helper ships in beta.1 (Phase 4).

## [2.0.0-alpha.14] - 2026-04-28

Stabilization release. `docker compose up` now boots on a fresh `git clone`
without manual surgery — previously six P0 bugs in the unified docker template
prevented it from working at all. First stop on the road to a publishable beta.

### Fixed

- **`docker compose up` boots on a fresh clone.** The Dockerfile was COPYing
  `templates/docker/unified/nginx.conf`, a runtime artifact not in the repo
  (so the build literally failed on a clean checkout). It now copies
  `nginx.conf.template` and `entrypoint.sh` substitutes `{{DOMAIN}}` from
  `$APP_DOMAIN` at container start.

- **PHP requests reach php-fpm.** `nginx.conf.template` had
  `fastcgi_pass 127.0.0.1:9000`, but `php-fpm.conf` listens on the unix
  socket `/var/run/php-fpm.sock`. Every PHP request returned 502. Switched
  to `fastcgi_pass unix:/var/run/php-fpm.sock`.

- **Trailing-slash redirects preserve the docker port.** Added
  `absolute_redirect off; port_in_redirect on;` so 301s like
  `/admin -> /admin/` keep `:8080` instead of dropping to `:80`.

- **`nginx -t` passes on a fresh install.** The vhost `include`d
  `/var/www/html/storage/analytics/blacklist.conf`, but `entrypoint.sh`
  wrote a placeholder to `/etc/nginx/conf.d/blacklist.conf`. Both now agree
  on the storage path; `entrypoint.sh` writes the placeholder if no
  blacklist data exists yet.

- **Admin login now persists the session over HTTP.** `app/Admin/Auth.php`
  set `'secure' => true` unconditionally, which made the session cookie
  `Secure`-only. Browsers and curl silently drop secure cookies on http://
  installs, so login appeared to work but every subsequent request was
  unauthenticated. The flag now follows `$_SERVER['HTTPS']`.

- **`config/admin.php` reads ADMIN_USERNAME / ADMIN_PASSWORD_HASH again.**
  `php-fpm.conf` was missing `clear_env = no`, so values from `.env` never
  reached `$_ENV` in PHP. `config/admin.php` reads via `$_ENV[...]`, so it
  always saw nulls and `password_verify` always failed.

- **Admin dashboard no longer 500s on a fresh install.**
  `themes/admin/views/dashboard.php` called `max($byCategory)` on the
  "Articles by Category" chart, which threw a fatal when no articles
  existed yet. Guarded with `!empty()`.

### Added

- **`.gitattributes` enforces LF line endings** across the repo. Without it,
  Windows checkouts produced CRLF and every cross-platform edit showed the
  whole working tree as "modified". Existing files re-attributed in this
  release (no content change, only EOL).

- **`templates/config-app.php.example`** — the missing template that README
  and `docker-compose.yml` referenced. Until the alpha.15 onboarding wizard
  ships, users `cp` it manually.

### Changed

- **`.gitignore` excludes runtime artifacts** that the engine generates on
  first run: `config/app.php`, `config/categories.php`,
  `templates/docker/unified/nginx.conf`, plus demo content directories.
- `docker-compose.yml` quick-start comment now describes the actually-working
  two-step flow.

### Migration

Existing alpha.13 docker installs: rebuild image
(`docker compose build --no-cache && docker compose up -d`). No data migration.

## [2.0.0-alpha.13] - 2026-04-28

Pre-publication polish on top of alpha.12. Cold-clone install now works
end-to-end on a fresh `git clone`; the default theme no longer leaks
vendor-specific copy from the author's own sites.

### Fixed

- **Cold-clone install now works without a pre-built release.**
  `scripts/install-site.sh` auto-runs `scripts/build-release.sh` when
  `releases/latest.txt` is missing (the `releases/` directory is gitignored,
  so a fresh clone has no archive yet). The README Quick Start now matches
  reality: `git clone && ./scripts/install-site.sh ...` succeeds in one step.

- **Admin pages no longer 500 on a fresh install.** `install-site.sh` now
  writes a starter `config/categories.php` with one example category. Before
  this fix, `/admin/articles`, `/admin/pipeline`, `/admin/categories`, and
  `/admin/templates` returned `Config 'categories' not found` until the
  operator created the file by hand.

- **Default `tailwind` theme genericized.** Removed hardcoded vendor copy
  that survived the agnostic-cleanup pass:
  - `themes/tailwind/views/home.php`: `og:description` and
    `twitter:description` now derive from `site_description()` (driven by
    the `SITE_DESCRIPTION` env var) instead of a hardcoded AI-publication
    tagline. Removed dead `/automation` link.
  - `themes/tailwind/views/category.php`: category descriptions now read
    from `config('categories')[<slug>]['description']`, with a neutral
    fallback. The hardcoded "Latest thinking, playbooks…" string is gone.
  - `themes/tailwind/layouts/base.php`: search placeholder is now
    "Search articles…" instead of "Search stories, tools, playbooks…".

- **`scripts/indexnow.php` no longer ships a fallback API key.** The
  hardcoded `indexnow_key` default was a leaked credential pattern: every
  install would have submitted under the same key. Operators must now set
  `INDEXNOW_KEY` in their site `.env`; the script fails loud with a link
  to the registration page if the key is missing.

### Added

- **CI pipeline.** `.gitlab-ci.yml` runs PHP syntax lint plus
  `tests/smoke-test.sh` against a `php -S` boot of the engine on every
  push and merge request.
- **`CODE_OF_CONDUCT.md`** referencing Contributor Covenant 2.1, with a
  dedicated `conduct@klymentiev.com` reporting channel.

### Changed

- `tests/smoke-test.sh` reports `[OK]` / `[FAIL]` instead of unicode
  checkmark / cross (matches the project's no-emoji rule).
- `RELEASES.md` collapsed to a one-screen pointer at `CHANGELOG.md`; the
  duplicated 1.x history (which predates the public OSS line) was removed.
- `composer.json` `support` URLs point at GitLab; canonical home is
  `gitlab.com/codimcc/bird-cms` — `<org>` placeholders in README,
  CONTRIBUTING, and RELEASING resolved.
- `RELEASING.md` "Post-release" / "Yanking" steps refer to GitLab Releases
  (the canonical home) instead of GitHub.

## [2.0.0-alpha.12] - 2026-04-27

First tag pushed to a public remote. Markdown-first PHP CMS with built-in
SEO/Schema.org, default-deny admin panel, and atomic versioned-symlink
upgrades.

### Engine

- **Markdown-first content model.** Articles, services, areas, and pages live
  on disk as `.md` + `.meta.yaml`. No content database. `git diff` shows
  changes; `cp -r` is a backup; `grep` is a search.
- **Versioned engine layout.** `engine -> versions/X.Y.Z/` symlink swap for
  atomic upgrade and rollback (`scripts/update.sh`, `scripts/rollback.sh`).
- **Site-first config.** `config/app.php` and `config/<name>.php` in the site
  override the engine defaults (whole-file Koval-style override). Engine ships
  defaults for analytics, content, generator, admin, and widgets.
- **Bootstrap fail-loud.** Refuses to boot with empty / known-default
  `APP_KEY`; refuses without `ANALYTICS_MODE` / `ANALYTICS_RETENTION_VISITS`;
  no silent fallbacks for env-driven configuration.

### Routing & rendering

- **Pattern-driven content router.** Adding a new content type is a
  `ContentRepository` class plus a config entry — no core changes. See
  `docs/recipes/add-content-type.md`.
- **Two themes shipped.** `themes/admin` for the admin panel and
  `themes/tailwind` for the public site. Both opt-in to a Tailwind CSS source
  via `TAILWIND_CDN_URL` (no hardcoded vendor URLs).
- **Schema.org markup built in.** Article, FAQPage with Q&A, HowTo,
  LocalBusiness, BreadcrumbList, Product, Review, Service, Organization, and
  more — generated automatically from front matter.
- **Sitemap, robots, canonical, llms.txt** generated from content.

### Admin

- **Hidden admin.** Unauthorized IPs receive the sites themed 404 page —
  `/admin` does not advertise itself.
- **Default-deny IP allow-list.** `ADMIN_ALLOWED_IPS=127.0.0.1` out of the
  box; `TRUSTED_PROXIES` controls which `X-Real-IP` / `CF-Connecting-IP`
  headers are honored.
- **CSRF on every mutating request**, HMAC-signed preview tokens for unsaved
  drafts, password-hashed admin auth (no plaintext defaults).
- **Article and category management**, draft pipeline, image upload (no SVG —
  XSS defense), template management, audit dashboards.
- **Optional AI generator** (`/admin/generator`). Requires
  `GENERATOR_TEXT_MODEL` and/or `GENERATOR_IMAGE_MODEL`. Throws fail-loud if
  invoked without these.

### Audit (built-in)

`audit/scripts/*.php` — generic site audit, no third-party API keys required:

- Broken links and redirect-chain detection
- Schema.org JSON-LD validation
- Image alt / format / size checks
- Word count, heading hierarchy, meta-tag coverage
- HTTPS / security-header checks
- 89-point checklist (`audit/CHECKLIST.md`), JSON output for CI

### Operations

- **Single-command install.** `./scripts/install-site.sh /var/www/example.com example.com`
  generates a fresh site directory with safe defaults: random `APP_KEY`,
  `ADMIN_ALLOWED_IPS=127.0.0.1`, `TRUSTED_PROXIES=127.0.0.1,::1,172.16.0.0/12`.
- **Atomic updates with rollback.** `scripts/update.sh` downloads a release
  archive, verifies SHA-256, extracts to `versions/X.Y.Z/`, swaps the symlink,
  and reloads PHP-FPM. Failure rolls back automatically.
- **Optional Docker entrypoint.** `docker/entrypoint.sh` clones a pinned
  engine ref into a container; `ENGINE_REPO` and `ENGINE_REF` are required —
  no silent default to the upstream repo.
- **Internal SQLite analytics** (visits.db) with hard-cap retention enforced
  by an `AFTER INSERT` trigger (DDoS-safe). Daily prune via
  `scripts/analytics-prune.php`.
- **Auto-blacklist** of bad scanners from access logs, generating an nginx
  blacklist conf.

### Documentation

- `README.md` — overview, requirements, quick start, repository layout,
  configuration reference.
- `CONTRIBUTING.md` — development setup, coding standards, PR hygiene.
- `SECURITY.md` — vulnerability disclosure policy.
- `docs/01-vision.md` — product vision and non-goals.
- `docs/02-spec.md` — feature spec (F-NN identifiers).
- `docs/03-architecture.md` — design decisions (DD-NN identifiers).
- `docs/04-roadmap.md` — versioned roadmap.
- `docs/05-api-reference.md` — admin and public API.
- `docs/06-testing-strategy.md` — test plan.
- `docs/07-operations.md` — runbook for deploys, audits, incidents.
- `docs/recipes/add-content-type.md` — worked example: events, products, etc.

### License

MIT.
