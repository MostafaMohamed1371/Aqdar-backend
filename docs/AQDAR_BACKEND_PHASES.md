# AQDAR (عَقدار) — Backend Analysis & Implementation Phases

Status: Phase 0 through Phase 9 (admin dashboard, reports, settings, audit, hardening) implemented.

## Sources

| Source | Coverage |
|--------|----------|
| Matryoshka Tech BRD v2.0 (English), 26 Oct 2026 | Read in full (29 pages) |
| `BACKEND_PROMPT_2.md` | Read in full |
| `BACKEND_2.md` | Read in full |
| `aqdar-ui-v2/index.html` | Read in full |
| `aqdar-ui-v2/flutter.html` | APP-01 … APP-40 read; APP-41 (viewing appointment) only partially |
| `aqdar-ui-v2/dashboard.html` | ADM-01 … ADM-15 read; tail of ADM-15 (reference data) not fully read |
| `aqdar-ui-v2/styles.css`, `ui.css` | Styling only — no business data |

When the BRD and the UI disagree, the BRD and `BACKEND_2.md` win. Conflicts are listed in section 3.

---

## 1. Product summary (backend view)

AQDAR is an Arabic-first real-estate **exit and resale** marketplace for **Egypt**. Currency is **EGP only**.

- **One account buys and sells.** «اعرض عقارك» does not create a second account.
- **Two markets.** `distressed` and `non_distressed`. Distressed has exactly two children:
  - **A** — current payment default (owner already has overdue installments).
  - **B** — expected payment default.
  - Labels and eligibility are master data and pending client sign-off (DEC-01).
- **Listing lifecycle:**
  `draft → submitted → in_review → needs_changes ↔ resubmitted → approved → published → hold | closed`
  `rejected` is terminal unless a reviewer reopens. Only `published` listings are public. **Approve** and **publish** are separate permissions.
- **Two money versions.** Seller-typed values (`declared_*`) are never overwritten. Staff-verified values (`verified_*`) are stored beside them.
- **Two file worlds.**
  - Private documents (contracts, receipts, national ID): signed URL ~10 minutes, watermark, every open logged.
  - Public photos: visible only after review, EXIF stripped.
- **Contact masking (non-negotiable).** No buyer↔seller phone, email, or name in any API payload, push body, or export. Every contact CTA shows the platform Sales number from settings. No in-app buyer↔seller messaging.
- **Inquiry.** «مهتم بالوحدة» → inquiry + CRM lead → response returns `INQ-…` reference and the Sales contact.
- **Custom buyer request.** Always saved as a CRM ticket, even with zero matching listings.
- **Transfer case.** Internal stages:
  `new_lead → buyer_qualified → seller_contacted → document_review_complete → parties_aligned → developer_approval_pending → assignment_scheduled → completed`
  Alternatives: `waiting_for_information`, `on_hold`, `cancelled`, `lost`. The app sees a safe external label only.
- **Commission.** Recorded manually (`earned | due | paid | waived`) on a closed case. No payment gateway, no escrow.
- **Exit estimate.** If the contract cancellation % is missing, return `computable: false`. Never invent a penalty percentage. Always include a disclaimer.

### Clients of the backend

| Client | Scope |
|--------|-------|
| Flutter app (iOS/Android) | APP-01 … APP-41 |
| Blade admin dashboard (this Laravel repo) | ADM-01 … ADM-15 |

Laravel serves **`/api/v1` for Flutter** and the **staff admin as Blade** in `app/Modules/Dashboard`. There is no separate React admin. The current Structer Blade login is the starting surface; it is restyled to `dashboard.html` + `ui.css` phase by phase.

### Out of launch scope (do not build)

Payment gateway, escrow, OCR/AI document parsing, government/developer APIs, buyer↔seller chat, telephony proxy, public SEO website, multi-country.

---

## 2. Roles and permissions (server-enforced, default deny)

Staff roles: `super_admin`, `operations`, `reviewer`, `sales`, `finance`.

| Action | Admin | Ops | Reviewer | Sales | Finance |
|--------|-------|-----|----------|-------|---------|
| Category / Sales number config | yes | no | no | no | no |
| Approve listing | yes | yes | yes | no | no |
| Publish / unpublish | yes | yes | no | no | no |
| Open private documents | yes* | yes* | yes | no | no |
| Reveal party phone | yes* | yes* | no | assigned only | no |
| CRM leads / requests | yes | yes | no | assigned | no |
| Advance case | yes | yes | no | assigned | no |
| Edit commissions | yes | no | no | no | yes |
| Manage staff roles | yes | no | no | no | no |
| CSV with PII | yes* | as granted | no | no | as granted |

