#!/bin/sh
# Entrypoint script for a Bird CMS site container

# Render nginx vhost from template. APP_DOMAIN comes from .env / docker-compose
# environment; defaults to "localhost" so the container works without env tuning.
echo "Rendering nginx vhost (domain=${APP_DOMAIN:-localhost})..."
sed -e "s|{{DOMAIN}}|${APP_DOMAIN:-localhost}|g" \
    /etc/nginx/http.d/default.conf.template \
    > /etc/nginx/http.d/default.conf
echo "Vhost rendered."

# Make /uploads/ web-accessible. uploads/ lives at the site root (next to
# content/) for clarity, but nginx serves from public/, so we symlink.
# Idempotent: -fn replaces any existing symlink in place.
echo "Linking uploads/ into public/..."
ln -sfn /var/www/html/uploads /var/www/html/public/uploads

# Make /content/ web-accessible the same way. Bundle-format articles store
# their hero/inline images alongside index.md (e.g.
# content/articles/blog/<slug>/hero.webp), and themes reference them as
# /content/articles/.../hero.webp. Without this symlink nginx's static
# location for image extensions tries to find them under public/ and 404s
# before the request can fall through to PHP.
echo "Linking content/ into public/..."
ln -sfn /var/www/html/content /var/www/html/public/content

# Fix permissions on writable directories
echo "Fixing permissions..."
for dir in \
    /var/www/html/content/articles \
    /var/www/html/public/assets/hero \
    /var/www/html/worklog \
    /var/www/html/storage
do
    if [ -d "$dir" ]; then
        chown -R www-data:www-data "$dir"
        find "$dir" -type f -exec chmod 644 {} \;
        find "$dir" -type d -exec chmod 755 {} \;
    fi
done
echo "Permissions fixed."

# Generate nginx blacklist where nginx.conf expects it (storage path).
# Without this, fresh installs fail nginx -t with "open() blacklist.conf failed".
echo "Generating IP blacklist..."
mkdir -p /var/www/html/storage/analytics
if [ -f /var/www/html/storage/analytics/blacklist.txt ]; then
    php /var/www/html/scripts/generate-blacklist-conf.php --force
fi
if [ ! -f /var/www/html/storage/analytics/blacklist.conf ]; then
    echo "# No blacklist configured" > /var/www/html/storage/analytics/blacklist.conf
fi
echo "Blacklist configured."

# Setup analytics cron jobs (overwrite to prevent duplicates on restart)
echo "Setting up cron jobs..."
cat > /etc/crontabs/root << 'CRON'
# do daily/weekly/monthly maintenance
# min	hour	day	month	weekday	command
*/15	*	*	*	*	run-parts /etc/periodic/15min
0	*	*	*	*	run-parts /etc/periodic/hourly
0	2	*	*	*	run-parts /etc/periodic/daily
0	3	*	*	6	run-parts /etc/periodic/weekly
0	5	1	*	*	run-parts /etc/periodic/monthly

# Analytics - parse access log every 5 minutes
*/5 * * * * php /var/www/html/scripts/parse-access-log.php >> /var/log/nginx/cron-analytics.log 2>&1
# Auto-blacklist malicious IPs every 15 minutes
*/15 * * * * php /var/www/html/scripts/auto-blacklist.php >> /var/log/nginx/cron-autoblock.log 2>&1; chmod 644 /var/log/nginx/cron-autoblock.log
# Update nginx blacklist if changed (every hour)
30 * * * * php /var/www/html/scripts/generate-blacklist-conf.php --apply >> /var/log/nginx/cron-blacklist.log 2>&1; chmod 644 /var/log/nginx/cron-blacklist.log
CRON
echo "Cron jobs configured."

# Start supervisord
exec /usr/bin/supervisord -c /etc/supervisord.conf
