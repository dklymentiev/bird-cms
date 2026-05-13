# Personal blog with 12 imported markdown posts in one evening

I had a folder of 12 markdown notes -- some from an old Hugo blog,
some from Obsidian, a few raw notes I'd been meaning to publish. They
were sitting in `~/notes/draft-posts/` doing nothing. This recipe is
the two-hour evening I spent moving them into a fresh Bird CMS site
with Claude doing the import work over MCP.

Output: a working dev-notes blog with 12 articles across 3 categories,
custom dark + lime theme, ready to deploy.

![Final blog homepage -- dark + lime](../screenshots/blog-home.jpg)
<!-- TODO: capture jpg -->

## Pre-state -- what I started with

Twelve `.md` files in `~/notes/draft-posts/`. Three different
frontmatter dialects because they came from three different tools:

**Hugo-style** (5 files):

```yaml
---
title: "Postgres LISTEN/NOTIFY at scale"
date: 2024-08-12T14:22:00-05:00
tags: ["postgres", "messaging"]
draft: false
---
```

**Obsidian-style** (4 files):

```yaml
---
title: Why I stopped using ORMs
tags: [orm, sql, opinion]
created: 2024-11-03
---
```

**No frontmatter at all** (3 files):

Just markdown with an `# H1` on the first line and nothing else. These
were raw notes I'd never published.

Bird CMS expects two files per article: `<slug>.md` (body, no
frontmatter) and `<slug>.meta.yaml` (frontmatter as YAML). Categories
live in subdirectories under `content/articles/<category>/`. The
import job was: normalize the three dialects into Bird's shape, split
each file into two, drop into category folders.

## The 2-hour breakdown

| Stage | Time | What happened |
|---|---:|---|
| Install + categories config | 10 min | Wizard + edit `config/categories.php` |
| Claude import via MCP | 30 min | Walk 12 files, normalize, write |
| Tag + category cleanup | 20 min | Fix bad slugs, redistribute tags |
| Custom dark + lime theme | 60 min | brand.css overrides + 1 view tweak |
| **Total** | **~2 hours** | |

## Step 1 -- install + categories

Wizard install on a dev box (`docker compose up -d`, fill 5 fields,
unchecked seed demo content this time). Then I edited
`config/categories.php` to match how I wanted the 12 posts grouped:

```php
return [
    'databases' => [
        'title'       => 'Databases',
        'description' => 'Postgres, SQLite, schema design, query plans.',
        'color'       => '#84cc16',
    ],
    'architecture' => [
        'title'       => 'Architecture',
        'description' => 'Service shapes, async patterns, distributed systems.',
        'color'       => '#a3e635',
    ],
    'opinions' => [
        'title'       => 'Opinions',
        'description' => 'Things I would do differently next time.',
        'color'       => '#bef264',
    ],
];
```

Three categories, color tokens for the chip styling on category pages.

## Step 2 -- the Claude prompt

Started Claude Code in the Bird CMS site directory with the source
notes folder mounted via the filesystem MCP server. Two MCP servers
wired up: Bird CMS (writes articles) and filesystem (reads source).

The prompt I used:

