If you're reading this, the install wizard finished cleanly and you're looking at a working Bird CMS site. This article is the homepage of your new install - feel free to delete it once you're comfortable, or keep it as a reference and tweak the copy.

## What you have

Bird CMS is a markdown-first PHP CMS. There's no database in the runtime path. Articles and pages live as `.md` files on disk under `content/articles/` and `content/pages/`. `git diff` shows what changed, `cp -r` is a backup, `grep` is your search.

The admin panel at `/admin` is **hidden by default** - IPs that aren't on your allow-list see a themed 404 page instead of a login form. The install wizard added your IP automatically; if you change networks, edit `ADMIN_ALLOWED_IPS` in `.env`.

## Three things to try next

**Write your first post.** Drop a `.md` file with frontmatter into `content/articles/<category>/<slug>.md` and refresh - no build step, no rebuild. Or use the in-admin editor at `/admin/articles`.

**Customize the look.** Bird ships with a brand palette (forest deep, teal, sun gold) defined as CSS variables in `public/assets/frontend/brand.css`. Override the variables there and every page picks up the new colors. No theme rebuild required.

**Replace this article.** This file lives at `content/articles/getting-started/welcome-to-bird-cms.md`. Edit it, delete it, or move it to a different category - Bird picks up the change on the next request.

## Where things live

```
your-site/
├── content/
│   ├── articles/<category>/<slug>.md   ← your posts
│   └── pages/<slug>.md                 ← your pages (about, contact, ...)
├── config/
│   ├── app.php                          ← site identity (name, URL, theme)
│   └── categories.php                   ← taxonomy
├── uploads/                             ← media you upload via /admin
├── public/
│   └── assets/                          ← CSS, fonts, brand assets
└── storage/                             ← cache, logs, install lock
```

## You're not stuck with the demo

The wizard seeded three articles, two pages, three categories, and a few SVG illustrations so the site looks like something the moment it's installed. Everything in this article is replaceable. The wizard did not add anything to `app/`, `themes/`, or `public/` outside the `uploads/` and `assets/` directories - your engine is the same as a fresh `git clone`.

## Need a hand?

- Repository - [`gitlab.com/codimcc/bird-cms`](https://gitlab.com/codimcc/bird-cms)
- Issues - file in the GitLab tracker
- Documentation - `docs/` in the repo

Welcome aboard.
