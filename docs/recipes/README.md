# Recipes

Cookbook-style walkthroughs for Bird CMS. Each recipe is a single
operator-facing scenario, written end-to-end with real prompts, real
tool calls, real timing, and an honest "what broke" section.

**Agents working on a Bird CMS site:** start here. The site shapes
below are the canonical patterns. If the site you're working on
doesn't match one, ask — don't invent a third path. Outliers convert
to the closest pattern, not the other way around.

## Walkthroughs (build something, end-to-end)

Ordered by use-case complexity, easiest first.

- [`ai-content-workflow.md`](ai-content-workflow.md) -- three ways
  to wire your AI tool (Claude Code, browser, Cursor) into Bird CMS
  for writing articles. Workflow primer, no specific build.
- [`small-business-cafe.md`](small-business-cafe.md) -- a 5-page
  small-business site for a local cafe in 90 minutes. Photos +
  brief -> Claude over MCP -> 5 pages, warm-Italian palette, deployed.
- [`portfolio.md`](portfolio.md) -- a 13-project portfolio site in
  90 minutes. One custom content type (`projects`), flat layout,
  current/past split. `klymentiev.com` as the case.
- [`services-catalog.md`](services-catalog.md) -- a 22-service
  cleaning company site with two subcategories in 2 hours. One
  custom content type (`services`) with division-as-URL-segment
  (`/residential/<slug>`, `/commercial/<slug>`). CleaningGTA as
  the case.
- [`personal-blog-import.md`](personal-blog-import.md) -- a personal
  blog with 12 imported markdown posts in one evening. Three
  source dialects (Hugo, Obsidian, raw), normalized via MCP, dark +
  lime theme.
- [`hugo-migration.md`](hugo-migration.md) -- migrating a small
  Hugo site (~30 posts) to Bird CMS in 20 minutes. Frontmatter diff,
  shell + Claude conversion script, what you gain, what you lose.

## Extending the engine

- [`add-content-type.md`](add-content-type.md) -- when articles +
  pages aren't enough: add events, products, courses, anything. Two
  files: a repository class and a config entry. Read this if no
  walkthrough above matches your site shape.

## Integrations

- [`integrate-statio.md`](integrate-statio.md) -- connect Statio
  for analytics and lead capture. 5-minute setup.

## Coming soon

- **Product catalog** (flat or with categories) -- not yet written.
  For now, follow [`services-catalog.md`](services-catalog.md) and
  rename `services` -> `products` in your config + theme views,
  or read [`add-content-type.md`](add-content-type.md) for the
  generic mechanism.
- **Personal site (composition)** -- portfolio + blog + pages in
  one site. Today, read [`portfolio.md`](portfolio.md) and
  [`personal-blog-import.md`](personal-blog-import.md) -- the
  klymentiev.com case in both is the same site.

## Conventions across recipes

- **Operator-first prose.** "I did X, then Y." Not "you should X."
- **Real Claude transcripts.** Where MCP tool calls are shown they
  match the actual tool names in
  [`mcp/README.md`](../../mcp/README.md): `write_article`,
  `write_page`, `read_article`, `list_pages`, etc. Never
  invented. For content types beyond articles/pages (services,
  projects), recipes use plain `write_file` via the filesystem MCP
  that Claude Code already has.
- **Pitfalls section in every walkthrough.** Honest failure modes.
- **Time breakdown table.** Minute-level steps, total, for sanity.
- **Word budget.** 800-1500 words per recipe. Cookbook-readable,
  not blog-padded.
