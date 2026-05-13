# Bird CMS

[![Version](https://img.shields.io/badge/version-3.1.0--rc.1-teal)](VERSION)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
[![Status](https://img.shields.io/badge/status-public%20beta-orange)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4)](composer.json)
[![Docker](https://img.shields.io/badge/docker-ready-2496ED)](docker-compose.yml)

> AI-first markdown CMS with native MCP support. Your AI agent edits the
> site directly. No copy-paste, no API token, no plugin.

## Why Bird CMS in 2026

- **Your AI agent edits this CMS directly.** Bird ships a stdio
  [Model Context Protocol server](mcp/README.md). Point Claude Desktop,
  Claude Code, Cursor, Continue, or Zed at your site and the agent
  reads, writes, publishes, and searches articles through 11 native
  tools. No API key to provision, no plugin to install, no copy-paste
  loop. See also the
  [AI content workflow recipe](docs/recipes/ai-content-workflow.md).
- **Markdown files on disk. Git-friendly. No DB to back up.**
  `content/articles/<category>/<slug>.{md,meta.yaml}`. `git diff` is
  your audit trail. `cp -r` is your backup. `grep` is your search.
- **Atomic versioned-symlink deploys with rollback in one command.**
  `engine -> versions/X.Y.Z`. Upgrade is a swap, rollback is the same
  swap reversed. See
  [`docs/install.md`](docs/install.md#production-install).
- **Browser-based install.** `docker compose up -d`, open the browser,
  step through the wizard (system check, site identity, finish, success).
  Five required fields plus a few sensible defaults. The wizard generates
  `APP_KEY`, bcrypts the admin password, derives network config from your
  request context, writes `.env`/`config/app.php` atomically. Re-runnable,
  idempotent, rate-limited.
- **Production defaults from day one.** Schema.org markup for 13+
  types, sitemap, robots, llms.txt all generated automatically.
  IP-allowlisted admin (the panel is invisible to unauthorized
  visitors). HMAC-signed preview tokens. Fail-loud boot — missing
  `APP_KEY` or `DEBUG=true` in production refuses to start.

## Quick start

**One-liner** (requires Docker; clones to `./bird-cms`, starts on port 8080):

macOS / Linux:

```bash
curl -fsSL https://gitlab.com/codimcc/bird-cms/-/raw/main/scripts/install.sh | bash
```

Windows (PowerShell):

```powershell
iwr -UseBasicParsing https://gitlab.com/codimcc/bird-cms/-/raw/main/scripts/install.ps1 | iex
```

The installer checks Docker, fetches the repo (git clone, falls back to
zip download), starts the container, waits for `/health`, and opens the
install wizard in your browser. Customize via `BIRD_CMS_DIR`,
`BIRD_CMS_PORT`, or `BIRD_CMS_BRANCH` env vars.

**Manual** (three commands):

```bash
git clone https://gitlab.com/codimcc/bird-cms.git
cd bird-cms
docker compose up -d
```

Then open <http://localhost:8080> in your browser. The wizard walks you
through a system check, site identity (five required fields plus
defaults for timezone, language, and description), and a "seed demo
content" checkbox that produces a working site with three articles, two
pages, three categories, and brand-colored hero illustrations.

No `cp .env.example .env`, no `php -r 'echo bin2hex(random_bytes(32));'`,
no SSH session.

### Connect your AI agent

Once installed, wire your MCP-capable client (Claude Desktop, Claude
Code, Cursor, Continue, Zed) to the Bird CMS MCP server. Full setup in
[`mcp/README.md`](mcp/README.md). For Claude Code from your site
directory:

```bash
claude mcp add bird-cms -- php ./mcp/server.php
```

For Claude Desktop, add this to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "bird-cms": {
      "command": "php",
      "args": ["/absolute/path/to/your-site/mcp/server.php"],
      "env": { "BIRD_SITE_DIR": "/absolute/path/to/your-site" }
    }
  }
}
```

A real session looks like this:

```text
> list my published articles in the tutorials category, then write
  a new draft article called scaling-bird-on-hostinger in the same
  category, type howto, ~600 words on PHP-FPM tuning.

[bird-cms] list_articles({ category: "tutorials", status: "published" })
  -> 3 articles: ["installing-bird-cms", "configure-statio",
                  "wiring-traefik"]

[bird-cms] write_article({
    slug: "scaling-bird-on-hostinger",
    category: "tutorials",
    type: "howto",
    status: "draft",
    title: "Scaling Bird CMS on Hostinger shared PHP",
    body: "Bird CMS runs in PHP-FPM with nginx in front..."
  })
  -> wrote content/articles/tutorials/scaling-bird-on-hostinger.md
     wrote content/articles/tutorials/scaling-bird-on-hostinger.meta.yaml

Done. The draft is at /admin/articles/scaling-bird-on-hostinger.
```

Two real files land on disk. `git diff` shows exactly what the agent
wrote. `git revert` undoes it.

## Who Bird CMS is for

- **AI-first developers** who write content via Claude or Cursor and
  want a deployment target that speaks the same protocol.
- **Single-operator agencies** running 3-10 small content sites on one
  box, with atomic upgrades across the fleet.
- **Markdown / git natives** who prefer `cp -r` backups and `git diff`
  audit trails over a database admin panel.

## Who it's NOT for

- **Non-technical content authors** expecting a Gutenberg-like editor.
  Bird ships a markdown textarea; the assumption is your editor is
  Claude / Cursor / a text editor.
- **Multi-author editorial teams** with role-based permissions and
  workflows. Bird ships one admin user.
- **Anyone who wants a plugin ecosystem.** There isn't one. Extension
  is via PHP code.
- **Sites with > 500 articles.** Performance debt.

## Requirements

- **Docker.** Any host with Docker Desktop or a Linux container
  runtime.

The image ships everything: PHP 8.3, nginx, supervisor, GD, intl,
pdo_sqlite, mbstring, OpenSSL, curl, fileinfo. If you'd rather not
use Docker, the engine runs against any nginx + PHP-FPM 8.1+ with
the same extension list.

## Repository layout

```
bird-cms/
├── app/                     PHP application
│   ├── Admin/               Admin panel controllers, Auth, Router
│   ├── Content/             Article/Page/Project/Service repositories
│   ├── Http/                Frontend Dispatcher, controllers
│   ├── Install/             Browser-based install wizard
│   ├── Newsletter/          Subscriber repositories (file-backed)
│   ├── Theme/               ThemeManager
│   └── Support/             Markdown, Schema.org, Config, helpers
├── bootstrap.php            Loads .env, validates APP_KEY, wires autoload
├── config/                  Engine config (admin, retention, content)
├── content/                 Per-site content (articles, pages) — gitignored
├── docs/                    install, structure, usage, customization,
│                            api reference, recipes
├── examples/seed/           Demo content the wizard optionally installs
├── mcp/                     Model Context Protocol stdio server
├── public/                  Web entry points + static assets
│   ├── index.php            Frontend
│   ├── install.php          Bootstrap-free install wizard entry
│   ├── admin/               Admin panel
│   ├── api/                 /api/lead, /api/subscribe, /api/track-event
│   └── assets/              brand/, frontend/, fonts/
├── scripts/                 CLI: install-site.sh, update.sh,
│                            generate-sitemap, indexnow, parse-access-log
├── templates/               Shared templates (Docker, config example)
└── themes/
    ├── admin/               Admin UI
    ├── install/             Install wizard UI
    └── tailwind/            Default frontend theme
```

## Customization

Three layers, each cheaper than the last:

1. **[Branding](docs/branding.md)** — change colors, logo, fonts via CSS
   custom-property overrides in `public/admin/assets/brand.css` and
   `public/assets/frontend/brand.css`. No theme rebuild required.
2. **[Theming](docs/theming.md)** — restructure layouts. Copy
   `themes/tailwind/` to `themes/<your-theme>/`, edit, set
   `ACTIVE_THEME=<your-theme>` in `.env`. Engine never imports views by
   name, so any theme that exposes the required view files works.
3. **Engine extension** — add a content type (e.g. `products`) by
   implementing `App\Content\ContentRepositoryInterface` and registering
   it in `config/app.php`. ~30 minutes for a working type.

## Production install

For multi-site deployments where one engine clone serves several sites
with atomic upgrades, use `scripts/install-site.sh`. It produces a
stripped-down per-site tree (`config/`, `content/`, `storage/`,
`uploads/`, `.env`) plus an `engine -> versions/X.Y.Z` symlink.
Upgrades and rollbacks are a one-command symlink swap. See
[`docs/install.md`](docs/install.md#production-install).

> **Note on package naming.** The Composer package name is
> `klymentiev/bird-cms` (author namespace) while the repository lives
> at `gitlab.com/codimcc/bird-cms` (organization namespace). The
> mismatch is intentional and historical: the package was published
> under the author handle before the `codimcc` org existed, and
> renaming would break downstream `composer require` users. Use the
> GitLab URL for `git clone` and the Composer name for dependency
> declarations.

## Documentation

- [`docs/install.md`](docs/install.md) — install wizard, production
  deploy, upgrades, backups
- [`docs/structure.md`](docs/structure.md) — repository layout,
  content layout, frontmatter, request flow, how to extend
- [`docs/usage.md`](docs/usage.md) — adding content via files, admin
  panel, MCP agent workflow
- [`docs/branding.md`](docs/branding.md) — colors, logo, fonts
- [`docs/theming.md`](docs/theming.md) — custom themes
- [`docs/api.md`](docs/api.md) — `/api/lead`, `/api/subscribe`,
  `/api/track-event`
- [`docs/troubleshooting.md`](docs/troubleshooting.md) — common
  install / runtime errors
- [`mcp/README.md`](mcp/README.md) — Model Context Protocol server
- [`docs/recipes/`](docs/recipes/) — cookbook examples

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md). PRs welcome on bug fixes,
docs, new content types, themes. Please file an issue before working on
anything that touches `app/Theme/ThemeManager.php`,
`app/Http/ContentRouter.php`, or the engine bootstrap — those are the
contract surface for themes and we want to evolve them with care.

## License

MIT. Permits commercial use including closed-source forks. See
[`LICENSE`](LICENSE).

## Security

See [`SECURITY.md`](SECURITY.md). To report a vulnerability privately,
email `security@klymentiev.com`.