`*` purpose-bound and logged. Guest = published listings only. Hidden UI is not authorization.

---

## 3. UI vs BRD conflicts (need a decision before building)

1. **APP-38 shows password and 2FA.** The BRD says phone + OTP is the login method. Decide whether passwords exist at all.
2. **APP-34 shows live chat («محادثة مباشرة»).** Buyer↔seller chat is forbidden. If this is support chat, it is new scope with no backend described.
3. **APP-14 smart notifications** (new match, price drop) are saved-search alerts — **SHOULD** in the BRD. Implement behind a feature flag, off at launch.
4. **APP-40 share link** uses `aqdar.com/property/12345`. A public website is excluded. Needs deep link or a minimal landing page decision.
5. **APP-13 categories** mix unit types (apartment, villa, land) with segments («إعادة بيع», «تصفيات»). The data model must keep unit types and segments separate.
6. **ADM-04 user types** (فرد / مطور / وسيط — individual / developer / broker) are not in the BRD data model.
7. **ADM-06 branches** and roles like «مدير فرع» are outside the five BRD roles.
8. **ADM-12 PDF export** — the BRD specifies CSV only.
9. **APP-33 seller interest shows offer amounts.** Allowed only if policy permits; buyer identity must never appear.
10. **APP-07 map pins** need an agreed location precision (DEC-06).

---

## 4. Current skeleton — keep vs replace

| Keep | Replace / remove |
|------|------------------|
| HMVC module structure (`app/Modules`, `make:module`) | Password register/login → phone OTP |
| Sanctum, rate limiting, `BaseFormRequest`, Arabic localization | Open `/api/v1/users` CRUD available to any authenticated user |
| Docker, Redis, queue setup | Generic `Category` → segment tree with exactly two distressed children |
| `Setting` concept → Sales number, hours, feature flags | Structer Blade theme → AQDAR Blade admin (`dashboard.html` + `ui.css`) |
| | SQLite/MySQL defaults → PostgreSQL |
| | Remember-me cookie that stores the plaintext password |
| | Unused `Core/Payments`, `Core/Inventory` code |

Target modules: `Auth`, `Catalog`, `Listings`, `Files`, `Leads`, `Requests`, `Cases`, `Finance`, `Notifications`, `Admin`.

### Architecture rules to follow while building

1. New feature = new module. Nothing feature-specific in `app/Core`.
2. Business rules live in Services. Controllers stay thin.
3. Cross-module calls go through Services, never another module's Eloquent models.
4. Repositories never call `abort()`. Return `null` or throw a domain exception; controllers map to HTTP.
5. Separate API resources per audience: `PublicListingResource`, `OwnerListingResource`, `AdminListingResource`. Never return a full user row.
6. Enums for every state and segment.
7. Every privileged action has a policy check and, where sensitive, an audit/access log entry.

---

## 5. Implementation phases

Each phase lists: goal, data, endpoints, screens served, and the tests that define "done".

### Phase 0 — Foundation and cleanup

Exact file-level plan: [PHASE_0_CHANGE_PLAN.md](PHASE_0_CHANGE_PLAN.md).  
Dashboard (parallel, per phase, from the UI mockup): [DASHBOARD_PHASES.md](DASHBOARD_PHASES.md).

**Phase 0 is implemented** (PostgreSQL, leftover cleanup, enums, public codes, audit/access logs, private/public disks). Do not build all 15 admin pages now.

**Goal:** a safe base to build on.

- Configure PostgreSQL, Redis, private object storage (S3-compatible) and public media disk.
- Remove or replace the items in the "Replace / remove" column of section 4.
- Shared building blocks:
  - State/segment enums.
  - Public-code generator: `AQ-####`, `INQ-YYYY-#####`, `BR-…`, `LD-…`, `TR-…`.
  - `audit_logs` and `access_logs` writer service.
  - Base pattern for audience-specific API resources.
- `.env.example` without secrets.

**Done when:** the app boots on PostgreSQL, no unauthenticated or unauthorized CRUD endpoints remain, the audit writer is covered by a test.

