# Bird CMS - Brand Spec

`index.html` is the canonical visual brand spec. Open it in a browser to
see the palette, typography scale, logo marks (animated), tone-and-voice
samples, AI image prompt, and the asset manifest in one place.

## Source of truth

The brand HTML is **the** spec. Every other reference in this repo
(`docs/branding.md`, `public/admin/assets/brand.css`,
`public/assets/frontend/brand.css`, `public/assets/brand/bird-logo.svg`)
must agree with it.

When the brand evolves:

1. Edit `index.html` first - the new palette/font/logo lives there.
2. Update the CSS variable defaults in the two `brand.css` files to match.
3. Update the rendered `bird-logo.svg` if the mark changed.
4. Note the change in CHANGELOG under "Brand updates".

## What's inside

- **Color palette** - 11 tokens across forest/teal/gold/orange/red/ink scales.
- **Type system** - Geist + Geist Mono, sizes from 12px to 88px.
- **Logo marks** - polygonal hummingbird at 16/32/48/80/160 px, on dark + light.
- **Tone & voice** - prose samples, do/don't language, the
  *"Wings settle. The logo holds."* line.
- **AI image prompt** - the text to paste into image generators when you
  need brand-consistent imagery.
- **Source files** - manifest of related assets (SVG, animated HTML
  variants, vertex/edge/face python sources).

## Use for video / screencasts

The hero block (animated bird + rainbow shimmer wordmark + tagline) plays
its assembly animation once on load, then settles into a slow sway. Open
the file, hit reload, capture the first 4 seconds and you have the
canonical brand intro.
