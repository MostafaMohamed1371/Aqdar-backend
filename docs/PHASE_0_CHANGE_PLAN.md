# Phase 0 — Exact change plan

Status: **Phase 0 complete** (0.1 PostgreSQL, 0.2 leftover removal, 0.3 enums/codes/audit, 0.4 disks, 0.5 identity/docs).

Dashboard is **not** all 15 pages now. See [DASHBOARD_PHASES.md](DASHBOARD_PHASES.md): each API phase builds only the matching ADM screens from `dashboard.html` + `ui.css`.

Parent document: [AQDAR_BACKEND_PHASES.md](AQDAR_BACKEND_PHASES.md)

This plan is the first implementation step of that document. **Step 0.1 (env + PostgreSQL) is the only work to approve now.** Steps 0.2–0.5 stay listed so Phase 0 is complete, but they wait for a later approval.

---

## Understanding of the parent document

`docs/AQDAR_BACKEND_PHASES.md` is the product contract for this Laravel API. Confirmed:

| Item | Meaning for this repo |
|------|------------------------|
| Product | AQDAR (عَقدار) — Egypt real-estate exit/resale marketplace, EGP only |
| Clients | Flutter (APP-01…41) via `/api/v1`, and **Blade admin** (ADM-01…15) in `app/Modules/Dashboard` |
| Database | PostgreSQL (BRD). Current skeleton defaults to SQLite locally and MySQL in Docker |
| Privacy | Contact masking, two money versions, two file worlds, no buyer↔seller chat |
| Architecture | Keep HMVC modules. New domain modules later. Do not dump features into `app/Core` |
| Phase 0 goal | Safe base: PostgreSQL, Redis-ready config, private/public disks, cleanup, shared building blocks |
| Not in Phase 0 | OTP, listings, CRM, notifications, AQDAR Blade restyle (ADM-04/06), Flutter |

Phase 0 in the parent file is too broad to do in one shot. This plan splits it. **We start with environment + PostgreSQL only.**

---

## Current state (verified)

| Item | Today |
|------|--------|
| `.env` | Missing (only `.env.example` and `.env.docker.example`) |
| `vendor/` | Missing — app cannot boot until `composer install` |
| Git | Not a git repository |
| Default DB | `DB_CONNECTION=sqlite` in `.env.example`; `mysql` in Docker |
| Laravel pgsql config | Already present in `config/database.php` (`pgsql`, port 5432) |
| Docker PHP | Installs `pdo_mysql` only — **no `pdo_pgsql`** |
| Docker Compose | MySQL 8.4 service, not Postgres |
| PHPUnit | PostgreSQL database `aqdar_eg_testing` (`phpunit.xml`) |
| App name / locale | `APP_NAME=Laravel`, `APP_LOCALE=en` |

Laravel already knows how to talk to Postgres. The work is: make Postgres the **default**, wire env/Docker/docs/PHP extension, and create a local `.env`.

---

## Step 0.1 — Environment and PostgreSQL (approve this first)

### Goal

Local and Docker development use **PostgreSQL** as the application database. No secrets in git. Arabic-first env defaults. Tests use PostgreSQL database `aqdar_eg_testing`, not SQLite.

### Out of this step

- New domain tables (listings, leads, …)
- OTP / roles / modules
- Redis as default cache/queue (can stay `database` until later)
- Private S3 disks (local disks stay; S3 env keys only as commented placeholders)
- Deleting Auth password login, remember-me cookie, Blade dashboard, inventory code

### Proposed `.env.example` (no secrets)

Application:

```env
APP_NAME=AQDAR
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Africa/Cairo

APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=ar_EG
```

Database (Postgres is default):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=aqdar
DB_USERNAME=aqdar
DB_PASSWORD=
DB_CHARSET=utf8
DB_SSLMODE=prefer
```

Do not use SQLite for the app or for tests.

Also add (empty / placeholders, never real numbers or keys):

```env
SALES_PHONE=
SALES_TEAM_NAME="فريق مبيعات عَقدار"
FRONTEND_URL=http://localhost:3000
STRUCTER_API_PREFIX=v1

# Private documents / public media (Phase 4). Local disks until S3 is configured.
FILESYSTEM_DISK=local
FILESYSTEM_PRIVATE_DISK=local
FILESYSTEM_PUBLIC_DISK=public
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=me-south-1
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Keep existing session/cache/queue/mail/redis keys. Do not commit a filled `DB_PASSWORD`.

### Proposed `.env` (local only, gitignored)

Copy from `.env.example`, then:

1. `php artisan key:generate`
2. You set `DB_PASSWORD` for your machine (not written into the repo)
3. Create database `aqdar` and role `aqdar` in Postgres (you run this, or Docker does it)

### Files to change (Step 0.1 only)

