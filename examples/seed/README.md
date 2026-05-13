# Bird CMS - Demo Seed

This directory is what the install wizard's optional "seed demo content" step
copies into a fresh site. Phase 3 (alpha.17) lands the actual content; Phase 1
(alpha.15) ships this empty scaffold so the wizard's `Seeder` runs cleanly.

Layout the wizard expects:

```
examples/seed/
├── content/
│   ├── articles/<category>/<slug>.md
│   └── pages/<slug>.md
├── uploads/
│   └── (any binary assets referenced by content)
└── config/
    ├── categories.php   (category metadata)
    └── menu.php         (top-nav structure)
```

The seeder copies these into the live site only if the corresponding target
file does not already exist - your customizations are never overwritten.
