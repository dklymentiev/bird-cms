Bird CMS keeps theme code separate from the engine. Everything in `themes/` is replaceable; the engine never imports view templates by name. This makes customization a two-step thought: pick what to change, then change one of three layers.

## The three customization layers

### 1. Brand tokens

The cheapest change. `public/assets/frontend/brand.css` defines every color, font, and radius the public site uses. To make Bird look like your brand, override the CSS custom properties:

```css
[data-theme="light"] {
    --bg: #fffaf0;
    --accent: #c084fc;
    --highlight: #f97316;
}
[data-theme="dark"] {
    --bg: #18122B;
    --accent: #a78bfa;
}
```

Save, reload - the whole frontend updates. Same trick works for the admin in `public/admin/assets/brand.css`.

### 2. Logo

Drop a new SVG at `public/assets/brand/bird-logo.svg`. The header, footer, favicon, and login screen all reference that path. No view template changes needed.

If you'd rather use a wordmark image, edit `themes/tailwind/partials/header.php` and `themes/tailwind/partials/footer.php` - both use the `<a class="bird-logo">` block, which you can replace with your own markup.

### 3. Templates

For deeper changes - adding a sidebar, restructuring article layouts, building a portfolio theme - copy `themes/tailwind/` to `themes/<your-theme>/` and edit there. Set `ACTIVE_THEME=<your-theme>` in `.env` (or via `/admin/settings`) and Bird routes through your copy.

The engine doesn't care what views exist; it asks the theme manager for `home`, `article`, `category`, `page`, `search`, `404` and renders whatever the active theme returns.

## What not to edit

`app/` and `public/admin/`, `public/install/`, `bootstrap.php` - these are the engine. Customizations there get overwritten on `update.sh`. If a feature you want is engine-level, file an issue rather than monkey-patching.

## A note on Tailwind

`themes/tailwind/` uses Tailwind via CDN. The `site.css` override layer in `public/assets/frontend/` maps every `bg-slate-*`, `text-slate-*`, `bg-brand-*` class to brand tokens. So Tailwind utilities still work in your views - they just inherit Bird colors.

If you don't want Tailwind, build a theme without it. The engine doesn't require it.
