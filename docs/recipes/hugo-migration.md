# Migrating a Hugo site to Bird CMS in 20 minutes

For a small Hugo site (under 30 posts) where rebuild-on-push is
more friction than it's worth, this is the 20-minute move. We diff
the frontmatter shapes, write a one-shot conversion prompt for
Claude, deploy.

Over 500 posts? Run the
[benchmark suite](../perf/benchmarks/README.md) first.

## What you give up, what you get

Worth being honest about. The SSG model exists for a reason.

**You lose:** build-time speed (Hugo renders 1000 pages in 800ms;
Bird CMS renders on-request, ~5ms per warm page on a $5 VPS);
static-deploy targets (Netlify, CF Pages, S3 -- Bird CMS needs PHP);
shortcodes (Bird is CommonMark + theme partials); Hugo Pipes (no
image resize at build); i18n bundles (no built-in language routing).

**You gain:** live admin edits (URL Inventory save -> live next
request, no git push, no deploy queue); native MCP for AI agents
(Claude/Cursor/Zed write articles directly --
[`mcp/README.md`](../../mcp/README.md)); no build step
(edit markdown, refresh); same `.md` files (different frontmatter
dialect, `git diff` still the audit trail); atomic versioned-symlink
deploys + rollback ([`docs/install.md`](../install.md)).

For a small ops-friendly site where the operator wants live edits
more than free hosting, Bird CMS wins. For a 5000-page docs site
with $0 hosting cost, stay on Hugo.

## Frontmatter -- side-by-side

Hugo (TOML, common default):

```toml
+++
title = "Postgres LISTEN/NOTIFY at scale"
date = 2024-08-12T14:22:00-05:00
draft = false
tags = ["postgres", "messaging"]
categories = ["databases"]
description = "When NOTIFY payloads start dropping under load."
[params]
  hero_image = "images/postgres-cover.jpg"
+++
```

Or Hugo (YAML, also common):

```yaml
---
title: "Postgres LISTEN/NOTIFY at scale"
date: 2024-08-12T14:22:00-05:00
draft: false
tags: ["postgres", "messaging"]
categories: ["databases"]
description: "When NOTIFY payloads start dropping under load."
params:
  hero_image: "images/postgres-cover.jpg"
---
```

Bird CMS `.meta.yaml` (always YAML, lives next to the `.md`, not inside it):

```yaml
title: "Postgres LISTEN/NOTIFY at scale"
slug: postgres-listen-notify-at-scale
description: "When NOTIFY payloads start dropping under load."
category: databases
date: 2024-08-12
hero_image: /uploads/articles/postgres-listen-notify-hero.jpg
type: article
status: published
tags:
  - postgres
  - messaging
primary: "postgres listen notify"
secondary:
  - postgres pub sub
  - postgres notification scale
```

Key shape differences:

| Hugo | Bird CMS | Notes |
|---|---|---|
| Frontmatter inside `.md` | Frontmatter in sibling `.meta.yaml` | Body file has no frontmatter at all |
| TOML or YAML | YAML only | Conversion needed for TOML sites |
| `draft: false` | `status: published` | Inverted semantic |
| `categories: ["x"]` (array) | `category: x` (string, single) | Bird CMS is single-category per article |
| Date with timezone | Date as `YYYY-MM-DD` | Truncate the time component |
| `params.hero_image` | top-level `hero_image` | Flatten the nested key |
| relative image paths | absolute `/uploads/...` | Move + rewrite |
| no slug field (derived from filename) | explicit `slug` field | Promote filename to slug |
| no `type` | `type: article\|guide\|howto\|...` | Default to `article` if unsure |
| no `primary`/`secondary` | both for pillar-cluster | Optional; derive from primary tag |

## The conversion script

Shell + Claude. Shell walks the Hugo `content/posts/`; Claude
normalizes each file via MCP. Drop into a working dir alongside the
Bird CMS site:

```bash
#!/usr/bin/env bash
# migrate-hugo.sh -- one-shot Hugo -> Bird CMS importer
# Run from your Bird CMS site directory. Source Hugo site path arg.
set -euo pipefail

HUGO_DIR="${1:-}"
[ -z "$HUGO_DIR" ] && { echo "usage: $0 <hugo-site-path>"; exit 1; }
[ ! -d "$HUGO_DIR/content/posts" ] && { echo "no content/posts in $HUGO_DIR"; exit 1; }

# Concatenate all source files into a single Claude prompt input.
# Claude reads, decides, writes via MCP -- one batch.
{
  echo "Convert each Hugo post below into a Bird CMS article."
  echo "Use the write_article MCP tool."
  echo "Frontmatter mapping rules:"
  echo "  - Detect TOML (+++ ... +++) or YAML (--- ... ---)."
  echo "  - status: draft==false -> published, else draft"
  echo "  - category: first element of Hugo's categories[] array"
  echo "  - date: truncate to YYYY-MM-DD"
  echo "  - hero_image: flatten params.hero_image, rewrite to /uploads/articles/<slug>-hero.jpg"
  echo "  - slug: derived from filename (drop .md)"
  echo "  - type: article (unless filename starts with how-, guide-, why-)"
  echo "  - primary: first tag; secondary: remaining tags"
  echo ""
  echo "After all writes, print: <slug> -> <status> -> <file-path>"
  echo "Stop on any error and report the source file."
  echo ""
  echo "=== SOURCE FILES ==="
  for f in "$HUGO_DIR"/content/posts/*.md; do
    echo ""
    echo "### FILE: $(basename "$f")"
    cat "$f"
    echo "### END"
  done
} > /tmp/hugo-migration-prompt.txt

echo "Prompt assembled at /tmp/hugo-migration-prompt.txt"
echo "Open Claude Code in this directory and paste the contents."
echo "Or pipe to your CLI if you have one wired up."
```

