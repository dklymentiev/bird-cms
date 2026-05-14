# I built a 22-service cleaning company site with two subcategories in 2 hours

CleaningGTA is a Toronto-area cleaning company with two divisions —
residential homes and commercial properties — and 22 distinct services
between them. The URL grammar they wanted is clean: every service lives
at `/<division>/<service-slug>`, and the two division landing pages
(`/residential`, `/commercial`) list the services in that division as
cards.

This recipe shows how to wire a multi-subcategory services catalog so
that adding service #23 is one filesystem operation — create the folder,
write the markdown, the site picks it up. No config edit, no admin
clicks, no restart.

![CleaningGTA residential landing](../screenshots/services-catalog-residential.jpg)
<!-- TODO: capture jpg -->

## What I built

| URL | Purpose |
|---|---|
| `/` | Hero + featured services + lead form |
| `/residential` | Division landing — 12 service cards |
| `/commercial` | Division landing — 10 service cards |
| `/residential/house-cleaning` | Service detail |
| `/residential/deep-cleaning` | Service detail |
| `/commercial/office-cleaning` | Service detail |
| … | 22 detail pages total |
| `/blog` | Articles (separate type, not covered here) |
| `/areas/<city>` | Service-area pages (separate type) |

The catalog auto-grows. To add a new service, the operator (or an
agent) drops a folder under `content/services/<division>/<slug>/` with
an `index.md` + `meta.yaml`. The division landing picks it up on next
render, no config edit, no cache flush.

## The 2-hour breakdown

| Stage | Time | What happened |
|---|---:|---|
| Install wizard | 90 s | `install.sh`, fill 5 fields |
| Content-type declaration | 5 min | `config/content.php` — one `services` entry |
| Theme views (3 files) | 30 min | `residential.php` + `commercial.php` (division landings) + `service.php` (detail) |
| Claude generates 22 services | 45 min | Brief + scope → 22 markdown bundles via filesystem MCP |
| Manual polish | 25 min | Two pricing fixes, one hero swap, slug collision fix |
| Deploy | 15 min | `deploy-all.sh`, Caddy reload, cert |
| **Total** | **~2 h** | |

The 45-minute generation step is the only AI step. Everything else is
plumbing that gets reused for every site of this shape.

## Step 1 — install + seed (90 seconds)

```bash
curl -fsSL https://gitlab.com/codimcc/bird-cms/-/raw/main/scripts/install.sh | bash
```

Wizard fields, then **uncheck** "Seed demo content" — this site has no
seeded articles to delete-and-replace; only pages and a brand-new
content type.

## Step 2 — declare the content type

`config/content.php` is the routing brain. One entry adds the type:

```php
return [
    'types' => [
        'services' => [
            'source'     => 'content/services',
            'format'     => 'markdown',
            'url'        => '/{type}/{slug}',
            'repository' => \App\Content\ServiceRepository::class,
            'sitemap'    => [
                'priority'   => '0.9',
                'changefreq' => 'monthly',
            ],
        ],
        // ... pages, articles, areas all follow
    ],
    'priority' => ['services', 'areas', 'articles', 'pages'],
];
```

Two things worth reading carefully:

- **`'url' => '/{type}/{slug}'`** — `{type}` here is the immediate
  child directory under `content/services/`. The router treats it as
  part of the URL path, not as the content-type name. A folder at
  `content/services/residential/house-cleaning/` becomes
  `/residential/house-cleaning`. **The hierarchy on disk is the URL.**
  No mapping table, no rewrite rule.
- **`'priority' => ['services', ...]`** — pages have a catch-all
  `'/{slug}'` URL, so a service URL like `/residential/...` would also
  match a hypothetical page. Putting `services` higher in priority
  resolves the ambiguity deterministically: services win, pages
  fallback.

`ServiceRepository` is already in the engine
(`app/Content/ServiceRepository.php`). It implements
`ContentRepositoryInterface`, supports flat and bundle layouts
(`<slug>.md` + `.meta.yaml` or `<slug>/index.md` + `meta.yaml`), and
handles cache invalidation on save. No code to write — just point the
config at it.

## Step 3 — theme views (three files)

The catalog needs three views:

- `views/residential.php` — listing for residential services
- `views/commercial.php` — listing for commercial services
- `views/service.php` — a single service detail

The listings are static pages: `content/pages/residential.md` and
`content/pages/commercial.md` exist as plain pages. Their templates
fetch services from the repo and render a grid:

```php
// themes/<name>/views/residential.php (excerpt)
$repo = $this->container->get(\App\Content\ServiceRepository::class);
$services = array_filter(
    $repo->all(),
    fn($s) => $s['type'] === 'residential'
);
// ... render hero + grid of service cards
```

The detail view is invoked by the router whenever a URL resolves to a
service (`type=residential`, `slug=house-cleaning`):

```php
// themes/<name>/views/service.php (excerpt)
$title    = $service['title'];
$features = $service['features'] ?? [];
$included = $service['included'] ?? [];
$pricing  = $service['pricing']  ?? [];
$faqs     = $service['faqs']     ?? [];
// ... render hero, features grid, what's included, pricing tiers, FAQ
```