---

### Phase 1 — Identity, staff and RBAC (BRD gate G2)

**Data:** `users`, `user_devices`, `staff_users`, `roles`, `permissions`, `role_permission`.

**User endpoints:**
- `POST /api/v1/auth/otp/send` `{ phone }` (throttled)
- `POST /api/v1/auth/otp/verify` `{ phone, code }` → token + user
- `GET /api/v1/me` · `PATCH /api/v1/me` (non-verified fields only)
- `POST /api/v1/devices` (push token)
- `GET /api/v1/config` (includes `sales_contact { name, phone, hours }`)

**Staff:**
- Separate staff guard and login.
- Five roles, policies with default deny.
- Admin: staff and roles (ADM-06); users list with masked phones, suspend/reactivate with reason, logged phone reveal (ADM-04).

**Screens served:** APP-01, APP-02, APP-03, APP-21, APP-38, ADM-04, ADM-06.

**Tests:**
- OTP cannot be bypassed; OTP send is rate limited.
- Suspended user loses authorized actions.
- Staff without permission gets 403 and the attempt is logged.
- UAT-01 groundwork: one user identity for buyer and seller.

---

### Phase 2 — Catalog, master data and content

**Data:** `categories` (tree + segment, ar/en copy, help, rank, active), `governorates`, `areas`, `developers`, `projects`, `unit_types`, `amenities`, `tags`, `content_blocks` (banners, FAQs, support).

**Endpoints:**
- Public: `GET /categories`, `/developers`, `/projects`, `/locations`, `/unit-types`, `/amenities`, `/tags`, `/content/faqs`, `/content/banners`, `/content/support`.
- Admin CRUD: Blade under `/dashboard/*` (`manage_catalog`), not REST `/admin/*`.

**Rules:**
- Distressed must have exactly two active children.
- Retired references (`is_active=false`) are omitted from public GETs and rejected for new listings in Phase 3.
- Tags are master-data chips; unit types stay separate from market segments.
- Controlled `other` option for developer/project.

**Seed:** Egypt governorates (Cairo, Giza, Alexandria, …) with sample areas; categories Distressed / A / B / Non-distressed (Arabic labels from UI, marked DEC-01); roles and permissions.

**Screens served:** APP-04, APP-05, APP-13, APP-22, APP-34, ADM-05, ADM-15, content part of ADM-11.

**Tests:** UAT-02 tree (exactly two distressed children A/B) and the category constraint cannot be broken (no third child, no deactivate A/B, no delete/rename protected slugs). Public catalog locale, inactive hide, 27 governorates, `?developer_id=`, one «أخرى» developer/project, ADM-05/11/15 CRUD, and Phase 2 E2E. Listing **filtered results** wait for Phase 3.

---

### Phase 3 — Listings (the spine of the product, BRD gate G3)

**Data:** `listings`, `listing_financials` (declared + verified + verification flags), `installment_items`, `listing_status_history`, `favorites`.

**Seller endpoints (APP-23 … APP-31):**
- `POST /api/v1/my/listings` — start draft
- `PUT /api/v1/my/listings/{id}/step/{category|specs|financials|media|documents|review}`
- `POST /api/v1/my/listings/{id}/submit` — consent required, locks the version, returns review reference
- `PUT /api/v1/my/listings/{id}/resubmit`
- `GET /api/v1/my/listings` · `/{id}` · `/{id}/history`

**Public endpoints (published only):**
- `GET /api/v1/listings` — filters: q, tag, segment, distressed child, governorate, area, developer, project, unit type, area min/max, price min/max, down payment min/max, installment min/max; sort; pagination. Optional Bearer for `is_favorited`.
- `GET /api/v1/listings/counts` — published totals for APP-04/05.
- `GET /api/v1/listings/{id}` — public DTO (labelled financials, amenities, tags)
- `GET /api/v1/listings/{id}/media`
- `GET /api/v1/map` — lightweight pins at approved precision
- `POST/DELETE /api/v1/favorites/{listing}` · `GET /api/v1/favorites`

**Rules:**
- State machine enforced in service + policy (no draft → published jump).
- Distressed requires child A or B.
- Positive area, integer rooms, EGP numeric.
- `total_paid` and `remaining` stored separately; never auto-corrected.
- Description scanned for phone / email / URL / WhatsApp patterns → flagged and held for review.
- Rejected or unpublished listings disappear from public search immediately.

