# AQDAR staff desk — workflows (step by step)

Living guide for every Blade screen and the main product flows. **In the app:** any signed-in staff opens **Home → Desk guide & workflows** (`/dashboard/guide`) or downloads **PDF** at `/dashboard/guide/pdf`.

Source of truth for the on-screen text and PDF body: `App\Modules\Dashboard\Services\StaffGuideService`. Screen inventory by phase stays in [DASHBOARD.md](DASHBOARD.md).

---

## Product shape

| Surface | Who | Base |
|---------|-----|------|
| Flutter marketplace | Buyers / sellers | `/api/v1` (Sanctum) |
| Staff desk | Operations, reviewers, sales, finance, super admin | `/dashboard` (session) |

Currency **EGP only**. No public staff register. Phones/emails stay masked until a logged Reveal.

---

## End-to-end flows

### A. Listing publish

1. Seller drafts on mobile, uploads required private docs (+ distressed developer letter).
2. Submit → in review → **ADM-02** queue.
3. **ADM-03** approve/reject private docs (signed URL, watermark, access log).
4. **ADM-02** verify financials, media, approve / request changes / reject.
5. **Approve ≠ publish.** Operations publish with `publish_listing`.
6. Buyers see **published** only. Videos stay private after media approve.

### B. Inquiry → lead → case → commission

1. Buyer inquiry / viewing / custom request → staff inbox.
2. **ADM-07** assign Sales → activity, stages, viewing confirm, reveal phones.
3. **ADM-08** match / send / open lead / convert.
4. **ADM-09** transfer case stages + lifecycle + checklist + offline escrow.
5. **ADM-10** commission on completed case → Operations approve. Platform does not collect money.

---

## Screens (ADM-01 … ADM-15)

| Id | Route | Permission (gate) | What staff do |
|----|-------|-------------------|---------------|
| GUIDE | `/dashboard/guide` | Any staff | Read steps + download PDF |
| ADM-01 | `/dashboard` | Any staff | KPIs, needs-action, funnel, charts, map |
| ADM-02 | `/dashboard/listings` | `approve_listing` (+ `publish_listing`) | Moderate / publish listings |
| ADM-03 | `/dashboard/documents` | `open_private_documents` | Private doc checklist |
| ADM-04 | `/dashboard/users` | `view_users` / `suspend_user` | Marketplace identities |
| ADM-05 | `/dashboard/developers` | `manage_catalog` | Developers & projects |
| ADM-06 | `/dashboard/staff` | `manage_staff` | Staff, roles, teams |
| ADM-07 | `/dashboard/leads` (+ `/support`) | `manage_crm` | Leads + Sales support chat |
| ADM-08 | `/dashboard/requests` | `manage_crm` | Buyer requests |
| ADM-09 | `/dashboard/cases` | `manage_crm` / `advance_finance_stages` | Transfer cases |
| ADM-10 | `/dashboard/commissions` | commission permissions | Revenue desk |
| ADM-11 | `/dashboard/content` | `manage_catalog` / `approve_broadcasts` | Content & broadcasts |
| ADM-12 | `/dashboard/reports` | `export_csv` | CSV/PDF reports |
| ADM-13 | `/dashboard/settings` | `manage_catalog` | Platform settings |
| ADM-14 | `/dashboard/audit` | `manage_staff` | Immutable activity log |
| ADM-15 | `/dashboard/categories` (+ locations…) | `manage_catalog` | Reference data |

Detailed actions: [DASHBOARD.md](DASHBOARD.md).

---

## Roles (cheat sheet)

| Role | Typical access |
|------|----------------|
| Super Admin | Full desk |
| Operations | Publish, CRM assign, commission/broadcast approve, user suspend |
| Reviewer | Listing + private docs; **no** publish |
| Sales | Assigned CRM/support; own commissions; users list only |
| Finance | Commission edit + finance case stages |

---

## PDF note

The downloadable guide PDF uses the same Helvetica/ASCII writer style as ADM-12 report PDFs (Arabic glyphs fall back to spaces in the file). The **on-screen guide** is fully bilingual (AR/EN) from the current locale.
