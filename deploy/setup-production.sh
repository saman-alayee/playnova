#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/PlayNova"
FRONTEND_DIR="${ROOT_DIR}/frontend"

echo "==> PlayNova production setup (native — no Docker)"

if ! command -v redis-cli >/dev/null 2>&1; then
  echo "WARNING: redis-cli not found. Install Redis on the server:"
  echo "  Ubuntu/Debian: sudo apt install redis-server php-redis"
  echo "  CentOS:        sudo yum install redis php-redis"
else
  if redis-cli ping >/dev/null 2>&1; then
    echo "==> Redis is running"
  else
    echo "WARNING: Redis is installed but not responding. Start it:"
    echo "  sudo systemctl enable --now redis-server"
  fi
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
