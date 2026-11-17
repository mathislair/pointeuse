# ---------- 1. Front-end build (Svelte + Tailwind via Vite) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js svelte.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------- 2. PHP dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

# ---------- 3. Runtime: Apache + PHP 8.4, SQLite ----------
FROM php:8.4-apache
ENV APP_NAME=Pointeuse \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_TIMEZONE=Europe/Paris \
    APP_LOCALE=fr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    LOG_CHANNEL=stderr

RUN apt-get update \
 && apt-get install -y --no-install-recommends libsqlite3-dev libzip-dev unzip \
 && docker-php-ext-install pdo_sqlite opcache \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'opcache.enable=1\nopcache.validate_timestamps=0\nupload_max_filesize=4M\npost_max_size=4M\n' > "$PHP_INI_DIR/conf.d/pointeuse.ini"

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
 && mkdir -p /data storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache /data

VOLUME ["/data"]
EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
