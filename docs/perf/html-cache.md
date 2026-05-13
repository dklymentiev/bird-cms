# HTML response cache

`HTML_CACHE=true` enables a filesystem cache of the fully rendered HTML
body for every stable-URL frontend page. Repeat GETs echo the persisted
file verbatim and skip the render pipeline entirely (repository load,
markdown render, theme include, sidebar pool, link filter, TOC pass).

Stage 2 of the perf work. Stage 1 was [`CONTENT_CACHE`](../perf/benchmarks/README.md),
which memoises parsed meta-arrays. The two are independent: enable
either, both, or neither.

## When to enable

- Your site is content-heavy and most traffic hits the same handful of
  stable URLs (homepage, top articles, llms.txt).
- You have measured a render-time problem under load and ruled out
  application bugs first.
- You accept up to 5 minutes of staleness in the worst case (TTL safety
  net) and same-request staleness on edits done outside the admin /
  MCP path (e.g. a manual `vi content/articles/blog/foo.meta.yaml`
  followed by an immediate page view -- the cache invalidation hooks
  only fire from repository writes).

Default off. Leave it off until a baseline says you need it.

## Cached routes

The dispatcher wraps these routes:

| Route               | Cache key                              |
|---------------------|----------------------------------------|
| `/`                 | `home`                                 |
| `/llms.txt`         | `llms.txt`                             |
| `/<slug>` (page)    | `<slug>`                               |
| `/<category>`       | `<category>`                           |
| `/<cat>/<slug>` (article) | `<cat>/<slug>` (or `<prefix>/<cat>/<slug>` when `articles_prefix` is set) |
| `/projects/<slug>`, `/services/...`, `/areas/...` | URL-shaped key |

Explicitly **not** cached:

- `/preview/<slug>` (draft tokens, per-request)
- `/search` (query string is part of the request)
- `/admin/...`, `/api/...`, `/install/...`, `/health` (auth-scoped or
  stateful)
- Anything with a query string (`?preview=1`, `?cb=foo`, `?page=2` --
  the cache is keyed on path only)
- Anything sent with `Cookie: bird_admin=...` / `dim_admin=...` /
  `*_admin=...` (anonymous render must never reach an admin session)
- Any non-GET method

## Invalidation

Repositories drop the relevant cache files on `save()`:

| Saved entity      | Files dropped                                                   |
|-------------------|-----------------------------------------------------------------|
| Article           | article URL, `<category>`, `home`, `llms.txt`                   |
| Page              | `<slug>`, `home`, `llms.txt`                                    |
| Project           | `projects/<slug>`, `projects`, `home`                           |
| Service           | `services/<type>/<slug>`, `<type>/<slug>`, `services`, `<type>`, `home` |
| Area              | `areas/<slug>`, `<slug>`, parent variants, `areas`, `home`      |

Settings save (`/admin/settings` → General tab) calls
`HtmlCache::flushAll()` because the affected fields (`site_name`,
`site_description`, `site_url`, default meta tags) drive the header /
footer / nav on every rendered page.

### TTL safety net

Every cache entry is rejected when `time() - filemtime > 300`. Even if
an invalidation path fails to fire (bug, manual edit outside the admin,
external write), the worst-case staleness window is bounded at five
minutes. This is by design: the cache is an optimisation, not a source
of truth.

### Manual flush

`rm -rf storage/cache/html/*` is safe at any time. The next request to
each URL repopulates. Idempotent atomic writes guarantee that
concurrent renders can't leave a half-written file.

## Storage layout

```
storage/cache/html/
├── home.html
├── llms.txt
├── about.html                          (page)
├── blog.html                           (category index)
├── blog/
│   └── welcome.html                    (article, no prefix)
├── articles/
│   └── blog/welcome.html               (article, articles_prefix=articles)
├── projects/
│   └── bird-cms.html
└── services/
    └── residential/
        └── house-cleaning.html
```

Filenames are sanitised before they touch disk: only `[a-z0-9/.-]` is
allowed, `..` segments are rejected, leading slashes stripped. A
malformed key fails closed (no-op) rather than half-matching a real
entry.

## Atomic writes

Cache writes follow the same temp-file + `rename(2)` pattern used
everywhere else in the engine
(`App\Content\AtomicMarkdownWrite::atomicWrite`,
`App\Install\ConfigWriter::atomicWrite`). A crashed PHP process can't
leave a half-written HTML body on disk; the next request either reads
the previous good copy or misses cleanly.

## Graceful degradation

When `storage/cache/html/` is not writable (permissions, full disk),
every cache operation no-ops and the engine falls back to live rendering
on every request. The site stays up; it just runs slower. No
`Throwable` is raised from inside the cache layer.

## Verifying it's working

```
# 1. Enable.
echo 'HTML_CACHE=true' >> .env
# (For local PHP-FPM: restart the pool so the env var lands in
#  $_SERVER. Apache mod_php: restart Apache. Docker: re-up the engine.)

# 2. Make a request.
curl -sS http://localhost/ > /dev/null
ls storage/cache/html/
# home.html

# 3. Second request reads the file (verify by tailing access log -- it
# should still log the GET, but profile or strace shows no PHP repo
# work, only fileread on storage/cache/html/home.html).
```

## Interaction with `CONTENT_CACHE`

Both can be on at once. `HTML_CACHE` hits short-circuit `CONTENT_CACHE`
entirely (no repository is loaded at all on a cache hit). On a miss,
the render pipeline benefits from `CONTENT_CACHE` for the meta-array
parsing under the hood, then the resulting HTML is persisted to
`HTML_CACHE`.
