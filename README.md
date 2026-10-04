# Backend — Laravel API

Setup, credentials, environment variables, Razorpay and the full API reference are in the main [README](../README.md).

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan storage:link
php artisan serve
```

## Free live demo (Render)

`render.yaml` + `Dockerfile` run the API on Render's free plan with SQLite and `DEMO_MODE=true`.

1. [dashboard.render.com](https://dashboard.render.com) → **New → Blueprint** → pick this repo → **Apply**.
2. Wait for the first deploy, then open `https://<service>.onrender.com/up` — it should say the app is up.
3. In the frontend's Vercel project set `VITE_API_BASE_URL=https://<service>.onrender.com/api/v1` and redeploy.

Demo logins: `admin@example.com` / `Admin@12345` and `customer@example.com` / `Customer@12345`.

Free instances sleep after ~15 minutes without traffic; the first request then takes about a minute.
Every start re-seeds the database, so the demo resets itself. Razorpay is off unless you add
`RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` (test keys) in the Render dashboard — Cash on Delivery works without them.
