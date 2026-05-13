# Screenshots

Canonical product screenshots referenced from the project README and
recipes.

## Files

Product screenshots (README):

- `frontend-home.jpg` -- frontend homepage in dark mode (default theme).
- `wizard-step-2.jpg` -- install wizard, step 2 (site identity form).
- `admin-dashboard.jpg` -- admin dashboard after the wizard finishes.

Recipe screenshots:

- `cafe-home.jpg` -- final home page of the cafe walkthrough
  ([small-business-cafe.md](../recipes/small-business-cafe.md)).
  Warm-Italian palette (cream + amber + olive). _Pending capture._
- `cafe-admin-menu-edit.jpg` -- URL Inventory edit modal open on
  `/menu` with a price being changed. _Pending capture._
- `blog-home.jpg` -- final home page of the personal blog walkthrough
  ([personal-blog-import.md](../recipes/personal-blog-import.md)).
  Dark + lime palette, 12 articles across 3 categories. _Pending
  capture._
- `blog-admin-inventory.jpg` -- URL Inventory in admin filtered to
  the `architecture` category, showing the imported articles.
  _Pending capture._

## Capture instructions

When refreshing screenshots after a release:

1. `git checkout` the release tag.
2. `docker compose up -d`, walk the wizard, accept "seed demo content".
3. Capture at 1440x900 viewport (or 1280x800 minimum), JPG quality 85,
   < 250 KB each.
4. Frontend in **dark mode** (default), admin and wizard in their
   shipped color.
5. Replace files in this directory and commit. Do not include the
   container's runtime `.env` or any captured admin password.

For recipe screenshots specifically: the recipe `.md` files reference
the filenames listed above and carry a `<!-- TODO: capture jpg -->`
HTML comment next to each image tag. Capture, drop the JPG into this
directory under the same filename, and remove the TODO comment from
the corresponding recipe in the same commit.

## License

Same as the project: MIT.
