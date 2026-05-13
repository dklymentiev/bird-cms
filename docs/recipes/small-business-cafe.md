# I built a 5-page small-business site for a local cafe in 90 minutes

I photographed the storefront of a neighborhood cafe on my block
(Caffe Allegro, the Italian place between the laundromat and the dry
cleaner), gave the photos plus a brief to Claude Code over MCP, and
ended the evening with a five-page site live on a $5/mo VPS.

This recipe shows what I typed, what Claude did, what broke, and what
I fixed by hand.

![Cafe Allegro home page](../screenshots/cafe-home.jpg)
<!-- TODO: capture jpg -->

## What I built

Five pages, one theme tweak, two photos. That's it. No blog, no
booking integration, no newsletter.

| URL | Purpose |
|---|---|
| `/` | Hero + today's hours + lead photo |
| `/menu` | Drinks + pastry + sandwiches with prices |
| `/location` | Address, hours, parking note, embedded map link |
| `/story` | One-paragraph history of the place |
| `/contact` | Phone, email, contact form (Statio leads) |

Total content: 5 markdown pages, ~700 words combined. Owner reviews
copy weekly; I push edits from my laptop.

## The 90-minute breakdown

| Stage | Time | What happened |
|---|---:|---|
| Install wizard | 90 s | `curl install.sh \| bash` + 5 wizard fields |
| Claude generation | 30 min | Brief + photos -> 5 markdown pages via MCP |
| Admin tweaks | 10 min | Price fix on `/menu`, hero swap, status flip |
| Theme polish | 30 min | Warm-Italian palette in `brand.css` |
| Deploy | 20 min | rsync to VPS + Caddy + cert |
| **Total** | **~90 min** | |

The 30-minute generation step is the bulk. Everything else is plumbing
that's been amortized away by the v3.0 install wizard and v3.1
brand-tokens system.

## Step 1 -- install (90 seconds)

On the VPS:

```bash
curl -fsSL https://gitlab.com/codimcc/bird-cms/-/raw/main/scripts/install.sh | bash
```

Open `http://<vps-ip>:8080`, fill five fields (site name, admin email,
admin password, site URL, timezone), check **Seed demo content** so I
have something to delete-and-replace from. Wizard generates `APP_KEY`,
bcrypts the password, writes `.env` and `config/app.php`.

Took me longer to find my SSH key than to run the wizard.

## Step 2 -- the Claude transcript

I dropped two storefront photos into `uploads/cafe/` and started
Claude Code in the site directory. Bird CMS MCP server was wired in
via `claude_desktop_config.json` (one line in `mcpServers`, see
[`mcp/README.md`](../../mcp/README.md)).

The brief:

> I have a neighborhood Italian cafe -- Caffe Allegro. They sell
> espresso drinks, pastries (cornetti, sfogliatelle), and pressed
> sandwiches. Owner: Marco, second generation, his parents opened in
> 1987. Address: 412 W Belmont Ave, Chicago. Hours: Tue-Sun 7am-6pm,
> closed Mon. Phone: (773) 555-0142.
>
> Build me a 5-page Bird CMS site:
> - / home with hero + today's hours
> - /menu with three sections (espresso, pastry, panini) and prices
> - /location with address + hours + a note that street parking is
>   tight, suggest the Belmont garage two blocks east
> - /story -- one paragraph, Marco's parents, second generation, no
>   embellishment
> - /contact -- phone, email allegro@example.com, mention they take
>   the contact form
>
> Use the `write_page` MCP tool. Don't write a blog. Don't write a
> services page. Don't write an FAQ. Five pages exactly.
>
> Set status: published on all five. Use type: page (these are pages,
> not articles). Description fields under 160 chars for SEO.

Claude's first three tool calls:

```
[tool_use] list_pages
  -> ["about", "contact"]   (the seed pages)

[tool_use] delete_page
  -> { "slug": "about" }    (removing seed about)

[tool_use] read_page
  -> { "slug": "contact" }  (sampling the seed format)
```

It pulled the seed `contact.meta.yaml` to confirm the frontmatter
shape, then started writing.

```
[tool_use] write_page
  args: {
    slug: "home",
    body: "Welcome to Caffe Allegro -- a second-generation Italian
           cafe on Belmont, serving espresso, fresh cornetti, and
           pressed panini since 1987.\n\n## Today's hours\n\nTuesday
           through Sunday, 7am to 6pm. Closed Mondays.\n\n[See the
           menu](/menu) -- [Find us](/location)",
    meta: {
      title: "Caffe Allegro -- Italian cafe on Belmont, Chicago",
      description: "Second-generation neighborhood Italian cafe.
                    Espresso, pastry, pressed panini. Belmont Ave,
                    Chicago. Tue-Sun 7am-6pm.",
      date: "2026-05-10",
      type: "page",
      status: "published",
      hero_image: "/uploads/cafe/storefront.jpg"
    }
  }
  -> { ok: true, path: "content/pages/home.md" }
```

Same pattern for `menu`, `location`, `story`, `contact`. Total: 5
`write_page` calls plus 1 `delete_page`. Took about 30 seconds of
real wall time after the brief. The slow part is me reading what
Claude generated and deciding whether the tone matched.

## Step 3 -- the resulting files

`content/pages/menu.md` came out like this:

