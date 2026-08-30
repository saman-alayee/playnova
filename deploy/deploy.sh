#!/usr/bin/env bash
set -euo pipefail

# Native production deploy for playnova.ir (no Docker)
# Usage on server:
#   cd /home/playnnu/domains/playnova.ir/public_html
#   bash deploy/deploy.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/PlayNova"
FRONTEND_DIR="${ROOT_DIR}/frontend"

echo "==> PlayNova deploy @ $(date -Iseconds)"

echo "==> Git pull"
git pull --ff-only origin main

echo "==> Backend"
cd "${BACKEND_DIR}"
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart || true

echo "==> Frontend"
cd "${FRONTEND_DIR}"
npm ci
npm run build

echo "==> Permissions"
chmod -R ug+rwx "${BACKEND_DIR}/storage" "${BACKEND_DIR}/bootstrap/cache" || true

echo "==> Health"
php "${BACKEND_DIR}/artisan" tinker --execute="echo json_encode(app(\\App\\Http\\Controllers\\Api\\V1\\HealthController::class)->index()->getData(true));"

echo "==> Deploy complete"
