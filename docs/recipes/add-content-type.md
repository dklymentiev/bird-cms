# Recipe: Add a content type

Bird CMS treats content types as data, not code. Adding a new type - events,
products, courses, case studies, anything - is two files:

1. A repository class that reads your content from disk.
2. A config entry that registers the type with the engine.

That is the entire engine surface. Routing, sitemap, theme view dispatch
are all pattern-driven and require zero core changes.

This recipe walks through adding an `events` type as a worked example. The
same shape applies to any new type.

---

## When to add a new type (not just a tag)

Reach for a new content type when at least one of these is true:

- The records have a domain-specific shape that articles/pages don't fit
  (events have `start_date`/`location`/`registration_url`; products have
  `price`/`sku`/`stock`; case studies have `before`/`after`/`testimonial`).
- The URL convention is distinct (`/events/{date}/{slug}`,
  `/shop/{category}/{slug}`).
- You want independent sitemap priority/changefreq.

If the use case is "blog post with a tag," just use articles plus a tag
field. Don't multiply types for filterable subsets of the same shape.

---

## The two files

### File 1 - `app/Content/EventRepository.php`

A minimal repository implementing `App\Content\ContentRepositoryInterface`.
The interface is two methods: `all(): array` and
`findByParams(array $params): ?array`.

```php
<?php

declare(strict_types=1);

namespace App\Content;

use App\Support\Markdown;

/**
 * Event Repository
 *
 * Reads event bundles from content/events/<slug>/index.md
 * (markdown body with YAML frontmatter at the top).
 *
 * Engine treats every frontmatter field as opaque metadata - themes
 * decide how to render `start_date`, `location`, `registration_url`.
 */
final class EventRepository implements ContentRepositoryInterface
{
    public function __construct(private readonly string $eventsDir)
    {
    }

    public function findByParams(array $params): ?array
    {
        $slug = (string) ($params['slug'] ?? '');
        return $slug === '' ? null : $this->find($slug);
    }

    public function find(string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return null;
        }
        $path = $this->eventsDir . '/' . $slug . '/index.md';
        if (!file_exists($path)) {
            return null;
        }
        return $this->loadBundle($slug, $path);
    }

    public function all(): array
    {
        if (!is_dir($this->eventsDir)) {
            return [];
        }
        $records = [];
        foreach (glob($this->eventsDir . '/*/index.md') as $indexPath) {
            $slug = basename(dirname($indexPath));
            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                continue;
            }
            $record = $this->loadBundle($slug, $indexPath);
            if ($record === null || !empty($record['meta']['draft'])) {
                continue;
            }
            $records[] = $record;
        }
        usort($records, static fn (array $a, array $b): int =>
            ($b['date'] ?? '') <=> ($a['date'] ?? '')
        );
        return $records;
    }

    private function loadBundle(string $slug, string $indexPath): ?array
    {
        $contents = file_get_contents($indexPath);
        if ($contents === false) {
            return null;
        }
        $parsed = FrontMatter::parseWithBody($contents);
        $meta = $parsed['meta'] ?? [];
        $body = $parsed['body'] ?? '';
        $bundleDir = dirname($indexPath);
        $date = isset($meta['date']) ? (string) $meta['date'] : null;

        return [
            'slug'        => $slug,
            'title'       => (string) ($meta['title'] ?? ucfirst(str_replace('-', ' ', $slug))),
            'date'        => $date,
            'lastmod'     => $date,
            'description' => (string) ($meta['description'] ?? ''),
            'meta'        => $meta,
            'body_md'     => $body,
            'html'        => Markdown::toHtml($body, $bundleDir),
            'bundle_path' => $bundleDir,
        ];
    }
}
```

About 65 lines. Mirrors `app/Content/PageRepository.php` and
`app/Content/ProjectRepository.php` - read either as a reference.

**The record shape contract (from `ContentRepositoryInterface`):**

- `slug` - required, valid `[a-z0-9-]+`
- `lastmod` - optional ISO 8601 date for sitemap

Every other field is conventional. Any field referenced as `{name}` in the
type's URL pattern must be present. Domain fields (`location`,
`registration_url`, etc.) are passed through to themes via `$record['meta']`.
The engine never inspects them.

### File 2 - `config/content.php`

Add an entry to the `types` array and to `priority`:

```php
return [
    'types' => [
        // ... existing types ...

        'events' => [
            'source'     => 'content/events',
            'format'     => 'bundle',
            'url'        => '/events/{slug}',
            'index_url'  => '/events',
            'repository' => \App\Content\EventRepository::class,
            'view'       => 'event',
            'index_view' => 'events',
            'sitemap'    => [
                'priority'   => '0.7',
                'changefreq' => 'monthly',
            ],
        ],
    ],

    'priority' => [
        'services',
        'areas',
        'projects',
        'events',     // ← add here, above pages so /events index matches
        'articles',
        'pages',
    ],
];
```

That is the entire engine wiring.

---

## Step-by-step

1. **Create the bundle directory.**
   ```
   content/events/
     summer-conference-2026/
       index.md          ← markdown body with YAML frontmatter on top
       hero.webp         ← optional hero image
       speakers/...      ← any media
   ```

2. **Write `app/Content/EventRepository.php`** - copy the example above,
   change `events`/`Event`/`eventsDir` to your type name.

3. **Add a config entry** to `config/content.php` under `types[]` and
   `priority[]`.

4. **Add view files** to your active theme:
   - `themes/<theme>/views/event.php` - single event page
   - `themes/<theme>/views/events.php` - index page (lists all events)

5. **Verify:**
   ```sh
   php -l app/Content/EventRepository.php
   php scripts/generate-sitemap.php   # should now include /events URLs
   curl -I https://your-site.test/events
   curl -I https://your-site.test/events/summer-conference-2026
   ```

6. **Commit.** Engine code is unchanged.

---

## Frontmatter format

`index.md` starts with a YAML frontmatter block, then the body:

```markdown
---
title: "Summer Conference 2026"
date: 2026-06-15
location: "Toronto, ON"
registration_url: https://example.com/register
hero_image: hero.webp
description: "Annual summer conference for ..."
draft: false
---

The conference brings together ...
```

`App\Content\FrontMatter::parseWithBody()` parses both the frontmatter and
the markdown body in one call. Use it from your repository's `loadBundle()`
helper, exactly as the example above does.

**Conventions across types:**

- `title` - used by sitemap and theme rendering
- `date` - drives `lastmod` and default sort order
- `description` - short summary, used in cards and meta description
- `hero_image` - filename relative to bundle directory, or absolute URL
- `draft: true` - excludes the record from `all()` and `findByParams()`

Any other field is yours to define. The engine does not validate it.

---

## URL pattern parameters

The engine's `ContentRouter` matches incoming URLs against your `url` and
optional `subarea_url` patterns. Placeholders in `{braces}` are extracted
into the `$params` map passed to `findByParams()`:

| Pattern | Example URL | `$params` |
|---|---|---|
| `/{slug}` | `/about` | `['slug' => 'about']` |
| `/events/{slug}` | `/events/summer-2026` | `['slug' => 'summer-2026']` |
| `/{category}/{slug}` | `/devops/docker-tutorial` | `['category' => 'devops', 'slug' => 'docker-tutorial']` |
| `/areas/{parent}/{slug}` | `/areas/toronto/north-york` | `['parent' => 'toronto', 'slug' => 'north-york']` |

If your type has a hierarchical layout (parent/child, residential/commercial
fork, etc.), declare both `url` and `subarea_url` in the config. The router
tries the more specific pattern first; whichever matches with all
placeholders resolved wins.

---

## Conventions and gotchas

- **One repository class per type.** Don't merge bundle-format types into a
  single generic class - see RFC `bird-cms/plans/2026-04-26-agnostic-content-types-rfc.md`
  non-goal N1. Domain helpers (`->featured()`, `->upcoming()`, `->latest()`)
  belong on the concrete class. The interface stays minimal: `all()` +
  `findByParams()`.
- **Always add the type to `priority`.** ContentRouter iterates the priority
  array, not the types array. A type missing from `priority` is invisible.
- **Order matters in `priority`.** Place specific patterns before catch-alls.
  `pages` uses `/{slug}` which catches one-segment URLs; put your new type's
  index above `pages` so `/events` index resolves first.
- **No engine domain knowledge.** Engine treats your `meta` fields as opaque.
  If you want validation, do it in your repository's `loadBundle()` (return
  `null` for invalid records) or in the theme view (skip rendering missing
  fields).
- **Themes don't have to support every type.** A theme that doesn't ship an
  `event.php` view will fail loud when an event URL is hit. Either add the
  view, or remove the type from that site's `config/content.php`.

---

## Reference

- `app/Content/ContentRepositoryInterface.php` - the contract
- `app/Content/PageRepository.php` - simplest existing example (~65 lines)
- `app/Content/ProjectRepository.php` - bundle format example
- `app/Http/ContentRouter.php` - how URL matching works
- `docs/structure.md` -- request flow, layer map, where to extend