```markdown
The menu is short and changes seasonally. Prices in USD, cash and card.

## Espresso

- **Espresso** -- $3.25
- **Cappuccino** -- $4.75
- **Cortado** -- $4.25
- **Iced Americano** -- $4.50

## Pastry

- **Cornetto** (plain, chocolate, or apricot) -- $3.50
- **Sfogliatella** -- $4.25
- **Tiramisu cup** -- $5.50

## Panini

- **Caprese** (mozzarella, tomato, basil) -- $9.50
- **Prosciutto e burrata** -- $12.50
- **Tuna nicoise** -- $11.00
```

`content/pages/menu.meta.yaml`:

```yaml
title: "Menu -- Caffe Allegro"
description: "Espresso, cornetti, sfogliatelle, and pressed panini. Menu and prices for Caffe Allegro on Belmont Ave."
date: 2026-05-10
type: page
status: published
hero_image: /uploads/cafe/cornetti.jpg
```

Five pages, ten files. Everything is markdown on disk. `git diff` is
the audit trail.

## Step 4 -- what broke

This is the honest part. Three things tripped me up.

**1. Claude initially called `write_article` instead of `write_page`
for `/menu`.** First draft of the brief said "create a menu page";
the LLM read "page" loosely and reached for the bigger tool because
articles have more frontmatter fields. I corrected the prompt --
"Use `write_page`, not `write_article`. These are five pages, not
articles." -- and the second attempt was clean. Lesson: be explicit
about content type when the LLM has both tools available.

**2. The `/menu` hero image path was wrong.** Claude wrote
`/uploads/menu-hero.jpg`; I had named the file `cornetti.jpg`. The
page rendered with a broken image. Fix took 10 seconds in the admin
URL Inventory: open the URL row, edit the meta tab, save.

**3. One price was wrong.** Marco emailed me to say the cortado is
$4.25, not $3.75 like I had in the brief. I opened the URL Inventory
in admin (`/admin/pages`), clicked the pencil on `/menu`, edited the
body in the Content tab, hit save. 20 seconds. No git push, no theme
rebuild, no cache to bust.

![URL Inventory edit modal -- menu page](../screenshots/cafe-admin-menu-edit.jpg)
<!-- TODO: capture jpg -->

## Step 5 -- theme polish (warm-Italian palette)

The default Bird tailwind theme is forest-deep + teal. Wrong vibe for
an Italian cafe. I wrote a 12-line override in
`public/assets/frontend/brand.css`:

```css
:root,
[data-theme="light"] {
    --bg:          #fdf6e3;   /* cream */
    --surface:     #ffffff;
    --text:        #3a2e1a;   /* roasted brown */
    --text-mute:   #7a6a52;
    --accent:      #b8860b;   /* amber */
    --highlight:   #6b8e23;   /* olive green */
    --danger:      #c0392b;
}
```

No JavaScript, no rebuild, no Tailwind config. nginx serves the file
directly. Reload the browser and the site is warm cream instead of
forest. The teal accents in the default theme all flipped to amber
because every component reads `--accent`, not `bg-blue-500`.

I left dark mode at defaults. The cafe site doesn't need a dark mode;
nobody browses a cafe menu at 2am.

## Step 6 -- categories config

For a five-page site you mostly don't touch categories -- pages don't
belong to categories. But I did edit `config/categories.php` to drop
the seed `getting-started` entry so it doesn't show up in `llms.txt`:

Before:

```php
return [
    'getting-started' => [
        'title'       => 'Getting started',
        'description' => 'Tutorials and how-tos for new operators.',
    ],
];
```

After:

```php
return [
    // No article categories -- this is a pages-only cafe site.
];
```

One line of `config/app.php` was already correct from the wizard:

```php
'site_name'        => $env('SITE_NAME')        ?? 'Caffe Allegro',
'site_url'         => $env('SITE_URL')         ?? 'https://caffeallegro.com',
'site_description' => $env('SITE_DESCRIPTION') ?? 'Italian cafe on Belmont since 1987.',
```

(Wizard wrote those from the form fields. I didn't touch them by hand.)

## Step 7 -- deploy

The VPS already had the site running on `:8080` from the install
wizard. I pointed a Caddyfile entry at it:

```
caffeallegro.com {
    reverse_proxy localhost:8080
}
```

Caddy provisioned the cert, the site went live. 20 minutes including
DNS propagation and one false start where I had the A record pointing
at an old VPS.

## What I'd do differently

- Brief Claude with the content type up front -- "pages, not articles"
  would have skipped the `write_article` mis-call.
- Skip the seed demo content checkbox for a pages-only site.

## See also

- [`ai-content-workflow.md`](ai-content-workflow.md) -- three ways to
  wire your AI tool into Bird CMS (Claude Code, browser, Cursor).
- [`personal-blog-import.md`](personal-blog-import.md) -- importing
  12 markdown posts in an evening (different shape: lots of articles,
  no pages).
- [`hugo-migration.md`](hugo-migration.md) -- migrating off Hugo
  in 20 minutes for a small site.
- [`../theming.md`](../theming.md) -- when brand tokens aren't
  enough and you want a full custom theme.
- [`../../mcp/README.md`](../../mcp/README.md) -- MCP server setup
  for Claude Desktop, Claude Code, Cursor, Continue, Zed.
