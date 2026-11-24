# AQDAR Blade admin

Staff desk is **Blade**, restyled from `dashboard.html` + `ui.css` (not the prototype hub `styles.css`). Arabic menu is RTL (right); English is LTR (left). Light desk only.

There is **no** `/register` for staff. Super admin creates marketplace users and staff from the desk.

When a phase ships a screen, append it here. Process: [DOCUMENTATION_PROCESS.md](DOCUMENTATION_PROCESS.md).

**Local login:** http://127.0.0.1:8000/dashboard  
**Seeded staff:** `test@example.com` / `password`

**Staff guide (all screens + workflows):** `/dashboard/guide` (any signed-in staff) · PDF `/dashboard/guide/pdf` · narrative: [DASHBOARD_WORKFLOWS.md](DASHBOARD_WORKFLOWS.md)

---

## Phase 1 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| — | Staff login | `GET/POST /dashboard` | Guest |
| ADM-01 | Executive dashboard | `GET /dashboard` | Any signed-in staff |
| ADM-04 | Users & identities | `/dashboard/users` | `view_users` |
| ADM-04 | Add user | `/dashboard/users/create` | `suspend_user` |
| ADM-04 | Edit user | `/dashboard/users/{id}/edit` | `suspend_user` |
| ADM-06 | Staff & roles | `/dashboard/staff` | `manage_staff` |
| ADM-06 | Add staff | `/dashboard/staff/create` | `manage_staff` |
| ADM-06 | Edit staff | `/dashboard/staff/{id}/edit` | `manage_staff` |

`manage_staff` is **super admin** only. `suspend_user` is super admin + operations. Sales may list users, not mutate them.

### ADM-04 actions

- Create / edit / delete marketplace user (email or phone required)
- Reveal phone (logged) — `reveal_phone`
- Suspend (reason required) / reactivate — `suspend_user`
- Index emails and phones stay masked until reveal

### ADM-06 actions

- Create staff (name, email, password, role)
- Edit (password optional — blank keeps current)
- Delete (cannot delete self; cannot remove last super admin)
- Revoke / restore access (cannot revoke self)

Roles: `super_admin`, `operations`, `reviewer`, `sales`, `finance`.

## Phase 2 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-05 | Developers & projects | `/dashboard/developers` | `manage_catalog` (super admin) |
| ADM-11 | Content (banners, FAQs, support) | `/dashboard/content` | `manage_catalog`; Operations may open schedule via `approve_broadcasts` |
| ADM-15 | Categories | `/dashboard/categories` | `manage_catalog` |
| ADM-15 | Locations | `/dashboard/locations` | `manage_catalog` |
| ADM-15 | Unit types | `/dashboard/unit-types` | `manage_catalog` |
| ADM-15 | Amenities | `/dashboard/amenities` | `manage_catalog` |
| ADM-15 | Tags | `/dashboard/tags` | `manage_catalog` |

Distressed A/B (and other protected slugs) cannot be deleted or deactivated. Category create/edit stores `name_ar` / `name_en` and `help_ar` / `help_en` for APP-05. Reference rows are **retired** (`is_active=false`), not hard-deleted; the controlled «other» developer/project cannot be retired. ADM-05 is catalog lookup (edit, retire, project count) plus staff-only company CRM fields on developers (contact person, phone, email, website, internal notes). Those fields never appear in any marketplace API. Notification templates on ADM-11 wait for Phase 8. Banner images store on the `public_media` disk.

Settings Blade is ADM-13 (Phase 9). Marketplace **API** writes stay 403.

## Phase 3 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-01 | Executive dashboard listing counts | `GET /dashboard` | Any signed-in staff |
| ADM-02 | Listings moderation (read-only peek) | `/dashboard/listings` | `approve_listing` |
| ADM-02 | Listing declared vs verified | `/dashboard/listings/{id}` | `approve_listing` |

`approve_listing` is super admin, operations, and reviewer. Sales and finance get 403.

Queue tabs: all, in review (submitted / in_review / resubmitted), needs changes, approved, published (published / under offer), hold (hold / closed / archived), rejected, draft. Search is title or public code. Phase 3 shipped the peek; **actions are Phase 4** below.

