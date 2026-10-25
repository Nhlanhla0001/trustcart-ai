# Deployment notes

The hackathon requires a working demo URL that stays available through the end of judging
(15 December 2026) and PayPal sandbox credentials configured on it. Nothing has been deployed yet.

## What the app needs

| Requirement                                                      | Notes                                                                                 |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| PHP 8.3+ with `pdo_sqlite`, `mbstring`, `curl`, `openssl`, `zip` | Laravel 13                                                                            |
| A persistent filesystem                                          | SQLite database + session/cache tables if you keep the defaults                       |
| `APP_KEY`, `APP_URL`                                             | `php artisan key:generate` on first deploy                                            |
| `npm run build` output                                           | `public/build` must be present; most PaaS build steps run it                          |
| PayPal sandbox credentials                                       | `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, sandbox base URL                          |
| AI key (optional)                                                | Without one, the deterministic parser runs and the record says so                     |
| Outbound HTTPS                                                   | `api-m.sandbox.paypal.com`, `generativelanguage.googleapis.com` or `api.deepseek.com` |

## Option A — a VPS / Laravel Forge / Ploi

1. Point a domain at the server.
2. Deploy the repository, set the document root to `public/`.
3. `composer install --no-dev --optimize-autoloader`
4. `npm ci && npm run build`
5. Copy `.env.example` to `.env`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, and the
   PayPal/AI keys. Run `php artisan key:generate`.
6. `touch database/database.sqlite && php artisan migrate --seed --force`
7. `php artisan config:cache route:cache view:cache`
8. Enable HTTPS. The app sends `X-Frame-Options`, `nosniff`, referrer and permissions headers, but
   does not configure HSTS.
9. Set `TRUSTCART_DEMO_MODE=false` if you do not want the public reset control.

## Option B — a PaaS (Render, Railway, Fly.io)

The repository has no Dockerfile. A typical build:

```
Build:  composer install --no-dev --optimize-autoloader && npm ci && npm run build
Start:  php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT
```

`php artisan serve` is not a production server. Prefer php-fpm + nginx, or a Laravel-specific
runtime. If the platform uses an ephemeral filesystem, SQLite will be lost on every deploy —
attach a persistent disk or switch `DB_CONNECTION` to MySQL/Postgres and update the session/cache
drivers accordingly.

## Option C — Laravel Cloud

Laravel Cloud is the lowest-friction option for this stack: connect the repository, add the
environment variables, provision a database, and let the platform run migrations. If you have the
`cloud` CLI configured, use the `deploying-to-cloud` workflow. SQLite is fine for a demo, but a
managed database is safer for a public URL that must stay up through judging.

## Post-deploy checklist

- [ ] `https://your-domain/up` returns 200
- [ ] `/` renders with Archivo loaded and the live specimen populated
- [ ] `php artisan migrate --seed --force` has created eight products
- [ ] PayPal sandbox credentials are present; the status strip says "PayPal sandbox connected"
- [ ] One real sandbox payment completes and the result page shows `COMPLETED` + capture ID
- [ ] A cancel at PayPal produces `cancelled` and no paid record
- [ ] The audit trail works in a fresh incognito window
- [ ] `APP_DEBUG=false`; no stack traces on errors
- [ ] No secrets in the repository or client bundle (`grep` the built assets for the secret)
- [ ] The URL stays reachable until 15 December 2026
- [ ] Consider disabling demo mode so the public reset control is unavailable

## Secrets

Never commit `.env`. Only the PayPal **client ID** is public in PayPal's own model; this app does
not even send that to the browser because it uses the redirect flow rather than the JS SDK. The
secret and AI keys are server-side only.
