# Bird CMS - Branding

> Visual reference: `docs/brand/index.html` in the repo is a
> single-file canonical spec for the palette, typography, logo marks,
> voice, and AI image prompt. Open it directly from your checkout in a
> browser. Every token below is sourced from it.

How to make Bird look like *your* site without forking the engine. Every
visual change in this doc is a small CSS or asset edit; no PHP, no rebuild.

## The three layers

```
                                            edit when you want to:
                                            ──────────────────────
public/admin/assets/brand.css       ←       change admin colors
public/assets/frontend/brand.css    ←       change frontend colors
public/assets/brand/bird-logo.svg   ←       swap the logo
themes/<name>/                      ←       restructure layouts (see theming.md)
```

The first three live in `public/`, so they're served directly by nginx -
no PHP touched, no cache to bust. Reload the page and you're done.

## Brand tokens

Both brand.css files define the same set of CSS custom properties:

```
admin (forest-deep + teal, dark-only):

  --bird-forest-deep    page background, sidebar
  --bird-forest-mid     cards, panels
  --bird-surface-tint   borders, hover states
  --bird-teal           primary accent
  --bird-sun-gold       secondary accent / highlights
  --bird-ember          danger / destructive
  --bird-ink-white      primary text
  --bird-ink-mute       secondary text

frontend (light + dark):

  --bg, --surface       page + card surfaces
  --text, --text-mute   primary + secondary text
  --accent              primary accent (links, CTAs)
  --highlight           secondary accent
  --warning, --danger   status colors

  Light mode:           cream + eye-navy + teal
  Dark mode:            forest-deep + ink-white + teal
```

Override values, not selectors. If you want a purple brand, you change
`--bird-teal: #c084fc;` in admin's brand.css and `--accent: #a78bfa;` in
frontend's brand.css. Every component that uses those tokens (sidebar
active state, primary buttons, focus rings, link colors, code-block
inline tint, …) updates in lock-step.

## Worked example: a violet brand

```css
/* public/admin/assets/brand.css */
:root {
    --bird-forest-deep:   #1a1130;
    --bird-forest-mid:    #251847;
    --bird-surface-tint:  #3b2868;
    --bird-teal:          #c084fc;   /* primary action */
    --bird-teal-deep:     #a855f7;   /* primary hover */
    --bird-sun-gold:      #f59e0b;   /* secondary accent */
    --bird-ember:         #f43f5e;   /* danger */
    --bird-ink-white:     #fafafa;
    --bird-ink-mute:      #a3a3a3;
}
```

```css
/* public/assets/frontend/brand.css */
:root,
[data-theme="light"] {
    --bg:          #faf5ff;
    --surface:     #ffffff;
    --text:        #2e1065;
    --text-mute:   #6b21a8;
    --accent:      #7c3aed;
    --highlight:   #f59e0b;
    --danger:      #f43f5e;
}
[data-theme="dark"] {
    --bg:          #1a1130;
    --surface:     #251847;
    --text:        #fafafa;
    --accent:      #c084fc;
    --highlight:   #f59e0b;
}
```

That's it. Every Tailwind utility used in the views (`.bg-blue-500`,
`.text-slate-700`, `.border-brand-500`, …) maps onto one of these tokens
through `admin.css` / `site.css`, so nothing else needs to change.

## Swapping the logo

The default logo is a 39-facet polygonal hummingbird at
`public/assets/brand/bird-logo.svg` (4.1 KB). Three places reference it:

- Admin sidebar header
- Admin login screen
- Frontend header (`themes/tailwind/partials/header.php`)
- Footer
- Favicon (`<link rel="icon" type="image/svg+xml">`)

Replace the file in place - every reference picks up the new SVG without
any code change.

For best results:
- **SVG, viewBox roughly square** (the original is `0 0 811 811`).
- **Single color or a tight palette** - the logo renders at sizes from
  16px (favicon) to 56px (login). Detail at the small end gets lost.
- **No external font dependencies** - convert text to paths if your
  logo includes lettering, otherwise different machines render
  differently.

If you want a wordmark instead of an icon-mark, edit
`themes/tailwind/partials/header.php` (and `footer.php`,
`themes/admin/partials/sidebar.php`) and replace the
`<img src="/assets/brand/bird-logo.svg">` with your own markup. The
`.bird-logo` wrapper class handles spacing and color inheritance.

## Swapping the font

Bird uses Geist (Vercel's geometric sans) for UI and Geist Mono for
code/monospace. Loaded via Google Fonts CDN in three places:

- `themes/admin/layout.php`
- `themes/admin/views/login.php`
- `themes/tailwind/layouts/base.php`
- `themes/install/layout.php` (the wizard)

To use a different font:

1. Add your `<link>` to Google Fonts (or your own self-hosted woff2 + `@font-face`)
2. Update `--bird-font-sans` and `--bird-font-mono` in
   `public/admin/assets/brand.css` and `public/assets/frontend/brand.css`

```css
:root {
    --bird-font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
    --bird-font-mono: "JetBrains Mono", ui-monospace, monospace;
}
```

Self-hosting (recommended for production):

```
public/assets/fonts/inter/Inter-Regular.woff2
public/assets/fonts/inter/Inter-SemiBold.woff2
...
```

```css
@font-face {
    font-family: "Inter";
    src: url("/assets/fonts/inter/Inter-Regular.woff2") format("woff2");
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}
```

## Favicon

`<link rel="icon" type="image/svg+xml" href="/assets/brand/bird-logo.svg">`
is set in admin and frontend layouts. SVG favicons work in every modern
browser. If you need a PNG fallback for older clients, ship
`/assets/brand/favicon-32.png` and add:

```html
<link rel="icon" sizes="32x32" href="/assets/brand/favicon-32.png">
```

## Don't edit (won't survive `update.sh`)

- `app/`, `bootstrap.php` - engine code
- `themes/admin/*.php` - engine views (CSS overrides are fine; markup edits get clobbered on update)
- `themes/install/*.php` - wizard views

Customizations belong in:
- `public/` (assets, override CSS)
- `themes/<your-theme>/` (custom theme - see [theming.md](theming.md))
- `config/` (site-specific settings)

## See also

- [Install](install.md) - what the install wizard does
- [Theming](theming.md) - building a custom theme from scratch