For a 30-post Hugo site this generates a ~50KB prompt, well within
Claude's context. The MCP server handles the writes; the shell just
assembles the brief.

## What Claude does (one cycle)

Source file (Hugo TOML):

```toml
+++
title = "Why I stopped using ORMs"
date = 2024-11-03T09:15:00-06:00
draft = false
tags = ["orm", "sql", "opinion"]
categories = ["opinions"]
description = "After ten years, the abstraction stopped paying for itself."
+++

The argument for ORMs is that they save you SQL...
```

Claude's tool call:

```
[tool_use] write_article
  args: {
    category: "opinions",
    slug: "why-i-stopped-using-orms",
    body: "The argument for ORMs is that they save you SQL...",
    meta: {
      title: "Why I stopped using ORMs",
      description: "After ten years, the abstraction stopped paying for itself.",
      date: "2024-11-03",
      type: "guide",
      status: "published",
      tags: ["orm", "sql", "opinion"],
      primary: "orm",
      secondary: ["sql", "opinion"]
    }
  }
  -> { ok: true, path: "content/articles/opinions/why-i-stopped-using-orms.md" }
```

Type bumped to `guide` because the title starts with "Why". Date
truncated. Category collapsed from array-of-one. Description carried
across. Body stripped of frontmatter.

For a 30-post site, 30 of these run in about 90 seconds wall time
(MCP writes are atomic and cheap -- `rename(2)` plus a YAML emit).
The bulk of the 20 minutes is you eyeballing the summary.

## The 20-minute breakdown (30-post site)

| Stage | Time | What happened |
|---|---:|---|
| Bird CMS install + categories | 5 min | Wizard + 1 category per old Hugo section |
| Run `migrate-hugo.sh` | 1 min | Assembles prompt |
| Claude conversion via MCP | 2 min | 30 `write_article` calls |
| Spot-check 5 random posts | 5 min | Compare rendered output to Hugo source |
| Image migration (rsync uploads) | 3 min | `rsync hugo/static/images/ uploads/articles/` |
| `config/app.php` URL change | 2 min | Point site_url at new domain |
| Caddy reverse proxy + cert | 2 min | One config block |
| **Total** | **~20 min** | |

## What broke (real one, not hypothetical)

**1. One post used a Hugo shortcode (`{{< youtube vid >}}`).** Bird
CMS renders that literally as text. Fix: either replace with a raw
`<iframe>` in the body, or add a theme partial. I chose iframe because
it was one post.

**2. Two posts had `draft: true` but I wanted them published anyway.**
The conversion preserved draft status (correct behavior). I used the
admin URL Inventory to flip them after import.

**3. The Hugo site's `aliases:` frontmatter (redirect rules) didn't
migrate.** Bird CMS doesn't ship a redirect table out of the box. I
configured Caddy to return 301s for the four `/old-path` -> `/new-path`
pairs I cared about. Five lines in the Caddyfile.

**4. Hugo's `taxonomies.series` -- one post belonged to a 3-part
series.** Bird CMS doesn't have a series concept. I added a tag
`series-postgres-deep-dive` and called it done; not a perfect
migration but acceptable for 3 posts.

## Config diffs

Before (Hugo `config.toml`, fragment):

```toml
baseURL = "https://example.com"
title = "My Notes"
languageCode = "en-us"
[params]
  description = "Notes on databases and architecture."
```

After (Bird CMS `config/app.php`):

```php
return [
    'site_name'        => $env('SITE_NAME')        ?? 'My Notes',
    'site_url'         => $env('SITE_URL')         ?? 'https://example.com',
    'site_description' => $env('SITE_DESCRIPTION') ?? 'Notes on databases and architecture.',
    'timezone'         => $env('TIMEZONE')         ?? 'America/Chicago',
    'active_theme'     => $env('ACTIVE_THEME')     ?? 'tailwind',
    // ... wizard-generated rest
];
```

`config/categories.php` -- one entry per old Hugo top-level section:

```php
return [
    'databases'    => ['title' => 'Databases'],
    'architecture' => ['title' => 'Architecture'],
    'opinions'     => ['title' => 'Opinions'],
];
```

## When to NOT do this

- **>500 posts on shared hosting** -- run
  `docs/perf/benchmarks/render.sh` against the imported site first.
- **Static deploy is your model** -- Cloudflare Pages, pages-on-S3
  expect a `public/` folder, not a PHP app.
- **You need i18n or Hugo Pipes** -- Bird CMS has neither.

## See also

- [`personal-blog-import.md`](personal-blog-import.md) -- the looser
  version of this recipe for when your source is a folder of
  markdown rather than a full Hugo site.
- [`small-business-cafe.md`](small-business-cafe.md) -- starting from
  zero, no migration.
- [`../../mcp/README.md`](../../mcp/README.md) -- MCP server setup.
- [`../install.md`](../install.md) -- production deploy + atomic
  upgrade + rollback.
