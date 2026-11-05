# AQDAR Postman

Import these files into [Postman](https://www.postman.com/).

**Sending this to a Flutter / mobile developer?** Start with [docs/MOBILE_DEVELOPER.md](../docs/MOBILE_DEVELOPER.md) — it lists exactly which files to share and how to auth.

| File | Purpose |
|------|---------|
| `AQDAR-API.postman_collection.json` | Product API, **one folder per finished phase** |
| `AQDAR-Local.postman_environment.json` | Local variables |

The old `Structer-API.postman_collection.json` is leftover starter. Do not use it for AQDAR.

---

## Import

1. Postman → **Import** → both AQDAR JSON files
2. Environment dropdown: **AQDAR Local**
3. `php artisan serve`
4. Run **Phase 1 — Identity → Register** or **Verify OTP**
5. `auth_token` is stored; Bearer is applied to protected requests

Local OTP code is `1234` (never returned by the API).

---

## After each phase

Add a folder `Phase N — …` with every new route. Full checklist: [docs/DOCUMENTATION_PROCESS.md](../docs/DOCUMENTATION_PROCESS.md).

Current folders:

```
AQDAR API
├── Phase 0 — Foundation     health + locked leftovers (401/403)
├── Phase 1 — Identity       register, login, OTP, me, devices, config, categories
├── Phase 2 — Catalog        locations, developers, projects, unit-types, amenities, content
├── Phase 3 — Listings       my/listings wizard, public search, counts, map, favorites
├── Phase 4 — Files          documents, media, my/documents, public photos
├── Phase 5 — CRM            inquiries, callback, interest, appointments, my/appointments, sales-contact
├── Phase 6 — Requests       buyer-requests, my/cases, mixed inbox
├── Phase 7 — Estimate       listings/{id}/estimate (owner; missing % never invented)
├── Phase 8 — Notifications  inbox, read, read-all, preferences (in-app always on)
├── Phase 9 — Handover       /up, /config (maintenance + platform_name), listings throttle note
├── Phase 10–12 — Consent/gate  otp/send without consent 422, PATCH /me locale; session + publish gate are Blade
├── Phase 13 — Listing fields  specs phase/parking/unit_code, financials offer expiry + installment paid/evidence, public categories hide policy code
├── Phase 14 — Cases           my/cases list + show for buyer or seller; no internal_stage / counterparty PII
├── Phase 15 — Notifications   inbox may include listing_held / listing_suspended; staff queue is Blade /dashboard/inbox
├── Phase 16 — Staff RBAC      Blade only: finance stages, commission approve, sales view own
├── Phase 17 — Estimate/files  POST /estimates (manual); upload scan; videos stay private
└── Phase 18 — Closure/search  POST /me/close; saved-searches CRUD + PATCH; price-drop alert behind smart flag
```

---

## Variables

| Variable | Default | Notes |
|----------|---------|-------|
| `base_url` | `http://127.0.0.1:8000` | Docker: `http://localhost:8080` |
| `api_prefix` | `v1` | |
| `auth_token` | (empty) | Set by Register / Login / Verify OTP |
| `email` / `password` | demo user | Marketplace, not staff |
| `phone` | `01012345678` | Egyptian |
| `otp_code` | `1234` | local/testing only |
| `category_slug` | `electronics` | Needs a seeded/active category |
| `listing_id` | `1` | Set by Start draft |
| `document_id` | `1` | Set by Phase 4 document upload |
| `media_id` | `1` | Set by Phase 4 media upload |
| `inquiry_id` | `1` | Set by Phase 5 inquiry |
| `appointment_id` | `1` | Set by Phase 5 viewing request |
| `lead_id` | `1` | Optional; desk only |

Staff dashboard login is **not** in this collection (session Blade). See [docs/DASHBOARD.md](../docs/DASHBOARD.md).

HTTP details: [docs/API.md](../docs/API.md).