**Screens served:** APP-04 … APP-11, APP-23 … APP-31, ADM-02 (read-only peek; actions in Phase 4).

**Tests:**
- UAT-05: guest GET of unpublished listing → 404.
- UAT-08: public listing JSON has no seller phone/email/user id/document keys.
- UAT-06: description with phone number is held with a logged reason.
- Invalid state transitions are rejected.
- A user cannot open another seller's draft.

---

### Phase 4 — Files and moderation

**Data:** `listing_documents` (private path, type, version, MIME, size, review status), `listing_media` (order, moderation status, public derivative path).

**Files:**
- Private documents on a segregated private disk; no public URLs.
- Signed download URL ~10 minutes, watermark with reviewer identity, every open/download written to `access_logs`.
- Public media stays `pending` until approved; EXIF stripped; derivatives created by queued job.
- Allowed MIME types and size limits configurable (DEC-04 / kickoff).

**Seller endpoints:**
- `POST /api/v1/my/listings/{id}/documents` (private)
- `POST /api/v1/my/listings/{id}/media` (pending public)
- `GET /api/v1/my/documents` (status per document)

**Admin moderation (ADM-02):**
- Queue filters by status, segment, alerts.
- Approve / reject / request changes (reason required).
- Publish / unpublish / hold / archive (separate permission, reason required).
- Financial verification writes `verified_*` only; declared values preserved and shown side by side.

**Admin documents (ADM-03):**
- Checklist per listing, request replacement, version history, access log view.

**Screens served:** APP-24, APP-25, APP-30, APP-36, ADM-02, ADM-03.

**Tests:**
- UAT-04: private document cannot be opened through a public URL.
- UAT-07: media with contact details stays held.
- Unauthorized staff role → 403 on documents; access attempt logged.
- Reviewer can approve but cannot publish.

---

### Phase 5 — Inquiries, leads and Sales CRM

**Data:** `inquiries`, `leads`, `lead_activities`, `appointments`.

**User endpoints:**
- `POST /api/v1/inquiries` `{ listing_id, message?, inquiry_type?, contact_method, preferred_time, consent }` → `INQ-…` + Sales contact from settings
- `GET /api/v1/my/inquiries` · `/{id}` (`?status=` for APP-18 chips) · `POST /api/v1/my/inquiries/{id}/callback`
- `GET /api/v1/my/listings/{id}/interest` — anonymized (`interested_buyer_seq`, kind, client_status, counts; no buyer name/phone)
- `POST /api/v1/appointments` — viewing request handled by Sales · `GET /api/v1/my/appointments`

**Admin CRM (ADM-07):**
- Lead list with source (listing inquiry, custom request, campaign, referral), stage, owner, priority, next action, SLA.
- Assign / reassign, activity notes (call, note, task; which party was contacted).
- Phone reveal for assigned Sales only, written to `access_logs`.
- Duplicate markers, loss reason, "open case" action.
- Stage filter, add-lead in the page title, SLA overdue hours, confirm/cancel viewing requests.

**Screens served:** APP-15, APP-16, APP-18, APP-33, APP-41, ADM-07.

**Tests:**
- UAT-09: inquiry creates a lead and returns the Sales number from settings.
- UAT-10: seller interest response has no buyer PII.
- UAT-11: changing the Sales number in settings changes the inquiry response.
- Phone reveal creates an access log row; unassigned Sales gets 403.

---

### Phase 6 — Custom buyer requests and transfer cases (BRD gate G4)

**Data:** `buyer_requests` (criteria JSON + normalized fields), `request_matches`, `cases`, `case_history`.

**Buyer requests:**
- `POST /api/v1/buyer-requests` — BRD 6.1 fields: intent, segment preference, unit type, governorate/area, developer/project, area min/max, bedrooms, finishing, budget min/max, down payment, monthly installment, duration, timeline, priorities, contact method, consent. Always 201, even with zero matches.
- `GET /api/v1/my/buyer-requests` · `/{id}` — client-safe status and options shared by Sales (no owner PII).
- Admin (ADM-08): assign, link matching listings, mark options sent, record buyer response, close / convert to case. Pipeline:
  `submitted → new/unassigned → assigned → contacting_buyer → matching → options_shared → negotiation/visit → converted | closed_no_match`

