# Contributing to Bird CMS

Thanks for considering a contribution. Bird CMS is a small, focused codebase;
the bar for changes is "does this make the CMS better for someone other than
the author".

## Before You Start

- **Bug fix** with clear repro: open an issue, then PR.
- **New feature**: open a discussion / issue first. Bird CMS optimizes for
  surface-area minimalism; not every feature lands.
- **Security issue**: please follow [SECURITY.md](SECURITY.md), not the public
  issue tracker.

## Development Setup

```bash
# 1. Clone
git clone https://gitlab.com/codimcc/bird-cms.git
cd bird-cms

# 2. Install a development site using the engine (versioned 2.0 layout)
./scripts/install-site.sh /var/www/example.com example.com

# 3. Edit .env in the new site (set ADMIN_ALLOWED_IPS, ADMIN_PASSWORD_HASH, APP_KEY)
$EDITOR /var/www/example.com/.env

# 4. Point a web server (nginx, Caddy, php -S) at the site's public/ directory
php -S localhost:8888 -t /var/www/example.com/public
curl http://localhost:8888/
```

PHP **8.1+** is required.

## Coding Standards

- **PHP**: PSR-12 spacing, `declare(strict_types=1)` at the top of new files,
  type hints on signatures (params and returns), avoid `mixed` when narrower
  types fit.
- **No silent fallbacks** for configuration. If a value is required, fail loud
  in `bootstrap.php` rather than substituting a default that hides
  misconfiguration.
- **Naming**: Classes `PascalCase`, methods `camelCase`, constants
  `SCREAMING_SNAKE`, files match class names.
- **Comments**: explain *why*, not *what*. Self-explanatory code shouldn't
  carry a comment.
- **No emoji** in code, comments, or generated content.

## Testing

Bird CMS ships a PHPUnit suite at `tests/`. Four test groups, each in its
own subdirectory and registered as a PHPUnit suite in `phpunit.xml`:

| Suite         | What it covers                                                    |
|---------------|--------------------------------------------------------------------|
| `unit`        | Pure-PHP coverage for the content repositories under `app/Content/`. Save/find/delete round trips, atomic write, slug guards, flat vs bundle layouts. |
| `integration` | Admin-side flows: `Auth` (login, IP allowlist, lockout, trusted-proxy headers), the file-system contract behind `ArticleController` / `PagesController` save/delete, CSRF compare logic. |
| `mcp`         | The MCP server (`mcp/server.php`) JSON-RPC handlers, called in-process. Pinned by golden fixtures under `tests/fixtures/mcp/`. |
| `parity`      | The drift-prevention layer. Round-trips between the MCP write path and the admin read path (and vice versa) for both articles and pages. If MCP and admin ever start serialising records differently, one of these goes red. |

Existing standalone test scripts (pre-PHPUnit) live under `tests/legacy/`
and run manually with `php tests/legacy/<file>.php`. They are kept until
folded into PHPUnit.

### Running the suite

```bash
composer install                          # one-time, pulls phpunit/phpunit
make test                                 # all four suites
vendor/bin/phpunit --testsuite=parity     # one suite at a time
vendor/bin/phpunit --filter testWriteArticle   # one test
```

CI runs `make test` in the `smoke` stage of `.gitlab-ci.yml`, so a red
test blocks merge.

### Adding a test

- **A new repository method?** Add a `tests/Unit/<Repo>Test.php` method
  that exercises both the success path and the slug/input-guard surface.
  Tests use `Tests\Support\TempContent::make('label')` for a per-test
  content directory and `Tests\Support\TempContent::cleanup()` in
  `tearDown()`.
- **A new MCP tool?** Add a method to `tests/Mcp/McpServerTest.php`
  *and* update `tests/fixtures/mcp/tools-list.expected.json` so the
  golden-fixture test stays green. Document the request shape with a
  matching `tests/fixtures/mcp/<tool>.request.json`.
- **A new field that crosses MCP and admin?** Add an assertion to one
  of the parity tests so drift on that field is caught.
- **Tests must be deterministic.** No live HTTP calls, no clock-sensitive
  assertions, no shared state between tests. The base bootstrap
  (`tests/bootstrap.php`) registers a tiny `config()` shim backed by
  `Tests\Support\TestConfig`; reset it in `setUp()` if your test mutates
  it.

If you can't run PHPUnit locally (e.g. PHP not installed), still write
the test -- CI will execute it and flag failures in the MR.

## Commit Hygiene

- One logical change per commit.
- Subject line in imperative ("Add foo", not "Added foo"), under 72
  characters.
- Body explains the *why* and any non-obvious decisions.
- Reference the issue it closes: `Fixes #123`.

## Pull Requests

- Branch from `main`.
- Include a short summary in the PR description: what changes, what the user
  should notice, and any migration steps.
- Confirm you've run a smoke check: `php -S` against a fresh install-site,
  `/`, `/admin/`, `/api/lead.php`.
- Keep PRs focused. Drive-by refactors in unrelated files belong in a
  separate PR.

## License

By contributing, you agree your contribution is licensed under the
[MIT License](LICENSE).
