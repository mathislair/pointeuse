#!/bin/sh
set -e
cd /var/www/html

# The application key is generated once and kept in the data volume so that
# it survives container rebuilds.
if [ -z "$APP_KEY" ]; then
    if [ ! -f /data/app_key ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > /data/app_key
    fi
    export APP_KEY="$(cat /data/app_key)"
fi

FRESH_DB=0
if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
    FRESH_DB=1
fi
chown www-data:www-data "$DB_DATABASE" /data

php artisan package:discover --ansi >/dev/null
# A consistent copy of the database before every migration / restart (kept in /data/backups).
[ "$FRESH_DB" = 1 ] || php artisan pointeuse:backup --no-interaction || true
php artisan migrate --force --no-interaction
# Demo restaurants / workers only on the very first boot (or with SEED_DEMO=1):
# deleting them later must not bring them back at the next restart.
if [ "$FRESH_DB" = 1 ] || [ "${SEED_DEMO:-0}" = 1 ]; then
    php artisan db:seed --force --no-interaction
fi
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache /data

exec "$@"
