# Backend — Laravel API

Setup, credentials, environment variables, Razorpay and the full API reference are in the main [README](../README.md).

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan storage:link
php artisan serve
```

## Production (Docker)

`docker-compose.prod.yml` runs the whole backend on one server: Caddy (automatic HTTPS), the API,
a queue worker (sends order and password-reset emails), the scheduler (expires unpaid orders,
releasing their stock) and MySQL. Data lives in the `mysql-data` and `uploads` volumes.

```bash
cp .env.production.example .env.production   # fill in every value; APP_KEY: php artisan key:generate --show
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
docker compose -f docker-compose.prod.yml --env-file .env.production exec app php artisan db:seed --force   # first deploy only
```

Point `API_DOMAIN`'s DNS at the server first so Caddy can get its certificate, then check `https://<API_DOMAIN>/api/v1/health`.
To deploy a new version: `git pull`, then the same `up -d --build`. Migrations run when the `app` container starts.

The image runs as one of three roles (`CONTAINER_ROLE`, see `docker/start.sh`): `web` (default), `worker`, `scheduler`.
On another platform, run the same image three times with those roles, a managed MySQL and a persistent disk at
`storage/app/public`. If you run several `web` containers, set `RUN_MIGRATIONS=false` on all but one.
The container refuses to start without `APP_KEY` and `FRONTEND_URL`, or with `APP_DEBUG=true`. Without `DB_CONNECTION=mysql`
it keeps the database in `storage/app/database.sqlite` (the Render setup below); mount a persistent disk at `storage/app`.

Seeding creates only the admin account (`ADMIN_EMAIL` / `ADMIN_PASSWORD`). Collections, products, banners, photos
and coupons are added in the admin panel.

Backups: `docker compose -f docker-compose.prod.yml --env-file .env.production exec mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' > backup.sql`,
plus the `uploads` volume (product and category images).

## Render

`render.yaml` + `Dockerfile` run the API as one container on a Starter instance with a 1 GB persistent disk at
`storage/app`. The SQLite database and uploaded images live on the disk, so they survive restarts and redeploys,
and Render snapshots the disk daily. The scheduler runs inside the same container (`RUN_SCHEDULER=true`) and
emails are sent during the request (`QUEUE_CONNECTION=sync`).

1. [dashboard.render.com](https://dashboard.render.com) → **New → Blueprint** → pick this repo. Render asks for
   `APP_KEY` (`php artisan key:generate --show`), `FRONTEND_URL` (your Vercel URL), `ADMIN_EMAIL` and
   `ADMIN_PASSWORD` (12+ characters) → **Apply**.
2. Wait for the first deploy, then open `https://<service>.onrender.com/up` — it should say the app is up.
3. In the frontend's Vercel project set `VITE_API_BASE_URL=https://<service>.onrender.com/api/v1` and redeploy.
4. Sign in to the storefront with the admin account and add your collections, products and banners.

The free plan has no disks: without one the container warns at startup and everything is lost on every restart.
Razorpay is off unless you add `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` in the Render dashboard — Cash on
Delivery works without them. For order emails set the `MAIL_*` values (see `.env.production.example`).
