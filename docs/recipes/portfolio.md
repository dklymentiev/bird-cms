# I built a 13-project portfolio site in 90 minutes

`klymentiev.com` is my personal site. It has a blog, an about page,
and the part that mattered most when I rebuilt it on Bird CMS: a
portfolio with 13 projects, separated into **Current Projects** and
**Past Projects**, each rendered as a card with logo, title, status,
stack, and a link to the project's own page.

This recipe is the simplest-possible content-type recipe in the book.
Flat layout, one repository (engine ships it), two theme views.
Adding project #14 is a single markdown file.

![klymentiev.com /projects landing](../screenshots/portfolio-projects-index.jpg)
<!-- TODO: capture jpg -->

## What I built

| URL | Purpose |
|---|---|
| `/` | Hero + featured projects + recent posts |
| `/projects` | Index — current then past, all cards |
| `/projects/bird-cms` | Project detail |
| `/projects/screenbox` | Project detail |
| `/projects/rein` | Project detail |
| … | 13 detail pages total |
| `/blog` | Articles |
| `/about`, `/contact` | Plain pages |

Adding project #14 is one filesystem write — `content/projects/<slug>.md`
with frontmatter on top, narrative below. The index re-renders, the
detail URL becomes live. No config edit, no admin click.

## The 90-minute breakdown

| Stage | Time | What happened |
|---|---:|---|
| Install wizard | 90 s | `install.sh`, fill 5 fields |
| Content-type declaration | 3 min | `config/content.php` — one `projects` entry |
| Theme views (2 files) | 25 min | `views/project.php` (detail) + `views/projects.php` (index) |
| Claude generates 13 projects | 30 min | Brief + repo list → 13 markdown files via filesystem MCP |
| Manual polish | 20 min | Logos, frontmatter normalization, current/past split |
| Deploy | 10 min | `deploy-all.sh`, Caddy reload |
| **Total** | **~90 min** | |

## Step 1 — install (90 seconds)

```bash
curl -fsSL https://gitlab.com/codimcc/bird-cms/-/raw/main/scripts/install.sh | bash
```

Wizard, 5 fields, **uncheck** the seed-demo box — I'm replacing
everything anyway.

## Step 2 — declare the content type

`config/content.php`:

```php
return [
    'types' => [
        'projects' => [
            'source'     => 'content/projects',
            'format'     => 'markdown',
            'url'        => '/projects/{slug}',
            'index_url'  => '/projects',
            'repository' => \App\Content\ProjectRepository::class,
            'view'       => 'project',
            'index_view' => 'projects',
            'sitemap'    => [
                'priority'   => '0.8',
                'changefreq' => 'monthly',
            ],
        ],
        'articles' => [ /* ... */ ],
        'pages'    => [ /* ... */ ],
    ],
    'priority' => ['projects', 'articles', 'pages'],
];
```

Three things to read:

- **`'url' => '/projects/{slug}'`** — fixed prefix, slug is the
  filename (`content/projects/bird-cms.md` → `/projects/bird-cms`).
  Flat layout, no subcategories.
- **`'index_url' => '/projects'`** — the listing URL. The router
  binds it to `index_view` instead of a content-type lookup.
- **`'view' => 'project'`, `'index_view' => 'projects'`** — explicit
  theme view names. The detail uses `views/project.php`, the listing
  uses `views/projects.php`. No filename convention to remember.

`ProjectRepository` ships with the engine
(`app/Content/ProjectRepository.php`) — flat-only, supports both
single-file (`<slug>.md`) and bundle (`<slug>/index.md` + assets)
formats per project. No code to write.

## Step 3 — theme views (two files)

The detail view is straight rendering of one project's frontmatter
plus body:

```php
// themes/<name>/views/project.php (excerpt)
$title  = $project['title'];
$stack  = $project['stack']  ?? [];
$repo   = $project['repo']   ?? null;
$stats  = $project['stats']  ?? [];
$status = $project['current'] ? 'current' : 'past';

// hero: title + description
// metadata block: status, started, last update, version
// stack chips
// repo link (visibility-aware)
// long-form body (markdown rendered)
```

The index view groups by current vs past — a `$project['current']`
boolean in frontmatter flips a card into either bucket:

```php
// themes/<name>/views/projects.php (excerpt)
$currentProjects = array_filter($projects, fn($p) => !empty($p['current']));
$pastProjects    = array_filter($projects, fn($p) => empty($p['current']));
?>
<section><h2>Current Projects</h2>
    <?php foreach ($currentProjects as $p): ?>
        <?php $this->partial('project-widget', ['project' => $p,
            'projectUrl' => '/projects/' . $p['slug']]); ?>
    <?php endforeach; ?>
</section>
<section><h2>Past Projects</h2>
    <?php foreach ($pastProjects as $p): ?>
        <?php $this->partial('project-widget', ['project' => $p,
            'projectUrl' => '/projects/' . $p['slug']]); ?>
    <?php endforeach; ?>
</section>
```

