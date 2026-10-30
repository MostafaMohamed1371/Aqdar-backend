# AQDAR testing — order and UI contract

Yes. After **each** implementation slice we run tests in this order. We do **not** jump to E2E first, and we do **not** E2E all 41 mobile + 15 admin screens in one go.

```
1. Implement the phase (API, and only that phase’s dashboard screens)
2. PHPUnit unit tests
3. PHPUnit integration tests
4. E2E against the UI contract (mockup now, real Blade admin + Flutter later)
```

Visual contract (reviewed):

| File | What E2E must match |
|------|---------------------|
| `aqdar-ui-v2/flutter.html` | Mobile APP-01 … APP-41 |
| `aqdar-ui-v2/dashboard.html` | Admin ADM-01 … ADM-15 |
| `aqdar-ui-v2/ui.css` | Dashboard look (tokens, RTL, tables) |
| `aqdar-ui-v2/index.html` | Product rules (F1–F12), not a runtime screen |
| `aqdar-ui-v2/styles.css` | Hub only — **not** the admin or app theme |

Those HTML files are **prototypes**, not production Flutter or the Blade admin. Until those exist, E2E:

1. Locks the mockup still contains every screen ID (contract).
2. Walks the journeys in the clickable HTML (optional Selenium).
3. Later, the **same screen IDs** run against the real dashboard and mobile builds.

---

## Layer 1 — Unit (PHPUnit)

**Where:** `tests/Unit/`  
**Command:** `php artisan test --testsuite=Unit`

No HTTP. Test services, enums, state machines, serializers, privacy helpers, public-code generator.

Examples by later phase: listing cannot jump `draft → published`; public listing array has no `phone`; estimate returns `computable: false` without cancellation %.

---

## Layer 2 — Integration (PHPUnit)

**Where:** `tests/Integration/`  
**Command:** `php artisan test --testsuite=Integration`

HTTP → Laravel → PostgreSQL database `aqdar_eg_testing` (`phpunit.xml`). Not the app database `aqdar_eg`.

Examples: guest GET unpublished listing → 404; inquiry creates lead and returns Sales number; reviewer PATCH commission → 403.

---

## Layer 3 — E2E (UI match)

**Where:** `tests/Unit/UiMockupContractTest.php` (always) and `qa/python/e2e/` (browser, optional)

**Command (contract):** `php artisan test --filter=UiMockupContractTest`

**Command (browser mockup):**

```bash
export AQDAR_UI_MOCKUP_PATH="/Users/mostafa.osmananmat.sa/anaylsis project/aqdar-ui-v2"
QA_RUN_E2E=1 pytest qa/python/e2e/test_mockup_screens.py
```

If `AQDAR_UI_MOCKUP_PATH` is unset, browser E2E for mockups is skipped. The PHP contract still asserts the frozen screen list in `tests/Fixtures/ui-screen-contract.json`.

E2E is **per phase**, same map as [DASHBOARD_PHASES.md](DASHBOARD_PHASES.md):

| Phase | Mobile E2E | Dashboard E2E |
|-------|------------|---------------|
| 0 | Contract: APP-01…41 and ADM-01…15 exist in mockup | none built |
| 1 | APP-01, 02, 03, 21, 38 | ADM-04, ADM-06 + shell |
| 2 | APP-04, 05, 13, 22, 34 | ADM-05, 15, 11 (content) |
| 3 | APP-04…11, APP-23…31 | ADM-02 read-only peek |
| 4 | APP-24, 25, 30, 36 | ADM-02, 03 |
| 5 | APP-15, 16, 18, 33, 41 | ADM-07 |
| … | same as phase map | same as phase map |

A screen is “matched” when:

1. The mockup still has that `APP-*` / `ADM-*` id.
2. The API for that phase returns the fields the screen shows (no seller phone on APP-09, Sales number on APP-16, etc.).
3. When the Blade admin and Flutter app exist, the browser/device test opens that screen and checks the same rules.

---

