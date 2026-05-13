# Recipes

Cookbook-style walkthroughs for Bird CMS. Each recipe is a single
operator-facing scenario, written end-to-end with real prompts, real
tool calls, real timing, and an honest "what broke" section.

## Walkthroughs (build something, end-to-end)

Ordered by use-case complexity, easiest first.

- [`ai-content-workflow.md`](ai-content-workflow.md) -- three ways
  to wire your AI tool (Claude Code, browser, Cursor) into Bird CMS
  for writing articles. Workflow primer, no specific build.
- [`small-business-cafe.md`](small-business-cafe.md) -- a 5-page
  small-business site for a local cafe in 90 minutes. Photos +
  brief -> Claude over MCP -> 5 pages, warm-Italian palette, deployed.
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
  files: a repository class and a config entry.

## Integrations

- [`integrate-statio.md`](integrate-statio.md) -- connect Statio
  for analytics and lead capture. 5-minute setup.

## Conventions across recipes

- **Operator-first prose.** "I did X, then Y." Not "you should X."
- **Real Claude transcripts.** Where MCP tool calls are shown they
  match the actual tool names in
  [`mcp/README.md`](../../mcp/README.md): `write_article`,
  `write_page`, `read_article`, `list_pages`, etc. Never
  invented.
- **Pitfalls section in every walkthrough.** Honest failure modes.
- **Time breakdown table.** Minute-level steps, total, for sanity.
- **Word budget.** 800-1500 words per recipe. Cookbook-readable,
  not blog-padded.
