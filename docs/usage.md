# Usage

Three ways to add content to a Bird CMS site: by editing files
directly, through the admin panel, or by pointing an AI agent at the
MCP server. Pick whichever matches the moment.

## Files on disk

The fastest path. Drop two files into the right directory:

```
content/articles/tutorials/scaling-bird-on-hostinger.md
content/articles/tutorials/scaling-bird-on-hostinger.meta.yaml
```

`scaling-bird-on-hostinger.meta.yaml`:

```yaml
title: Scaling Bird CMS on Hostinger shared PHP
description: PHP-FPM tuning for low-RAM shared hosts.
type: howto
status: published
date: 2026-05-11
primary: php-fpm
```

`scaling-bird-on-hostinger.md` is the body — pure markdown, no
preamble. The `.meta.yaml` sibling is required for articles; the
two files always travel together.

The engine reindexes on the next request. The article appears at
`/tutorials/scaling-bird-on-hostinger`, in the sitemap, and in
pillar-cluster recommendations for anything sharing the `php-fpm`
keyword. No rebuild step.

Pages live in `content/pages/<slug>.md`. Same frontmatter shape.

## Admin panel

Sign in at `/admin/login` with the credentials from the install
wizard. The IP allow-list silently 404s the panel for unauthorized
visitors — if the login screen returns 404, your IP isn't on the list
(edit `.env`, restart).

The sidebar has two modes — `minimal` (the OSS default) hides
Articles, Security, and API keys. Flip to `full` in `config/admin.php`
to surface all of them.

| Section | Mode | What it does |
|---|---|---|
| Dashboard | both | Draft count, recent edits, quick links. |
| Pages | both | List, edit, publish/unpublish. Markdown editor with live preview. |
| Categories | both | Add/rename/delete categories. Articles in a deleted category move to `uncategorized`. |
| Media | both | Upload jpg/png/gif/webp (no svg, no exe-by-extension). Files land in `public/assets/`. |
| Docs | both | This documentation, served from the running engine. |
| Settings | both | Site name, URL, description, active theme. Writes `config/app.php`. |
| Articles | full | List, filter, edit, publish/unpublish. Markdown editor with live preview. |
| Security | full | IP allow-list, rate-limit state, recent failed logins. |
| API keys | full | Issue/revoke keys for the `/api/*` endpoints. |

Every POST is CSRF-protected. Failed-login rate limit kicks in after
configurable attempts per IP.

### Drafts and preview

Save with `status: draft` and the article is excluded from the index,
sitemap, and frontend routes. To share a draft externally, the editor
generates a preview URL signed with `APP_KEY` (HMAC). Anyone with the
link can view that one draft until the token expires; nothing else
becomes reachable.

## AI agent (MCP)

Bird CMS ships a stdio Model Context Protocol server at `mcp/server.php`.
Any MCP-capable client (Claude Desktop, Claude Code, Cursor, Continue,
Zed) can read, write, publish, and search articles via 11 native
tools. No API key to provision. No plugin to install.

### Connect

**Claude Code**, from the site directory:

```bash
claude mcp add bird-cms -- php ./mcp/server.php
```

**Claude Desktop**, add to `claude_desktop_config.json`:

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

Same shape for Cursor / Continue / Zed in their MCP settings.

For multi-site setups, register one MCP server per site with a
different `BIRD_SITE_DIR`.

### Tools

| Tool | Purpose |
|---|---|
| `list_articles` | Filter by category/status; returns slug, title, status, date. |
| `read_article` | Returns frontmatter + markdown body. |
| `write_article` | Atomic `.md` + `.meta.yaml` write; creates category dir if needed. |
| `delete_article` | Removes both files. Idempotent. |
| `publish` / `unpublish` | Toggle `status`. |
| `list_categories` | Categories with at least one article. |
| `list_pages` / `read_page` / `write_page` | Same shape for pages. |
| `search` | Full-text across content/. |

Full reference in [`mcp/README.md`](../mcp/README.md).

### Sample session

```text
> list my published articles in tutorials, then write a new draft
  called scaling-bird-on-hostinger in the same category, type howto,
  ~600 words on PHP-FPM tuning.

[bird-cms] list_articles({ category: "tutorials", status: "published" })
  -> 3 articles

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

Two files land on disk. `git diff` shows what the agent wrote. `git
revert` undoes it.

## Customization pointers

- Colors, logo, fonts: [branding.md](branding.md). CSS variables in
  `public/admin/assets/brand.css` and
  `public/assets/frontend/brand.css`. No rebuild.
- Layouts and structural changes: [theming.md](theming.md). Copy
  `themes/tailwind/` to `themes/<your-theme>/`, edit, point
  `ACTIVE_THEME` at it.
- New content type: [structure.md](structure.md#extending).
- Recipes for common scenarios: [recipes/](recipes/).