## Commands for a finished slice

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
php artisan test --filter=UiMockupContractTest
```

Do not add Playwright/Pest/new packages without approval. Reuse PHPUnit + existing Python Selenium.

---

## Phase 2 coverage

**PHPUnit unit:** `CategoryProtectionTest`, `HasLocalizedNameTest`, `CategoryServiceTest`, `MasterDataServiceTest`, `CatalogReadServiceTest`.

**PHPUnit integration:** `CatalogPhase2Test` (UAT-02 tree, distressed invariant, locale, seed, inactive hide, write lock, ADM screens, tags, A/B help, other-last for developers/projects) and `DashboardCatalogCrudTest` (ADM-05 / 11 / 15 writes, retire-not-delete including categories, tags, banner upload + link_url, toggle without clearing FKs).

**E2E:** `qa/python/e2e/test_phase2_api.py` (live catalog GETs including tags), `test_mockup_screens.py` Phase 2 IDs, Selenium ADM-05 / 11 / 15.

## Phase 3 coverage

**PHPUnit unit:** `ListingStateMachineTest`, `ContactLeakScannerTest` (phone, email, URL/WhatsApp, QR).

**PHPUnit integration:** `ListingPhase3Test` — wizard submit + review_reference, UAT-05 unpublished 404, UAT-08 no seller PII/documents, UAT-06 phone/email/WhatsApp/QR held + audit, owner isolation, invalid edits after submit, resubmit, consent required, invalid transition, distressed child required + A/B from child category, retired catalog rejected, public filters (q/tag/segment/sort/pagination/price/area/down payment/installment), labelled financial summary + amenities/tags, optional Bearer `is_favorited`, counts, locale title, hold/closed hidden, my-listings status chips, installment items, integer rooms, map rounding, favorites, remaining wizard UI fields (furnished, handover_date, neighborhood, contract date, payment system, assignment restrictions), public show increments `view_count`. Successful submit paths attach mandatory documents (Phase 4). `DashboardListingPeekTest` — reviewer/ops can open ADM-02, sales/finance 403, tab/search, ADM-01 live listing counts; approve/publish actions are covered in Phase 4.

**E2E:** `qa/python/e2e/test_phase3_api.py`, `test_mockup_screens.py` Phase 3 IDs (including ADM-02), Selenium ADM-02 desk.

## Phase 4 coverage

**PHPUnit unit:** `DocumentTypeTest` (required types by segment), `ListingStateMachineTest` (rejected → in_review reopen), `DocumentWatermarkerTest` (PDF footer stamp keeps original page; images stay JPEG).

**PHPUnit integration:** `ListingPhase4Test` — submit blocked without mandatory documents, distressed letter required, UAT-04, UAT-07 (caption + filename), owner isolation, MIME reject, media delete, video upload, in-review document replacement, owner `/my/documents` has no path, approved photos on public listing. `DashboardModerationTest` — reviewer approve/request-changes/reject/reopen, cannot publish, operations publish/hold/close, declared vs verified including seller recovery flag, sales documents 403 + audit, signed download logged, held media public only after staff approve, reject removes public derivative. `DashboardDocumentsTest` — ADM-03 checklist + versions, approve/reject, unsigned/expired download 403, PDF signed download contains `AQDAR staff-{id}`. `DashboardListingPeekTest` — reviewer cannot publish, developer search, tab links keep segment/alerts.

**E2E:** `qa/python/e2e/test_phase4_api.py`, `test_mockup_screens.py` Phase 4 IDs (APP-24/25/30/36, ADM-02/03), Selenium ADM-02 actions note + ADM-03.

## Phase 5 coverage

**PHPUnit integration:** `InquiryPhase5Test` — UAT-09 inquiry creates lead + Sales number, UAT-11 settings phone change, UAT-10 seller interest has no buyer PII, duplicate flag, unpublished 404, owner cannot inquire own listing, contact leak in message, consent + auth, my inquiries isolation + status filter + callback, listing card / next_steps / inquiry_type, seller kind+counts, appointment creates lead + GET /my/appointments (consent optional), duplicate_of_id after converted. `DashboardCrmTest` — ops ADM-07, reviewer/finance 403, Sales assigned-only, unassigned reveal 403, assigned reveal writes `access_logs`, activity New→Contacted, open case creates `TR-` transfer case, Sales cannot assign, ops can assign, SLA overdue hours + add-lead + stage filter, assign syncs inquiry to in_review, confirm appointment → visit_scheduled / inquiry replied.

**E2E:** `qa/python/e2e/test_phase5_api.py`, `test_mockup_screens.py` Phase 5 IDs (APP-15/16/18/33/41, ADM-07), Selenium ADM-07.

## Phase 6 coverage

**PHPUnit integration:** `BuyerRequestPhase6Test` — UAT-12 custom request 201 + `BR-` with zero matches, guest 401, consent, contact leak in priorities, buyer isolation + callback, unsent matches hidden, mixed inbox kinds, client case API has no `internal_stage`, buyer document upload, BRD 6.1 criteria persist + score ≥ 40, sent options hide owner PII, listing-publish rematch, `closed_no_match` → client `closed`, `GET /my/cases`, inbox `in_review` includes open cases with `label` = client label. `DashboardCaseTest` — ops ADM-08/09, reviewer 403, finance 403 on requests and 200 on cases, Sales assigned-only (UAT-13), assigned Sales stage writes `case_history` (UAT-15), unassigned Sales 403, send match + convert to case, Sales cannot assign, unsigned download 403, signed download watermarked + `access_logs`, developer letter upload, desk add-request, type tabs / region / combined closed chevron, market + refresh on detail, lifecycle filter + last update, unassigned Sales cannot send a match. `RequestMatchServiceTest` — perfect catalog score 100 persists; score 20 is not stored.

**E2E:** `qa/python/e2e/test_phase6_api.py`, `test_mockup_screens.py` Phase 6 IDs (APP-17/19/20, ADM-08/09), Selenium ADM-08/09.

## Phase 7 coverage

**PHPUnit integration:** `EstimatePhase7Test` — UAT-19 guest 401, missing cancellation % → `computable: false` / `cancellation_percent_missing`, declared percent computes penalty, verified wins over declared, 0% is computable, other user 404, CMS `exit_estimate` disclaimer ar/en, fallback source, input `verified`/`required` flags, market min/max from published comparables. `DashboardCommissionTest` — finance ADM-10 KPIs + table + completed-case select, listing unit·area, last-change from←to, reviewer 403 + `permission_denied` audit (UAT-16), sales/ops can open (view/approve, no create), create on completed case (2.5% of 3,200,000 = 80,000) + history + `commission.created` audit, open case 422, duplicate 422, PATCH paid with reason + history + `commission.updated`, missing reason 422, fixed amount, CSV has no phone/email, default rate from settings, month filter on table/KPIs/CSV, SVG monthly bars + unit-type donut (waived excluded). `EstimateServiceTest` — missing percent never invents a penalty.

**E2E:** `qa/python/e2e/test_phase7_api.py`, `test_mockup_screens.py` Phase 7 IDs (APP-32, ADM-10), Selenium ADM-10 KPIs + table + completed case + month select + SVG charts.

## Phase 8 coverage

**PHPUnit integration:** `NotificationPhase8Test` — guest 401 (inbox + GET/PATCH prefs), UAT-14 publish persists in-app + push without PII, UAT-18 push disabled still readable, inquiry notifies buyer + seller without buyer name/phone, case stage uses client label, read / read-all / preferences, GET prefs defaults, quiet hours skip push, listing submitted / needs-changes / rejected, buyer request created / updated, overdue follow-up job, broadcast custom copy to opted-in only, cross-user read 404, unread + category filters, already-sent push is not retried, smart category empty when flag off, expired token cleared, idempotent duplicate publish; FCM HTTP v1 fake send without PII and UNREGISTERED expiry. `DashboardNotificationTest` — ADM-11 templates + copy note + announcements/categories/البانرات tabs, reviewer 403, template rejects phone, staff test send uses visible form copy.

**E2E:** `qa/python/e2e/test_phase8_api.py` (inbox + GET/PATCH prefs), `test_mockup_screens.py` Phase 8 IDs (APP-14/35/37, ADM-11), Selenium ADM-11 templates + announcements + category copy + banners + send log.

## Phase 9 coverage

**PHPUnit integration:** `DashboardPhase9Test` — ADM-01 KPIs/funnel/needs-action/date filter reconcile with listing/lead counts and exclude out-of-range leads; inquiries KPI uses the selected range; unassigned-leads filter; UAT-01 one buyer+seller identity; UAT-17 CSV without phones/email + full export logged; requests/revenue/sales CSV; first-response KPI + overview SVG; reviewer **and Sales** 403 on reports; operations 403 on settings/audit; finance can export CSV but not phones; ADM-13 tabs + last change; ADM-14 access-log search/export and no delete; listing search 429; maintenance 503 on listings with desk + `/up` still 200; round-robin assigns Sales on inquiry and custom request; UAT-20 `/up` + `/config` + dashboard login; failed_jobs count; prune/ops-health/backup-check/`aqdar:backup` (empty path skips dump)/openapi commands; ADM-01 30-day preset + KPI deltas + Egypt map counts; map/counts throttle; CSV segment filter; compact commission KPI; private-file backup copy; default locale; ADM-14 all-types mix. `PrivacyNegativeSuiteTest` — UAT-03 non-distressed segment + verified public price + no staff-only fields, UAT-05/08/17 privacy negatives plus public config has no secrets. `DashboardCatalogTest` settings PUT named form. `UiMockupContractTest` Phase 9 IDs.

**E2E:** `qa/python/e2e/test_phase9_api.py` (`/up`, `/config`), `test_mockup_screens.py` Phase 9 IDs (ADM-01/12/13/14), Selenium ADM-01 date filter + charts + Egypt map + 30-day preset + English locale, ADM-12 first-response + overview SVG + CSV/PDF + date presets, ADM-13 tabs/sales/maintenance, ADM-14 actor filter + immutable log.

## Phase 10 / 11 coverage

**PHPUnit:** `OtpAuthTest` (consent on first OTP send, resend_after, first unverified email, SMS 503), `OtpServiceTest`, `HttpSmsGatewayTest` / `LogSmsGatewayTest` / `NullSmsGatewayTest`, `DashboardTest` password/role session invalidation and login audit. `OtpAuthTest` suspended user gets **422** on `phone` at `otp/send` (no code is issued). Marketplace password/2FA, staff 2FA/IP, and a real SMS vendor stay out (DEC-07).

**E2E:** `qa/python/e2e/test_phase10_api.py` — first send without consent 422; accepted send returns `resend_after` and no code; verified user has consent timestamps; `PATCH /me` locale `en` ok and `fr` 422.

## Phase 12 coverage

**PHPUnit integration:** `ListingPhase12Test` — approve/publish blocked when required docs are pending or the latest version is pending; unpublish 404 including `/property/{code}`; live title/financials/media edits return to review; submit snapshot on APP-25 `needs_changes`; PUT/GET expose `previous_snapshot` + `reviewer_notes`; planted snapshot on resubmit; under_offer stays public; archived is not; APP-23 `published`/`live` chips include under_offer; ADM-02 tab copy + list Hold/Archive. `ListingStateMachineTest` includes under_offer/archived transitions.

## Phase 13 coverage

**PHPUnit integration:** `ListingPhase13Test` — public JSON, listing index, favorites, and share hide `unit_code` / assigned reviewer / commission policy; public still returns `phase`, `parking`, `offer_expires_at`; public `GET /categories` has no policy code; owner specs/financials round-trip and parking `51` is 422; seller cannot set `assigned_reviewer_id`; live phase/parking edits return to review; installment `paid` + evidence on the same listing only and ADM-02 shows the filename; category `commission_policy_code` copied onto the listing, created on ADM-15, and is not a payout amount; staff assign / unassign reviewer, inactive and Sales cannot be assigned, Sales 403 on the route, audit `listing.reviewer_assigned`; desk search by `unit_code`.

**E2E:** `qa/python/e2e/test_phase13_api.py` — public categories omit `commission_policy_code`; public listings omit `unit_code`; owner specs round-trip `phase` / `parking` / `unit_code`.

## Phase 14 coverage

**PHPUnit integration:** `CasePhase14Test` — seller lists/opens own case, other user 404, no buyer PII / `internal_stage`; hold and cancel write `case_history` old/new status + actor + reason; `waiting_for_information` on ADM-09 lifecycle column; stage change notifies buyer and seller without PII; seller mixed inbox includes the case; `timeline[].at` from history; `lost` notifies both parties; completing via stage writes status history; seller document upload. `PrivacyNegativeSuiteTest` seller `GET /my/cases/{id}` hides buyer PII.

**E2E:** `qa/python/e2e/test_phase14_api.py` — guest 401 on `/my/cases`; authenticated list has no `internal_stage` or counterparty objects; seller can open a staff-created case without buyer objects (`timeline[0].at` present).

## Phase 15 coverage

**PHPUnit integration:** `NotificationPhase15Test` — hold notifies seller (`listing_held`) via `GET /notifications` with no phone, push `sent`, and no staff copy; unpublish and archive are `listing_suspended`; submit notifies `approve_listing` staff (`staff_new_submission`) and skips finance/seller (seller API inbox has no staff event keys); assigned reviewer gets the submission instead of the full queue; inactive assigned reviewer falls through to the queue; resubmit creates a new unread staff row (version in the idempotency key); staff push is skipped (`staff_in_app_only`); unassigned inquiry lead notifies ops not sales or parties; assigned inquiry does not fire `staff_unassigned_lead`; buyer request notifies the CRM queue including Sales without buyer PII; guest is redirected from `/dashboard/inbox`; inbox lists the unread badge, marks read, hides Mark-read after, other staff 404. `NotificationPhase8Test` UAT-14 still passes. `DashboardNotificationTest` templates include `listing_held` / `listing_suspended` / `staff_new_submission` / `staff_unassigned_lead` / `staff_new_buyer_request`.

**E2E:** `qa/python/e2e/test_phase15_api.py` — guest 401 on `/notifications`; authenticated inbox payload has no phone keys.

## Phase 16 coverage

**PHPUnit integration:** `StaffPhase16Test` — reviewer cannot PATCH commission; finance cannot approve listings; sales see own commission row only (no add/CSV/PATCH); finance open ADM-09, 403 on leads/requests and on early-pipeline stage / lifecycle / create / assign / documents, 200 on `schedule_transfer` then `completed` after developer approval (`schedule_transfer` on an early case stays 403); operations GET ADM-10, cannot create/PATCH amounts, POST approve writes `commission.approved`; finance create stays pending until ops approve, then finance PATCH re-pends; Super Admin create auto-approves; ADM-06 matrix checkmarks for finance stages / ops approve / sales view-own; finance sidebar links ADM-09+10 not leads/listings; sales sidebar links ADM-10. `DashboardCommissionTest` / `DashboardCaseTest` keep the 200/403 split.

**E2E:** `qa/python/e2e/test_phase16_api.py` — public `/config` does not leak staff permission slugs. Role checks stay on the Blade desk.

## Phase 17 coverage

**PHPUnit unit:** `MimeUploadScannerTest` — PDF magic accepted; EXE magic named `.pdf` rejected; PNG magic named `.pdf` rejected; `.exe` extension rejected. `ClamAvUploadScannerTest` — missing binary falls back to MIME and still rejects EXE magic; `FOUND` exit rejects. `EstimateServiceTest` — listing and manual missing % never invent a penalty.

**PHPUnit integration:** `EstimatePhase17Test` — guest POST `/estimates` 401; empty percent `computable: false` / `source: manual` / inputs `verified: false`; 10% of 1,450,000 → penalty 145,000 `percent_source: manual`; `0` is computable; other-user `listing_id` 404; owned `listing_id` fills market comparables; `area_id` + `unit_type_id` without listing fills market; CMS `exit_estimate` disclaimer. `EstimatePhase7Test` asserts GET listing estimate `source: listing`. `ListingPhase17Test` — EXE payload named PDF is not stored (service + HTTP); case documents same; staff-approved video keeps `public_path` null; owner `kind` is `video` and `url` is null; public `photos` and `GET /listings/{id}/media` omit that video and still return an approved photo. Existing `ListingPhase4Test` covers video upload-on-create `kind: video`.

**E2E:** `qa/python/e2e/test_phase17_api.py` — guest POST `/estimates` 401; authenticated missing % is not computable; posted % computes penalty; other-user `listing_id` is 404.

## Phase 18 coverage

**PHPUnit integration:** `AccountPhase18Test` — guest POST `/me/close` 401; missing reason 422; close archives published listings, revokes the token, returns `data.is_close_pending`, login/OTP 422 pending; `GET /me` still bound via Sanctum shows close fields; ADM-04 pending badge + Complete/Decline; Operations complete anonymizes and keeps the row; decline clears pending; Sales 403 on complete/decline; pending session 403 on `PATCH /me`. `SavedSearchPhase18Test` — guest 401 (including PATCH); CRUD + PATCH name/notify/filters with smart flag off; cap 20; other-user delete/PATCH 404; publish with smart on writes `smart_saved_search` without seller PII, buyer `GET /notifications?category=smart` sees it, second job is idempotent; `notify: false` skips; smart off writes none; owner is not notified of their own listing; verified asking-price drop on a public listing writes `smart_price_drop` with public `price` and no PII. `BroadcastPhase18Test` — Operations create stays `pending` and cannot approve own; Super Admin approve then sends; Super Admin create still sends immediately; due dispatcher skips unapproved; approved future broadcast sends after travel + `dispatchDueBroadcasts`; Operations GET schedule 200 and 403 on catalog POST; Reviewer 403; ADM-06 matrix has `approve_broadcasts` for Operations. `NotificationPhase8Test` Super Admin broadcast still passes. `CatalogPhase2Test` allows Operations GET `/dashboard/content` (schedule) and keeps other Phase 2 screens 403.

**E2E:** `qa/python/e2e/test_phase18_api.py` — guest 401 on `/me/close` and `/saved-searches`; authenticated saved search 201, PATCH, DELETE; close returns `is_close_pending` then `/me` is 401.

## Post-G5 coverage

**PHPUnit integration:** `SupportChatTest` — user opens Sales-only thread (`SUP-`), contact leak 422, other user 404, staff reply appears as `author: sales` with no staff id, unassigned Sales 403, reviewer 403 on desk. `PublicShareTest` — published `/property/{code}` hides seller phone/email, unpublished 404, `share_url` on public listing JSON, maintenance 503. `DashboardPhase9Test` PDF export starts with `%PDF` and omits phones by default.

