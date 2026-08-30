# PlayNova Performance Setup (Local)

This guide applies the Phase A performance optimizations added on 2026-08-21.

## What changed

- `Setting::get()` is cached (1 hour TTL, invalidated on `set()`)
- Team invite queries are cached per user (30 seconds)
- Admin dashboard stats cached for 5 minutes
- Broadcast notifications run via queue job
- Wallet stale deposit cleanup moved to scheduled command
- Safe database indexes migration added
- Home page uses one tournament query instead of two
- Notifications page paginated (20 per page)
- Tournament capacity checks use `registered_count` column

### Phase E (2026-08-23)

- Home / leaderboard / rules / static pages cached via `TournamentListingService` and `ContentCacheService`
- Public API GET responses cached for 60s (`CachePublicGetResponse` middleware)
- Admin POST/PUT/PATCH/DELETE invalidates dashboard + public caches automatically
- SMS OTP uses `SendOtpSmsJob` (sync when `QUEUE_CONNECTION=sync`)
- User notifications use `SendUserNotificationJob`
- Auth API routes rate-limited (`throttle:auth`, 15/min per IP)
- Additional safe indexes migration (`2026_08_23_000001_add_remaining_performance_indexes.php`)
- Admin list pages paginated (tickets, broadcasts, tournaments, news, discounts)

### Phase F (2026-08-28)

- `admin.cache.invalidate` middleware wired on all admin routes
- Home cache bust on content/news mutations via `ContentCacheService::forgetAll()`
- `SendUserNotificationJob` dispatched for team invites, withdrawals, and KYC status changes
- Home page league filtering moved to SQL queries
- `registered_count` used for capacity checks during registration
- Admin user search prefers exact/prefix matches (uses new username/email indexes)
- New query performance indexes migration (`2026_08_28_000001_add_query_performance_indexes.php`)
- `/history` endpoint cached via `api.cache.public` middleware
- Redundant `registrations` duplicate indexes removed

### Phase G (2026-08-30)

- Admin dashboard API: `pending_kyc`, `pending_withdrawals_count`
- Nuxt admin dashboard: full financial report UI
- Nuxt select-seat: golden theme parity with legacy Blade
- Production deploy assets: `deploy/supervisor-playnova-worker.conf`, `deploy/docker-compose.redis.yml`

## 1. Database migrations (safe — no data loss)

```bash
cd PlayNova
php artisan migrate
```

This only **adds indexes** and creates the `jobs` table if missing. It does not drop tables or delete rows.

## 2. Recommended `.env` for performance

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database
```

If Redis is not installed locally, keep:

```env
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

With `sync`, broadcast jobs still run inline (same as before) but other optimizations remain active.

## 3. Queue worker (when using database/redis queue)

```bash
php artisan queue:work --tries=3
```

## 4. Scheduler (wallet cleanup)

Add to cron / Task Scheduler:

```bash
php artisan schedule:run
```

Or run manually:

```bash
php artisan wallet:cleanup-stale-deposits
```

## 5. After changing settings in admin

Settings cache invalidates automatically per key on save. No manual flush needed.

## 6. Production checklist (when deploying later)

- Enable Redis for cache + session
- Run `php artisan config:cache` and `php artisan route:cache`
- Run queue worker via Supervisor
- Run scheduler via cron
- Run `php artisan migrate` during maintenance window (indexes on large tables may take a few minutes)
