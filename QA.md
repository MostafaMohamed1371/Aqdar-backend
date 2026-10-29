# Structer — Quality Assurance (QA)

> **Docs index:** [docs/README.md](docs/README.md) · [README.md](README.md)

Automated testing across **three layers**, plus **TDD**, **BDD**, and **performance** testing.

## Test pyramid

```
        ┌─────────────┐
        │  E2E        │  Selenium (browser)
        │  (few)      │
        ├─────────────┤
        │ Integration │  PHPUnit + Python requests
        │  (some)     │
        ├─────────────┤
        │  Unit       │  PHPUnit (isolated)
        │  (many)     │
        └─────────────┘
```

## Quick start

```bash
# 1. PHP tests (Unit + Integration) — no server needed
php artisan test

# 2. Start app for Python / E2E / performance
php artisan serve

# 3. Full QA (PHP + Python BDD + performance)
php artisan structer:qa

# 4. Everything including Selenium + JMeter
QA_RUN_E2E=1 QA_RUN_JMETER=1 ./qa/run-all.sh
```

---

## 1. Unit tests (PHPUnit)

**Location:** `tests/Unit/`

Tests isolated classes with no HTTP — services, validation rules, helpers.

```bash
php artisan test --testsuite=Unit
# or
composer test:unit
```

**Examples:**
- `AuthServiceTest` — login, register, logout logic
- `ValidationRulesTest` — validation rule definitions

---

## 2. Integration tests (PHPUnit)

**Location:** `tests/Integration/`

Tests full HTTP request → Laravel → database flow.

```bash
php artisan test --testsuite=Integration
# or
composer test:integration
```

**Covers:** Auth API, dashboard, locale, validation, security.

---

## 3. End-to-end tests (Selenium + Python)

**Location:** `qa/python/e2e/`

Real browser tests using **Selenium WebDriver** (headless Chrome).

```bash
# Requires: running app + Chrome
pip3 install -r qa/python/requirements.txt
QA_BASE_URL=http://127.0.0.1:8000 python3 -m pytest qa/python/e2e -m e2e -v

# Or
php artisan structer:qa --e2e
```

**Scenarios:**
- Guest sees login form
- User logs in and sees Overview
- Password show/hide toggle works

Skip E2E: `QA_SKIP_E2E=1`

---

## BDD — Behavior Driven Development

**Location:** `qa/python/bdd/features/` + `qa/python/bdd/step_defs/`

Gherkin `.feature` files describe behavior in plain language:

```gherkin
Scenario: User can log in with valid credentials
  Given a registered user exists
  When I log in with valid credentials
  Then I should see the dashboard overview
```

Run BDD:

```bash
cd qa/python && python3 -m pytest bdd/step_defs -v
```

---

## TDD — Test Driven Development

Workflow for new features:

1. **Red** — Write a failing test first
2. **Green** — Write minimal code to pass
3. **Refactor** — Clean up without breaking tests

### Example (new module endpoint)

```bash
# 1. Create failing integration test
php artisan make:test Modules/Product/CreateProductTest

# 2. Run — should FAIL
php artisan test --filter=CreateProductTest

# 3. Implement ProductService, controller, routes

# 4. Run — should PASS
php artisan test --filter=CreateProductTest
```

PHPUnit tests live next to the code they verify. Prefer **unit tests** for business logic, **integration tests** for routes.

---

## Python QA suite

**Location:** `qa/python/`

| Folder | Type | Tool |
|--------|------|------|
| `integration/` | API integration | `requests` + pytest |
| `bdd/` | Behavior tests | `pytest-bdd` (Gherkin) |
| `e2e/` | Browser tests | `selenium` |
| `performance/` | Load + metrics | `pytest` + `psutil` |

```bash
cd qa/python
pip3 install -r requirements.txt
QA_BASE_URL=http://127.0.0.1:8000 python3 -m pytest -v
```

---

## Performance testing

### Python (built-in)

Measures response time, throughput, CPU, memory:

```bash
cd qa/python
QA_BASE_URL=http://127.0.0.1:8000 python3 -m pytest performance -m performance -v
```

Environment variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `QA_PERF_CONCURRENCY` | 10 | Parallel workers |
| `QA_PERF_REQUESTS` | 50 | Total requests |
| `QA_PERF_MAX_P95_MS` | 2000 | p95 budget (ms) |

### Apache JMeter

**Location:** `qa/jmeter/structer-load-test.jmx`

```bash
jmeter -n -t qa/jmeter/structer-load-test.jmx \
  -JHOST=127.0.0.1 -JPORT=8000 \
  -JTHREADS=10 -JLOOPS=20 \
  -l storage/app/qa/results.jtl \
  -e -o storage/app/qa/jmeter-report
```

See [qa/jmeter/README.md](jmeter/README.md).

---

## Commands reference

| Command | Description |
|---------|-------------|
| `php artisan test` | All PHPUnit (Unit + Integration) |
| `php artisan test --testsuite=Unit` | Unit only |
| `php artisan test --testsuite=Integration` | Integration only |
| `php artisan structer:qa` | PHP + Python (no E2E) |
| `php artisan structer:qa --e2e` | Include Selenium |
| `php artisan structer:qa --jmeter` | Include JMeter |
| `php artisan structer:qa --all` | Everything |
| `php artisan structer:security-check` | Security audit |
| `./qa/run-all.sh` | Shell script full pipeline |
| `composer test:unit` | Unit tests |
| `composer test:integration` | Integration tests |

---

## CI (GitHub Actions)

See [DOCKER.md](DOCKER.md) for full CI/CD + Docker documentation.

**Trigger:** every push and pull request  
**Workflow:** `.github/workflows/ci.yml`

| Job | Description |
|-----|-------------|
| `php-tests` | PHPUnit Unit + Integration |
| `security` | Security check + Composer audit |
| `python-qa` | Python integration, BDD, performance |
| `e2e` | Selenium browser tests |
| `jmeter` | Load test + artifact |
| `docker` | Build Docker image + test in container |

**CD Workflow:** `.github/workflows/cd.yml` — builds & pushes image to GHCR on `main`.

---

## File structure

```
tests/
  Unit/              ← PHPUnit unit tests
  Integration/       ← PHPUnit integration tests
qa/
  python/
    integration/     ← API tests (requests)
    bdd/features/    ← Gherkin scenarios
    bdd/step_defs/   ← BDD step implementations
    e2e/             ← Selenium browser tests
    performance/     ← Load + CPU/memory
  jmeter/            ← Apache JMeter plans
  run-all.sh         ← Run full QA pipeline
QA.md                ← This file
```