**Cases:**
- Admin (ADM-09): create case from qualified lead; link buyer, seller, listing; stage transitions with reason; internal notes; developer approval letter upload; missing documents count.
- Every transition writes `case_history` (actor, old/new stage, reason, time).
- `GET /api/v1/my/cases/{id}` — external label timeline only; user can upload a requested document.

**Screens served:** APP-17, APP-19, APP-20, ADM-08, ADM-09.

**Tests:**
- UAT-12: custom request with no matches still created with tracking ID.
- UAT-13: assigned Sales can act; others denied private contact.
- UAT-15: stage change creates audit row; app sees only the safe label.

---

### Phase 7 — Exit estimate and commissions

**Estimate:**
- `GET /api/v1/listings/{id}/estimate`
- Returns inputs used and the verification status of each.
- Cancellation-vs-transfer comparison only when the cancellation % exists (seller-provided or staff-verified).
- Missing % → `{ "computable": false, "reason": "cancellation_percent_missing" }`.
- Optional market range from comparable published listings, labelled as estimate.
- Disclaimer text from CMS.

**Commissions:**
- Data: `commissions`, `commission_history`.
- Admin (ADM-10): create on closed case; payer party, basis/rate/amount, invoice reference, status `earned | due | paid | waived`; reason on changes; CSV export.
- Finance records/edits (pending until Operations approve); Sales view assigned-case rows only; every change audited. Default rate is a setting, not hard-coded.

**Screens served:** APP-32, ADM-10.

**Implemented:** owner-only `GET /listings/{id}/estimate`; Phase 17 `POST /estimates` (BRD APP-17) accepts manual contract inputs with the same `computable: false` / disclaimer rules; missing % never invents a penalty; CMS disclaimer `exit_estimate`; ADM-10 desk (`commissions` + `commission_history`); Finance edit pending Operations approve, Sales view own rows (Phase 16 / BRD §8.2); default rate/status/tax from settings/config (DEC-02/03); month filter; SVG monthly bars (sale value vs commission) and unit-type donut. Notifications stay Phase 8.

**Tests:**
- UAT-19: estimate without cancellation % → `computable: false`.
- UAT-16: reviewer PATCH commission → 403; finance succeeds and audit row exists.

---

### Phase 8 — Notifications

**Data:** `notifications`, `notification_deliveries`, `notification_templates` (ar/en).

**Behavior:**
- In-app inbox is the source of truth; push (FCM/APNs) is best effort via queued jobs.
- Idempotency key per event; retries never send duplicates.
- Expired device tokens are invalidated; provider failures never block the transaction.
- Marketing opt-in is separate from operational notifications.
- Push bodies contain no third-party PII.

**Endpoints:**
- `GET /api/v1/notifications` · `POST /api/v1/notifications/{id}/read` · `POST /api/v1/notifications/read-all`
- `GET|PATCH /api/v1/me/notification-preferences`
- Admin (ADM-11): templates, test send to staff, scheduled broadcast to opted-in users, delivery log.

**Events:** listing submitted, needs changes, published, rejected; inquiry created (buyer + Sales); seller anonymized interest; buyer request created / updated; case stage changed (external label); Sales follow-up overdue (scheduled job, staff only).

**Screens served:** APP-14 (behind flag), APP-35, APP-37, ADM-11.

**Implemented:** in-app inbox (`notifications` + `notification_deliveries` + `notification_templates`); idempotency per event; push via queued job; log driver by default, FCM HTTP v1 when `AQDAR_PUSH_DRIVER=fcm` (no Firebase SDK); marketing opt-in separate from operational; quiet hours and smart alerts from settings (DEC-09 / APP-14 off by default). ADM-11 templates, announcements, category copy, banners (البانرات), shared test/schedule form, staff test send using visible copy, scheduled broadcast to opted-in users using stored title/body, send log. Failures never block the listing/CRM transaction. Already-sent push deliveries are not retried.

**Tests:**
- UAT-14: status change persists notification and queues safe push.
- UAT-18: push disabled → in-app notification still readable.
- Push payload contains no other party's name or phone.

---

### Phase 9 — Admin dashboard, reports, settings, hardening and handover (BRD gate G5)

