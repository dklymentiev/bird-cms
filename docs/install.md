# Install

Three commands plus a browser wizard turn a fresh `git clone` into a
running site.

## Requirements

- Docker (Desktop on macOS/Windows, any container runtime on Linux).
- Port 8080 free (override in `docker-compose.yml` if not).

No PHP, no Composer, no database. The image ships PHP 8.3, nginx,
supervisor, GD, intl, pdo_sqlite, mbstring, OpenSSL, curl, fileinfo.

## Quick start

```bash
git clone https://gitlab.com/codimcc/bird-cms.git
cd bird-cms
docker compose up -d
```

Open <http://localhost:8080>. The pre-bootstrap install guard redirects
to `/install` because `storage/installed.lock` doesn't exist yet.

## Wizard

### Screen 1 — System check

Audits PHP version (>= 8.1), required extensions, and writable
directories (`storage/`, `content/`, `uploads/`, `config/`). Missing
extensions or unwritable dirs block **Continue** until fixed.

### Screen 2 — Site identity

| Field | Default | Notes |
|---|---|---|
| Site name | (empty) | Used as `<title>` and OG meta. |
| Site URL | auto from request host | Drives `APP_DOMAIN`. |
| Description | (empty) | Drives meta description. |
| Admin email | (empty) | Stored in `.env`; reset-by-email not implemented. |
| Admin username | `admin` | 3–32 chars, alphanumeric + `-_`. |
| Admin password | (empty) | Min 8 chars, letters + digits. Bcrypted — plaintext never touches disk. |
| Timezone | system default | Any `DateTimeZone::listIdentifiers()` value. |
| Language | `en` | English only today. |

CSRF-protected. Server-side validation; if anything fails, the form
re-renders with errors and the password is intentionally not refilled.

### Screen 3 — Finish

One checkbox: **Install demo content** copies `examples/seed/*` (3
articles, 2 pages, 3 categories, 4 SVG heroes). Clicking
**Install Bird CMS** runs atomically:

1. Generate `APP_KEY` (32 random bytes, signs HMAC preview tokens).
2. Bcrypt the admin password.
3. Write `.env` (substituting fields; auto-derives `APP_DOMAIN`,
   `ADMIN_ALLOWED_IPS` from your client IP + docker bridge,
   `TRUSTED_PROXIES`).
4. Write `config/app.php`.
5. Seed demo content if requested.
6. Write `storage/installed.lock` — disables the wizard for all
   subsequent requests.

Each file uses `.tmp` + rename so a crash mid-install leaves no
half-written `.env`. Rate-limited: 5 finish attempts per minute per IP.

### Screen 4 — Success

Confirms version, lists seeded files, shows **Visit site** and **Go to
admin** buttons. Sign in with the credentials from screen 2. Cookie name
is `dim_admin`.

## What the wizard doesn't do

- No outbound network calls (no telemetry, no license check, no CDN
  ping).
- No database setup.
- No restart needed — bootstrap loads the new `.env` on the next
  request.

## Re-running the wizard

Idempotent. To reset (testing, or before real go-live):

```bash
rm storage/installed.lock .env config/app.php config/categories.php
docker compose restart
```

## Production install

For multi-site setups where one engine serves several sites with atomic
upgrades, use `scripts/install-site.sh`:

```bash
./scripts/install-site.sh /var/www/example.com example.com 3.1.0
```

It scaffolds a per-site tree (`config/`, `content/`, `storage/`,
`uploads/`, `.env`) with an `engine -> versions/X.Y.Z` symlink. Edit
the generated default-deny `.env`, point nginx at `<site>/public/`,
reload.

### Upgrade

```bash
./scripts/update.sh /var/www/example.com 3.1.1
```

Verifies checksum, extracts to `versions/<new>/`, then
`ln -sfn versions/<new> engine`. The old version stays in
`versions/` for one-command rollback.

### Rollback

```bash
./scripts/rollback.sh /var/www/example.com           # auto-previous
./scripts/rollback.sh /var/www/example.com 3.0.0     # pinned
```

`ln -sfn versions/<old> engine`. No data loss — `content/`,
`storage/`, `uploads/` live outside the engine.

### Backup

Per site, back up: `content/`, `uploads/`, `config/app.php`, `.env`,
`storage/analytics/*.db`, `storage/subscribers/`, `storage/leads/`.
Skip `versions/`, `engine`, `storage/cache/`, `storage/logs/`.

```bash
tar czf bird-backup-$(date +%F).tar.gz \
    -C "$SITE" \
    --exclude='./storage/cache' \
    --exclude='./storage/logs' \
    --exclude='./versions' \
    --exclude='./engine' \
    .env config content uploads storage
```

Run a monthly restore drill on a non-prod VM. Untested backups are
hope, not backups.

## Common install errors

**Wizard returns 502 on first load.** Container booting. Wait 5s and
reload — entrypoint.sh renders nginx vhost on each container start.

**Step 1 fails on PHP extension.** Custom Docker image without gd/intl.
Rebuild against upstream Dockerfile, or apt-install the missing
package.

**`/install/finish` returns 429.** Rate limit. Wait one minute.

**Password rejected as "Mix letters and digits".** Add at least one of
each character class.

**`/admin/login` returns 404.** Your client IP isn't in
`ADMIN_ALLOWED_IPS`. Edit `.env`, add your IP or CIDR, restart.

**`Uncaught RuntimeException: APP_KEY is missing`.** `.env` lost or
never set. Restore from backup, or regenerate via
`php -r 'echo bin2hex(random_bytes(32));'`.

**`Class "..." not found` after upgrade.** Partial extraction.
`./scripts/rollback.sh <site>`.