| File | Change |
|------|--------|
| `.env.example` | Defaults above. `DB_CONNECTION=pgsql`. Remove sqlite as the advertised default. |
| `.env.docker.example` | `APP_NAME=AQDAR`, `APP_LOCALE=ar`, `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432`, `DB_DATABASE=aqdar`, `DB_USERNAME=aqdar`, `DB_PASSWORD` via compose env (not a real production secret). |
| `.gitignore` | Add `.history/`, `.env.docker` (already has `/docker/.env.docker`; also ignore root `.env.docker` if used). |
| `config/database.php` | Default `env('DB_CONNECTION', 'pgsql')` instead of `sqlite`. Optionally add `read`/`write` hosts on the **pgsql** connection (same pattern as mysql), so scaling later does not stay MySQL-only. |
| `config/queue.php` | Default fallback `env('DB_CONNECTION', 'pgsql')` where it currently falls back to sqlite. |
| `docker-compose.yml` | Replace `mysql` service with `postgres` (`postgres:16-alpine`). Healthcheck `pg_isready`. Volume `postgres_data`. Port `${DOCKER_POSTGRES_PORT:-5432}:5432`. Env: `POSTGRES_DB=aqdar`, `POSTGRES_USER=aqdar`, `POSTGRES_PASSWORD` from compose env. App `depends_on: postgres`. |
| `docker-compose.prod.yml` | Same MySQL → Postgres swap. |
| `docker-compose.scale.yml` | Point `depends_on` at `postgres` instead of `mysql`. |
| `Dockerfile` | Replace `mysql-client` + `pdo_mysql` with `postgresql-client` + `pdo_pgsql` (`libpq-dev`). Keep `pdo`. |
| `docs/GETTING_STARTED.md` | PostgreSQL as the only local/Docker database. Drop sqlite/mysql quick-start as the product path. |
| `docs/README.md` | `DB_*` documented as pgsql. |
| `DOCKER.md` | Diagram and service name: postgres, not mysql. |
| `SCALING.md` | Read/write hosts described for pgsql, not mysql. |
| `app/Core/Console/Commands/ScalingCheckCommand.php` | Stop treating mysql as the only driver that can have read/write split (pgsql can too). Small check change only. |
| `phpunit.xml` | `DB_CONNECTION=pgsql`, `DB_DATABASE=aqdar_eg_testing`. |

### Files not touched in Step 0.1

- Application modules, routes, Auth, Blade views
- Migrations (existing Laravel tables still work on Postgres; inventory mysql-specific branch is Phase 0.2)
- `composer.json` (Laravel already supports pgsql; PHP extension is OS/Docker, not a Composer package)
- Installing `vendor/` — needed on your machine after this step, but it is a local command, not a repo change

### Local commands after files are changed (you run, or I run after approval)

```bash
# PHP pgsql extension must be installed on the Mac (Homebrew php already; confirm):
php -m | grep pdo_pgsql

# Create DB (example; adjust to your Postgres install)
createdb aqdar   # or: psql -c "CREATE USER aqdar WITH PASSWORD '...'; CREATE DATABASE aqdar OWNER aqdar;"

cp .env.example .env
php artisan key:generate
composer install   # if vendor is still missing
php artisan migrate
```

Docker alternative:

```bash
cp .env.docker.example .env.docker
# set APP_KEY after first boot or generate inside the container
docker compose up -d --build
```

### Risks

| Risk | Mitigation |
|------|------------|
| Mac PHP missing `pdo_pgsql` | Confirm `php -m \| grep pdo_pgsql` before migrate. Install via Homebrew if missing. |
| Existing sqlite/mysql data | None in this clone (no `.env`, no sqlite file in use). Fresh database. |
| Docker volume still named `mysql_data` | New volume `postgres_data`. Old mysql volume unused. |
| Tests wiping the app database | `phpunit.xml` uses `aqdar_eg_testing`, not `aqdar_eg`. |
| Inventory migration mysql branch | Still works on pgsql (skips mysql-only index). Remove in Step 0.2. |

### Done when (Step 0.1)

1. `.env.example` and Docker env default to `pgsql`.
2. `config/database.php` default is `pgsql`.
3. Docker PHP image has `pdo_pgsql`; compose runs Postgres 16.
4. Docs no longer tell a new developer to use sqlite/mysql for AQDAR.
5. After `composer install` + migrate, `php artisan migrate` succeeds against Postgres.
6. `php artisan test` uses PostgreSQL `aqdar_eg_testing` and requires that database to exist.

---

## Remaining Phase 0 (do not implement until you approve each step)

### Step 0.2 — Hygiene and unsafe skeleton leftovers

- `.history/` is gitignored. Env copies still on disk — delete locally; they contain an `APP_KEY`.
- Remove unused `Core/Payments`, `Core/Inventory`, inventory migration — **still pending**.
- **Done:** `/api/v1/users` is locked. Guest → 401, authenticated marketplace user → 403. Staff user CRUD is Blade ADM-04 (Phase 1).
- **Done in Phase 1:** OTP send/verify, `/me`, `/devices`, `/config`.

### Step 0.3 — Shared building blocks (no domain tables yet)

- Enums folder for future states/segments (empty or listing-state enum only if useful).
- Public-code generator service (`AQ-`, `INQ-YYYY-`, `BR-`, `LD-`, `TR-`).
- `audit_logs` / `access_logs` migrations + writer service + one unit test.
- Base API resource pattern (audience comment / abstract), not listing resources yet.

### Step 0.4 — Filesystem disks (config only)

- Named disks `private_documents` and `public_media` in `config/filesystems.php`.
- Local paths now; S3 later in Phase 4. No upload endpoints yet.

### Step 0.5 — Identity rename in docs/config only

- `APP_NAME=AQDAR` already in 0.1.
- Optional: `composer.json` name `aqdar/backend`. Not required to boot.

---

## Approval needed

Please confirm **Step 0.1 only**:

1. Default database is PostgreSQL (`aqdar` / user `aqdar` / port `5432`).
2. Tests use PostgreSQL `aqdar_eg_testing` (not SQLite).
3. Docker switches from MySQL 8.4 to Postgres 16.
4. I may create/edit only the files listed in the Step 0.1 table.
5. You will set the real `DB_PASSWORD` locally; I will not put a real password in the repo.

Reply with approval (for example: “approve step 0.1”) and I will apply only those files.
