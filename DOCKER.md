# Docker & CI/CD — Structer Laravel

> **Docs index:** [docs/README.md](docs/README.md) · [GETTING_STARTED.md](docs/GETTING_STARTED.md)

## Overview

| Layer | Tool | File |
|-------|------|------|
| **CI** (test on push) | GitHub Actions | `.github/workflows/ci.yml` |
| **CD** (deploy on main) | GitHub Actions | `.github/workflows/cd.yml` |
| **Local dev** | Docker Compose | `docker-compose.yml` |
| **Production** | Docker Compose | `docker-compose.prod.yml` |
| **Image** | Multi-stage Dockerfile | `Dockerfile` |

```
Developer push
      │
      ▼
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│  CI         │     │  CD (main)   │     │  Production │
│  Tests      │────▶│  Build image │────▶│  Server     │
│  Security   │     │  Push GHCR   │     │  docker up  │
│  Docker     │     │  Deploy      │     │             │
└─────────────┘     └──────────────┘     └─────────────┘
```

---

## Quick start (local Docker)

**Requirements:** Docker Desktop or Docker Engine + Compose v2

```bash
# One-time setup
chmod +x docker/setup.sh
./docker/setup.sh
```

Or manually:

```bash
cp .env.docker.example .env.docker
docker compose build
docker compose run --rm app composer install
docker compose up -d
```

| Service | URL |
|---------|-----|
| App | http://localhost:8080 |
| MySQL | `localhost:3307` (user: `aqdar`, database: `aqdar`) |

### Useful commands

```bash
docker compose logs -f app          # App logs
docker compose exec app php artisan test
docker compose exec app php artisan migrate
docker compose exec app php artisan structer:security-check
docker compose down                 # Stop
docker compose down -v              # Stop + delete DB volume
```

### Horizontal scaling (100k users)

See **[SCALING.md](SCALING.md)** for the full architecture.

```bash
# Redis + Horizon + 3 stateless app servers behind nginx
cp .env.docker.example .env.docker
docker compose -f docker-compose.yml -f docker-compose.scale.yml up -d --build --scale app=3

docker compose exec app php artisan structer:scaling-check
# Horizon dashboard: http://localhost:8080/horizon
```

| Service | Purpose |
|---------|---------|
| `redis` | Shared sessions, cache, queues |
| `horizon` | Background workers (payments → emails) |
| `nginx` + `--scale app=N` | Load-balanced Laravel replicas |

---

## Dockerfile targets

| Target | Use |
|--------|-----|
| `development` | Local dev with `docker-compose.yml` |
| `production` | CI/CD and production deploy |

Build production image locally:

```bash
docker build --target production -t structer:local .
```

Run tests inside container:

```bash
docker run --rm \
  -e APP_ENV=testing \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=mysql \
  -e DB_PORT=3306 \
  -e DB_DATABASE=aqdar_eg_testing \
  -e DB_USERNAME=aqdar \
  -e DB_PASSWORD=secret \
  structer:local \
  sh -c "php artisan key:generate --force && php artisan test"
```

---

## CI (Continuous Integration)

**Trigger:** every push and pull request  
**Workflow:** `.github/workflows/ci.yml`

| Job | Description |
|-----|-------------|
| `php-tests` | PHPUnit Unit + Integration |
| `security` | Security check + Composer audit |
| `python-qa` | Python integration, BDD, performance |
| `e2e` | Selenium browser tests |
| `jmeter` | Load test + artifact |
| `docker` | Build production image + run tests in container |

---

## CD (Continuous Delivery)

**Trigger:** push to `main` / `master`, or manual  
**Workflow:** `.github/workflows/cd.yml`

| Job | Description |
|-----|-------------|
| `build-and-push` | Build production Docker image → **GitHub Container Registry** (`ghcr.io`) |
| `verify-image` | Run PHPUnit inside pulled image |
| `deploy` | Placeholder — configure your server |

### Image tags

- `ghcr.io/<owner>/<repo>:latest` (main branch)
- `ghcr.io/<owner>/<repo>:<git-sha>`
- `ghcr.io/<owner>/<repo>:main`

### Enable deployment

1. Create GitHub **Environment** named `production`
2. Add secrets: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`
3. Uncomment the SSH deploy step in `.github/workflows/cd.yml`
4. On server, clone repo and use `docker-compose.prod.yml`

Example server deploy:

```bash
export DOCKER_IMAGE=ghcr.io/your-org/structer-laravel:latest
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

---

## Production compose

Copy and configure production env:

```bash
cp .env.example .env.production
# Set APP_ENV=production, APP_DEBUG=false, DB_*, APP_KEY, etc.
```

```bash
export DOCKER_IMAGE=ghcr.io/your-org/structer-laravel:latest
docker compose -f docker-compose.prod.yml up -d
```

---

## Architecture

```
┌──────────┐     ┌──────────┐     ┌──────────┐
│  nginx   │────▶│  app     │────▶│  mysql   │
│  :80     │     │  PHP-FPM │     │  :3306   │
└──────────┘     └──────────┘     └──────────┘
```

- **nginx** — serves `public/`, proxies PHP to FPM
- **app** — Laravel 13, PHP 8.4, runs migrations on start
- **mysql** — MySQL 8.4 with persistent volume

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `APP_KEY` missing | `docker compose exec app php artisan key:generate` |
| Permission denied on `storage/` | `docker compose exec app chmod -R 775 storage bootstrap/cache` |
| MySQL connection refused | Wait for healthcheck: `docker compose ps` |
| Assets missing | Run `npm run build` on host, or rebuild image |
| CI: Symfony packages require PHP >= 8.4.1 | `composer.lock` was generated on PHP 8.4/8.5. Docker + CI use PHP **8.4**. Do not run CI on 8.3. |
| CI: `composer dump-autoload` exit 255 | Caused by `artisan package:discover` without APP_KEY. Image build uses `--no-scripts`; discovery runs in `docker/entrypoint.sh`. |

See also: [QA.md](QA.md) for full test documentation.