---

## Phase 4 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-02 | Listing queue + filters | `/dashboard/listings` | `approve_listing` |
| ADM-02 | Approve / request changes / reject / reopen | `POST /dashboard/listings/{id}/…` | `approve_listing` (reason required) |
| ADM-02 | Publish / hold / close | `POST /dashboard/listings/{id}/publish` (hold, close) | `publish_listing` |
| ADM-02 | Unpublish / under offer / archive (Phase 12) | `POST /dashboard/listings/{id}/unpublish`, `…/under-offer`, `…/archive` | `publish_listing` (reason required) |
| ADM-02 | Verify financials | `POST /dashboard/listings/{id}/verify-financials` | `approve_listing` |
| ADM-02 | Approve / reject photos | `POST …/media/{id}/approve` | `approve_listing` |
| ADM-02 | Assign reviewer | `POST /dashboard/listings/{id}/assign-reviewer` | `approve_listing` |
| ADM-03 | Private document queue | `/dashboard/documents` | `open_private_documents` |
| ADM-03 | Checklist + versions | `/dashboard/documents/listing/{id}` | `open_private_documents` |
| ADM-03 | Approve / reject / request replacement | `POST /dashboard/documents/{id}/…` | `open_private_documents` |
| ADM-03 | Signed download | `GET /dashboard/documents/{id}/download` | `open_private_documents` + signed URL (~10 min) |

`approve_listing` is super admin, operations, reviewer. `publish_listing` is super admin and operations only — reviewer gets 403 on publish/hold/close. `open_private_documents` is super admin, operations, reviewer. Sales and finance get 403; the attempt is written to `audit_logs` as `permission_denied`.

Approve does not publish. Buyers only see `published`. Financial verification writes `verified_*` beside declared values (including remaining installments, next due date, seller recovery, cancellation percent). Image and PDF downloads are watermarked with the reviewer id; every open is `access_logs.document.opened` with the staff name. Queue tabs keep segment/alerts. ADM-03 shows required types for the segment, prior versions, reject, and a signed Open on the queue.

---

## Phase 5 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-07 | Sales CRM & leads | `/dashboard/leads` | `manage_crm` |
| ADM-07 | Lead detail | `/dashboard/leads/{id}` | `manage_crm` (Sales: assigned only) |
| ADM-07 | Assign / reassign | `POST /dashboard/leads/{id}/assign` | Operations + super admin |
| ADM-07 | Activity note / call / task | `POST /dashboard/leads/{id}/activity` | Assigned Sales or ops/admin |
| ADM-07 | Stage / lost / open case | `POST /dashboard/leads/{id}/status` | Assigned Sales or ops/admin |
| ADM-07 | Reveal buyer or seller phone | `POST /dashboard/leads/{id}/reveal` | Assigned Sales or ops/admin; written to `access_logs` |
| ADM-07 | Confirm / cancel viewing | `POST /dashboard/leads/{id}/appointments/{appointment}` | Assigned Sales or ops/admin; confirm → `visit_scheduled` |

`manage_crm` is super admin, operations, and Sales. Reviewer and finance get 403. Sales sees **assigned leads only**; unassigned reveal is 403. Phone/email stay masked until reveal. Duplicate flag, SLA badge `Overdue Nh` (default 2h from `config('aqdar.crm.sla_hours')`), stage filter in the toolbar, add-lead in the page title, and “open case” (marks `converted` and opens `TR-YYYY-#####`) match ADM-07. Add-lead supports campaign/referral with a buyer id. Staff confirm/cancel viewing requests on the lead detail.

ADM-01 `inquiries_this_month`, `custom_buy_requests`, `overdue_followups`, `active_transfers`, and `recorded_commissions` use live counts.

---