Division-specific styling is one boolean: the theme reads
`$service['type']` and picks a slate-blue gradient for `commercial`
and a warm primary for `residential`. One view, two looks.

## Step 4 — the Claude transcript

22 services is too many to write by hand. I gave Claude Code the
business brief in the site directory with the bird-cms MCP server
wired in.

The brief:

> CleaningGTA is a Toronto cleaning company. Two divisions:
>
> residential: house-cleaning, condo-cleaning, deep-cleaning,
> move-out-cleaning, maid-service, regular-cleaning, airbnb-cleaning,
> spring-cleaning, steam-cleaning, carpet-cleaning, luxury-home-cleaning,
> one-time-cleaning
>
> commercial: office-cleaning, retail-cleaning, post-construction,
> gas-station, window-cleaning, high-rise-window-cleaning,
> truck-fleet-washing, drone-cleaning
>
> For each, write `content/services/<division>/<slug>/index.md` (body
> only — 4 sections: Why us, Our process, What's included, Service
> areas) and `meta.yaml` (frontmatter: title, slug, description 155–160
> chars, hero_text, features 5–8, included 8–15, pricing 3 tiers, faqs
> 4–6, hero_image: ./hero.webp, priority).
>
> Tone: friendly, professional. Location: Toronto + GTA suburbs.
> Audience: residential = busy families, commercial = ops managers.
> Status: published.

Claude's loop, abbreviated:

```
[tool_use] list_files { path: "content/services" }
  -> []                                # empty, nothing seeded

[tool_use] write_file
  args: {
    path: "content/services/residential/house-cleaning/meta.yaml",
    content: "title: House Cleaning\nslug: house-cleaning\n..."
  }
  -> { ok: true }

[tool_use] write_file
  args: {
    path: "content/services/residential/house-cleaning/index.md",
    content: "## Why Choose Our House Cleaning Service\n..."
  }
  -> { ok: true }

... 42 more writes (2 files × 22 services minus existing)
```

The MCP server's v0.2 toolset covers articles and pages — for a new
content type, generation is plain `write_file` via the filesystem MCP
that Claude Code already has. 45 minutes of wall time, ~14,000 words
of body content plus structured frontmatter. The slow part was me
reviewing what Claude wrote and flagging two off-market pricing tiers
that needed a Toronto adjustment.

## Step 5 — what broke

**1. URL collision with pages.** I'd created
`content/pages/residential.md` as the division landing AND a service
type matching `/residential/*`. Without `'priority'` set, the router
picked whichever loaded first — non-deterministic. Adding explicit
priority (`['services', 'areas', 'articles', 'pages']`) resolved it:
detail URLs always go to services, `/residential` with no slug after
falls through to the page. Doctor `--deep` would have caught this on
the next run.

**2. Hero image paths.** Claude invented filenames like
`hero-house-cleaning.jpg` that didn't exist on disk. The pages
rendered with broken `<img>` tags. Fix: a stock-image bank under
`/assets/images/bank/` plus a clarification in the brief
("`hero_image: ./hero.webp` — drop the actual image into the bundle
folder under that exact name"). Bundle-relative paths kept Claude
from inventing.

**3. Slug collision between divisions.** Both
`/residential/window-cleaning` and
`/commercial/high-rise-window-cleaning` existed (close enough to
confuse the sitemap deduper). They're URL-distinct (different parent
segments), so the bug was in my sitemap aggregation, not the router.
ServiceRepository keys results by `$type/$slug` internally, which I
hadn't honored on the way out.

## Step 6 — adding service #23 (the whole point)

The pattern earns its keep when content scales without code changes.
Service #23 is one filesystem operation:

```bash
mkdir -p content/services/residential/window-washing
$EDITOR content/services/residential/window-washing/meta.yaml
$EDITOR content/services/residential/window-washing/index.md
```

(Or via Claude over MCP: two `write_file` calls.)

Next request to `/residential` lists the new card. Next request to
`/residential/window-washing` renders the detail. No config edit, no
restart, no template change, no cache to bust. The repository's
filesystem walk discovers it on the first read; the on-disk
modification time triggers HTML-cache invalidation.

## What I'd do differently

- Build the theme views **first**, before the brief. The first batch
  had subtle frontmatter drift (some services used `included`, others
  `whats_included`). The template would have surfaced the mismatch
  on render; a written contract from the start would have caught it
  in the brief.
- Stock-image bank up front. Don't let the LLM invent paths.

## See also

- [`add-content-type.md`](add-content-type.md) — the underlying
  mechanism (Events used as the example, same shape as services).
- [`portfolio.md`](portfolio.md) — a simpler, flat-layout content
  type (no subcategories).
- [`small-business-cafe.md`](small-business-cafe.md) — five-page
  site with no content types beyond pages.
- [`../theming.md`](../theming.md) — how theme views are resolved.
- [`../structure.md`](../structure.md) — content directory layout
  reference.
- [`../../mcp/README.md`](../../mcp/README.md) — MCP server v0.2 tool
  list (articles + pages today; services/projects via filesystem MCP).
