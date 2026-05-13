# Writing Bird CMS articles with an AI agent

Bird CMS ships **without** built-in AI. The lean-3.0 release removed the
in-engine generation stack; in 2026 we believe content is best written
half-by-human, half-by-agent, with the LLM living **outside** the CMS.

This recipe shows the three workflows that work in practice. Pick the
one that matches your editor.

## The pattern

```
+----------------+        writes .md          +-----------+
|  Your AI tool  |  ───────────────────────>  |   Bird    |
| (Claude, GPT,  |     to content/articles/   |  CMS site |
|  Cursor, etc.) |                             | (renders) |
+----------------+                             +-----------+
```

- The CMS doesn't know there's an AI in the loop.
- Articles are `.md` files. Any LLM with file access can write them.
- No API to babysit, no plugin to update.

## File format Bird expects

Each article is **two files** in `content/articles/<category>/<slug>/`:

`<slug>.md` (the body, plain CommonMark markdown):

```markdown
Lead paragraph that summarizes the piece. Keep it under 60 words —
this is what shows up in category index pages and pillar-cluster
recommendations.

## First section

Body content. Standard markdown. `inline code`, **bold**, _italics_,
[links](https://example.com), images, tables, fenced code blocks.

## Second section

More body.
```

`<slug>.meta.yaml` (the frontmatter):

```yaml
slug: my-article-slug
title: "Article Title"
description: "Meta description for search engines and AEO. Under 160 chars."
category: getting-started
date: 2026-05-02
hero_image: /uploads/articles/my-article-hero.jpg
type: guide
status: published
tags:
  - tag1
  - tag2
primary: "main keyword phrase"
secondary:
  - related keyword 1
  - related keyword 2
```

Field reference:
- **slug**: URL-safe, matches filename
- **type**: one of `guide`, `article`, `review`, `comparison`, `howto` — controls Schema.org type emitted (see [`structure.md`](../structure.md))
- **status**: `published` or `draft`. Draft articles don't appear in indexes.
- **primary** + **secondary**: drive pillar-cluster interlinking. Pillar pages use canonical patterns (`best ...`, `top ...`, `how to ...`, `... guide`, `... 2026`, `... comparison`, `... tools`); everything else is treated as a cluster.

## Workflow A — Claude Code / Claude Desktop (filesystem MCP)

The cleanest setup. Claude has direct file access; you describe what
you want, it writes the two files in place.

1. Add your Bird CMS site directory to Claude Desktop's MCP filesystem
   server config (or run Claude Code in the site directory).
2. Open a chat:

> Write a new article for my Bird CMS site at
> `content/articles/tutorials/configuring-statio.md` plus its
> `.meta.yaml` companion. Topic: how to wire Statio's leads endpoint
> into a fresh Bird CMS install. Type: howto. Primary keyword:
> "configure statio leads bird cms". 600-800 words.
> Use the format from `examples/seed/content/articles/tutorials/`
> for reference.

3. Claude reads the seed examples, follows the format, writes the two
   files. Save and refresh — Bird picks up new content on next request.

## Workflow B — ChatGPT / Claude.ai in browser (copy-paste)

Works without any tooling. Slower than A.

1. Paste the **prompt template** below into your chat with the LLM.
2. LLM produces both the `.md` body and the `.meta.yaml` frontmatter.
3. Save them to disk in the right place yourself.

Prompt template:

```
You are writing content for a Bird CMS markdown site.

Topic: {{topic}}
Type: {{howto|guide|review|comparison|article}}
Category: {{category-slug}}
Primary keyword: {{primary keyword phrase}}
Secondary keywords: {{kw1, kw2, kw3}}
Word count: {{600-1200}}

Output format — produce TWO blocks separated by a line of `===`:

BLOCK 1: the .md body
- No frontmatter inside the .md file (it goes in .meta.yaml)
- Lead paragraph under 60 words
- Use ## for section headings (no h1, the title comes from frontmatter)
- Standard CommonMark only

BLOCK 2: the .meta.yaml frontmatter, exactly these fields:
slug, title, description, category, date (YYYY-MM-DD today),
hero_image (placeholder /uploads/articles/<slug>-hero.jpg),
type, status: published, tags (3-5 items), primary, secondary
```

## Workflow C — Cursor / VS Code with AI assistant

Useful when you're already editing the site code.

1. Open the site directory in Cursor (or VS Code with the AI extension
   of your choice).
2. Create the file path you want, e.g.
   `content/articles/tutorials/my-article.md`.
3. Use inline AI: *"Write a 700-word how-to about X. Bird CMS markdown,
   no frontmatter — that goes in the sibling .meta.yaml."*
4. Repeat for the `.meta.yaml`.

This is the workflow you'll use most when iterating on existing
articles — fix a typo, restructure a section, add a code block.

## Quality checklist before publishing

Before you commit:

- [ ] Both files exist at the same path with matching slug
- [ ] `.meta.yaml` has all required fields (slug, title, description, category, date, type, status, tags)
- [ ] `description` under 160 characters (search snippet)
- [ ] `primary` keyword appears in the lead paragraph and in at least one h2
- [ ] No h1 in the `.md` body (Bird renders the h1 from frontmatter `title`)
- [ ] `hero_image` path resolves (or remove the field if no image)
- [ ] `status: published` (not `draft` if you want it live)

Refresh `/<category>/<slug>` in your browser. If it 404s, check the
filename matches the slug.

## Anti-pattern: don't wire AI into the engine

If you're tempted to add an "OpenAI write button" inside `/admin`,
stop. That was the old AI factory; it was removed in v3.0.0 because:

- The engine has no business holding API keys
- Generation prompts evolve faster than CMS releases
- Writers' AI tooling preferences vary (Claude vs GPT vs local llama)
- Markdown-first means you don't need a CMS-specific generation UI

Keep Bird AI-free. Bring your own LLM. Files on disk are the contract.

## See also

- [`add-content-type.md`](add-content-type.md) — when you outgrow
  articles + pages and want a new content type (products, locations,
  recipes, …) the AI can also write to.
- [`integrate-statio.md`](integrate-statio.md) — analytics + lead capture.
- [`docs/structure.md`](../structure.md) for the content layout +
  frontmatter reference.