## Phase 6 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-08 | Buyer requests | `/dashboard/requests` | `manage_crm` |
| ADM-08 | Request detail | `/dashboard/requests/{id}` | `manage_crm` (Sales: assigned only) |
| ADM-08 | Assign | `POST /dashboard/requests/{id}/assign` | Operations + super admin |
| ADM-08 | Status / rematch / send / response / open lead / convert | `POST /dashboard/requests/{id}/…` | Assigned Sales or ops/admin |
| ADM-09 | Transfer cases | `/dashboard/cases` | `manage_crm` or `advance_finance_stages` |
| ADM-09 | Case detail | `/dashboard/cases/{id}` | `manage_crm` (Sales: assigned only) or Finance (all, finance stages only) |
| ADM-09 | Stage / lifecycle / developer letter | `POST /dashboard/cases/{id}/stage` etc. | Assigned Sales or ops (`advance_case` for stage). Finance: stage only, `schedule_transfer` / `completed` after developer approval. |
| ADM-09 | Signed document download | `GET /dashboard/cases/documents/{id}/download` | Signed URL + `manage_crm`; watermarked; `access_logs.document.opened` |
| ADM-09 | Title transfer checklist | `POST /dashboard/cases/{id}/checklist/{item}` | `manage_crm` (not Finance-only). Eight steps, from `aqdar.transfer_checklist` |
| ADM-09 | Escrow / payments (offline) | `POST /dashboard/cases/{id}/escrow`, `POST /dashboard/escrow/{id}/settle` | Record: `edit_commissions`. Release or refund with a reason: `approve_commissions` |
| ADM-09 | Suggested values review | `POST /dashboard/cases/documents/{id}/extraction` | `manage_crm`. Manual driver by default; staff confirm or reject each upload |

Type tabs on ADM-08: apartments / villas / land / investment. Table columns: code (link), buyer, unit type, region, price, area, status, owner. Pipeline chevrons are filters; the last step combines converted + closed. Request detail lists market (distressed / non-distressed), asking price, score, sent date, and a refresh-matches action. Convert opens a transfer case. ADM-09 KPI uses the building icon for awaiting developer. Table columns: code (link), listing, buyer, seller, internal stage, **lifecycle**, client sees, owner, missing docs, last update. Lifecycle filter: open / waiting for information / on hold / cancelled / lost / completed. Waiting-for-information is a lifecycle badge (`warn`), not an extra pipeline stage (BRD §9.2). Hold, cancel, lost, and waiting-for-information write `case_history` (actor, time, old/new status, reason). Completing via the stage form also writes status history. Chevrons filter by internal stage. Developer approval and title transfer are uploaded by hand — no government integration. Missing-documents count and a request-document note are staff tools; the app only sees the safe label.

---

## Phase 7 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-10 | Commissions & revenue | `/dashboard/commissions` | `edit_commissions` (Finance + Super Admin), `approve_commissions` (Operations), `view_commissions` (Sales, own rows) |
| ADM-10 | Create | `POST /dashboard/commissions` | completed transfer case only; unique per case; pending until Operations approve |
| ADM-10 | Update | `PUT/PATCH /dashboard/commissions/{id}` | Finance only; reason required; re-pends; writes `commission_history` + audit |
| ADM-10 | Approve | `POST /dashboard/commissions/{id}/approve` | Operations (`approve_commissions`) |
| ADM-10 | CSV | `GET /dashboard/commissions/export` | view permission + `export_csv`; no phones |

KPIs: total (non-waived), due, deal count, average percent rate. Month dropdown (`YYYY-MM`, last 12 months) filters KPIs, table, CSV, and the donut. SVG monthly grouped bars (last 6 months ending at the selected month, or now): teal sale value vs gold commission amount — no chart library. Donut splits recorded sale value into apartment / villa / land / investment (same buckets as ADM-08). Table: date, case code (link to ADM-09 when the staff has `manage_crm` or `advance_finance_stages`), payer (`buyer` / `seller` / `both`), listing as unit type · area, sale value, basis/rate, amount, invoice, status (`earned` / `due` / `paid` / `waived`) with inline update for Finance. Pending rows show a badge until Operations approve. Last change shows old → new status, reason, and date. Create uses a completed-case dropdown, not a raw numeric id. Reviewer PATCH is **403** (UAT-16). Operations approve and cannot edit amounts. Sales view assigned-case rows only. Default rate comes from setting `commission_default_rate` (config fallback 2.5). CMS disclaimer `exit_estimate` is seeded. The platform does not collect money. Staff can record offline payments against a case (ADM-09 escrow), which are held until released to the seller or refunded to the buyer, with a reason on each step. No payment gateway is connected.

