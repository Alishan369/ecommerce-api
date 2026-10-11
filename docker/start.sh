#!/bin/sh
# Container start. One image, three roles (CONTAINER_ROLE):
#   web (default) — migrates the database, caches config/routes/views, then serves the API with Apache
#   worker        — sends queued mail (order confirmations, status updates, password resets)
#   scheduler     — runs routes/console.php's schedule (expire unpaid orders, prune carts and tokens)
# With DEMO_MODE=true the web role instead rebuilds a throwaway SQLite demo store on every start.
set -e
cd /var/www/html

ROLE="${CONTAINER_ROLE:-web}"
fail() { echo "start.sh: $*" >&2; exit 1; }

case "$ROLE" in
    web | worker | scheduler) ;;
    *) fail "unknown CONTAINER_ROLE '$ROLE' (expected web, worker or scheduler)" ;;
esac

# Render provides the public URL.
export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"

if [ "$DEMO_MODE" = "true" ]; then
    # A per-boot APP_KEY is fine: the demo's data resets on every start anyway.
    if [ -z "$APP_KEY" ]; then
        APP_KEY="$(php artisan key:generate --show --no-ansi)"
        export APP_KEY
    fi
else
    # A real store: refuse settings that would log everyone out on restart, leak stack traces,
    # keep orders in a file that disappears with the container, or point emails at the API.
    [ -n "$APP_KEY" ] || fail "APP_KEY is not set. Generate one with: php artisan key:generate --show"
    case "$APP_DEBUG" in
        [Tt][Rr][Uu][Ee] | 1 | "(true)") fail "APP_DEBUG=$APP_DEBUG shows stack traces to visitors. Set APP_DEBUG=false." ;;
    esac
    case "${DB_CONNECTION:-sqlite}" in
        sqlite) fail "DB_CONNECTION is sqlite: the database file would be lost with the container. Use MySQL." ;;
    esac
    [ -n "$FRONTEND_URL" ] || fail "FRONTEND_URL is not set (storefront URL for CORS and links in emails)."
fi

# Config, routes, events and views compiled once instead of on every request.
php artisan optimize

case "$ROLE" in
    web)
        if [ "$DEMO_MODE" = "true" ]; then
            # Admin, catalogue, demo coupons, customers and 30 days of orders.
            rm -f database/database.sqlite
            touch database/database.sqlite
            php artisan migrate --force --seed
        elif [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
            # Only one web container should migrate: set RUN_MIGRATIONS=false on extra replicas.
            php artisan migrate --force
        fi

        php artisan storage:link --force || true
        chown -R www-data:www-data storage bootstrap/cache database

        exec apache2-foreground
        ;;
    worker)
        # --max-time: exit hourly so the restart policy hands out a fresh process (no slow memory creep).
        exec php artisan queue:work --tries=3 --backoff=30 --max-time=3600
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
esac
