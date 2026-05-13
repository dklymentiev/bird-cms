FROM php:8.3-fpm-alpine

# Install nginx, supervisor, and PHP extensions.
# GD requires libpng + libjpeg + freetype dev headers; without them the build
# falls back to a stub GD that fails fileinfo checks downstream. See SystemCheck.
RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    curl \
    git \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    sqlite-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    logrotate \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl pdo_sqlite gd \
    && docker-php-ext-enable opcache

# Configure PHP-FPM
COPY templates/docker/unified/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf

# Create required directories
RUN mkdir -p /var/www/html/storage/cache \
    /var/www/html/storage/logs \
    /var/log/nginx \
    /var/run/nginx \
    /run/php \
    && chown -R nginx:nginx /var/www/html /var/log/nginx

# Copy configurations. nginx.conf is rendered from the template at runtime
# (entrypoint.sh substitutes {{DOMAIN}} from $APP_DOMAIN).
COPY templates/docker/unified/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY templates/docker/unified/supervisord.conf /etc/supervisord.conf
COPY templates/docker/unified/logrotate.conf /etc/logrotate.d/nginx
COPY templates/docker/unified/php-opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY templates/docker/unified/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