> Read every `.md` file under `~/notes/draft-posts/`. For each one:
>
> 1. Detect the frontmatter dialect (Hugo TOML/YAML, Obsidian, or none).
> 2. Read the first line of the body if no frontmatter -- use it as
>    title and strip the leading `#`.
> 3. Normalize into Bird CMS shape: slug (kebab-case from title),
>    description (first 150 chars of body, no markdown), date (parse
>    whatever's there or default to file mtime), tags (lowercase),
>    type (`guide` if title starts with "How" or "Why"; otherwise
>    `article`), status: published.
> 4. Pick a category from {databases, architecture, opinions} based
>    on tag overlap (postgres/sql/orm -> databases;
>    async/distributed/queue -> architecture; rant/opinion/took ->
>    opinions; default architecture).
> 5. Call `write_article` with category, slug, body, and meta.
>
> Show me a summary table at the end: filename -> slug -> category.
> Don't ask me before each one -- run all 12 and report.

Claude's loop (one cycle, abbreviated -- it ran 12 of these):

```
[tool_use] read_file
  path: "~/notes/draft-posts/postgres-listen-notify.md"
  -> "---\ntitle: \"Postgres LISTEN/NOTIFY at scale\"\n..."

[reasoning]
  Hugo frontmatter detected. Title -> slug "postgres-listen-notify-at-scale".
  Tags include "postgres" -> category "databases". Type: article
  (title doesn't start with How/Why). Date present, parse to YYYY-MM-DD.

[tool_use] write_article
  args: {
    category: "databases",
    slug: "postgres-listen-notify-at-scale",
    body: "<stripped frontmatter, normalized body>",
    meta: {
      title: "Postgres LISTEN/NOTIFY at scale",
      description: "When NOTIFY payloads start dropping under load,
                    you've hit the 8KB payload cap. Here's the workaround.",
      date: "2024-08-12",
      type: "article",
      status: "published",
      tags: ["postgres", "messaging"],
      primary: "postgres listen notify",
      secondary: ["postgres pub sub", "postgres notification"]
    }
  }
  -> { ok: true, path: "content/articles/databases/postgres-listen-notify-at-scale.md" }
```

After 12 cycles Claude printed the summary:

```
filename                         -> slug                              -> category
postgres-listen-notify.md        -> postgres-listen-notify-at-scale   -> databases
sqlite-wal-mode.md               -> sqlite-wal-mode                   -> databases
why-i-stopped-using-orms.md      -> why-i-stopped-using-orms          -> opinions
schema-migrations-down.md        -> schema-migrations-without-down    -> databases
async-fanout-pattern.md          -> async-fanout-pattern              -> architecture
queue-backpressure-notes.md      -> queue-backpressure-notes          -> architecture
... (6 more) ...

12 articles written. 0 errors.
```

Total wall time for the import: ~4 minutes. The slow part was me
spot-checking three of them.

## Step 3 -- what broke

**1. Two slug collisions.** I had `migrations.md` and
`schema-migrations.md` both wanting slug `migrations`. Claude picked
`migrations` for the first and -- on the second -- generated
`schema-migrations-2`. I renamed the second to
`schema-migrations-without-down` because the suffix was ugly.
`write_article` with the new slug, `delete_article` for the
auto-suffixed one. Two MCP calls, fixed.

**2. Date format mismatches.** Hugo dates have timezone suffixes
(`2024-08-12T14:22:00-05:00`); Bird's `.meta.yaml` expects
`YYYY-MM-DD`. Claude got 10 of 12 right but two Obsidian posts had
`created: 2024-11-03` (no time) and `date: 11/03/2024` (US slash
format). The slash one became `0011-03-20` -- silent corruption.
Caught it because the archive page showed the article as 0011.
Fixed by hand with `read_article` + `write_article` from Claude after
I called it out.

**3. Hero image paths.** Five posts had `hero_image:
images/cover.jpg` (relative to Hugo's content root). Bird CMS expects
`/uploads/articles/<slug>-hero.jpg` (absolute path). Claude left the
relative paths intact because I hadn't told it about the image
convention. Two of those photos I cared about; I moved them to
`uploads/articles/` and corrected the paths. The other three I
dropped the field entirely.

The honest lesson: tell the LLM about the file conventions explicitly.
Don't assume it'll guess.

## Step 4 -- tag and category cleanup

Claude's category picks were ~75% right. Three posts ended up in
`architecture` that I wanted in `opinions`. URL Inventory in
`/admin/articles`, click pencil, change category in the meta tab,
save. 30 seconds per post.

I also normalized tags. Some were singular (`migration`) where I
wanted plural (`migrations`); some had stale ones from Hugo
(`golang` when the post was actually about node). Used the admin
search to find them, fixed inline.

![URL Inventory filter -- architecture category](../screenshots/blog-admin-inventory.jpg)
<!-- TODO: capture jpg -->

## Step 5 -- dark + lime theme

Default Bird is forest-deep + teal. I wanted dark + lime because the
posts are technical and lime is the kind of accent that doesn't try
too hard. Override in `public/assets/frontend/brand.css`:

```css
:root,
[data-theme="dark"] {
    --bg:          #0a0f0a;   /* near-black */
    --surface:     #131a13;
    --text:        #e6f5e6;
    --text-mute:   #94a394;
    --accent:      #84cc16;   /* lime-500 */
    --highlight:   #bef264;   /* lime-300 */
    --danger:      #f43f5e;
}
```

Forced dark by default in `themes/tailwind/layouts/base.php`:

```html
<html lang="en" data-theme="dark">
```

(One-line edit. The theme has light + dark; I'm picking dark, no
toggle.)

One view tweak: the article hero on `themes/tailwind/views/article.php`
originally showed a category color stripe. I wanted the stripe wider
because lime-on-black looks better at scale. Two lines of CSS in
`brand.css` -- nothing in the view itself changed.

## Step 6 -- final config

`config/app.php` is mostly wizard-generated; one edit I made was
`title_separator`:

```php
'seo' => [
    'default_og_image' => $env('SEO_DEFAULT_OG_IMAGE') ?? '/uploads/og-default.jpg',
    'title_separator'  => $env('SEO_TITLE_SEPARATOR') ?? '·',
],
```

Em-dash was too heavy for the title bar. Middle dot reads cleaner.

## Pitfalls to know up front

- **Slug collisions are silent.** Two source files with the same
  title produce a suffix you didn't ask for. Pre-scan your source
  set if you have any near-duplicates.
- **Slash dates corrupt.** `11/03/2024` is interpreted as YAML
  literally -- you get `0011-03-20`. Brief the LLM that input dates
  may be slash-formatted and to default to file mtime when in doubt.
- **Hero image paths are absolute.** Bird CMS serves from `/uploads/`.
  Any relative paths from your source CMS need translation.
- **Tags travel by reference, not by validation.** There's no central
  tag registry -- a typo creates a new tag silently. Audit
  `/admin/articles` filter after import to catch them.

## See also

- [`ai-content-workflow.md`](ai-content-workflow.md) -- the three
  general AI workflows. This recipe is workflow A (Claude Code + MCP)
  applied to a one-time import job.
- [`hugo-migration.md`](hugo-migration.md) -- if your source is a
  full Hugo site (not just loose markdown), there's a tighter script
  for that.
- [`small-business-cafe.md`](small-business-cafe.md) -- five-page
  site from scratch, no import.
- [`../../mcp/README.md`](../../mcp/README.md) -- MCP setup, all 11
  tools.
