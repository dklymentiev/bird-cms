# Structure

How a Bird CMS site is laid out on disk, how a request flows through
the engine, and where to extend it.

## Repository layout

```
bird-cms/
├── app/                     PHP application
│   ├── Admin/               Admin panel: Auth, Router, controllers
│   ├── Content/             Article/Page/Project/Service repositories
│   ├── Http/                Frontend Dispatcher, controllers
│   ├── Install/             Three-screen install wizard
│   ├── Newsletter/          Subscriber repositories (file-backed)
│   ├── Theme/               ThemeManager
│   └── Support/             Markdown, Schema.org, Config, helpers
├── bootstrap.php            Loads .env, validates APP_KEY, wires autoload
├── config/                  Engine config (admin, retention, content)
├── content/                 Articles + pages (per-site, gitignored)
├── examples/seed/           Demo content the wizard optionally installs
├── mcp/                     Model Context Protocol stdio server
├── public/
│   ├── index.php            Frontend entry
│   ├── install.php          Install wizard entry (bootstrap-free)
│   ├── admin/               Admin panel entry + assets
│   ├── api/                 /api/lead, /api/subscribe, /api/track-event
│   └── assets/              brand/, frontend/, fonts/
├── scripts/                 install-site.sh, update.sh, generate-sitemap, ...
├── templates/               Docker template, config example
└── themes/
    ├── admin/               Admin UI
    ├── install/             Install wizard UI
    └── tailwind/            Default frontend theme
```

## Content layout

Content lives as markdown + YAML on disk. No database.

```
content/
├── articles/
│   └── <category>/
│       ├── <slug>.md            body
│       └── <slug>.meta.yaml     frontmatter (or embed in .md)
└── pages/
    └── <slug>.md                static pages: about, contact, terms
```

`git diff` is the audit trail. `cp -r` is the backup. `grep` is search.

### Frontmatter

Either embedded as a `---` block at the top of the `.md` file, or
written to a sibling `<slug>.meta.yaml`. Both shapes parse to the same
record.

```yaml
title: Scaling Bird CMS on Hostinger shared PHP
description: PHP-FPM tuning for low-RAM shared hosts.
type: howto
status: published
date: 2026-05-11
category: tutorials
primary: php-fpm
secondary: [hostinger, shared-hosting]
hero_image: heros/scaling-bird.jpg
```

| Field | Purpose |
|---|---|
| `title` | `<title>`, OG title, h1 in default theme. |
| `description` | Meta description, OG description. |
| `type` | Drives Schema.org markup. Article \| howto \| faq \| review \| service \| ... |
| `status` | `published` \| `draft`. Drafts are excluded from index/sitemap. |
| `date` | Sort key. `YYYY-MM-DD`. |
| `category` | Path-derived; explicit here for completeness. |
| `primary` / `secondary` | Pillar-cluster interlinking. |
| `hero_image` | Path under `uploads/` or `public/assets/`. Auto-resolved to WebP. |
| Type-specific keys | `faq.questions[]`, `howto.steps[]`, `service.area`, etc. |

## Request flow

### Frontend `GET /<category>/<slug>`

1. nginx matches the location, falls through `try_files` to
   `/index.php?$query_string`.
2. `public/index.php` requires `bootstrap.php`.
3. `bootstrap.php`:
   - Walks up to find `config/app.php`, defines `SITE_ROOT`.
   - Resolves `ENGINE_ROOT = SITE_ROOT/engine` (versioned) or
     `SITE_ROOT` (legacy).
   - Loads `.env` into `$_ENV` / `putenv`.
   - Sets `display_errors` per `DEBUG`.
   - Validates `APP_KEY` — `RuntimeException` if missing or
     known-default.
   - Loads helpers and repositories.
4. `App\Http\Frontend\Dispatcher` routes to the right controller; the
   controller instantiates a repository, finds the record, hands data
   + Schema.org payload to the active theme.

### Admin `GET /admin/articles/<category>/<slug>`

1. Same nginx → bootstrap path.
2. `public/admin/index.php` instantiates `App\Admin\Router`.
3. `Controller::__construct()` runs `enforceIpRestriction()` — on
   rejection, emits 404 with the site's themed 404 view, then `exit`.
   The admin panel never advertises its existence to unauthorized
   visitors.
4. If the IP is allowed but the user isn't logged in, `requireAuth()`
   redirects to `/admin/login`.
5. Action runs, view renders.

### IP detection (security-critical)

`Auth::getClientIp()`:

1. If `REMOTE_ADDR` is in `TRUSTED_PROXIES` (env-driven, default
   `127.0.0.1,::1`): prefer `HTTP_CF_CONNECTING_IP`, else
   `HTTP_X_REAL_IP`, both validated through `FILTER_VALIDATE_IP`.
2. Otherwise: use `REMOTE_ADDR` directly. Headers are ignored — a
   client sending `X-Real-IP: 1.2.3.4` from a non-proxy IP gets
   nowhere.

## Layer map

| Layer | Lives here | May depend on |
|---|---|---|
| `bootstrap.php` | path resolution, .env load, fail-loud validators | nothing |
| `app/Support/` | Markdown, Schema.org, Config, helpers | bootstrap |
| `app/Content/` | repositories (read & write content) | Support |
| `app/Http/` | frontend dispatch, controllers | Content + Support |
| `app/Admin/` | admin controllers, auth | Content + Support |
| `themes/` | views | controller-provided context |
| `public/` | web entry points | bootstrap + layer above |
| `scripts/` | CLI utilities | standalone, or load what they need |

Content -> Admin or Admin -> Http is a layer violation.
`App\Admin\Controller` is the one place where IP restriction is
enforced before any concrete admin action runs.

## Versioned engine layout (production)

```
/var/www/example.com/
├── .env                          secrets (NEVER in git)
├── config/app.php                site_name, site_url, active_theme
├── content/                      articles, pages
├── storage/                      cache, logs, analytics, admin_auth.json
├── uploads/                      media uploaded via admin
├── public/                       symlinks into engine + per-site assets
├── versions/
│   ├── 3.1.0/                    extracted release archive
│   └── 3.0.0/                    previous, preserved for rollback
└── engine -> versions/3.1.0      atomic switch target
```

The `engine` symlink is the upgrade primitive: `ln -sfn versions/<new>
engine`. Rollback is the same call aimed backwards.

## Extending

### New content type

Add `products` (or anything) in three steps:

1. Implement `App\Content\ContentRepositoryInterface` —
   `all()` returns a flat list, `findByParams(array $params)` maps
   URL parameters to a record. `app/Content/PageRepository.php` is
   the smallest baseline to copy from.
2. Register in `config/app.php`:
   ```php
   'content_types' => [
       'products' => App\Content\ProductRepository::class,
       // ...
   ],
   ```
3. The frontend dispatcher picks it up automatically. No edits to
   engine routing.

### Custom theme

Copy `themes/tailwind/` to `themes/<your-theme>/`, edit views, set
`ACTIVE_THEME=<your-theme>` in `.env`. The engine never imports views
by name, so any theme that exposes the required view files works.

### Brand only

Override CSS custom properties in `public/admin/assets/brand.css` and
`public/assets/frontend/brand.css`. No theme rebuild required. See
[branding.md](branding.md).
