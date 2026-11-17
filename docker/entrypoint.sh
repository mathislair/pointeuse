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

[ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
chown www-data:www-data "$DB_DATABASE" /data

php artisan package:discover --ansi >/dev/null
php artisan migrate --force --no-interaction
# Seeds restaurants / workers only when the database is empty (see DatabaseSeeder).
php artisan db:seed --force --no-interaction
php artisan optimize

exec "$@"
