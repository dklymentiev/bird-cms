# Bird CMS - guidance for AI agents

You're contributing to Bird CMS, a markdown-first PHP CMS. This file gives
you the orientation a human contributor would pick up over a coffee.

## What this codebase is

Markdown files on disk, rendered through a PHP engine. No database for
content. The engine deploys via a versioned-symlink layout (`engine ->
versions/X.Y.Z`) so atomic upgrade and rollback are a single `ln -sfn`.

Six production sites run on the same engine code today; they share via
the symlink mechanism, not by sharing files. Each site has its own
`content/`, `storage/`, `.env`, and `config/app.php`.

## Where things are

| Path | What |
|---|---|
| `app/` | Engine PHP. PSR-4 mapped to `App\` namespace via `composer.json`, but bootstrap currently uses manual `require_once` chains (migration to PSR-4 autoload is on the roadmap). |
| `app/Admin/` | Admin panel: `Auth.php`, `Router.php`, `Controller.php` (base), per-area controllers (`ArticleController`, `MediaController`, …). |
| `app/Content/` | Repositories: `ArticleRepository`, `PageRepository`, `ProjectRepository`, `ServiceRepository`, `AreaRepository`, plus `MetricsRepository` for analytics. All implement `ContentRepositoryInterface`. |
| `app/Http/` | Frontend: `ContentRouter`, `HomeController`, `ContentCollectors`. |
| `app/Support/` | Shared helpers: `Markdown`, `SchemaGenerator` (Schema.org), `Config`, `LinkFilter`, `Analytics`, `ImageResolver`. |
| `bootstrap.php` | Loads `.env`, validates `APP_KEY`, sets `display_errors` per `DEBUG`, wires manual autoload. |
| `public/` | Web entry: `index.php` (frontend), `admin/` (admin), `api/` (lead, subscribe, track-event). |
| `themes/` | Frontend `tailwind/`, admin `admin/`. Per-site theme override is **not** in the engine yet (roadmap). |
| `scripts/` | CLI: `install-site.sh`, `update.sh`, `check-update.sh`, `build-release.sh`, audit helpers. |
| `audit/` | SEO + security audit runners. |
| `factory/` | Optional AI content-generation pipeline (`run-pipeline.php`). Requires `GENERATOR_TEXT_MODEL` + `GENERATOR_IMAGE_MODEL`; throws if unset. |

## Conventions

**Strict & explicit.**
- PHP 8.1+ minimum (uses `readonly`, `str_contains`).
- `declare(strict_types=1);` at the top of every new PHP file.
- Type hints on every parameter and return.
- PSR-12 spacing.

**No silent fallbacks for configuration.**
If a value is required (like `APP_KEY`), `bootstrap.php` validates it and
throws `RuntimeException` with a clear message. Do not write
`config('app_key', 'some-default-value')` — that pattern was the cause of
the leaked HMAC key incident. Use `config('app_key')` and trust the boot
validator.

**Default-deny security.**
- Admin panel `ADMIN_ALLOWED_IPS` defaults to `127.0.0.1`.
- `TRUSTED_PROXIES` defaults to loopback only — proxy headers are
  ignored unless `REMOTE_ADDR` is in this list.
- `DEBUG` defaults to `false`. PHP `display_errors` follows it.
- Auto-update cron is opt-in via `ENABLE_AUTO_UPDATE=true`.
- Engine clone in `docker/entrypoint.sh` requires explicit `ENGINE_REF`,
  no silent default to HEAD.

**Schema.org is built-in, not an afterthought.**
Most renderers emit Schema.org via `app/Support/SchemaGenerator.php`. If
you add a new content type, hook into the generator rather than emitting
ad-hoc JSON-LD inline.

## Common tasks

**Add a new content type (e.g. `products` for an online store):**
1. Add a class extending `ContentRepositoryInterface` under `app/Content/`.
2. Register it in `bootstrap.php` `require_once` list (or, post-PSR-4
   migration, via composer autoload).
3. Add URL config in the site's `config/app.php`.
4. The router (`ContentRouter`) consults the interface uniformly — no
   `switch ($typeName)` edits needed (this was the alpha.12 refactor).

**Add a new admin page:**
1. Create controller in `app/Admin/MyController.php` extending the base
   `App\Admin\Controller`.
2. Register routes in `public/admin/index.php`.
3. Add view template under `themes/admin/views/my/`.
4. The base controller's `enforceIpRestriction()` runs in the constructor
   — you don't need to repeat the auth check.

**Add an audit check:**
1. Drop a `php audit/scripts/check-foo.php` runner.
2. Use `php audit/scripts/full-audit.php` as the orchestrator.

## Things to be careful about

- **`.env` and `.claude/` must not be committed.** The `.gitignore`
  covers these, but if you `git add -f` them, secrets land in the repo.
- **`APP_KEY` is HMAC-load-bearing.** Changing it invalidates all
  preview-token signatures. Don't change it casually on a running site.
- **Admin login form being publicly visible is a regression.** If you
  see `/admin` returning 200 (not 404) for an unallowed IP, that's a
  bug — the design is "hide the existence of the panel".
- **Mandatory TLS on SMTP.** Don't restore the plain-fsockopen path;
  refusing to send beats sending plaintext credentials.

## Style notes

- No emoji in code, comments, or generated content.
- Comments explain **why**, not **what**. The variable named
  `$retainedAfterPurge` doesn't need a comment "this is the variable
  retained after purge".
- Imperative subject lines in commits, under 72 chars. Body explains
  motivation and non-obvious decisions.

## When in doubt

- Read the relevant doc under `docs/` first.
- Check `CHANGELOG.md` to see what shipped recently and why.
- For security-sensitive paths, read `SECURITY.md` and `app/Admin/Auth.php`.
- For architecture-shape questions, read `docs/structure.md`.
- Open a discussion before non-trivial PRs (see `CONTRIBUTING.md`).
