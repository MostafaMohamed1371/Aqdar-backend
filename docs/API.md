# AQDAR REST API

**Base URL:** `{APP_URL}/api/v1`  
**Default:** `http://127.0.0.1:8000/api/v1`

Postman: [../postman/AQDAR-API.postman_collection.json](../postman/AQDAR-API.postman_collection.json)

When a phase ships, append a section here. Process: [DOCUMENTATION_PROCESS.md](DOCUMENTATION_PROCESS.md).

**Shipped in this file:** Phase 0 (locks + health), Phase 1 (identity), Phase 2 (catalog), Phase 3 (listings), Phase 4 (files + moderation), Phase 5 (inquiries + CRM), Phase 6 (buyer requests + transfer cases), Phase 7 (exit estimate + commissions), Phase 8 (notifications), Phase 9 (admin reports, settings, audit, hardening), Phases 10–12 (OTP/consent, session hardening, listing publish gate), Phase 13 (listing fields), Phase 14 (seller case timeline + case history), Phase 15 (notification matrix leftovers), Phase 16 (staff permission table vs BRD §8.2), Phase 17 (manual exit estimate + upload scan + private video), Phase 18 (account closure, saved searches, broadcast approval), UI closure (2FA, recently viewed, media order, onboarding/seller guide), plus post-G5 support chat, staff PDF, and public share landing.