---

## Phase 8 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-11 | Content & notifications | `/dashboard/content` | `manage_catalog` |
| ADM-11 | Templates | `PUT /dashboard/notifications/templates/{id}` | copy cannot include phones/emails/links |
| ADM-11 | Test send | `POST /dashboard/notifications/test-send` | in-app to active staff |
| ADM-11 | Broadcast | `POST /dashboard/notifications/broadcasts` | marketing opted-in users only; schedule supported |

Tabs match the mockup: templates, push, public announcements, banners (البانرات), FAQs, category copy (help text + ADM-15 edit), support (Phase 2), schedule, send log. Test send and schedule share one form (`formaction` for staff test). Recipient column uses `USR-{id}`, never a phone. APP-14 smart alerts stay behind `feature_smart_notifications` (off).

Header bell opens **`/dashboard/inbox`** (all signed-in staff). Rows are this staff account’s operational alerts (`staff_new_submission`, `staff_unassigned_lead`, `staff_new_buyer_request`, overdue follow-up, test send). Unread rows show an unread badge; Mark-read is hidden after `read_at`. Mark-read deep-links to ADM-02 / ADM-07 / ADM-08. Marketplace users never receive these. Staff push stays skipped (`staff_in_app_only`).

---

## Phase 9 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-01 | Executive KPIs, funnel, needs-action, `from`/`to` | `GET /dashboard` | Any signed-in staff |
| ADM-12 | Reports | `/dashboard/reports` | `export_csv` |
| ADM-12 | CSV | `/dashboard/reports/export?type=` listings\|users\|requests\|revenue\|sales\|ledger | `export_csv`; phones need `reveal_phone`; `ledger` also needs a commission permission (`view_commissions`, `edit_commissions` or `approve_commissions`) |
| ADM-13 | Platform settings | `GET/PUT /dashboard/settings` | `manage_catalog` (super admin) |
| ADM-14 | Activity log | `/dashboard/audit` | `manage_staff` (super admin) |

ADM-01 date range defaults to start of year → today. Presets: this year, this month (أشهر), last 30 days, last 6 months. Queue counters (in review, overdue, unassigned) are current, not date-filtered. Period KPIs (including inquiries in range) and the funnel use `from`/`to`. KPI % deltas compare the same-length previous window. “Leads without an owner” opens ADM-07 with `assigned_to=unassigned`. SVG monthly bars (3/6/12 months) + approved/in-review/rejected/hold donut + Egypt governorate map (Cairo, Giza, North Coast, other) from live listing counts. Failed jobs / failed pushes always show on ADM-01.

FR-073 basics on ADM-12: lead and request funnels (records created in range, by current stage), a commission ledger (by status and month; net revenue counts earned, due and paid, not waived or reversed; receivable is earned + due all time) and a flat run-rate forecast (average net revenue of the last 3 full months, projected 3 months; an estimate, not a commitment). Ledger and forecast are hidden from staff without a commission permission. No external BI tool and no ML.

ADM-12 CSV and PDF use the same rows. PDF is staff-only Helvetica of those columns (Arabic cells fall back to English/slug). Default files have no phone/email. `include_phones=1` requires `reveal_phone` and writes `report.exported_with_phones`. Users export as `USR-{id}`. Reviewer and Sales get 403. Rate KPIs include average first reply. Overview uses SVG bars (listings / requests / users). Owner filter is a Sales staff dropdown (`assigned_to` on requests and sales). Date presets match ADM-01; volume KPIs show period-over-period %.

ADM-07 includes `/dashboard/support` (Sales support chat with the marketplace user). Support chat also polls every 3 seconds as a fallback when Pusher is unavailable. The **whole staff desk** is realtime via a Cubit-style JS store (`window.deskCubit`): Pusher events `emit()` into one shared state and **patch DOM fields in place** (`data-live-row` / `data-live-field` / `data-live-kpi`) — no page reload and no HTML refetch. Inbox bell, support list/thread, listing/lead/request/case/user/commission status, and home KPIs update dynamically. Buyer↔seller chat stays forbidden on ADM-13.

