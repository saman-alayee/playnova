# PlayNova

پلتفرم مسابقات آنلاین Call of Duty Mobile

```
Nuxt 3 (frontend)  →  Laravel API v1  →  MySQL
```

- **Backend:** Laravel 10 + Sanctum (`PlayNova/`)
- **Frontend:** Nuxt 3 + Pinia + Tailwind (`frontend/`)
- **Blade:** هنوز فعال است برای مقایسه / fallback

---

## پیش‌نیازها

| ابزار | نسخه پیشنهادی |
|--------|----------------|
| PHP | 8.1+ (با extensions: mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, bcmath, fileinfo) |
| Composer | 2.x |
| Node.js | 20+ |
| MySQL | 8.x / MariaDB 10.x |
| Git | آخرین نسخه |

روی Windows می‌توانید از **Laragon** یا **XAMPP** استفاده کنید.

---

## کلون و راه‌اندازی سریع

```bash
git clone https://github.com/saman-alayee/playnova.git
cd playnova
```

### ۱) دیتابیس

```sql
CREATE DATABASE playnova_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
# Windows (PowerShell / CMD)
mysql -u root -p playnova_local < database/playnova-database.sql

# یا اگر mysql در PATH نیست، از phpMyAdmin / HeidiSQL ایمپورت کنید
```

### ۲) Laravel Backend

```bash
cd PlayNova
composer install
copy .env.example .env
php artisan key:generate
```

فایل `.env` را ویرایش کنید:

```env
APP_NAME=PlayNova
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=playnova_local
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

سپس:

```bash
php artisan migrate
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

- Blade UI: http://127.0.0.1:8000  
- API: http://127.0.0.1:8000/api/v1  

### ۳) Nuxt Frontend

ترمینال جدا:

```bash
cd frontend
copy .env.example .env
npm install
npm run dev
```

فرانت: http://127.0.0.1:3000

محتوای `frontend/.env`:

```env
NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000/api/v1
NUXT_PUBLIC_BACKEND_URL=http://127.0.0.1:8000
```

---

## ساختار پروژه

```
playnova/
├── PlayNova/                 # Laravel backend + Blade + API
├── frontend/                 # Nuxt 3
├── database/
│   └── playnova-database.sql # بکاپ دیتابیس (~25MB)
├── LOCAL-SETUP.md
├── ARCHITECTURE.md
└── README.md
```

---

## چک‌لیست بعد از اجرا

1. Laravel روی `:8000` بالا باشد
2. Nuxt روی `:3000` بالا باشد
3. صفحه اصلی Nuxt مسابقات را از API بگیرد (نه خطای «بارگذاری ممکن نشد»)
4. ورود / ثبت‌نام از Nuxt کار کند
5. ادمین: `/admin` در Nuxt یا `/admin/dashboard` در Blade

---

## نکات مهم

- فایل‌های `.env` در git نیستند — از `.env.example` کپی کنید
- Migrationها فقط index / جدول `jobs` اضافه می‌کنند؛ داده حذف نمی‌شود
- برای عملکرد بهتر (اختیاری): Redis + `QUEUE_CONNECTION=database` و `php artisan queue:work`
- مستندات بیشتر: `LOCAL-SETUP.md`، `ARCHITECTURE.md`، `PlayNova/API.md`، `PlayNova/PERFORMANCE-SETUP.md`

---

## عیب‌یابی سریع

| مشکل | راه‌حل |
|------|--------|
| Nuxt: مسابقات لود نمی‌شود | Laravel را با `php artisan serve` اجرا کنید |
| CORS / API | `APP_URL` و `FRONTEND_URL` و `NUXT_PUBLIC_*` را چک کنید |
| `composer` / `php` پیدا نمی‌شود | PHP را به PATH اضافه کنید (Laragon/XAMPP) |
| خطای migrate | دیتابیس را ایمپورت کرده باشید و نام DB در `.env` درست باشد |
