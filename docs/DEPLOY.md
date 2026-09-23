# Production deploy (Docker Compose)

One-origin stack: **Caddy** (TLS) → **nginx SPA** + **FrankenPHP API**, plus Postgres, queue worker, and scheduler.

## What you need

- Docker Desktop
- A domain pointing at the server (for real TLS), or `localhost` for a local smoke test
- A strong Postgres password and a Laravel `APP_KEY`

## 1. Create production env

From the repo root:

```powershell
copy .env.production.example .env.production
```

Edit `.env.production`:

1. Set `DB_PASSWORD` to a strong secret.
2. Generate `APP_KEY`:

```powershell
cd backend
php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
```

Paste as `APP_KEY=base64:...` in `.env.production`.

3. Set `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, and `SESSION_DOMAIN` to your domain (no scheme on session domain, e.g. `.example.com`).
4. Configure real SMTP under `MAIL_*`.
5. Optional: paste Web Push VAPID keys (`npx --yes web-push generate-vapid-keys`).

Also create a root `.env` used by Compose for `${DB_PASSWORD}` / `${DOMAIN}` interpolation:

```powershell
@"
DOMAIN=app.example.com
DB_DATABASE=gitlife
DB_USERNAME=gitlife
DB_PASSWORD=change-me-strong-password
"@ | Set-Content .env
```

## 2. Build and start

```powershell
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
```

First API boot runs migrations and caches config.

## 3. Smoke checklist

- [ ] `https://YOUR_DOMAIN/up` returns OK (API health)
- [ ] `https://YOUR_DOMAIN/` loads the PWA
- [ ] Register / log in works (cookies on same origin)
- [ ] Create a payment, mark paid
- [ ] `docker compose -f docker-compose.prod.yml logs -f scheduler` shows reminder ticks
- [ ] Mail arrives when a reminder fires (SMTP configured)
- [ ] Settings → Web push works over HTTPS

## 4. Day-2 ops

```powershell
# logs
docker compose -f docker-compose.prod.yml logs -f api queue scheduler

# migrate after pulling code
docker compose -f docker-compose.prod.yml up -d --build

# stop
docker compose -f docker-compose.prod.yml down
```

Database volume: `gitlife_pg_prod`. Back it up before destructive changes.

## Hardening notes

- `APP_DEBUG=false` in production
- Secrets only in `.env` / `.env.production` (never commit them)
- Same-origin `/api` and `/sanctum` avoid CORS cookie pain
- Queue + scheduler are separate containers so HTTP stays responsive

## Local smoke (no public DNS)

```powershell
# .env
DOMAIN=localhost
DB_PASSWORD=gitlife-local-prod

# .env.production APP_URL / FRONTEND_URL / SANCTUM_STATEFUL_DOMAINS = https://localhost
docker compose -f docker-compose.prod.yml up -d --build
```

Trust Caddy’s local certificate if the browser warns, or use HTTP-only only for development (not this compose file).