| Phase | Method | Path | Auth |
|-------|--------|------|------|
| 0 | GET | `/up` | Public (not under `/api/v1`) |
| 0 | * | `/users`, `/settings`, category writes | Locked 401 / 403 |
| 1 | GET | `/config` | Public |
| 1 | GET | `/categories`, `/categories/slug/{slug}` | Public |
| 1 | POST | `/auth/register`, `/auth/login` | Public |
| 1 | POST | `/auth/otp/send`, `/auth/otp/verify` | Public |
| 1 | POST | `/auth/forgot-password`, `/auth/reset-password` | Public |
| 1 | GET | `/me`, `/auth/me` | Bearer |
| 1 | PATCH | `/me` | Bearer (not suspended) |
| 18 | POST | `/me/close` | Bearer (not suspended; FR-007 request only) |
| 18 | POST | `/me/password` | Bearer (change password; signs out other devices) |
| 1 | POST | `/auth/login/two-factor` | Public (finish a 2FA password login) |
| 1 | PUT | `/me/two-factor` | Bearer (turn two-factor on/off) |
| 18 | GET/POST/PATCH/DELETE | `/saved-searches`, `/saved-searches/{id}` | Bearer (CRUD always; match alerts need smart flag) |
| 1 | POST | `/devices` | Bearer (not suspended) |
| 1 | POST | `/auth/logout` | Bearer |
| 2 | GET | `/categories` (tree + children) | Public |
| 2 | GET | `/locations`, `/developers`, `/projects` | Public |
| 2 | GET | `/unit-types`, `/amenities`, `/tags` | Public |
| 2 | GET | `/content/faqs`, `/content/banners`, `/content/support` | Public |
| 2 | GET | `/content/onboarding`, `/content/seller-guide` | Public |
| 3 | POST | `/my/listings` | Bearer |
| 3 | PUT | `/my/listings/{id}/step/{category|specs|financials|media|documents|review}` | Bearer |
| 3 | POST | `/my/listings/{id}/submit` | Bearer (consent) |
| 3 | PUT | `/my/listings/{id}/resubmit` | Bearer |
| 3 | GET | `/my/listings`, `/my/listings/{id}`, `/my/listings/{id}/preview`, `/my/listings/{id}/history` | Bearer |
| 3 | GET | `/listings`, `/listings/counts`, `/listings/compare`, `/listings/{id}`, `/listings/{id}/media`, `/map` | Public (published only; optional Bearer) |
| 3 | GET/POST/DELETE | `/favorites`, `/favorites/{listing}` | Bearer |
| 3 | GET/DELETE | `/recently-viewed` | Bearer (FR-014) |
| 4 | POST | `/my/listings/{id}/documents` | Bearer |
| 4 | POST | `/my/listings/{id}/media` | Bearer |
| 4 | DELETE | `/my/listings/{id}/media/{media}` | Bearer |
| 4 | PUT | `/my/listings/{id}/media/order` | Bearer |
| 4 | GET | `/my/documents` | Bearer |
| 4 | GET | `/listings/{id}/media` | Public photos after staff approve |
| 5 | POST | `/inquiries` | Bearer |
| 5 | GET | `/my/inquiries`, `/my/inquiries/{id}` | Bearer (`?status=` APP-18 chips) |
| 5 | POST | `/my/inquiries/{id}/callback` | Bearer |
| 5 | GET | `/my/listings/{id}/interest` | Bearer (listing owner) |
| 5 | GET | `/my/interest` | Bearer (all of the seller's listings) |
| 5 | GET | `/appointments/slots?date=YYYY-MM-DD` | Bearer |
| 5 | POST | `/appointments` | Bearer |
| 5 | GET | `/my/appointments`, `/my/appointments/{id}` | Bearer |
| 5 | GET | `/config/sales-contact` | Public |
| 6 | POST | `/buyer-requests` | Bearer (always 201, even with 0 matches) |
| 6 | GET | `/my/buyer-requests`, `/my/buyer-requests/{id}` | Bearer |
| 6 | POST | `/my/buyer-requests/{id}/callback` | Bearer |
| 6 | GET | `/my/cases`, `/my/cases/{id}` | Bearer (client-safe labels only) |
| 6 | POST | `/my/cases/{id}/documents` | Bearer (requested document upload) |
| 7 | GET | `/listings/{id}/estimate` | Bearer (owner: full estimate; others: published listings only, without the seller's cancellation terms) |
| 17 | POST | `/estimates` | Bearer (manual contract inputs; BRD APP-17) |

---

## Conventions

| Header | Required | Value |
|--------|----------|-------|
| `Accept` | Yes | `application/json` |
| `Content-Type` | POST/PATCH | `application/json` |
| `Authorization` | Protected | `Bearer {token}` |
| `Accept-Language` | Optional | `ar` (default) or `en` |

JSON resources are wrapped in `data`.

| Status | Meaning |
|--------|---------|
| 200 | OK |
| 201 | Created |
| 400 | Malformed JSON / bad request |
| 401 | Guest / invalid token |
| 403 | Authenticated but not allowed (locked leftover or suspended) |
| 404 | Missing route or resource |
| 405 | Wrong HTTP method |
| 422 | Validation (field errors) |
| 429 | Rate limit |
| 500 | Unexpected server error (no stack trace in JSON) |

Every API error is JSON with translated fields (`Accept-Language: ar` or `en`):

| Field | Purpose |
|-------|---------|
| `code` | Stable machine code (`validation`, `unauthenticated`, …) |
| `title` | Short human heading for toasts / banners |
| `message` | Main readable sentence (for single-field validation = that field’s text) |
| `hint` | What the user should do next |
| `errors` | Field map (422 only) |
| `details` | Flat unique list of all field messages (422 only) |

**400**

```json
{
  "code": "bad_request",
  "title": "Could not complete this action",
  "message": "The request body is not valid JSON.",
  "hint": "Check the request body and send valid JSON."
}
```

**401 / 403 / 404 / 405 / 429 / 500**

```json
{
  "code": "unauthenticated",
  "title": "Could not complete this action",
  "message": "Authentication is required.",
  "hint": "Sign in again, then retry this action."
}
```

**422**

```json
{
  "code": "validation",
  "title": "Some fields need attention",
  "message": "Enter a valid Egyptian phone number.",
  "hint": "Correct the items listed, then submit again.",
  "errors": {
    "phone": ["Enter a valid Egyptian phone number."]
  },
  "details": ["Enter a valid Egyptian phone number."]
}
```

Currency is **EGP**. Phone numbers are Egyptian (`010…` or `+2010…`). OTP is never returned in JSON.

---

## Rate limits

| Limiter | Routes | Default |
|---------|--------|---------|
| `login` | `POST /auth/login` | 5 / min / email+IP |
| `api-auth` | `POST /auth/register` | 10 / min / email+IP |
| `otp` | OTP send/verify | 5 / 10 min / phone+IP |
| `password-reset` | forgot / reset | 3 / min / email+IP |
| `api` | config, categories | 60 / min / user or IP |

---

## Phase 0 — Foundation

Health and leftover API locks. No product listings yet.

### GET `/up`

Laravel health. Not under `/api/v1`.

### Locked marketplace routes

These must **not** be used by Flutter. Guest → **401**. Sanctum user → **403**.

| Method | Path |
|--------|------|
| GET, POST, PUT, PATCH, DELETE | `/users`, `/users/{id}` |
| GET, POST, PUT, PATCH, DELETE | `/settings`, `/settings/{id}`, `/settings/key/{key}` |
| POST | `/categories` |
| PUT, PATCH, DELETE | `/categories/{id}` |

Staff manage users and settings on Blade (`/dashboard/...`), not these APIs.

---

## Phase 1 — Identity, OTP, config

### POST `/auth/register`

Email register for the marketplace app (not the staff desk).

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "consent_privacy": true,
  "consent_terms": true,
  "locale": "ar"
}
```

**201** — `{ data: { user, token, token_type: "Bearer" } }`

Password: min 8, letters and numbers. `consent_privacy` and `consent_terms` are required. `locale` is `ar` or `en`.

### POST `/auth/login`

```json
{ "email": "jane@example.com", "password": "password123" }
```

**200** — same shape as register. Suspended users are rejected.

When the account has two-factor on (`two_factor_enabled` on the user resource), a correct password does **not** return a token. The server sends an OTP to the verified phone and returns **202**:

```json
{ "two_factor_required": true, "challenge": "<64 chars>", "phone": "010****5678", "resend_after": 45, "message": "…" }
```

### POST `/auth/login/two-factor`

```json
{ "challenge": "<from the 202>", "code": "1234" }
```

**200** — same shape as login. Wrong code is **422** on `code` (counts toward the OTP lockout). The challenge is single-use and expires with the OTP TTL; an expired or reused challenge is **422** on `challenge` (sign in again). Phone OTP login (`/auth/otp/verify`) is not affected — it already proves possession of the phone.

### POST `/auth/otp/send`

APP-02 sends the phone plus one consent tick. Either `consent: true` or both `consent_privacy` / `consent_terms`.

```json
{ "phone": "01012345678", "consent": true, "locale": "ar" }
```

**200**

```json
{ "message": "…", "resend_after": 45, "consent_required": false }
```

`resend_after` is the UI countdown (seconds). Server still rate-limits send at 5 / 10 minutes. First send for a new or unconsented phone **requires** `consent: true` (or both flags); missing consent is **422** and SMS is not sent. Returning users with stored consent may omit the flags. Resend within the OTP TTL may omit flags because consent is cached from the accepted send. `consent_required` on **200** is `false` after an accepted send (or for returning users). Local/testing code is `1234` (logs only in `local`). Never in the JSON body. Staging/production send the code through `AQDAR_SMS_DRIVER` (`log` | `http` | `null`). SMS provider failure is **503**.

### POST `/auth/otp/verify`

```json
{
  "phone": "01012345678",
  "code": "1234",
  "consent": true,
  "locale": "ar"
}
```

`consent_privacy` + `consent_terms` still work. **200** — token + user. Creates the user on first verify. Consent is required the first time unless APP-02 already sent it on `/otp/send`. Returning users with stored consent may omit the flags. Code is single-use.

### POST `/auth/forgot-password`

```json
{ "email": "jane@example.com" }
```

### POST `/auth/reset-password`

```json
{
  "token": "reset-token-from-email",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

### GET `/auth/me` and GET `/me`

Bearer required. **200** user resource.

### PATCH `/me`

Bearer + not suspended. Name, `is_seller`, `is_buyer`, `account_type`, `locale`. A first email may be set when the account has none (stored unverified). Sending a different email when one is already set returns **422** on `email` (`aqdar.auth.email_locked`); resending the same address is accepted. Phone, password, identity status and consent timestamps are ignored.

```json
{ "name": "Jane Doe", "is_seller": true, "account_type": "individual", "locale": "ar", "email": "jane@example.com" }
```

`account_type`: `individual` | `developer` | `broker`

### POST `/devices`

Bearer + not suspended. **201**

```json
{
  "device_uuid": "ios-device-1",
  "platform": "ios",
  "push_token": "optional"
}
```

### POST `/auth/logout`

Bearer. Deletes the current token. **200** `{ "message": "…" }`

### GET `/config`

Public. **200**. `sales_contact.name` and `hours` follow `Accept-Language` (`ar` Arabic, otherwise English).

```json
{
  "data": {
    "currency": "EGP",
    "locale": "ar",
    "default_locale": "ar",
    "locales": ["ar", "en"],
    "platform_name": "AQDAR",
    "maintenance": false,
    "sales_contact": {
      "name": "فريق مبيعات عَقدار",
      "phone": "",
      "hours": "يومياً 10ص – 10م"
    },
    "consent": {
      "version": "1",
      "terms_url": "",
      "privacy_url": ""
    },
    "otp": {
      "resend_after": 45,
      "length": 4
    }
  }
}
```

### GET `/categories`

Public. Active **tree**: roots with nested `children`. Distressed has exactly two children (`a`, `b`). `name` follows `Accept-Language`.

### GET `/categories/slug/{slug}`

Public. **404** if missing.

### User resource (`data.user` / `GET /me`)

```json
{
  "id": 1,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "+201012345678",
  "phone_verified_at": "2026-10-28T12:00:00.000000Z",
  "account_type": "individual",
  "is_buyer": true,
  "is_seller": false,
  "identity_status": "unverified",
  "locale": "ar",
  "consent_privacy_at": "2026-10-28T12:00:00.000000Z",
  "consent_terms_at": "2026-10-28T12:00:00.000000Z",
  "consent_version": "1",
  "is_suspended": false,
  "created_at": "2026-10-28T12:00:00.000000Z",
  "updated_at": "2026-10-28T12:00:00.000000Z"
}
```

---

## Phase 2 — Catalog, locations, content

Public master data. Writes stay on Blade (`manage_catalog`, super admin). Marketplace POST/PUT/DELETE on these resources is not provided (category writes still 403).

Inactive (`is_active=false`) rows are omitted. `is_other: true` is the controlled «أخرى» option (one developer, one project) and is listed last. Staff retire rows instead of hard-deleting them.

Category tree includes localized `help` on A/B children (APP-05).

### GET `/locations`

Governorates with nested active `areas`.

### GET `/developers`

Active developers, `is_other` last.

### GET `/projects`

Optional `?developer_id=`. Active projects, `is_other` last.

### GET `/unit-types` · GET `/amenities` · GET `/tags`

Separate from category segments (APP-13 mix is not the data model). Tags are listing chips (featured / verified / resale), not unit types.

### GET `/content/banners` · `/content/faqs` · `/content/support` · `/content/onboarding` · `/content/seller-guide`

Banner `image_url` is the public_media URL (null when no image). `onboarding` feeds the first-run intro slides; `seller-guide` feeds the seller "how it works" screen. Both return the same block shape, active only, ordered by `sort_order`.

```json
{
  "id": 1,
  "type": "faq",
  "key": "what-is-distressed",
  "title": "ما هو السوق المتعثر؟",
  "body": "…",
  "title_ar": "…",
  "title_en": "…",
  "body_ar": "…",
  "body_en": "…",
  "image_path": null,
  "image_url": null,
  "link_url": null
}
```

---

## Phase 3 — Listings

Seller wizard + public search. File uploads, staff approve/publish, and ADM-03 are in **Phase 4**. `GET /listings/{id}/media` returns approved public photos only.

Only `published` and `under_offer` listings are public. Draft / in_review / approved / hold / closed / archived / rejected return **404** (UAT-05). Public JSON never includes seller `user_id`, phone, email, or document keys (UAT-08). Description phone/email/URL flags the listing and writes `listing.held_contact` (UAT-06). Staff approve and publish both require every required document type to be **Approved** (pending does not count). Unpublish is `published|under_offer|hold → approved` with a reason and removes the listing from public search immediately. Editing public fields on a live listing moves it to `in_review`.

`total_paid` and `remaining` are stored separately and never auto-balanced. Distressed requires child A or B. Retired catalog rows cannot be used on new listings. Search is throttled (`30/min`).

### Seller (Bearer, not suspended)

- `POST /my/listings` — draft + `AQ-####` public code; marks the user as seller.
- `PUT /my/listings/{id}/step/{step}` — `category`, `specs`, `financials`, `media`, `documents`, `review`. Editable in `draft`, `needs_changes`, `published`, and `under_offer`. A public-field edit on a live listing moves it to `in_review`.
- `POST /my/listings/{id}/submit` — `{ "consent": true }`. Required: category, specs, declared contract + asking price, and mandatory private documents (`national_id`, `contract`, `payment_proof`; distressed also `developer_status_letter`). Moves `draft → submitted → in_review` and returns `review_reference`. Stores a field snapshot of that version.
- `PUT /my/listings/{id}/resubmit` — from `needs_changes` only. Stores a new field snapshot. Owner GET/PUT include `previous_snapshot` (last submitted fields) and `reviewer_notes` (request-changes reasons) for APP-25. While the listing is `needs_changes`, `reviewer_field_notes: [{ section, note }]` carries the reviewer's per-section notes from the latest request (`section` is one of `category|specs|financials|media|documents`). Saving edits without resubmit keeps `needs_changes` (APP-25 «حفظ كمسودة»).
- `GET /my/listings` · `/{id}` · `/{id}/preview` · `/{id}/history`. `?status=` exact status, `review` (submitted + in_review + resubmitted + approved-awaiting-publish), `ended` (hold + closed + archived), or `live` / APP-23 `published` (published + under_offer). Includes `favorite_count`. The list response adds top-level `counts: { all, draft, review, needs_changes, live, rejected, ended }` for the APP-23 chips (unaffected by the current filter). Owner `financials` include both `declared_*` and staff `verified_*` (with `*_verified` flags). `GET /my/listings/{id}/preview` returns the **public** listing DTO for the owner of a draft or unpublished listing (no view count, no seller PII) so APP-24 can show the buyer-facing card before publish.

Submit body:

```json
{ "consent": true }
```

Owner listing (trimmed):

```json
{
  "id": 1,
  "public_code": "AQ-0001",
  "review_reference": "AQ-0001-R1",
  "status": "in_review",
  "segment": "non_distressed",
  "furnished": "furnished",
  "handover_date": "2027-06-01",
  "neighborhood": "النزهة الجديدة",
  "view_count": 0,
  "contact_flagged": false,
  "financials": {
    "declared_contract_price": 1000000,
    "declared_contract_date": "2024-03-15",
    "declared_total_paid": 400000,
    "declared_remaining": 700000,
    "declared_asking_price": 950000,
    "declared_payment_system": "mix",
    "declared_assignment_restrictions": "بعد سداد 50٪"
  }
}
```

Specs may also send `furnished` (`unfurnished`|`furnished`), `handover_date`, `neighborhood` (free text), `phase` (compound phase name), `parking` (space count), and `unit_code` (owner + staff only; never public). Financials may send `declared_contract_date`, `declared_payment_system` (`cash`|`installment`|`mix`), `declared_assignment_restrictions`, `offer_expires_at`, and `installment_items[].paid` / `evidence_document_id` (document must belong to the same listing). Category step copies `commission_policy_code` from the category (FR-032 reference only; not a computed payout). Staff assign `assigned_reviewer_id` on ADM-02; it is never in public JSON.

### Public

- `GET /listings` — filters: `q` (title / public code / area / unit type), `segment` (`distressed` | `non_distressed`; `voluntary` is invalid), `distressed_child`, `governorate_id`, `area_id`, `developer_id`, `project_id`, `unit_type_id`, `bedrooms`, `finishing` (case-insensitive exact match), `tag` or `tag_id`, `area_min`/`area_max`, `price_min`/`price_max`, `down_payment_min`/`down_payment_max` (remaining), `installment_min`/`installment_max`, `neighborhood` (case-insensitive exact match), `sort=newest|price_asc|price_desc|area_asc|area_desc`, pagination. Optional Bearer sets `is_favorited`. Price, down-payment and installment filters and price sorting use the value buyers see (staff-verified when verified, else declared), stored in `listing_financials.public_*`.
- `GET /listings/counts` — published totals for home cards: `non_distressed`, `distressed`, `distressed_a`, `distressed_b`, plus `unit_types: [{ id, slug, count }]` (only types with published listings) and `tags: [{ id, slug, count }]` (every active tag, count may be 0).
- `GET /listings/compare?ids=1,2,3` (or `ids[]=`) — 2–3 ids, returns the public DTO for each published listing in the requested order; unpublished ids are dropped.
- `GET /listings/{id}` — public DTO + labelled `financial_summary` + `installment_items` (`sequence`, `due_date`, `amount`, `paid` — no evidence document ids) + amenities/tags + `sales_contact`. Increments `view_count` (not returned on the public DTO). With a Bearer token the view is also stored for recently viewed.
- `GET /listings/{id}/media` — approved public photos only (`id`, `sort_order`, `url`, `width`, `height`). Empty when none are approved. Never private originals or documents.
- `GET /map` — pins with lat/lng rounded to 3 decimals.

Public listing never includes seller identity. Each financial field is `{ value, source: declared|verified }`. Cards also carry `price_badge`: `verified` or `estimated` (seller-declared, not yet verified). Asking price uses verified value when staff has verified it (Phase 4). Contact text matching phone / email / URL / WhatsApp / QR is flagged.

### Favorites (Bearer)

`POST /favorites/{listing}` (published only) · `GET /favorites` · `DELETE /favorites/{listing}`.

### Recently viewed (Bearer, FR-014)

`GET /recently-viewed` — up to 30 public listing cards, most recently opened first (one row per listing; reopening moves it to the top). Listings that are no longer public drop out. `DELETE /recently-viewed` clears the history.

---

## Phase 4 — Files and moderation

Two file worlds. **Private documents** never get a public URL. **Public media** originals stay on `private_media`; a JPEG derivative is written to `public_media` only after staff approve.

Document types live in `config/aqdar.php` (DEC-04): `national_id`, `contract`, `payment_proof` (all segments) and `developer_status_letter` (distressed only). Allowed MIME/size are the same config. Neighborhood stays a free-text string.

Staff approve ≠ publish. Reviewer can approve a listing and open documents; operations/super admin publish, hold, and close. Every status change needs a reason and is written to the activity log. Financial verification writes `verified_*` only — declared values are never overwritten.

### Seller files (Bearer, owner only; 404 if not owner)

- `POST /my/listings/{id}/documents` — `multipart/form-data` `type` + `file`. Private disk, version bump. Allowed in draft/needs_changes, and while `in_review` only for a type staff marked replacement-requested or rejected. Response has no `path` / `disk`.
- `POST /my/listings/{id}/media` — `multipart/form-data` `file` + optional `caption`. Images: EXIF stripped and resized (JPG/PNG/WebP, max 10MB). Video (`mp4`/`webm`) is stored as-is and published only after staff approve. Caption/filename contact leaks set `moderation_status=held`.
- `DELETE /my/listings/{id}/media/{media}` — while the listing is editable.
- `PUT /my/listings/{id}/media/order` — `{ "ids": [12, 9, 10] }`. Must list every media id of the listing exactly once (else 422 on `ids`). Rewrites `sort_order` (first id = cover). Does not send the listing back to review. Returns the reordered media list.
- A document whose filename contains contact details (phone, email, URL) is stored as `{type}-v{version}.{ext}` instead of the original name.
- `GET /my/documents` — owner status list (no file path).

Upload document:

```
type: national_id
file: <pdf|jpg|png|webp>
```

Owner document (trimmed):

```json
{
  "id": 1,
  "listing_id": 1,
  "public_code": "AQ-0001",
  "type": "national_id",
  "version": 1,
  "original_name": "national_id.pdf",
  "mime": "application/pdf",
  "size_bytes": 12000,
  "review_status": "pending",
  "review_note": null
}
```

Public listing `data.photos` is approved media only. The word `documents` is not a public key (UAT-08). Held or pending photos are omitted (UAT-07). A leaked private path must 404 (UAT-04).

Signed document download is **Blade ADM-03**, not this API: ~10 minute signed URL, session + `open_private_documents`, image and PDF watermark `AQDAR staff-{id} {datetime}`, every open written to `access_logs`.

---


## Phase 5 — Inquiries, leads and Sales CRM

Buyers inquire on **published** listings only. Each inquiry opens (or reuses) a Sales lead `LD-YYYY-#####`. Duplicate same buyer + listing while the lead is still open is flagged `is_duplicate`. A later inquiry after the lead is `converted`/`lost` opens a new lead with `duplicate_of_id`. The response includes the platform Sales number from settings (UAT-09 / UAT-11). Contact text in the message is rejected (phone / email / URL / WhatsApp / QR). Owner cannot inquire their own listing. Unpublished listings 404.

Inquiry `status` follows the lead for APP-18 chips: New unassigned → `new`, New assigned → `in_review`, Contacted/Qualified/VisitScheduled/OfferSent → `replied`, Converted/Lost → `closed`. Optional `inquiry_type` (APP-15, default `interested`). APP-16 `next_steps` plus `POST /my/inquiries/{id}/callback`. Listing card is `{ id, public_code, title, photo }`. `GET /my/inquiries` is the mixed inbox (listing inquiry + buyer request + case).

Sales contact is also `GET /config` (`sales_contact`) and the thin alias `GET /config/sales-contact`. Changing `sales_phone` / `sales_hours` / `sales_name` in ADM-13 applies immediately.

Seller interest (`GET /my/listings/{id}/interest`) is anonymized: `interested_buyer_seq`, `kind` (`listing_inquiry`|`viewing`), `client_status` (`new`|`in_review`|`closed`), `status`, `created_at`, `offer_amount` only if staff recorded a seller-visible amount, plus `counts`. No buyer name, phone, or id (UAT-10). `GET /my/interest` returns the same rows for all of the seller's listings (newest first), each with `listing: { id, public_code, title }`; `interested_buyer_seq` is numbered per listing. Viewing requests `POST /appointments` are handled by Sales (no owner PII). APP-41 has no consent checkbox; consent defaults to true. Buyers list them at `GET /my/appointments`. Buyer requests (`BR-`) and transfer cases (`TR-`) are mixed into `GET /my/inquiries`.

### Inquiries (Bearer)

`POST /inquiries`

```json
{
  "listing_id": 1,
  "message": "مهتم بالمعاينة",
  "inquiry_type": "interested",
  "contact_method": "phone",
  "preferred_time": "evening",
  "consent": true
}
```

`contact_method`: `phone` | `whatsapp` | `email`. `preferred_time`: `morning` | `afternoon` | `evening`. `consent` required. `inquiry_type` optional (default `interested`).

Created (trimmed):

```json
{
  "id": 1,
  "public_code": "INQ-2026-00001",
  "kind": "listing_inquiry",
  "inquiry_type": "interested",
  "status": "new",
  "contact_method": "phone",
  "preferred_time": "evening",
  "listing": { "id": 1, "public_code": "AQ-0001", "title": "شقة للبيع", "photo": null },
  "lead_code": "LD-2026-00001",
  "next_steps": ["…", "…", "…"],
  "sales_contact": { "name": "فريق مبيعات عَقدار", "phone": "16890", "hours": "يومياً 10ص – 10م" }
}
```

- `GET /my/inquiries?status=` — mixed APP-18 inbox (`kind`: `listing_inquiry` | `buyer_request` | `case` | `appointment`). Appointment rows add `requested_date` and `requested_slot`; their chip is `in_review` while requested, `replied` once confirmed, `closed` when completed or cancelled. Each row has a chip `status` (`new` | `in_review` | `replied` | `closed`) plus `label` (same chip for inquiries/requests; the client-safe case label for cases). `GET /my/inquiries/{id}` is still a listing inquiry. Buyer only (404 for others). Open cases filter as `in_review`. The detail adds `timeline: [{ key, state, at }]` (`received` → `assigned` → `contacted` → `viewing` → `closed`; `state` is `done|current|future`) and `consultant: { name, role }` once a sales agent is assigned (name only, never staff email or phone).
- `GET /appointments/slots?date=YYYY-MM-DD` — APP-41 slot picker: `{ date, open, slots: [{ value, available }] }`. Slots come from `AQDAR_VIEWING_SLOTS`; past times today, days beyond `AQDAR_VIEWING_DAYS_AHEAD`, and `AQDAR_VIEWING_CLOSED_WEEKDAYS` are unavailable. `POST /appointments` rejects any other `requested_slot` with 422.
- `POST /my/inquiries/{id}/callback` — sets lead `next_action=callback` (APP-16).
- `GET /my/listings/{id}/interest` — owner only. Rows: `interested_buyer_seq`, `kind`, `kind_label`, `client_status`, `status`, `status_label`, `created_at`, `offer_amount`. Labels follow `Accept-Language`. Top-level `counts` + `sales_contact`.
- `POST /appointments` `{ listing_id, requested_date, requested_slot, notes? }` → `APT-YYYY-#####`, status `requested`, `kind=viewing`. Consent optional.
- `GET /my/appointments` · `GET /my/appointments/{id}` — buyer viewing requests.
- `GET /config/sales-contact` — public `{ name, phone, hours }`.

Staff CRM (assign, activity, phone reveal, convert, confirm/cancel viewing) is **Blade ADM-07**, not this API. Reveal is purpose-bound to an assigned lead (Sales does not have global `reveal_phone`). Convert marks the lead `converted` with `next_action=open_case` and opens a transfer case `TR-YYYY-#####`. Confirming a viewing request sets the lead to `visit_scheduled`.

## Phase 6 — Custom buyer requests and transfer cases

A custom buy request is always saved as a CRM ticket (`BR-YYYY-#####`), even when matching finds zero published listings (UAT-12). Matching scores published listings (unit type 20, governorate 15, area 15, developer/project 10, bedrooms 10, sqm 10, budget 15, segment 10, finishing 5) and persists rows with score ≥ 40. Unsent matches stay staff-only. Sales send a listing to the buyer; the buyer never receives owner PII.

Client `status` chips: `in_review` | `replied` | `closed`. Internal pipeline stays on the desk: `new → assigned → contacting_buyer → matching → options_shared → negotiation_visit → converted | closed_no_match`.

Transfer cases (`TR-YYYY-#####`) open from a converted lead or from a buyer request. The app sees `client_label` only (UAT-15): `interest_received`, `buyer_qualified`, `seller_contacted`, `documents_reviewed`, `agreement_in_progress`, `awaiting_developer`, `transfer_scheduled`, `completed`. Internal stages and notes stay on ADM-09. Developer approval letters are uploaded by hand (no government integration). The buyer may upload a requested document.

### Buyer requests (Bearer)

`POST /buyer-requests`

```json
{
  "intent": "living",
  "segment_preference": "any",
  "unit_type_id": 1,
  "governorate_id": 1,
  "area_id": 1,
  "budget_min": 2000000,
  "budget_max": 3500000,
  "area_sqm_min": 100,
  "area_sqm_max": 160,
  "bedrooms": 3,
  "contact_method": "phone",
  "consent": true
}
```

`intent`: `living` | `investment`. `contact_method`: `phone` | `whatsapp` | `email`. `consent` required. Contact text in `priorities` is rejected. Always **201** with `public_code` `BR-…` and `options: []` until Sales sends a listing.

Created (trimmed):

```json
{
  "id": 1,
  "public_code": "BR-2026-00001",
  "kind": "buyer_request",
  "status": "in_review",
  "intent": "living",
  "unit_type": "شقة",
  "purchase_timeline": "3_months",
  "options": [],
  "timeline": [{"key": "received", "state": "done"}],
  "sales_contact": { "name": "فريق مبيعات عَقدار", "phone": "16890", "hours": "يومياً 10ص – 10م" }
}
```

`purchase_timeline` is the buyer’s stated timing (BRD 6.1). `timeline` is the client-safe progress chips. Catalog names use `localizedName()`.

- `GET /my/buyer-requests` · `GET /my/buyer-requests/{id}` — buyer only. `options` are sent matches with listing cards `{ id, public_code, title, photo }` only (no owner PII). Matches with score &lt; 40 are not stored.
- `POST /my/buyer-requests/{id}/callback` — sets linked lead `next_action=callback`.
- `GET /my/cases` · `GET /my/cases/{id}` — **buyer or seller** of the case. `status` is the client stage label; `lifecycle` is `open|waiting_for_information|on_hold|cancelled|lost|completed`. `timeline[].key` uses client labels (never `internal_stage`); `timeline[].at` is when that milestone was recorded. `viewer` is `buyer` or `seller`. No counterparty name/phone/email.
- `POST /my/cases/{id}/documents` — buyer or seller upload (`kind` default `buyer_requested`). Decrements `missing_documents_count`.

Staff desks **ADM-08** / **ADM-09** are Blade, not this API. Assigned Sales can act; others get 403 on private case actions (UAT-13). Finance may open ADM-09 and advance `schedule_transfer` / `completed` only (BRD §8.2); they cannot open leads, requests, lifecycle, assign, or documents.

## Phase 7 — Exit estimate and commissions

Owner-only. Contract price, paid amount, and cancellation percent are private. Guests get **401**. Other users get **404**. A missing cancellation percent is never invented.

`GET /listings/{id}/estimate`

```json
{
  "computable": false,
  "reason": "cancellation_percent_missing",
  "inputs": [
    {"key": "contract_price", "value": 2900000, "verified": true},
    {"key": "total_paid", "value": 1450000, "verified": false},
    {"key": "remaining", "value": 1450000, "verified": false},
    {"key": "cancellation_percent", "value": null, "verified": false, "required": true},
    {"key": "asking_price", "value": 2650000, "verified": false}
  ],
  "cancellation": null,
  "transfer": {"asking_price": 2650000, "paid": 1450000, "note": "paid_plus_agreed_difference"},
  "market": {"label": "estimate", "certified_valuation": false, "min": null, "avg": null, "max": null, "sample_size": 0, "comparables": []},
  "disclaimer": {"text": "…", "source": "cms"}
}
```

When `declared_cancellation_percent` or a verified percent exists, `computable` is true. Penalty = paid × percent / 100. A non-owner viewing a published listing gets `viewer: "buyer"`, `percent: null` and `reason: "cancellation_terms_private"`; unpublished listings are 404 for non-owners. Owners get `viewer: "owner"`. Verified wins over declared. `0` is a real percent (computable). Market range uses published listings in the same area and unit type and is labelled `estimate`, not a certified valuation. Disclaimer prefers CMS `content_blocks.key = exit_estimate` (locale fallback), else `aqdar.estimate.disclaimer`.

Staff commissions (**ADM-10**) are Blade, not this API. Finance (`edit_commissions`) records a row on a **completed** transfer case; the row stays pending until Operations (`approve_commissions`) approves it. Sales (`view_commissions`) see assigned-case rows only. Reviewer PATCH is **403** (UAT-16). CSV export has no phones. Default rate is setting `commission_default_rate` (config fallback 2.5). Default status `earned`. Optional `commission_tax_percent`. The desk month filter (`?month=YYYY-MM`) applies to KPIs, table, and CSV. SVG monthly bars and a unit-type donut use recorded (non-waived) sale value / amount — no extra chart library.

## Phase 8 — Notifications

In-app inbox is the source of truth (F10). Push is best-effort via a queued job. `AQDAR_PUSH_DRIVER=log` (default) writes a local log. `AQDAR_PUSH_DRIVER=fcm` sends FCM HTTP v1 using a service-account JSON/env (`AQDAR_FCM_CREDENTIALS` or project id + client email + private key). No Firebase PHP SDK. Expired/UNREGISTERED tokens are cleared. Provider failures never block listing/CRM writes. Copy never includes another party’s name or phone. Ops: `php artisan aqdar:fcm-status --verify`; desk Content → Push can verify credentials and send a live diagnostic to the newest `POST /devices` token.

- `GET /notifications` — paginated inbox + `meta.unread_count`. Each row has `event`, `category`, `title`, `body`, `context` (safe keys only: listing code, area, client label — never a name or phone), `read_at`. `?category=` listing\|inquiry\|case\|system\|marketing\|smart. `?unread=1`. APP-14 `smart` is empty while `feature_smart_notifications` is off.
- `POST /notifications/{id}/read`
- `POST /notifications/read-all`
- `GET|PATCH /me/notification-preferences` (alias `GET|PATCH /me/preferences`) — `push_enabled`, `email_enabled`, `sms_enabled`, `marketing_opt_in`. In-app cannot be turned off.

UAT-14: listing publish writes an inbox row and a push delivery. UAT-18: `push_enabled=false` still returns the in-app row. Duplicate events reuse `idempotency_key`. Expired device tokens are cleared. Quiet hours and default copy live in settings/templates (DEC-09).

Staff ADM-11 is Blade, not this API.

## Phase 9 — Dashboard, reports, settings, hardening

Marketplace API in this phase:

- `GET /up` — health (not under `/api/v1`). Stays up during ADM-13 maintenance.
- `GET /config` — adds `platform_name` and `maintenance`. Sales contact still applies immediately after ADM-13 save.
- `GET /listings`, `/listings/counts`, `/map`, `/listings/{id}`, `/listings/{id}/media` — `throttle:search` (30/min) to limit scraping.
- Maintenance setting `maintenance_mode=1` returns **503** on marketplace API except `GET /config` and `GET /config/sales-contact`. The Blade desk stays available.

OpenAPI is generated with `php artisan aqdar:openapi` (no Scribe). Output: [openapi.yaml](openapi.yaml).

Staff desks **ADM-01 / 12 / 13 / 14** are Blade. CSV export (UAT-17) has no phones unless `reveal_phone` + `export_csv`; that path writes `report.exported_with_phones`. Lead `lead_assignment_rule=round_robin` assigns **Sales** only.

UAT-20: `/up`, `/config`, staff dashboard login.

## Not an API (staff desk)


Staff login is session-based Blade, not Sanctum. See [DASHBOARD.md](DASHBOARD.md).

Seeded local staff: `test@example.com` / `password`.

## Post-G5 — Support chat, staff PDF, share landing

Buyer↔seller chat, payment/escrow, and OCR stay **out**. These three slices are platform-only.

- `GET /my/support` — current open Sales-support thread, or `data: null`.
- `POST /my/support` — `{ body, consent }` creates or appends. Contact details in `body` are rejected (same scanner as inquiries). Messages are `author: user|sales` only — no staff id, no other party's name or phone.
- `GET /my/support/{id}` — owner only (404 otherwise).
- Realtime (Pusher, Sales support only — never buyer↔seller): `GET /config` → `data.pusher` (`enabled`, `key`, `cluster`, `auth_endpoint`, `channel_prefix`). Subscribe to `private-support.thread.{id}` after `POST /api/v1/broadcasting/auth` with the Sanctum Bearer token. Events: `support.message.sent`, `support.thread.closed`.
- Public listing JSON adds `share_url` (`{APP_URL}/property/{public_code}`).
- `GET /property/{public_code}` — HTML landing for published listings (APP-40). Unpublished → 404. No seller PII. Follows maintenance 503. Not a catalog/SEO index.

Staff: ADM-12 `format=pdf` uses the same rows and phone rules as CSV (`export_csv`, `reveal_phone`). Support desk is under ADM-07 at `/dashboard/support` (`manage_crm`; Sales assigned-only).

## Phase 10 — OTP delivery and consent

No new routes. `POST /auth/otp/send` and `POST /auth/register` require consent the first time (**422** otherwise; SMS is not sent). Send returns `resend_after` and `consent_required`; the code is never in JSON. SMS goes through `AQDAR_SMS_DRIVER` (`log` | `http` | `null`); provider failure is **503**. The user stores `locale`, `consent_privacy_at`, `consent_terms_at`, `consent_version`. `PATCH /me` may change `locale` (`ar` | `en`) and set a first unverified email only. Details: [Phase 1](#phase-1--identity-otp-config).

## Phase 11 — Session and audit hardening

No new marketplace routes. Sanctum tokens expire (config, default 30 days). Staff remember-me no longer stores a password cookie. Staff deactivate, role change, or password change invalidates the session (`session_version`). Staff and marketplace logins are audited without the password. Staff 2FA / IP allowlist stay out (DEC-07).

## Phase 12 — Listing publish gate and lifecycle

No new marketplace routes. Staff approve and publish require every required document type's **latest** version to be **Approved**. New statuses: `under_offer` (public) and `archived` (not public). Unpublish (`published|under_offer|hold → approved`) removes the listing from public search and `/property/{code}` immediately. Editing public fields or media on a live listing moves it to `in_review` (FR-024). `GET /my/listings/{id}` exposes `previous_snapshot` and `reviewer_notes` for APP-25. APP-23 `published` / `live` chips include `under_offer`. Staff actions are Blade (ADM-02). Details: [Phase 3](#phase-3--listings).

## Phase 13 — Listing fields, installment evidence, commission policy reference

No new marketplace routes. Existing seller steps and public listing DTOs gain BRD §3.3 fields. Staff assign a reviewer on ADM-02 (Blade).

**Seller (Bearer, owner):**

- `PUT /my/listings/{id}/step/specs` — also `phase` (compound phase name, max 64), `parking` (0–50 spaces), `unit_code` (owner + staff; never public).
- `PUT /my/listings/{id}/step/financials` — also `offer_expires_at`, `installment_items[].paid`, `installment_items[].evidence_document_id` (must belong to the same listing).
- `PUT /my/listings/{id}/step/category` — copies `commission_policy_code` from the category (FR-032 **reference only**; not a rate or payout). Seller cannot send `assigned_reviewer_id`.

**Public:**

- `GET /listings`, `GET /listings/{id}`, `GET /favorites`, `GET /property/{code}` — may include `phase`, `parking`, `offer_expires_at`. Never `unit_code`, `assigned_reviewer_id`, or `commission_policy_code`.
- `GET /categories` and `GET /categories/slug/{slug}` — no `commission_policy_code`.

**Staff (Blade, `approve_listing`):**

- `POST /dashboard/listings/{id}/assign-reviewer` — `{ assigned_reviewer_id }` nullable. Assignee must be active staff with `approve_listing` (reviewer / operations / super admin). Sales cannot be assigned. Writes `listing.reviewer_assigned`.

Hard-coded commission rates and overprice rules stay out (DEC-02 / DEC-03).

## Phase 14 — Seller case timeline and complete history

`GET /my/cases` and `GET /my/cases/{id}` return cases where the authenticated user is **buyer or seller**. Payload stays client-safe: stage labels only, `lifecycle` for hold/cancel/lost/waiting-for-information, no counterparty PII, no `internal_stage` / internal notes / staff ids. `timeline[].at` is the recorded milestone time (APP-20 / BRD APP-29). `viewer` is `buyer` or `seller`.

Stage changes notify **both** parties with the client label (no names or phones).

Staff ADM-09 `POST /dashboard/cases/{id}/lifecycle` writes `case_history` (`actor_id`, timestamps, `old_status` / `new_status`, reason) for `open`, `waiting_for_information`, `on_hold`, `cancelled`, `lost`, `completed` (BR-08 / §9.2). Completing the deal from the **stage** form also writes status history when `status` changes. The ADM-09 list shows a lifecycle column (waiting-for-information is a badge, not a pipeline stage).

## Phase 15 — Notification matrix leftovers (BRD §7.1)

No new marketplace routes. Existing `GET /notifications` now also receives:

- `listing_held` — seller, when staff hold a listing
- `listing_suspended` — seller, when staff unpublish or archive

Staff-only events (in-app + Blade `/dashboard/inbox`, **no push**):

- `staff_new_submission` — `approve_listing` queue (or the assigned reviewer)
- `staff_unassigned_lead` — ops/admin CRM queue when a new inquiry lead has no owner
- `staff_new_buyer_request` — CRM queue including Sales on a new custom request (BRD §7.1 sales queue)

Copy still cannot include party names or phones. Saved-search matching alerts stay behind the smart flag (SHOULD / APP-14).

## Phase 16 — Staff permission table (BRD §8.2)

No new marketplace routes. Blade RBAC only:

- **Finance** advances ADM-09 finance stages (`schedule_transfer`, `completed`) after developer approval. Not the full pipeline. Still 403 on listing approve, leads, requests, case create/assign/lifecycle/documents.
- **Operations** open ADM-10 and **approve** pending commission changes. They cannot create or PATCH amounts.
- **Sales** open ADM-10 for **assigned cases only**. No edit, no approve, no CSV.
- **Reviewer** remains review-only on commissions (UAT-16). Staff teams / team-scoped Sales numbers stay out (DEC-05).

New permissions: `advance_finance_stages`, `approve_commissions`, `view_commissions`. Finance create/update sets `approved_at` null unless Super Admin. Existing commission rows are backfilled as approved.

## Phase 17 — Manual exit estimate, upload scan, private video

BRD **APP-17** is the exit estimate (the Flutter mockup labels the listing-sourced screen APP-32). Inputs may come from the seller listing **or** from a manual POST. Missing cancellation % is never invented. Same disclaimer as `GET /listings/{id}/estimate`.

`POST /estimates`

Auth required. Guest **401**. Body fields are all optional numbers. `listing_id` is optional and must be **owned** (other users get **404**); it is used only for market comparables, not to copy stored financials. Optional `area_id` / `unit_type_id` also feed comparables. Manual inputs are always `verified: false`. `source` is `manual`. `0` is a real percent (computable). No row is persisted.

```json
{
  "contract_price": 2900000,
  "total_paid": 1450000,
  "remaining": 1450000,
  "cancellation_percent": 10,
  "asking_price": 2650000,
  "listing_id": null,
  "area_id": null,
  "unit_type_id": null
}
```

Response shape matches the listing estimate (`computable`, `reason`, `inputs`, `cancellation`, `transfer`, `market`, `assumptions`, `disclaimer`) plus `source`.

**NFR-07 uploads.** `AQDAR_UPLOAD_SCAN_DRIVER=null` (default, local/tests) checks magic bytes against the MIME allowlist and rejects EXE/ELF payloads even when named `.pdf`. `clamav` also runs `clamdscan` when the binary exists and falls back to the MIME check with a warning if it does not. Listing documents, listing media, and case documents are scanned before they are stored.

**Video.** Originals stay on `private_media`. Staff approve does **not** copy the original to `public_media`. `public_path` stays null, so public `photos` and `GET /listings/{id}/media` omit the video (`isApproved` requires a public derivative). Owner `ListingMedia` includes `kind: video|photo` so APP-30 can show a play tile; `url` stays null (no public original). Images are still re-encoded to JPEG and copied after approve.

APP-32 «إدخال النسبة» saves on the listing via `PUT /my/listings/{id}/step/financials` (existing). Computing without saving uses `POST /estimates`. Mockup APP-17 is Custom Buyer Request (Phase 6); BRD APP-17 is this estimate.

## Phase 18 — Account closure, saved searches, broadcast approval

SHOULD items after MUST. Closure does **not** hard-delete the user (NFR-14). Saved-search **CRUD is always on**; match alerts stay behind `feature_smart_notifications`. Staff broadcasts wait for approval unless Super Admin created them.

### `POST /me/close`

Auth required. Guest **401**. Suspended / already-closed / pending-close sessions are **403**. Body:

```json
{ "reason": "أريد إغلاق الحساب" }
```

`reason` is required (3–500 chars). Sets `close_requested_at`, archives public listings, revokes tokens and devices, writes `user.closure_requested`. The JSON body is the user snapshot (`is_close_pending`, `is_closed`, …) plus `message` — the Bearer token is already revoked, so a later `GET /me` is **401**. The row is **not** anonymized yet. Login and OTP then return **422** (`account_close_pending`). Staff on ADM-04 **complete** anonymizes name/email/phone, sets `closed_at`, and keeps the row. **Decline** clears the pending request (archived listings stay archived). Existing admin hard-delete remains a separate ADM-04 action. `GET /me` (Sanctum session still bound in tests) exposes `is_close_pending` / `is_closed`.

### `POST /me/password`

Auth required. Body: `current_password` (required when the account already has a password; must be omitted for OTP-only accounts setting their first one), `password`, `password_confirmation`. Wrong current password is **422**. On success the password is updated, every other token for the user is revoked (the calling token stays valid), and `auth.password_changed` is written to the audit log. Throttled like password reset.

### `PUT /me/two-factor`

Auth required. Body: `enabled` (boolean), `current_password` (required when the account has a password; must be omitted otherwise). Enabling needs a verified phone (**422** on `enabled` otherwise). Returns the user resource. Audited as `auth.two_factor_enabled` / `auth.two_factor_disabled`.

The user resource also carries `region: {code: "EG", name: "مصر"}` for the settings screen's read-only region row.

### Saved searches

`GET /saved-searches` · `POST /saved-searches` · `PATCH /saved-searches/{id}` · `DELETE /saved-searches/{id}`

Auth required. Guest **401**. Max **20** per user (**422**). `filters` uses the same keys as public listing search (`area_id`, `price_max`, …). `notify` defaults true. `PATCH` may change `name`, `notify`, and/or `filters` (APP-14 settings gear). Other users’ rows are **404**. Publishing a listing runs `MatchSavedSearchesJob`; alerts (`smart_saved_search`) fire only when the smart flag is on, skip the listing owner, skip `notify: false`, and never include seller PII. Copy may use `listing_code` and `area` only. Staff verify of a **lower** public asking price on a live listing dispatches `smart_price_drop` (public `price` only; still behind the smart flag). Duplicate match/price keys are idempotent. The same verified price drop also sends `smart_favorite_updated` to buyers who saved the listing (not the owner; same smart flag; public `price` only).

```json
{
  "name": "القاهرة الجديدة",
  "notify": true,
  "filters": { "area_id": 1, "price_max": 3000000 }
}
```

### Broadcasts (Blade ADM-11)

Operations may **schedule** a marketing broadcast (`pending`). They cannot approve their own (four-eyes). Super Admin create still **auto-approves and sends** (Phase 8). `sent_at` on the schedule tab is the usage record; opted-in inbox rows stay in the send log. Reviewer/Sales/Finance stay **403** on the content desk.
