# PlayNova — راه‌اندازی لوکال (سیستم دیگر)

راهنمای کامل‌تر در `README.md` است. این فایل خلاصهٔ گام‌به‌گام است.

## محتویات ریپو

| مسیر | توضیح |
|------|--------|
| `PlayNova/` | Laravel 10 Backend + Blade + API v1 |
| `frontend/` | Nuxt 3 Frontend |
| `database/playnova-database.sql` | بکاپ کامل دیتابیس (~25MB) |

---

## ۱. دیتابیس

```sql
CREATE DATABASE playnova_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -u root -p playnova_local < database/playnova-database.sql
cd PlayNova
php artisan migrate
```

Migrationها **فقط index و jobs table اضافه می‌کنند** — هیچ داده‌ای حذف نمی‌شود.

---

## ۲. Laravel Backend

```bash
cd PlayNova
composer install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Blade UI: http://127.0.0.1:8000  
API: http://127.0.0.1:8000/api/v1

### `.env` پیشنهادی

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:3000
DB_DATABASE=playnova_local
DB_USERNAME=root
DB_PASSWORD=
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

با Redis (عملکرد بهتر):

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database
```

---

## ۳. Nuxt Frontend

```bash
cd frontend
copy .env.example .env
npm install
npm run dev
```

Nuxt UI: http://127.0.0.1:3000

### `frontend/.env`

```env
NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000/api/v1
NUXT_PUBLIC_BACKEND_URL=http://127.0.0.1:8000
```

---

## ۴. Queue Worker (اختیاری)

```bash
cd PlayNova
php artisan queue:work --tries=3
```

---

## ۵. مستندات

- `README.md` — شروع سریع
- `ARCHITECTURE.md` — معماری
- `PlayNova/API.md` — REST API
- `PlayNova/PERFORMANCE-SETUP.md` — بهینه‌سازی

---

## نکات

- Blade و Nuxt **همزمان** کار می‌کنند
- `.env` را commit نکنید
- روی سیستم جدید حتماً `composer install` و `npm install` بزنید
