## What changes

<!-- One paragraph: what does this PR do, and what should the user notice? -->

## Why

<!-- The problem you're solving. Link the issue if one exists: Fixes #123 -->

## How to verify

<!-- Concrete steps a reviewer can run. Examples:
     - docker compose up -d, walk wizard, observe the new field in step 2
     - php -S, hit /api/lead with payload X, expect 200
     - load /admin/articles, observe Ctrl+S now saves
-->

## Migration / rollback

<!-- Does this require running a migration script? Are config files
     touched? How would someone roll back if it breaks production? -->

## Checklist

- [ ] Feature/bug branched from `main`
- [ ] Smoke-tested locally (`/`, `/admin/`, install wizard if touched)
- [ ] CHANGELOG.md updated under "Unreleased" or the next version section
- [ ] Docs updated when behavior or interfaces change (`docs/install.md`,
      `docs/structure.md`, `docs/usage.md`, `docs/troubleshooting.md`)
- [ ] No new silent fallbacks for required config (fail loud in
      `bootstrap.php`)
- [ ] No emoji in code, docs, comments, or commit messages