ADM-13 push provider status shows the log driver or FCM HTTP v1 (credentials from env, never stored in settings). ADM-11 **Content → Push** shows an FCM checklist (driver, credentials, queue, device tokens), `POST /dashboard/notifications/fcm-verify` (Google OAuth probe), and `POST /dashboard/notifications/fcm-diagnostic` (live send to the newest marketplace `push_token`). CLI: `php artisan aqdar:fcm-status --verify`.

ADM-13 is a named form (not generic key/value CRUD) with General / Security / Audit tabs. Sales name/phone/hours apply immediately to `GET /config`. Assignment rule `manual` \| `round_robin` (Sales staff only). Maintenance 503s the marketplace API; the desk stays up. Staff 2FA (emailed code) and the IP allowlist are enforced only when `AQDAR_STAFF_TWO_FACTOR` / `AQDAR_STAFF_IP_ALLOWLIST` are set (off by default). Buyer↔seller chat is permanently **غير مسموح**. Currency EGP is locked. Signed URL, watermark, mask, and leak scan stay on in code. Last settings change is shown on the Audit tab.

ADM-14 searches `audit_logs` / `access_logs`. No delete route. Retention default 24 months (`aqdar:prune-audit`).

## Phase 13 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-02 | Assign reviewer + unit code / phase / parking / offer expiry / installments | `/dashboard/listings/{id}` | `approve_listing` |
| ADM-02 | Save assigned reviewer | `POST /dashboard/listings/{id}/assign-reviewer` | `approve_listing` |
| ADM-15 | Category commission policy code (reference only) | create/edit/index categories | `manage_catalog` |
| ADM-15 | Listing fields per category (FR-051) | `GET/PUT /dashboard/categories/{id}/form` | `manage_catalog`. Saving publishes a new form version |

Queue search includes `unit_code`. Installment evidence links to ADM-03 (document type + original filename), not a raw id. `unit_code` is shown to staff and returned to the owner API; it is never public. Commission policy on a category is copied onto the listing; public `GET /categories` does not expose the code.

---

## Phase 16 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-09 | Finance stage only | `GET /dashboard/cases`, `POST /dashboard/cases/{id}/stage` | `advance_finance_stages` (Finance) |
| ADM-10 | Approve commission | `POST /dashboard/commissions/{id}/approve` | `approve_commissions` (Operations) |
| ADM-10 | View own commissions | `GET /dashboard/commissions` | `view_commissions` (Sales, assigned cases) |

Staff teams (DEC-05) live on ADM-06: managers (`manage_staff`) create, edit, and retire teams and set each member's team. A team may own a Sales number and hours; buyer-facing `sales_contact` then shows the team's number for records assigned to its members (leads, buyer requests, cases, support threads, appointments). Unassigned records, members without a team, retired teams, and teams without a number use the platform number from ADM-13. Reviewer remains listing review-only.

---

## Phase 17 screens

No new Blade screens. ADM-03 media approve is unchanged: **images** still get a public JPEG after review; **videos** stay on the private disk even after approve (the original is never copied to `public_media`). Owner API `kind` is `video` so APP-30 can show a play tile without a public URL.

---

## Phase 18 screens

| Id | Screen | Route | Permission |
|----|--------|-------|------------|
| ADM-04 | Complete / decline account closure | `POST /dashboard/users/{id}/close/complete`, `…/decline` | `suspend_user` |
| ADM-11 | Schedule broadcasts | `GET /dashboard/content?tab=schedule`, `POST /dashboard/notifications/broadcasts` | `manage_catalog` **or** `approve_broadcasts` |
| ADM-11 | Approve broadcast | `POST /dashboard/notifications/broadcasts/{id}/approve` | `approve_broadcasts` (not own row, except Super Admin) |

Operations open ADM-11 for schedule/approve only. Catalog banners, templates, and test-send stay Super Admin (`manage_catalog`). User-requested closure is a pending badge until legal review; complete anonymizes the row and does not hard-delete.

---

## Locale

Header locale switcher: Arabic (RTL, menu on the right) and English (LTR, menu on the left). Sidebar groups and items are bilingual. ADM-13 sales team name/hours have Arabic and English fields; `GET /config` returns the matching locale.
