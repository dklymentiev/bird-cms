# Troubleshooting

Common problems and how to fix them. If your issue is not here and the
error message is not actionable, open an issue on
[GitLab](https://gitlab.com/codimcc/bird-cms/-/issues) with
`cat VERSION`, the install method, and a redacted log snippet.

## Install

### "Port 8080 is already in use"

Something else (another `docker compose`, a local nginx, a previous Bird
CMS instance) holds the port. Either stop it or change Bird CMS's port:

```bash
# Find the process
docker ps --filter "publish=8080"
lsof -i :8080            # macOS / Linux
netstat -ano | find "8080"   # Windows
```

To change Bird CMS's port without touching the canonical compose file,
add a `docker-compose.override.yml`:

```yaml
services:
  bird-cms:
    ports:
      - "8088:80"
```

Then `docker compose up -d` and visit <http://localhost:8088>.

### Wizard says a PHP extension is missing

The unified Docker image already ships every required extension. If the
check still fails, you are running on bare PHP-FPM:

```bash
# Debian/Ubuntu (replace 8.3 with your version)
sudo apt install php8.3-mbstring php8.3-intl php8.3-curl \
                  php8.3-gd php8.3-sqlite3 php8.3-fileinfo

# Alpine
apk add php83-mbstring php83-intl php83-curl php83-gd \
        php83-sqlite3 php83-fileinfo php83-openssl
```

Restart PHP-FPM and re-check.

### "Wizard refuses to start: storage/installed.lock exists"

You already have an installed site at this path. Either:

- visit `/admin/login` to manage the existing site, or
- delete `storage/installed.lock` to re-run the wizard. Doing so does
  **not** wipe content -- only the gate is removed. To start truly fresh,
  also clear `.env`, `config/app.php`, and content directories.

### "Failed to write .env: permission denied"

The container's web user (`www-data` in the unified image) needs write
access to the project root for the wizard to write `.env`,
`config/app.php`, and `storage/installed.lock`.

```bash
# From the host
sudo chown -R 33:33 .            # 33 = www-data uid in the image
chmod -R u+w storage config
```

If you bind-mount the repo into the container as your host user, run
the wizard once, then chown back.

### Container starts but `localhost:8080` returns 502

php-fpm isn't running yet, or the unix socket is missing. Check:

```bash
docker compose logs bird-cms --tail 100
```

Most common cause: the image was built before you added a PHP file with
a syntax error. Fix the syntax, restart container.

### Wizard flow advances but homepage shows raw HTML / unstyled

Tailwind CDN script blocked or empty. Check `.env` for
`TAILWIND_CDN_URL`. Default is `https://cdn.tailwindcss.com`. If your
network blocks `cdn.tailwindcss.com`, mirror it locally and set
`TAILWIND_CDN_URL=https://your-mirror.example.com/tailwind.js`.

## Runtime

### Admin shows 404 instead of the login page

`ADMIN_ALLOWED_IPS` defaults to `127.0.0.1`. If you reach the admin from
any other IP (LAN, VPN, reverse proxy), the engine returns the themed
404 page on purpose -- the admin is hidden by design.

```ini
# .env
ADMIN_ALLOWED_IPS=127.0.0.1,10.0.0.0/24,2001:db8::/32
```

If you sit behind a reverse proxy, also set `TRUSTED_PROXIES` to the
proxy's IP so `X-Forwarded-For` is honored.

### "Boot failed: APP_KEY is missing or default"

`bootstrap.php` refuses to start when `APP_KEY` is empty or set to a
known placeholder. The wizard sets a fresh one; if you copied an
example `.env` from elsewhere, regenerate:

```bash
php -r 'echo bin2hex(random_bytes(32)) . "\n";'
```

Paste it into `.env` as `APP_KEY=...` and restart.

### Health endpoint returns 503

`GET /health` returns 503 when the VERSION file is missing or required
PHP extensions are not loaded. The JSON body lists what is missing:

```json
{
  "status": "degraded",
  "version": null,
  "missing_extensions": ["intl"]
}
```

Fix the listed extensions, redeploy, retry.

### Drafts disappear after editing in two browser tabs

Bird CMS does not yet auto-save in the article editor. Two open tabs
will overwrite each other on the second save. Track the work-in-progress
in only one tab until auto-save lands (planned).

## Operations

### Backup before upgrade

```bash
./scripts/backup.sh /var/www/your-site /backups
# -> /backups/your-site-2026-04-29-1530.tar.gz
```

The script tarballs `content/`, `config/`, `storage/`, `uploads/`, and
`.env`. Restore by extracting the tarball into the same path and
running the engine again -- the lock file inside `storage/` keeps the
wizard from re-running.

### Rolling back to a previous engine version

The versioned-symlink layout makes rollback a one-command swap:

```bash
cd /var/www/your-site
ln -sfn versions/2.0.0-beta.2 engine
docker compose restart
```

### "Out of date" badge after `git pull`

The frontend caches its own brand CSS hash off the file mtime. If
styles look stale after a pull, hard-reload the browser (Cmd+Shift+R /
Ctrl+F5). If still stale, restart the container; the unified image
clears opcache on boot.

### Logs

```bash
# nginx access + error
docker compose logs bird-cms --tail 200 --follow
ls storage/logs/nginx/

# PHP errors
docker compose exec bird-cms tail -f /var/log/php-fpm.log
```

## Getting more help

- Read the relevant section of [`docs/`](.) -- vision, spec,
  architecture, operations.
- Search existing issues on [GitLab](https://gitlab.com/codimcc/bird-cms/-/issues).
- Open a `[q]` issue with `cat VERSION` and a redacted log snippet.
