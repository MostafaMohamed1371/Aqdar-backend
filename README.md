# AQDAR (عَقدار)

Arabic-first real-estate **exit and resale** marketplace for **Egypt**. Currency is **EGP only**.

This repository is the Laravel backend:

- **`/api/v1`** — Flutter marketplace (Sanctum)
- **`/dashboard`** — staff admin as Blade (not React), styled from `dashboard.html` + `ui.css`

**Stack:** Laravel 13 · PHP 8.4+ · PostgreSQL · Sanctum · Vite · Tailwind 4 + `ui.css`

**Current delivery:** Phase 0 through Phase 9, plus Phases 10–15 (OTP/consent, session audit, listing publish gate, listing fields, seller case timeline + history, notification matrix leftovers).

---

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* to PostgreSQL (database aqdar_eg)
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

| URL | Who |
|-----|-----|
| http://127.0.0.1:8000/dashboard | Staff login |
| http://127.0.0.1:8000/api/v1/config | Public marketplace config |
| http://127.0.0.1:8000/up | Health |

**Staff (after seed):** `test@example.com` / `password`  
There is **no public register** on the dashboard. Super admin creates marketplace users and staff from the desk.

**Marketplace OTP (local/testing only):** code `123456` — never returned in JSON.

Full install: [docs/GETTING_STARTED.md](docs/GETTING_STARTED.md)

---

## Documentation

| Document | Purpose |
|----------|---------|
| [docs/README.md](docs/README.md) | Index |
| [docs/GETTING_STARTED.md](docs/GETTING_STARTED.md) | Install, PostgreSQL, first run |
| [docs/API.md](docs/API.md) | REST API by phase (source of truth for HTTP) |
| [docs/DASHBOARD.md](docs/DASHBOARD.md) | Blade admin screens |
| [docs/DOCUMENTATION_PROCESS.md](docs/DOCUMENTATION_PROCESS.md) | **After each phase:** update API.md + Postman |
| [docs/AQDAR_BACKEND_PHASES.md](docs/AQDAR_BACKEND_PHASES.md) | Product phases |
| [docs/DASHBOARD_PHASES.md](docs/DASHBOARD_PHASES.md) | Admin UI by mockup screen |
| [docs/TESTING_PHASES.md](docs/TESTING_PHASES.md) | Unit → integration → E2E |
| [docs/OPS.md](docs/OPS.md) | Monitoring, backups, restore drill |
| [docs/DEPLOY.md](docs/DEPLOY.md) | Staging/production deploy and rollback |
| [postman/README.md](postman/README.md) | Import AQDAR Postman collection |

When a phase is finished, add every new route and screen to **`docs/API.md`**, **`docs/DASHBOARD.md`**, and the **Postman collection**. Do not wait until the end of the project.

---

## API (Phase 1–3)

Base: `{APP_URL}/api/v1`

| Group | Method | Path | Auth |
|-------|--------|------|------|
| Config | GET | `/config` | Public |
| Categories | GET | `/categories` (tree), `/categories/slug/{slug}` | Public |
| Catalog | GET | `/locations`, `/developers`, `/projects`, `/unit-types`, `/amenities`, `/tags` | Public |
| Content | GET | `/content/faqs`, `/content/banners`, `/content/support` | Public |
| Auth | POST | `/auth/register`, `/auth/login` | Public |
| Auth | POST | `/auth/otp/send`, `/auth/otp/verify` | Public |
| Auth | POST | `/auth/forgot-password`, `/auth/reset-password` | Public |
| Me | GET | `/me` | Bearer |
| Me | PATCH | `/me` | Bearer (not suspended) |
| Devices | POST | `/devices` | Bearer (not suspended) |
| Auth | POST | `/auth/logout` | Bearer |
| Listings | GET | `/listings`, `/listings/counts`, `/listings/{id}`, `/map` | Public (published) |
| Listings | POST/PUT/GET | `/my/listings` wizard, submit, resubmit, history, `?status=` | Bearer |
| Favorites | GET/POST/DELETE | `/favorites` | Bearer |
| CRM | POST/GET | `/inquiries`, `/my/inquiries`, `/appointments`, `/my/appointments` | Bearer |
| Requests | POST/GET | `/buyer-requests`, `/my/buyer-requests`, `/my/cases` | Bearer |
| Locked | * | `/users`, `/settings`, category writes | 401 guest / 403 marketplace |

Import [postman/AQDAR-API.postman_collection.json](postman/AQDAR-API.postman_collection.json).

---

## Admin (Blade)

| Screen | Route | Who |
|--------|-------|-----|
| Login | `/dashboard` | Guest staff |
| ADM-01 Overview | `/dashboard` | Signed-in staff |
| ADM-04 Users | `/dashboard/users` | `view_users`; create/edit/delete: `suspend_user` |
| ADM-06 Staff | `/dashboard/staff` | `manage_staff` (super admin) |
| ADM-05 Developers | `/dashboard/developers` | `manage_catalog` |
| ADM-11 Content | `/dashboard/content` | `manage_catalog` (banners/FAQs/support) |
| ADM-15 Reference | `/dashboard/categories`, `/locations`, `/unit-types`, `/amenities`, `/tags` | `manage_catalog` |
| ADM-07 Leads | `/dashboard/leads` | `manage_crm` |
| ADM-08 Buyer requests | `/dashboard/requests` | `manage_crm` |
| ADM-09 Cases | `/dashboard/cases` | `manage_crm` |
| ADM-12 Reports | `/dashboard/reports` | `export_csv` |
| ADM-13 Settings | `/dashboard/settings` | `manage_catalog` |
| ADM-14 Audit | `/dashboard/audit` | `manage_staff` |

---

## Tests

```bash
php artisan test
```

PHPUnit uses PostgreSQL database `aqdar_eg_testing` (not the app DB). Create that database once.

## Privacy

- Public listing JSON has no seller phone, email, or user id.
- Staff phones stay masked until a logged reveal (`access_logs`).
- CSV exports (ADM-12) omit phones unless the staff has `reveal_phone`; that export is audited.
- Buyer↔seller chat is not available. Users can message **AQDAR Sales** at `POST /api/v1/my/support`.
- Published listings have a public share page at `/property/{public_code}`.
- Marketplace users cannot call `/users` or `/settings` (401 guest / 403 signed-in).
- Maintenance mode 503s the marketplace API; `/up` and the staff desk stay up.

---

## Project structure

```
app/
├── Core/                 # Shared infrastructure
└── Modules/
    ├── Auth/             # OTP + email auth (API) and staff web login
    ├── User/             # Marketplace users
    ├── Staff/            # Staff RBAC
    ├── Category/         # Segment tree (distressed A/B)
    ├── Catalog/          # Locations, developers, unit types, content
    ├── Setting/          # GET /config
    └── Dashboard/        # Blade admin
docs/
postman/                  # AQDAR collection (append per phase)
tests/
```

---

## License

Private product work on Laravel. Starter leftovers (STRUCTER.md, old Structer Postman) are not the product contract.
