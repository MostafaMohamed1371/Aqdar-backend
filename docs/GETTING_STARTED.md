# Getting Started

Install and run **AQDAR** locally or with Docker.

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | 8.4+ with `pdo_pgsql` |
| Composer | 2.x |
| Node.js | 18+ (for frontend assets) |
| PostgreSQL | 16 (database `aqdar_eg`) |
| Redis | Optional locally; required for scaling |
| JMeter | Optional — load tests only |

---

## Local installation

### 1. Clone and install dependencies

```bash
git clone <your-repo-url> my-project
cd my-project

composer install
cp .env.example .env
php artisan key:generate
```

### 2. Database (PostgreSQL)

`.env.example` already uses Postgres. Create the database and role, then set `DB_PASSWORD` in `.env` (never commit it):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=aqdar_eg
DB_USERNAME=aqdar_eg
DB_PASSWORD=your_password
```

```bash
php artisan migrate --seed
```

PHPUnit uses PostgreSQL (`phpunit.xml` → database `aqdar_eg_testing`). Create that database once, then `php artisan test`. Do not point tests at `aqdar_eg`.

### 3. Frontend assets

```bash
npm install
npm run build        # production build
# or
npm run dev          # watch mode (run alongside php artisan serve)
```

### 4. Run the application

```bash
php artisan serve
```

| URL | Description |
|-----|-------------|
| http://127.0.0.1:8000/dashboard | Staff login (no public register) |
| http://127.0.0.1:8000/api/v1/config | Public marketplace config |
| http://127.0.0.1:8000/up | Health check |

Privacy: public listings hide seller PII; CSV exports hide phones by default; see [README.md](../README.md#privacy). Backups and staging/production: [OPS.md](OPS.md), [DEPLOY.md](DEPLOY.md).

**Staff after seed:** `test@example.com` / `password`

### 5. Marketplace API user

Do **not** use `/register` in the browser. Create a marketplace user from **Dashboard → Users**, or:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Demo User","email":"demo@example.com","password":"password123","password_confirmation":"password123"}'
```

Save `data.token`. OTP login (local): send then verify with code `123456`.

Import Postman: [../postman/README.md](../postman/README.md). Full HTTP reference: [API.md](API.md).

---

## Docker installation

```bash
cp .env.docker.example .env
./docker/setup.sh
docker compose up -d
```

| Service | URL |
|---------|-----|
| App (nginx) | http://localhost:8080 |
| PostgreSQL | localhost:5433 (user `aqdar`, database `aqdar`) |
| Redis | localhost:6379 |

Run migrations inside the container:

```bash
docker compose exec app php artisan migrate --seed
```

See [DOCKER.md](../DOCKER.md) for scaling compose and production.

---

## Postman setup

1. Open Postman → **Import**
2. Import `postman/AQDAR-API.postman_collection.json`
3. Import `postman/AQDAR-Local.postman_environment.json`
4. Select the **AQDAR Local** environment
5. Run **Phase 1 — Identity → Register** or **Verify OTP** — the token is saved automatically
6. Run any authenticated request (Bearer `{{auth_token}}`)

Local OTP code is `123456`. After each phase, a new **Phase N** folder is added. See [postman/README.md](../postman/README.md).

Do not use the leftover `Structer-API` collection.

---

## Configuration

### API prefix

```env
STRUCTER_API_PREFIX=v1
```

Routes become `/api/v1/...`. Change to `v2` when you need a new API version.

### Locales

Product locales: **Arabic** (`ar`, default, RTL) and **English** (`en`, LTR).

```env
APP_LOCALE=ar
```

API: send `Accept-Language: ar` or `en`.  
Staff desk: header language switcher (menu right in Arabic, left in English).

### Mail (password reset)

```env
MAIL_MAILER=log          # local — emails go to storage/logs
MAIL_FROM_ADDRESS=noreply@example.com
```

For real emails, configure SMTP in `.env`.

### Redis (recommended for staging/production)

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Then run queue workers:

```bash
php artisan horizon
```

Verify with:

```bash
php artisan structer:scaling-check
```

---

## Verify installation

```bash
# All PHPUnit tests
php artisan test

# Security checklist
php artisan structer:security-check

# Scaling readiness (Redis warnings OK when CACHE_STORE is not redis)
php artisan structer:scaling-check
```

---

## Next steps

- [API.md](API.md) — REST API by phase
- [DASHBOARD.md](DASHBOARD.md) — Blade staff desk
- [DOCUMENTATION_PROCESS.md](DOCUMENTATION_PROCESS.md) — update docs and Postman after each phase
- [AQDAR_BACKEND_PHASES.md](AQDAR_BACKEND_PHASES.md) — remaining product phases
