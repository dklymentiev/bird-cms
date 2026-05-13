# Bird CMS - Theming

How to build a custom theme from scratch - for when [branding.md](branding.md)
(color and logo overrides) isn't enough and you want to restructure layouts,
add new view types, or ship a theme as a separate package.

## Theme structure

Themes live under `themes/<name>/`. The engine doesn't care what's inside
beyond a few well-known view names:

```
themes/<your-theme>/
├── layouts/
│   └── base.php          required: shell that wraps every view
├── partials/
│   ├── header.php        included by base.php
│   ├── footer.php
│   └── ...               anything else you want to share between views
└── views/
    ├── home.php          required: homepage (/)
    ├── article.php       required: single article (/<category>/<slug>)
    ├── category.php      required: category listing (/<category>)
    ├── page.php          required: static page (/<slug>)
    ├── search.php        recommended: search results
    └── 404.php           recommended: not-found
```

That's it. The engine routes a request, picks the right view name, and
calls `$theme->render('view-name', $data)`. Your view receives `$data`
plus a `$theme` reference and `$config` for site-wide settings.

## Render lifecycle

```
public/index.php
  └─ resolves URL                  /tutorials/customizing-your-theme
  └─ ArticleRepository::find()     fetches the article
  └─ $theme->render('article', [   passes view data
       'article' => $article,
       'related' => [...],
       ...
     ])
       └─ themes/<active>/views/article.php   captures output via ob_start
       └─ themes/<active>/layouts/base.php    receives $content + shared vars
       └─ echoes the layout
```

`base.php` is the shell. It receives:

| Variable | Type | Purpose |
|----------|------|---------|
| `$content` | string | Already-rendered view HTML - echo it inside `<main>`. |
| `$theme` | `App\Theme\ThemeManager` | Use `$theme->partial('name', $vars)` for shared partials. |
| `$config` | array | Site-wide config (name, URL, theme settings). |
| `$pageTitle` | ?string | Set inside the view via `$pageTitle = '...'`; surfaces here for `<title>`. |
| `$meta` | array | OG/Twitter tags, canonical, hreflang. |
| `$structuredData` | array | JSON-LD schemas to embed. |
| `$breadcrumbItems` | array | Breadcrumb structure. |

Set those four context variables at the top of any view to populate
the layout - the engine pulls them out of `get_defined_vars()` after
rendering.

```php
// themes/your-theme/views/article.php
$pageTitle = $article['title'] . ' - ' . $config['site_name'];
$meta = [
    'description' => $article['description'],
    'canonical'   => $config['site_url'] . $article['url'],
    'og_image'    => $article['hero_image'] ?? '/assets/brand/og-default.jpg',
];
?>
<article>
  <h1><?= htmlspecialchars($article['title']) ?></h1>
  <?= $article['html'] ?>
</article>
```

## Activating a theme

Edit `config/app.php`:

```php
return [
    // ...
    'active_theme' => 'your-theme',
];
```

Or set `ACTIVE_THEME=your-theme` in `.env` (the default `config/app.php`
falls back to `$env('ACTIVE_THEME') ?? 'tailwind'`).

That's the whole switch. The engine's `ThemeManager` looks for views
under `themes/<active_theme>/`; if a view doesn't exist there, the
engine throws - there's no automatic fallback to another theme. (This
is intentional: a missing view should crash loud, not silently render
a different theme's output.)

## Starting from the default

Cheapest way to fork: copy `themes/tailwind/` to `themes/<your-theme>/`
and edit. Switch `active_theme` to your fork. The Bird brand CSS still
applies - your custom views inherit `public/assets/frontend/brand.css`
through `base.php`'s `<link>` tags.

If you don't want the brand styling, drop those two `<link>` lines
from your `base.php` and ship your own CSS.

## Building one from nothing

Minimal viable theme:

```
themes/minimal/
├── layouts/base.php
└── views/
    ├── home.php
    ├── article.php
    ├── category.php
    ├── page.php
    └── 404.php
```

```php
<!-- layouts/base.php -->
<!DOCTYPE html>
<html lang="<?= $meta['lang'] ?? 'en' ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle ?? $config['site_name']) ?></title>
    <link rel="stylesheet" href="<?= $theme->asset('site.css') ?>">
</head>
<body>
    <header><a href="/"><?= htmlspecialchars($config['site_name']) ?></a></header>
    <main><?= $content ?></main>
    <footer>&copy; <?= date('Y') ?> <?= htmlspecialchars($config['site_name']) ?></footer>
</body>
</html>
```

```php
<!-- views/article.php -->
<?php $pageTitle = $article['title']; ?>
<article>
    <h1><?= htmlspecialchars($article['title']) ?></h1>
    <p><time datetime="<?= $article['date'] ?>"><?= $article['date'] ?></time></p>
    <?= $article['html'] ?>
</article>
```

`$theme->asset('site.css')` resolves to
`<site_url>/assets/<theme-name>/site.css`, so drop your stylesheet at
`public/assets/minimal/site.css` (matching directory name) and the
helper handles the URL.

Repeat for `home.php`, `category.php`, `page.php`, `404.php`. Each
receives a different `$data` shape - see `themes/tailwind/views/` for
working examples and the variable names the engine passes.

## Partials

Share blocks between views via `$theme->partial('name', $data)`. The
engine looks for `themes/<active>/partials/<name>.php` and runs it
with the supplied data. Useful for header/footer/sidebar/CTA strips.

`base.php` typically calls partials directly:

```php
<header><?php $theme->partial('header', ['config' => $config]); ?></header>
```

If a partial doesn't exist, the call silently no-ops (vs. `render`
which throws). This makes partials an opt-in extension point - your
custom theme can omit a partial and the engine won't complain.

## Theme assets

Bird has two conventions for static files:

**Theme-scoped** (`public/assets/<theme>/`): served via
`$theme->asset('path')`. Live alongside your view files in spirit but
in `public/` for direct nginx serving.

**Global** (`public/assets/brand/`, `public/assets/fonts/`): shared
across all themes. Use these for the logo, favicons, fonts - anything
where a brand swap shouldn't fork the theme.

## Shipping a theme as a package

Themes can live in their own git repo:

```
my-bird-theme/
├── themes/    ← matches Bird's themes/ directory
│   └── my-theme/
└── public/    ← matches Bird's public/ directory
    └── assets/
        └── my-theme/
```

Drop it into a Bird install with a Composer install hook, a git submodule,
or `cp -r`. The engine doesn't care how it gets there.

## Don't edit

`app/Theme/ThemeManager.php`, `app/Http/ContentRouter.php`,
`public/index.php` - engine code. If you want a feature that requires
changing them, file an issue (or send a patch) - the engine welcomes
new view-context variables, partial conventions, or asset helpers.

## See also

- [Install](install.md) - what the install wizard sets up
- [Branding](branding.md) - quick brand tweaks via CSS variables
- `themes/tailwind/` - the default theme, useful as a copy-and-edit baseline
