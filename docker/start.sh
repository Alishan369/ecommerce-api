#!/bin/sh
# Container start. One image, three roles (CONTAINER_ROLE):
#   web (default) — migrates the database, caches config/routes/views, then serves the API with Apache
#   worker        — sends queued mail (order confirmations, status updates, password resets)
#   scheduler     — runs routes/console.php's schedule (expire unpaid orders, prune carts and tokens)
# With DB_CONNECTION=sqlite (Render) the database is storage/app/database.sqlite, next to the uploads in
# storage/app/public. Mount a persistent disk at storage/app to keep both; the first start creates the admin.
# RUN_SCHEDULER=true runs the scheduler inside the web container (a single-container setup like Render's).
set -e
cd /var/www/html

ROLE="${CONTAINER_ROLE:-web}"
fail() { echo "start.sh: $*" >&2; exit 1; }
# Same spellings Laravel's env() reads as true, so the script and the app agree on APP_DEBUG.
is_true() { case "$1" in [Tt][Rr][Uu][Ee] | "("[Tt][Rr][Uu][Ee]")" | 1) return 0 ;; *) return 1 ;; esac; }

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

# Refuse settings that would log everyone out on restart, leak stack traces or point emails at the API.
[ -n "$APP_KEY" ] || fail "APP_KEY is not set. Generate one with: php artisan key:generate --show"
if is_true "$APP_DEBUG"; then
    fail "APP_DEBUG=$APP_DEBUG shows stack traces to visitors. Set APP_DEBUG=false."
fi
[ -n "$FRONTEND_URL" ] || fail "FRONTEND_URL is not set (storefront URL for CORS and links in emails)."

SQLITE=false
case "${DB_CONNECTION:-sqlite}" in
    sqlite)
        [ "$ROLE" = "web" ] || fail "DB_CONNECTION is sqlite, but the $ROLE container can't reach the web container's database file. Use MySQL."
        # Always this file, whatever DB_DATABASE says (a MySQL database name copied from .env isn't a path).
        unset DB_URL
        export DB_CONNECTION=sqlite DB_DATABASE=/var/www/html/storage/app/database.sqlite
        SQLITE=true
        if ! grep -qs ' /var/www/html/storage/app ' /proc/mounts; then
            echo "start.sh: no persistent disk at /var/www/html/storage/app — products, orders and uploads" \
                "are lost whenever this container is replaced." >&2
        fi
        ;;
    *)
        # Inside a container 127.0.0.1 is the container itself, and it has no MySQL server.
        if [ -z "$DB_URL" ]; then
            case "${DB_HOST:-127.0.0.1}" in
                127.0.0.1 | localhost | ::1) fail "DB_HOST is '${DB_HOST:-<not set>}', but this container has no MySQL server." \
                    "Point DB_HOST at your database, or set DB_CONNECTION=sqlite." ;;
            esac
        fi
        ;;
esac

# Config, routes, events and views compiled once instead of on every request.
php artisan optimize

case "$ROLE" in
    web)
        # A new disk starts empty.
        mkdir -p storage/app/public storage/app/private

        if [ "$SQLITE" = "true" ] && [ ! -f "$DB_DATABASE" ]; then
            # First start: tables and the admin account (ADMIN_EMAIL / ADMIN_PASSWORD).
            # On failure drop the half-built file, so the next start creates the admin again instead of running without one.
            touch "$DB_DATABASE"
            php artisan migrate --force --seed || { rm -f "$DB_DATABASE" "$DB_DATABASE-wal" "$DB_DATABASE-shm"; exit 1; }
        elif [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
            # Only one web container should migrate: set RUN_MIGRATIONS=false on extra replicas.
            php artisan migrate --force
        fi

        php artisan storage:link --force || true
        chown -R www-data:www-data storage bootstrap/cache database

        if is_true "$RUN_SCHEDULER"; then
            # As www-data, like Apache: a root process would create database and cache files Apache can't write.
            setpriv --reuid=www-data --regid=www-data --init-groups php artisan schedule:work &
        fi

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