**Implemented:** ADM-01 KPIs + needs-action + funnel + date presets (year / month / last 30 days / 6 months) + period-over-period KPI % + Egypt governorate map from live listing counts. ADM-12 CSV reports (no PDF; no phones/email by default; first-response KPI + overview SVG; owner is Sales staff; matching date presets). ADM-13 named platform form with General/Security/Audit tabs (Sales contact applies immediately, assignment rule, maintenance, smart-notification flag, last-change actor). ADM-14 immutable audit/access search + actor dropdown + 24-month prune. Round-robin assigns Sales only. Marketplace 503 in maintenance; `/up` and Blade stay up. OpenAPI from routes (`aqdar:openapi`). Monitoring via ADM-01 + `aqdar:ops-health`. `aqdar:backup` dumps Postgres only when `AQDAR_BACKUP_PATH` is set. Backup/restore and deploy/rollback: [OPS.md](OPS.md), [DEPLOY.md](DEPLOY.md).

**Admin:**
- ADM-01 executive dashboard: KPIs, "needs action now" counters, conversion funnel, date filters; counts must reconcile with underlying records.
- ADM-12 reports: filters by status, source, owner, segment, date; CSV export without phones by default; full export requires permission and is logged.
- ADM-13 settings: platform name, support email, Sales name/phone/hours (applies immediately), assignment rule, feature flags, maintenance mode, security toggles.
- ADM-14 audit search: immutable, searchable, retention per policy.

**Hardening and handover:**
- Throttling on search and listing endpoints to limit scraping.
- OpenAPI documentation generated from routes.
- Full privacy negative test suite (UAT-01 … UAT-20).
- Monitoring of errors, failed jobs, notification failures.
- DB and private file backups with a restore drill.
- Separate staging and production; reproducible deploy and rollback docs.
- README: migrate, seed, test, and how privacy is enforced.

**Tests:**
- UAT-17: export includes only authorized fields.
- UAT-20: staging handover smoke tests pass.

---

## 6. Client decisions that block phases

| Decision | Topic | Blocks |
|----------|-------|--------|
| DEC-07 | OTP provider, consent copy, password yes/no | Phase 1 |
| DEC-01 | Distressed A/B labels and eligibility | Phases 2, 3 |
| DEC-06 | Public vs staff-only fields, location precision | Phase 3 |
| DEC-04 | Mandatory document types, who validates | Phase 4 |
| DEC-05 | Official Sales number(s), hours, routing | Phase 5 |
| DEC-08 | CRM status labels, SLAs, follow-up ownership | Phases 5, 6 |
| DEC-02 / DEC-03 | Price/overprice policy per segment; commission basis, earned stage, tax | Phase 7 |
| DEC-09 | Notification copy, marketing consent, quiet hours | Phase 8 |

Until decided, these values live in master data or settings, not in hard-coded business logic.

---

## 7. Dependencies to approve before the relevant phase

| Need | Options | Phase |
|------|---------|-------|
| Roles and permissions | `spatie/laravel-permission` or custom tables | 1 |
| OTP / SMS provider | Client-selected provider (DEC-07) | 1 |
| Object storage driver | `league/flysystem-aws-s3-v3` | 0 / 4 |
| Image processing (EXIF strip, derivatives, watermark) | `intervention/image` or similar | 4 |
| Push delivery | FCM HTTP v1 client | 8 |
| OpenAPI generation | `knuckleswtf/scribe` or swagger | 9 |

No dependency is added without explicit approval.

---

## 8. Mapping summary — screens to phases

| Phase | Mobile screens | Admin pages |
|-------|----------------|-------------|
| 1 | APP-01, 02, 03, 21, 38 | ADM-04, 06 |
| 2 | APP-04, 05, 13, 22, 34 | ADM-05, 11 (content), 15 |
| 3 | APP-04 … 11, 23 … 31 | — |
| 4 | APP-24, 25, 30, 36 | ADM-02, 03 |
| 5 | APP-15, 16, 18, 33, 41 | ADM-07 |
| 6 | APP-17, 19, 20 | ADM-08, 09 |
| 7 | APP-32 | ADM-10 |
| 8 | APP-14, 35, 37 | ADM-11 |
| 9 | — | ADM-01, 12, 13, 14 |

APP-12 and APP-39 (compare) and APP-40 (share) can be served from existing listing endpoints; a dedicated `/compare` endpoint is optional.

Admin UI is built **in the same phase as the API**, not all 15 pages at once. Visual contract and page-by-page map: [DASHBOARD_PHASES.md](DASHBOARD_PHASES.md).
