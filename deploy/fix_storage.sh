#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-/var/www/playnova/PlayNova}"
WEB_USER="${2:-apache}"

echo "==> Ensuring Laravel storage directories under ${ROOT}"

mkdir -p "${ROOT}/storage/framework/views"
mkdir -p "${ROOT}/storage/framework/cache/data"
mkdir -p "${ROOT}/storage/framework/sessions"
mkdir -p "${ROOT}/storage/framework/testing"
mkdir -p "${ROOT}/storage/app/public"
mkdir -p "${ROOT}/storage/app/private/kyc"
mkdir -p "${ROOT}/storage/logs"
mkdir -p "${ROOT}/bootstrap/cache"

chown -R "${WEB_USER}:${WEB_USER}" "${ROOT}/storage" "${ROOT}/bootstrap/cache"
chmod -R ug+rwx "${ROOT}/storage" "${ROOT}/bootstrap/cache"

if command -v getenforce >/dev/null 2>&1 && [ "$(getenforce)" = "Enforcing" ]; then
  echo "==> Applying SELinux write contexts for ${WEB_USER}"
  chcon -R -t httpd_sys_rw_content_t "${ROOT}/storage" "${ROOT}/bootstrap/cache" || true
fi

echo "==> Storage directories ready"