`project-widget.php` is a shared partial used by both the index and
the home page's "featured projects" strip. One template, two homes.

## Step 4 — the Claude transcript

13 projects, each ~400 words. The brief was small because Claude could
read my GitHub README for each project as the source.

The brief:

> I'm rebuilding klymentiev.com on Bird CMS. Portfolio at /projects/*,
> one markdown file per project under content/projects/.
>
> Projects (current = actively maintained, past = not):
>
> current: bird-cms, screenbox, rein, mesh, doci, agent-memory
> past: knowster, statio, scaffolding-crm, craft-distillery-erp,
> entis, smart-node, typus
>
> For each, write `content/projects/<slug>.md` with:
> - frontmatter: slug, title, description (160-180 chars), url (live
>   site URL or repo URL if no public site), current: true/false,
>   order (lower = higher in list, 1-20), image (/assets/<slug>-card.jpg
>   placeholder), icon (/assets/<slug>.png placeholder), date (project
>   start, YYYY-MM-DD), repo: { visibility, url }, stats: { version,
>   last_update, started }, stack: ["PHP 8", "Docker", …],
>   keywords: 4-6 long-tail.
> - body: 4-6 sections (~400 words). What it does. Why I built it.
>   Stack notes. Status. Where to find it.
>
> Look at the project's own README on github.com/dklymentiev/<slug>
> if I have one — match the tone there. Status: published.

Claude's loop, abbreviated:

```
[tool_use] web_fetch
  args: { url: "https://github.com/dklymentiev/bird-cms/blob/main/README.md" }
  -> "# Bird CMS\n\nAI-first markdown CMS..."

[tool_use] write_file
  args: {
    path: "content/projects/bird-cms.md",
    content: "---\nslug: bird-cms\ntitle: Bird CMS\n..."
  }
  -> { ok: true }

[tool_use] write_file
  args: {
    path: "content/projects/screenbox.md",
    content: "---\nslug: screenbox\n..."
  }
  -> { ok: true }

... 11 more
```

Wall time 30 minutes for 13 files. The slow part wasn't generation
(Claude churned through it in 8) — it was me reading each file and
toggling `current: false` on the four past projects, plus fixing two
descriptions that drifted into marketing copy.

## Step 5 — what broke

**1. Frontmatter shape drift.** First batch had some projects with
`stack: [PHP, Docker]` and others with `stack: { backend: PHP, infra:
Docker }`. The widget partial only knew the array form. Fix: tighten
the brief to a single shape and re-run the divergent four. Bird's
FrontMatter parser is tolerant, but the theme isn't — `array_map`
on a hash fails silently and the chip strip rendered as `0, 0, 0`.

**2. Missing `current` flag default.** A few projects came back
without `current` at all (Claude read it as optional). The index
view's `array_filter(..., fn($p) => !empty($p['current']))` treated
them as past. Fix: brief explicit ("`current: true` or `current:
false`, never omit"), one re-write pass.

**3. Image paths.** Same story as in
[`services-catalog.md`](services-catalog.md) — Claude invented card
images that didn't exist. Card grid rendered with broken
`<img>` and `alt` text. Two-second fix per project in the admin URL
Inventory editor.

## Step 6 — adding project #14

```bash
$EDITOR content/projects/new-thing.md
```

Frontmatter on top with `current: true`, narrative below. Refresh
`/projects` — new card appears in **Current Projects**. Open
`/projects/new-thing` — detail renders. No restart, no cache flush,
no config edit.

## What I'd do differently

- Write the `views/project.php` schema first and lock the frontmatter
  shape with a written contract before the Claude brief. The drift
  bugs all come from the brief being looser than the template.
- Don't bother with a `featured` flag for the home page. Order
  current projects by `order:` ascending and slice the top three.
  Fewer flags, less drift.

## See also

- [`services-catalog.md`](services-catalog.md) — multi-subcategory
  catalog (services with residential/commercial divisions).
- [`add-content-type.md`](add-content-type.md) — Events example,
  same shape as projects.
- [`personal-blog-import.md`](personal-blog-import.md) — the same
  site's blog half: importing 12 articles in an evening.
- [`small-business-cafe.md`](small-business-cafe.md) — pages-only
  site, no content types beyond the defaults.
- [`../theming.md`](../theming.md) — theme view resolution.
- [`../../mcp/README.md`](../../mcp/README.md) — MCP v0.2 tool list.
