# PlayNova — Architecture & Migration Guide

## Overview

PlayNova is a tournament platform (Call of Duty Mobile) migrated from monolithic Laravel Blade to:

```
Nuxt 3 Frontend (frontend/)  →  REST API v1  →  Laravel Backend  →  MySQL + Redis
```

**Blade frontend remains functional** for gradual migration. Nuxt runs independently on port 3000.

---

## Project Structure

```
playnova-local/
├── PlayNova/                    # Laravel 10 Backend
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── Admin/           # Modular admin (18 controllers)
│   │   │   ├── Api/V1/          # REST API for Nuxt
│   │   │   └── ...              # Web controllers (Blade)
│   │   ├── Http/Resources/V1/   # API JSON transformers
│   │   ├── Services/            # Business logic
│   │   ├── Jobs/                # Queue jobs (broadcast)
│   │   └── Models/
│   ├── routes/
│   │   ├── web.php              # Blade routes
│   │   ├── admin.php            # Admin panel routes
│   │   └── api/v1.php           # REST API
│   └── database/migrations/
├── frontend/                    # Nuxt 3 SPA/SSR
│   ├── pages/                   # All user + admin pages
│   ├── components/              # UI matching Blade
│   ├── stores/                  # Pinia (auth)
│   └── composables/useApi.ts    # API client
└── database/playnova-database.sql
```

---

## Backend Architecture

### Modular Admin (replaces 1004-line AdminController)

| Controller | Responsibility |
|-----------|----------------|
| `Admin\DashboardController` | Stats (cached 5 min) |
| `Admin\TournamentController` | Tournament CRUD + results |
| `Admin\TournamentSeatController` | Seat map (seat_admin) |
| `Admin\UserController` | User management |
| `Admin\WithdrawalController` | Withdrawals + transactions |
| `Admin\KycController` | KYC review |
| `Admin\BroadcastController` | Mass notifications (queued) |
| `Admin\*Settings*` | Site, SMS, payment, logo, referral |
| ... | discounts, news, rules, tickets, roles |

### API v1 (`/api/v1/*`)

- **Auth:** Sanctum Bearer tokens
- **Public:** home, tournaments, pages, leaderboard, history, settings
- **Protected:** profile, wallet, KYC, notifications, team invites, seat selection
- **Admin:** dashboard, users, tournaments, withdrawals, KYC, site settings

### Performance Optimizations (Phase A)

| Feature | Implementation |
|---------|----------------|
| Setting cache | 1h TTL, per-key invalidation |
| Team invite cache | 30s per user |
| DB indexes | Safe migration on hot columns |
| Broadcast | `BroadcastNotificationJob` (queue) |
| Dashboard stats | 5 min cache |
| Notifications | Paginated (20/page) |
| Home page | Single tournament query |

---

## Frontend Architecture (Nuxt 3)

### Stack
- Nuxt 3 + Vue 3 + TypeScript
- Pinia (auth state)
- Tailwind CSS (build-time, NOT CDN)
- RTL Persian + Vazirmatn font

### UI/UX Preservation
Design tokens extracted from `layouts/app.blade.php`:
- `--bg-dark: #050505`, `--primary: #9333EA`
- Same header, footer, sidebar, modals, tournament cards

### Pages Migrated
| Category | Pages |
|----------|-------|
| Public | home, leaderboard, history, rules, privacy, about, contact, faq |
| Auth | login, register, verify, forgot/reset password |
| User | profile, wallet, KYC, notifications, tournament show/seat |
| Admin | dashboard, tournaments, users, withdrawals, KYC, settings |

---

## Local Setup

### 1. Database (preserve existing data)
```bash
mysql -u root -p playnova_local < database/playnova-database.sql
cd PlayNova && php artisan migrate
```

### 2. Laravel Backend
```bash
cd PlayNova
php artisan config:clear
php artisan serve
# http://127.0.0.1:8000
```

### 3. Nuxt Frontend
```bash
cd frontend
copy .env.example .env
npm install
npm run dev
# http://127.0.0.1:3000
```

### 4. Queue Worker (optional)
```bash
cd PlayNova
php artisan queue:work
```

### Environment
```env
# PlayNova/.env
CACHE_DRIVER=redis          # or file
SESSION_DRIVER=redis        # or file
QUEUE_CONNECTION=database   # or sync

# frontend/.env
NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000/api/v1
NUXT_PUBLIC_BACKEND_URL=http://127.0.0.1:8000
```

---

## API Authentication

```javascript
// Login
POST /api/v1/auth/login
{ "login": "09123456789", "password": "..." }
→ { "data": { "user": {...}, "token": "..." } }

// Authenticated requests
Authorization: Bearer {token}
```

---

## Migration Strategy

1. **Phase A (Done):** Performance quick wins
2. **Phase B (Done):** Modular admin + API v1
3. **Phase C (Done):** Nuxt scaffold + page migration
4. **Phase D (Next):** Switch production traffic to Nuxt
5. **Phase E (Next):** Remove Blade after parity verification

### Running Both Frontends
- Blade: `http://127.0.0.1:8000` (existing users)
- Nuxt: `http://127.0.0.1:3000` (new frontend)

Compare pages side-by-side before cutover.

---

## Data Safety Rules

- All migrations are **additive only** (indexes, jobs table)
- No DROP TABLE, no data deletion
- `Setting` cache invalidates on save
- Wallet cleanup only removes stale pending deposits >24h

---

## Future Scaling

| Step | Action |
|------|--------|
| Redis | Cache + Session + Queue |
| Horizon | Queue monitoring |
| Octane | Persistent PHP workers |
| CDN | Static assets from Nuxt build |
| Read replicas | MySQL for leaderboard/history |
| Load balancer | Multiple Laravel instances |
