#!/bin/sh
# Container start for the free demo: fresh SQLite database with demo data, then Apache.
set -e
cd /var/www/html

# Render provides the public URL. A per-boot APP_KEY is fine: the data resets on every start anyway.
export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"
if [ -z "$APP_KEY" ]; then
    APP_KEY="$(php artisan key:generate --show --no-ansi)"
    export APP_KEY
fi

php artisan config:cache
php artisan route:cache

# Admin, catalogue, demo coupons, customers and 30 days of orders (DEMO_MODE=true).
rm -f database/database.sqlite
touch database/database.sqlite
php artisan migrate --force --seed

php artisan storage:link --force || true
chown -R www-data:www-data storage bootstrap/cache database

exec apache2-foreground
