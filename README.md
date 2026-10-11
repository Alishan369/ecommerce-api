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
Without `DEMO_MODE=true` the container refuses to start without `APP_KEY`, `FRONTEND_URL` and a MySQL connection, or with `APP_DEBUG=true`.

Backups: `docker compose -f docker-compose.prod.yml --env-file .env.production exec mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' > backup.sql`,
plus the `uploads` volume (product and category images).

## Free live demo (Render)

`render.yaml` + `Dockerfile` run the API on Render's free plan with SQLite and `DEMO_MODE=true`.

1. [dashboard.render.com](https://dashboard.render.com) → **New → Blueprint** → pick this repo → **Apply**.
2. Wait for the first deploy, then open `https://<service>.onrender.com/up` — it should say the app is up.
3. In the frontend's Vercel project set `VITE_API_BASE_URL=https://<service>.onrender.com/api/v1` and redeploy.

Demo logins: `admin@example.com` / `Admin@12345` and `customer@example.com` / `Customer@12345`.

Free instances sleep after ~15 minutes without traffic; the first request then takes about a minute.
Every start re-seeds the database, so the demo resets itself. Deletes are blocked; image uploads work
while `DEMO_ALLOW_UPLOADS=true` (set it to `false` to block them too). Razorpay is off unless you add
`RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` (test keys) in the Render dashboard — Cash on Delivery works without them.
