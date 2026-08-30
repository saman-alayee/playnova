#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/PlayNova"
FRONTEND_DIR="${ROOT_DIR}/frontend"

echo "==> PlayNova production setup"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker not found. Install Redis manually or via your hosting panel."
else
  echo "==> Starting Redis"
  docker compose -f "${ROOT_DIR}/deploy/docker-compose.redis.yml" up -d
fi

cd "${BACKEND_DIR}"

if [ ! -f .env ]; then
  echo "Copy deploy/.env.production.example to PlayNova/.env and configure it first."
  exit 1
fi

echo "==> Installing backend dependencies"
composer install --no-dev --optimize-autoloader

echo "==> Running migrations"
php artisan migrate --force

echo "==> Caching config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Health check"
php artisan tinker --execute="echo json_encode(app(\\App\\Http\\Controllers\\Api\\V1\\HealthController::class)->index()->getData(true));"

cd "${FRONTEND_DIR}"
echo "==> Building frontend"
npm ci
npm run build

echo ""
echo "Done. Next steps:"
echo "  1. Install Supervisor config: deploy/supervisor-playnova-worker.conf"
echo "  2. Add cron: * * * * * cd ${BACKEND_DIR} && php artisan schedule:run"
echo "  3. Configure reverse proxy: / -> Nuxt, /api -> Laravel"
echo "  4. Set SENTRY_LARAVEL_DSN and NUXT_PUBLIC_SENTRY_DSN"
