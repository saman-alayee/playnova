# PlayNova API v1 Documentation

Base URL: `http://127.0.0.1:8000/api/v1`

All responses follow:
```json
{
  "success": true,
  "message": "optional message",
  "data": { ... }
}
```

Errors:
```json
{
  "success": false,
  "message": "Error description",
  "errors": { "field": ["validation message"] }
}
```

---

## Authentication

Use Sanctum Bearer token in header:
```
Authorization: Bearer {token}
```

### POST /auth/login
```json
{ "login": "username_or_mobile", "password": "secret" }
```

### POST /auth/register
```json
{
  "username": "player1",
  "mobile": "09123456789",
  "password": "secret",
  "password_confirmation": "secret",
  "cod_id": "123456789",
  "accept_rules": true,
  "referral_code": "OPTIONAL"
}
```

### POST /auth/register/verify/{token}
```json
{ "code": "123456" }
```

### POST /auth/forgot-password
```json
{ "mobile": "09123456789" }
```

### POST /auth/reset-password/{token}
```json
{
  "code": "123456",
  "password": "newpass",
  "password_confirmation": "newpass"
}
```

### GET /auth/me (auth required)

### POST /auth/logout (auth required)

---

## Public Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | /settings | Logo, social links, contact |
| GET | /home | Active tournaments, leagues, news |
| GET | /leaderboard | Top 100 players by kills |
| GET | /history | Finished tournaments (paginated) |
| GET | /rules | Rule sections |
| GET | /tournaments/{id} | Tournament detail + players |
| GET | /pages/privacy | Privacy content |
| GET | /pages/about | About content |
| GET | /pages/contact | Contact info |
| GET | /pages/faq | FAQ data |
| GET | /wallet/callback | Payment gateway callback |

---

## Protected Endpoints (Bearer token)

### Profile
| Method | Path |
|--------|------|
| GET | /profile |
| PUT | /profile |

### Wallet
| Method | Path |
|--------|------|
| GET | /wallet |
| POST | /wallet/deposit `{ "amount": 50000 }` |
| POST | /wallet/withdraw `{ "amount": 10000, "bank_card_number": "...", "bank_account_name": "..." }` |

### Tournaments
| Method | Path |
|--------|------|
| POST | /tournaments/{id}/register |
| GET | /tournaments/{id}/select-seat |
| POST | /tournaments/{id}/select-seat `{ "seat_number": 5 }` |
| GET | /tournaments/{id}/game-login |
| POST | /tournaments/{id}/team-invite |

### Team Invites
| Method | Path |
|--------|------|
| GET | /team-invites |
| POST | /team-invites/{id}/accept |
| POST | /team-invites/{id}/decline |
| POST | /team-invites/{id}/cancel |

### Notifications
| Method | Path |
|--------|------|
| GET | /notifications |
| POST | /notifications/{id}/read |
| POST | /notifications/read-all |
| DELETE | /notifications/{id} |

### KYC
| Method | Path |
|--------|------|
| GET | /kyc |
| POST | /kyc (multipart: document) |

---

## Admin Endpoints (admin user + Bearer token)

| Method | Path |
|--------|------|
| GET | /admin/dashboard |
| GET | /admin/tournaments |
| GET | /admin/users?search= |
| GET | /admin/withdrawals?status=pending |
| GET | /admin/kyc |
| GET | /admin/settings/site |
| PUT | /admin/settings/site |

---

## Pagination

Paginated endpoints include:
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

---

## CORS

Allowed origins for Nuxt dev:
- `http://localhost:3000`
- `http://127.0.0.1:3000`

Credentials supported for Sanctum SPA mode.
