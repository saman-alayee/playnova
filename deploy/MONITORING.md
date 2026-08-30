# PlayNova — Production & Monitoring

## Redis + Queue

### 1. Start Redis

```bash
docker compose -f deploy/docker-compose.redis.yml up -d
```

### 2. Configure `PlayNova/.env`

Copy `deploy/.env.production.example` and set:

```env
APP_ENV=production
APP_DEBUG=false

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CLIENT=phpredis
```

### 3. Bootstrap

```bash
bash deploy/setup-production.sh
```

Or manually:

```bash
cd PlayNova
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:work redis --tries=3
```

### 4. Supervisor

Copy `deploy/supervisor-playnova-worker.conf` to `/etc/supervisor/conf.d/` and run:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start playnova-worker:*
```

### 5. Health check

```bash
curl https://api.your-domain.com/api/v1/health
```

Expected when healthy:

```json
{
  "success": true,
  "status": "ok",
  "checks": {
    "database": { "ok": true },
    "cache": { "ok": true, "driver": "redis" },
    "queue": { "ok": true, "driver": "redis" },
    "redis": { "ok": true }
  }
}
```

---

## Sentry

### 1. Create projects

In [sentry.io](https://sentry.io):

- **Laravel** project → copy DSN → `SENTRY_LARAVEL_DSN`
- **Nuxt/JavaScript** project → copy DSN → `NUXT_PUBLIC_SENTRY_DSN`

### 2. Backend `.env`

```env
SENTRY_LARAVEL_DSN=https://xxx@oXXX.ingest.sentry.io/XXX
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_ENVIRONMENT=production
SENTRY_RELEASE=playnova@1.0.0
```

Then:

```bash
cd PlayNova
composer install
php artisan config:cache
```

### 3. Frontend `.env`

```env
NUXT_PUBLIC_SENTRY_DSN=https://xxx@oXXX.ingest.sentry.io/XXX
NUXT_PUBLIC_SENTRY_TRACES_SAMPLE_RATE=0.1
```

Then rebuild:

```bash
cd frontend
npm run build
```

### 4. Verify

Trigger a test error in production (or staging) and confirm it appears in Sentry dashboard.

- Backend: unhandled exceptions auto-report via `Handler`
- Frontend: Vue errors + API 5xx via `useApi`

---

## Cron (required)

```cron
* * * * * cd /var/www/playnova/PlayNova && php artisan schedule:run >> /dev/null 2>&1
```
