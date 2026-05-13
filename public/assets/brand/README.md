# Bird CMS - Brand Assets

Source-of-truth: `C:/Projects/projects-cards/bird-cms-brand.html` (the brand
guidelines page) and `bird-cms-handcrafted.svg` (the canonical logo).

## Files

| File | Purpose | Size |
|------|---------|------|
| `bird-logo.svg` | Polygonal hummingbird, 39 facets, viewBox 811×811. Use anywhere a logo is needed. | 4.1 KB |

## Use

```html
<link rel="icon" type="image/svg+xml" href="/assets/brand/bird-logo.svg">

<a class="bird-logo" href="/admin/">
  <img src="/assets/brand/bird-logo.svg" width="32" height="32" alt="Bird CMS">
  <span class="bird-logo-name">Bird CMS</span>
</a>
```

## Editing

The hummingbird is hand-built from 49 vertices and 87 edges. Don't edit the
SVG paths directly — regenerate from the source in `projects-cards/`
(`_build_logo.py` and the vertex/edge/face python files there).
