# Backend — Laravel API

Setup, credentials, environment variables, Razorpay and the full API reference are in the main [README](../README.md).

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan storage:link
php artisan serve
```

## Production

Run the API on any PHP 8.2+ server (Apache or Nginx pointing at `public/`) with MySQL and HTTPS.

```bash
cp .env.production.example .env      # fill in every value; APP_KEY: php artisan key:generate --show
composer install --no-dev --optimize-autoloader
php artisan migrate --force --seed   # first deploy: tables + the admin account (ADMIN_EMAIL / ADMIN_PASSWORD)
php artisan storage:link
php artisan optimize
```

Keep `storage/app/public` (uploaded images) on persistent storage and back it up with the database.
Run the scheduler (cron: `* * * * * php artisan schedule:run`) and a queue worker
(`php artisan queue:work --tries=3 --max-time=3600` under systemd or Supervisor).
To deploy a new version: `git pull`, `composer install --no-dev`, `php artisan migrate --force`, `php artisan optimize`.

Seeding creates the admin account, then loads the catalogue saved by `php artisan catalog:export` (if any).
Razorpay needs `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET`; Cash on Delivery works without them.
For order emails set the `MAIL_*` values (see `.env.production.example`).
Point the frontend's `VITE_API_BASE_URL` at `https://<your-api-domain>/api/v1`.
