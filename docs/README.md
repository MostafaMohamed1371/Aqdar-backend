# AQDAR documentation

Product backend for the Egypt resale marketplace. Laravel serves Flutter at `/api/v1` and the staff desk as Blade.

**Shipped:** Phase 0 through Phase 9. Plan: [AQDAR_BACKEND_PHASES.md](AQDAR_BACKEND_PHASES.md).

After **every** finished phase, follow [DOCUMENTATION_PROCESS.md](DOCUMENTATION_PROCESS.md) so `API.md`, `DASHBOARD.md`, and Postman stay in sync.

---

## Start here

| If you want to… | Read |
|-----------------|------|
| Install and run | [GETTING_STARTED.md](GETTING_STARTED.md) |
| Call or extend the REST API | [API.md](API.md) + [Postman](../postman/README.md) |
| Use the Blade admin | [DASHBOARD.md](DASHBOARD.md) |
| See phase plan | [AQDAR_BACKEND_PHASES.md](AQDAR_BACKEND_PHASES.md) |
| See admin screens by mockup | [DASHBOARD_PHASES.md](DASHBOARD_PHASES.md) |
| Run tests | [TESTING_PHASES.md](TESTING_PHASES.md) |
| Add docs after a phase | [DOCUMENTATION_PROCESS.md](DOCUMENTATION_PROCESS.md) |
| Backups / monitoring | [OPS.md](OPS.md) |
| Deploy / rollback | [DEPLOY.md](DEPLOY.md) |

Starter-era files (`STARTER_GUIDE.md`, `STRUCTER.md`, `SCALING.md`, `DOCKER.md`, `QA.md`) describe the HMVC skeleton, not AQDAR product rules. If they conflict with the BRD or `API.md`, **API.md and the BRD win**.

---

## Modules (current)

| Module | API | Blade | Purpose |
|--------|-----|-------|---------|
| **Auth** | `/api/v1/auth/*`, `/me`, `/devices` | Staff login | OTP + email token, staff session |
| **User** | `/users` locked | ADM-04 | Marketplace identity |
| **Staff** | — | ADM-06 | RBAC, staff CRUD |
| **Setting** | `GET /config`; settings CRUD locked | ADM-13 | Sales contact, maintenance, assignment rule |
| **Dashboard** | — | ADM-01 … ADM-15 | Staff Blade desk |
| **Category** | Public GET tree; writes locked | ADM-15 categories | Distressed A/B + segments |
| **Catalog** | locations, developers, projects, unit-types, amenities, tags, content | ADM-05, ADM-11, ADM-15 | Master data |
| **Listing** | `/listings`, `/my/listings`, `/favorites`, `/map`, files | ADM-02 + ADM-03 | Seller wizard, public search, private docs, media |
| **Crm** | `/inquiries`, `/buyer-requests`, `/my/cases`, `/appointments`, `/my/listings/{id}/interest` | ADM-07 / 08 / 09 | Inquiries, buyer requests, transfer cases |
| **Finance** | owner `GET /listings/{id}/estimate` | ADM-10 | Commissions |
| **Notification** | `/notifications`, `/me/notification-preferences` | ADM-11 | Inbox + templates |

---

## Environment (product)

| Variable | Purpose |
|----------|---------|
| `APP_NAME` | `AQDAR` |
| `APP_LOCALE` | `ar` (Arabic first) |
| `DB_CONNECTION` | `pgsql` — database `aqdar_eg` |
| `AQDAR_OTP_TEST_CODE` | Local/testing OTP (`123456`) — never in JSON |
| `SALES_PHONE` / `SALES_TEAM_NAME` / `SALES_HOURS` | Public `GET /config` sales contact |

Full list: [.env.example](../.env.example). Never commit secrets.
