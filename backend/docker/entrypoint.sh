#!/bin/sh
# Container start: bind to the host's $PORT, check the key, migrate, optionally seed, cache config, start Apache.
set -e

PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html

if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY is not set. Run 'php artisan key:generate --show' locally and add it as an environment variable." >&2
    exit 1
fi
if [ -z "${DB_URL}" ]; then
    echo "DB_URL is not set. Add the Neon DIRECT connection string as an environment variable." >&2
    exit 1
fi

php artisan config:clear >/dev/null 2>&1 || true
php artisan package:discover --ansi >/dev/null 2>&1 || true

# Creates missing tables only; existing data is untouched.
php artisan migrate --force

# One-off: set RUN_SEED=true for the first deploy (or to reset demo data), then set it back to false.
if [ "${RUN_SEED}" = "true" ]; then
    php artisan db:seed --force
fi

# routes/web.php has a closure, so only the config is cached (not the routes).
php artisan config:cache

exec apache2-foreground
