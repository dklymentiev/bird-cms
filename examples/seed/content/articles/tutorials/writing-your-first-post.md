Bird CMS doesn't have a custom editor or a proprietary format. Posts are plain Markdown files with metadata in a side `.meta.yaml` file. You can write them in any editor, version them with Git, and pipe them in from external tools.

## The minimum

A working post needs two files: the body (`<slug>.md`) and the metadata (`<slug>.meta.yaml`).

`my-first-post.md`:

```markdown
This is the body. Write Markdown. **Bold**, *italic*, [links](https://example.com),
images, code blocks, lists - all standard.
```

`my-first-post.meta.yaml`:

```yaml
title: "My first post"
description: "Short summary used as the meta description."
category: tutorials
date: 2026-04-29
status: published
```

Save both as `content/articles/tutorials/my-first-post.{md,meta.yaml}` and visit `/tutorials/my-first-post` - it's live.

## What goes in the meta file

| Field | Purpose | Required |
|-------|---------|----------|
| `title` | Article title (used as `<h1>` and `<title>`) | yes |
| `description` | One-sentence summary; drives meta description and OG tags | yes for SEO |
| `category` | Slug matching a key in `config/categories.php` | yes |
| `date` | Publication date (`YYYY-MM-DD`) | yes |
| `tags` | YAML list used for related-article matching | no |
| `hero_image` | Cover image, absolute path from site root | no |
| `type` | `guide`, `tutorial`, `review`, `news`, `comparison` | no |
| `status` | `published` (default), `draft`, `scheduled` | no |
| `priority` | Integer; higher floats up in "popular" sort | no |

Drafts don't render publicly but show in `/admin/articles`. Scheduled posts publish themselves at midnight on `date`.

## Writing the body

Plain CommonMark Markdown plus a few extensions:

- **Tables** - pipe-style, like the one above
- **Code blocks** - triple-backtick fences with optional language tag for highlighting (`\`\`\`php`, `\`\`\`bash`)
- **Headings** - `##` for sections, `###` for subsections. The first `<h1>` is generated from `title` - don't add another in the body
- **Inline code** - single backticks, rendered with a sun-gold tint per the brand

The engine generates a table of contents from your `<h2>` and `<h3>` headings automatically; you don't have to maintain one.

## Images

Two options:

1. Drop the file into `content/articles/<category>/<slug>/<image>.jpg` - co-located with the article. Reference as `![alt](image.jpg)`. The engine serves it from the bundle.
2. Upload via `/admin/media` - files go to `uploads/`. Reference as `![alt](/uploads/image.jpg)`.

Bird optimizes uploaded images to WebP on first request and caches the result.

## Editing in the admin

`/admin/articles` lists everything in `content/articles/`. Click a row to edit - the in-browser editor saves to the `.md` and `.meta.yaml` files on disk. Useful for quick tweaks; if you're writing a long piece, your usual editor + Git is better.
