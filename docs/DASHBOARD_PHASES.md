# AQDAR admin dashboard — build by phase (not all at once)

Status: Phase 1 (ADM-01 / 04 / 06), Phase 2 (ADM-05 / 11 content / 15), Phase 3 (ADM-02 peek), Phase 4 (ADM-02 actions + ADM-03), Phase 5 (ADM-07), Phase 6 (ADM-08 / 09), Phase 7 (ADM-10), Phase 8 (ADM-11 templates / announcements / category copy / broadcast / send log), and Phase 9 (ADM-01 KPIs, ADM-12 / 13 / 14) are shipped. **Do not implement extra screens beyond the BRD.**

Visual contract (reviewed):

| File | Role |
|------|------|
| `aqdar-ui-v2/dashboard.html` | 15 admin pages ADM-01 … ADM-15, layout, tables, KPIs, Arabic RTL |
| `aqdar-ui-v2/ui.css` | **The dashboard look** — colors, sidebar, tables, badges, Cairo/Inter |
| `aqdar-ui-v2/styles.css` | Prototype hub (`index.html`) only. **Not** the admin theme |

Laravel in this repo serves **both** `/api/v1` (Flutter) and the **staff admin in Blade**. The product admin lives in `app/Modules/Dashboard` and is restyled to match `dashboard.html` + `ui.css`. There is **no React admin app**.

---

## Rule (understand this)

Whenever a backend phase ships an API, the **matching Blade admin pages for that phase** are built in the same phase — layout and behaviour copied from `dashboard.html`, tokens from `ui.css`.

- One phase → only that phase’s ADM screens.
- Later ADM pages stay mockup-only until their API exists.
- Hidden UI is not authorization. Every action still hits the API with staff roles.

```
Phase N API  ──parallel──▶  Phase N dashboard screens from the mockup
```

---

## Visual system to copy (`ui.css`)

RTL Arabic-first. Fonts: Cairo (UI), Inter (codes, numbers, English).

| Token | Value | Use |
|-------|--------|-----|
| `--ink` | `#14283f` | Sidebar, headings |
| `--teal` | `#087e82` | Primary actions |
| `--gold` | `#c5985f` | Accents, featured |
| `--cream` | `#f6f1e8` | Page background |
| `--ok` / `--bad` / `--warn` | green / red / amber | Status badges |

Layout from the mockup (the inner “desk”, not the prototype viewer chrome):

- Right sidebar: logo عَقدار / AQDAR, grouped menu, optional queue counts
- Top bar: search, notifications, signed-in staff
- Main: page title (Arabic + English), filters, tables, KPI cards

Sidebar groups (same as `dashboard.html`):

| Group | Pages |
|-------|--------|
| الرئيسية | ADM-01 |
| العمليات | ADM-02, 03, 04, 05 |
| الإدارة | ADM-06, 07, 08, 09 |
| المالية والمحتوى | ADM-10, 11, 12 |
| النظام | ADM-13, 14, 15 |

Show a menu item only when that phase has shipped. Other items can appear disabled, or stay hidden until their phase.

---

## Phase map — API and dashboard together

| Phase | API (this Laravel repo) | Dashboard screens (Blade, mockup) | Not yet |
|-------|-------------------------|-----------------------------------|---------|
| **0** | Env, PostgreSQL | **No pages.** This document + tokens noted. No staff login yet. | All ADM-* |
| **1** | OTP users, staff RBAC, `/me`, `/config` | **Shell** (sidebar + top bar from mockup) + **staff login** + **ADM-04** users (masked phones, reveal, suspend) + **ADM-06** staff & permission matrix | Listings, CRM, … |
| **2** | Categories, developers, locations, content | **ADM-05** developers/master data + **ADM-15** reference (cities, distressed A/B copy) + **ADM-11 content tabs only** (banners, FAQs) | Moderation, CRM |
| **3** | Public + seller listings | **ADM-02 read-only peek** (queue + declared vs verified). ADM-01 listing counts are live. | ADM-02 actions (approve ≠ publish), ADM-03 |
| **4** | Private docs, media, moderation APIs | **ADM-02** listing moderation (approve ≠ publish, declared vs verified money) + **ADM-03** private documents (signed URL, checklist, access log) | CRM |
| **5** | Inquiries, leads, appointments | **ADM-07** Sales CRM | Requests, cases |
| **6** | Buyer requests, transfer cases | **ADM-08** buyer requests + **ADM-09** cases (internal stage vs client-safe label) | Finance |
| **7** | Estimate, commissions | **ADM-10** commissions (Finance only) | Notifications UI (Phase 8) |
| **8** | Notifications, templates, push log | **ADM-11** remaining: templates, broadcast, send log | Reports |
| **9** | Dashboard KPIs, reports, settings, audit | **ADM-01** executive KPIs + **ADM-12** reports/CSV + **ADM-13** settings/Sales number + **ADM-14** activity log | — |

---

## What “done” means for a dashboard page

For each ADM screen in a phase:

1. Looks like the mockup (RTL, tokens, table/KPI structure) — not the Structer Tailwind starter theme.
2. Reads/writes the **real backend** for that phase (controllers/services, no fake numbers in production). Empty states are allowed.
3. Actions that the role cannot perform return 403; the button is hidden or disabled.
4. Phone/email stay masked until a logged reveal endpoint exists (ADM-04 / ADM-07).

---

## Out of dashboard scope (same as BRD)

- Buyer↔seller chat (Sales support chat is shipped)
- Payment / escrow / OCR
- Building all 15 pages before their APIs
- Building a separate React admin app
- Shipping all 15 Blade pages in one go before their APIs exist

---

## Phase 1 screens (shipped)

Staff desk is Blade, tokens from `ui.css`, Arabic RTL by default.

Shipped in Phase 1:

- Staff login (no public register)
- Shell: right sidebar + top bar from the mockup
- **ADM-01** overview shell (Phase 9 fills executive KPIs, funnel, needs-action, date filter)
- **ADM-04** users (masked phones, reveal, suspend, create/edit/delete)
- **ADM-06** staff & permission matrix

Later ADM pages stay disabled until their API phase. Testing after each slice: unit → integration → E2E. See [TESTING_PHASES.md](TESTING_PHASES.md) and [DASHBOARD.md](DASHBOARD.md).
