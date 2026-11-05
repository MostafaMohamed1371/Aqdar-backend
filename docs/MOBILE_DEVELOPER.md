# AQDAR — Mobile developer handoff

This pack is for the **Flutter / mobile** marketplace app only.  
Staff admin (`/dashboard`) is a separate Blade desk — do **not** call it from the app.

---

## What this backend is

AQDAR (عَقدار) is an Arabic-first Egypt real-estate **exit & resale** marketplace.

| Surface | URL | Auth |
|---------|-----|------|
| Mobile / Flutter API | `{BASE}/api/v1/...` | Sanctum Bearer token |
| Health | `{BASE}/up` | Public |
| Staff desk | `{BASE}/dashboard` | **Not for mobile** |

- Currency: **EGP only**
- Default locale: **Arabic** (`Accept-Language: ar` or `en`)
- Phones: Egyptian (`010…` or `+2010…`)
- Local/testing OTP code: **`1234`** (never returned in JSON)
- After `php artisan migrate --seed`: demo buyer `test@example.com` / `password` (or OTP to `+201012345678`); seller `seller@example.com` / `password`. Seeded data fills listings with photos, map, favorites, recently viewed, notifications, saved searches, inquiries, appointments, buyer requests, case timeline, support chat, seller listings/documents, onboarding, FAQs, and banners. Re-run `php artisan db:seed --class=MobileDemoSeeder` to attach the same buyer demo rows to any OTP user already in the database.
- Listing photos are in `data.photos[].url` (and `GET /listings/{id}/media`). Those URLs use **the same host you called** so an emulator hitting `http://10.0.2.2:8000` gets image URLs on `10.0.2.2`, not `localhost`.
- `segment` is `non_distressed` or `distressed` — not `voluntary`.

Default local base: `http://127.0.0.1:8000` (Android emulator: `http://10.0.2.2:8000`)

---

## Files to send the mobile team

Send these four files together (zip or repo paths):

| File | Purpose |
|------|---------|
| `docs/MOBILE_DEVELOPER.md` | This handoff (start here) |
| `docs/API.md` | Full HTTP reference (source of truth) |
| `docs/openapi.yaml` | Machine-readable OpenAPI |
| `postman/AQDAR-API.postman_collection.json` | Runnable requests by phase |
| `postman/AQDAR-Local.postman_environment.json` | Local variables (`base_url`, `auth_token`, …) |
| `postman/README.md` | Import steps |

Do **not** send or use `postman/Structer-API.postman_collection.json` (legacy starter).

Optional context (product phases, not required to call the API): `docs/AQDAR_BACKEND_PHASES.md`.

---

## Postman setup (first 5 minutes)

1. Postman → **Import**
2. Import `AQDAR-API.postman_collection.json`
3. Import `AQDAR-Local.postman_environment.json`
4. Select environment **AQDAR Local**
5. Run **Phase 1 — Identity → OTP send** then **OTP verify** with code `1234`  
   (or **Register** / **Login** for email).  
   The collection stores `auth_token` automatically.
6. Any protected request then sends `Authorization: Bearer {{auth_token}}`

Collection folders match shipped phases (identity → catalog → listings → files → CRM → requests/cases → estimate → notifications → saved searches → support, etc.).

---

## Required headers

| Header | Value |
|--------|--------|
| `Accept` | `application/json` |
| `Content-Type` | `application/json` (JSON bodies) |
| `Authorization` | `Bearer {token}` on protected routes |
| `Accept-Language` | `ar` (default) or `en` |

Success payloads are wrapped in `data`. Errors always include a translated `message`. Validation errors also include `errors`.

| Status | Meaning |
|--------|---------|
| 200 / 201 | OK / created |
| 202 | Password login needs 2FA OTP (`/auth/login/two-factor`) |
| 401 | Missing / invalid token |
| 403 | Authenticated but not allowed (or locked leftover route) |
| 422 | Validation |
| 429 | Rate limited |
| 503 | Marketplace maintenance (except `/config` and sales-contact) |

---

## App flows → API (quick map)

| Mobile need | Start with |
|-------------|------------|
| Splash / config | `GET /config` |
| Categories / home chips | `GET /categories` |
| OTP login | `POST /auth/otp/send` → `POST /auth/otp/verify` |
| Email register / login | `POST /auth/register`, `POST /auth/login` |
| Profile | `GET /me`, `PATCH /me` |
| Close account | `POST /me/close` |
| Password / 2FA | `POST /me/password`, `PUT /me/two-factor`, `POST /auth/login/two-factor` |
| Push device | `POST /devices` |
| Browse / search / map | `GET /listings`, `GET /listings/counts`, `GET /map`, `GET /listings/compare` |
| Listing detail + gallery | `GET /listings/{id}`, `GET /listings/{id}/media` |
| Favorites | `GET/POST/DELETE /favorites…` |
| Recently viewed | `GET/DELETE /recently-viewed` |
| Sell wizard | `POST /my/listings` + `PUT …/step/…` + `POST …/submit` |
| Upload photos / docs | `POST /my/listings/{id}/media`, `…/documents` |
| Inquiry / viewing | `POST /inquiries`, `POST /appointments`, `GET /appointments/slots` |
| My inbox | `GET /my/inquiries` |
| Custom buyer request | `POST /buyer-requests`, `GET /my/buyer-requests` |
| Case timeline | `GET /my/cases`, `GET /my/cases/{id}` |
| Exit estimate | `GET /listings/{id}/estimate`, `POST /estimates` |
| Notifications | `GET /notifications`, preferences under `/me/preferences` (alias: `/me/notification-preferences`) |
| Saved searches | CRUD `/saved-searches` |
| Support chat | `GET/POST /my/support` |
| Onboarding / seller guide | `GET /content/onboarding`, `GET /content/seller-guide` |
| Catalog masters | `GET /locations`, `/developers`, `/projects`, `/unit-types`, `/amenities`, `/tags` |

Full request/response detail: **[API.md](API.md)**.

---

## Do not use from mobile

These are staff-only or locked leftovers. Expect **401** (guest) or **403** (marketplace token):

- Anything under `/dashboard`
- Staff CRUD / role changes (“make admin”)
- Marketplace `POST/PUT/DELETE` on `/users`, `/settings`, `/categories` (writes)

Staff create/edit marketplace users and staff accounts on the Blade desk after seed (`test@example.com` / `password` locally). That is out of scope for the Flutter app.

---

## Privacy rules the app must respect

- Public listing JSON **never** includes seller phone, email, or identity
- Case / inquiry client payloads use safe labels only (no counterparty PII, no internal stage codes)
- OTP codes are never in API responses
- Contact details pasted into inquiry / support bodies are rejected by the scanner

---

## Questions / source of truth

1. **HTTP contract** → `docs/API.md`  
2. **Try a call** → Postman `AQDAR-API` collection  
3. **Schema import** → `docs/openapi.yaml`  
4. If Postman and `API.md` ever disagree, **`API.md` wins** — then update Postman to match.
