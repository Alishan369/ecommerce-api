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
# Same spellings Laravel's env() reads as true, so the script and the app agree on DEMO_MODE/APP_DEBUG.
is_true() { case "$1" in [Tt][Rr][Uu][Ee] | "("[Tt][Rr][Uu][Ee]")" | 1) return 0 ;; *) return 1 ;; esac; }
DEMO=false
if is_true "$DEMO_MODE"; then DEMO=true; fi

case "$ROLE" in
    web | worker | scheduler) ;;
    *) fail "unknown CONTAINER_ROLE '$ROLE' (expected web, worker or scheduler)" ;;
esac

# Render provides the public URL. Image links are built from APP_URL, so a localhost value
# (copied from a local .env) would point every image at the visitor's own computer.
export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"
if [ -n "$RENDER_EXTERNAL_URL" ]; then
    case "$APP_URL" in
        *://localhost* | *://127.0.0.1*) export APP_URL="$RENDER_EXTERNAL_URL" ;;
    esac
fi

if [ "$DEMO" = "true" ]; then
    # The demo always uses the container's own SQLite file, whatever DB_* settings the host passes in,
    # so stray MySQL settings can't break it and demo data is never seeded into a real store.
    unset DB_URL
    export DB_CONNECTION=sqlite DB_DATABASE=/var/www/html/database/database.sqlite
    # A per-boot APP_KEY is fine: the demo's data resets on every start anyway.
    if [ -z "$APP_KEY" ]; then
        APP_KEY="$(php artisan key:generate --show --no-ansi)"
        export APP_KEY
    fi
else
    # A real store: refuse settings that would log everyone out on restart, leak stack traces,
    # keep orders in a file that disappears with the container, or point emails at the API.
    [ -n "$APP_KEY" ] || fail "APP_KEY is not set. Generate one with: php artisan key:generate --show"
    if is_true "$APP_DEBUG"; then
        fail "APP_DEBUG=$APP_DEBUG shows stack traces to visitors. Set APP_DEBUG=false."
    fi
    case "${DB_CONNECTION:-sqlite}" in
        sqlite) fail "DB_CONNECTION is sqlite: the database file would be lost with the container. Use MySQL," \
            "or set DEMO_MODE=true for a throwaway demo store (DEMO_MODE is currently '${DEMO_MODE-<not set>}')." ;;
    esac
    # Inside a container 127.0.0.1 is the container itself, and it has no MySQL server.
    if [ -z "$DB_URL" ]; then
        case "${DB_HOST:-127.0.0.1}" in
            127.0.0.1 | localhost | ::1) fail "DB_HOST is '${DB_HOST:-<not set>}', but this container has no MySQL server. Point DB_HOST" \
                "at your database, or set DEMO_MODE=true for a throwaway demo store (DEMO_MODE is currently '${DEMO_MODE-<not set>}')." ;;
        esac
    fi
    [ -n "$FRONTEND_URL" ] || fail "FRONTEND_URL is not set (storefront URL for CORS and links in emails)."
fi

# Config, routes, events and views compiled once instead of on every request.
php artisan optimize

case "$ROLE" in
    web)
        if [ "$DEMO" = "true" ]; then
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
