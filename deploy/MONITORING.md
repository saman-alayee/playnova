# PlayNova — Production & Monitoring (بدون Docker)

## Redis (نصب native روی سرور)

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y redis-server php-redis
sudo systemctl enable --now redis-server
redis-cli ping   # باید PONG برگرداند
```

## تنظیم `PlayNova/.env`

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

## Deploy کامل

```bash
cd /home/playnnu/domains/playnova.ir/public_html
bash deploy/deploy.sh
```

یا مرحله‌به‌مرحله:

```bash
git pull origin main
bash deploy/setup-production.sh
```

## Supervisor (Queue Worker)

```bash
sudo cp deploy/supervisor-playnova-worker.conf /etc/supervisor/conf.d/playnova-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start playnova-worker:*
```

## Sentry

```env
# PlayNova/.env
SENTRY_LARAVEL_DSN=https://...

# frontend/.env
NUXT_PUBLIC_SENTRY_DSN=https://...
```

بعد از تغییر env:

```bash
cd PlayNova && composer install && php artisan config:cache
cd ../frontend && npm run build
```

## Health check

```bash
curl https://playnova.ir/api/v1/health
```

## Cron

```cron
* * * * * cd /home/playnnu/domains/playnova.ir/public_html/PlayNova && php artisan schedule:run >> /dev/null 2>&1
```
