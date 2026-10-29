# Apache JMeter — Structer load tests

## Install

```bash
# macOS
brew install jmeter
```

## Credentials (not committed)

```bash
cp qa/jmeter/credentials.env.example qa/jmeter/credentials.env
# Edit LOGIN_EMAIL and LOGIN_PASSWORD
```

## Run (recommended)

```bash
chmod +x qa/jmeter/run-load-test.sh
./qa/jmeter/run-load-test.sh
```

This script:

1. Creates/updates the load-test user in the database
2. Starts `php artisan serve`
3. Logs in **once** via API (avoids rate-limit errors)
4. Runs load on health, dashboard, `/auth/me`, settings, and categories

## Run (manual)

```bash
php artisan serve &
jmeter -n -t qa/jmeter/structer-load-test.jmx \
  -JHOST=127.0.0.1 -JPORT=8000 -JPROTOCOL=http \
  -JTHREADS=10 -JLOOPS=20 \
  -JLOGIN_EMAIL=your@email.com \
  -JLOGIN_PASSWORD=your-password \
  -l storage/app/qa/jmeter-results.jtl \
  -e -o storage/app/qa/jmeter-report
```

## What it tests

| Phase | Request | Purpose |
|-------|---------|---------|
| setUp (once) | `POST /api/v1/auth/login` | Get Sanctum token |
| Loop | `GET /up` | Health check |
| Loop | `GET /dashboard` | Web dashboard |
| Loop | `GET /api/v1/auth/me` | Authenticated API (Bearer token) |
| Loop | `GET /api/v1/settings` | Cached public API |
| Loop | `GET /api/v1/categories` | Cached public API |

## Metrics

Open `storage/app/qa/jmeter-report/index.html` for throughput, latency percentiles, and error rate.

## Tuning

| Property | Default | Description |
|----------|---------|-------------|
| `THREADS` | 10 | Concurrent users |
| `LOOPS` | 20 | Iterations per thread |
| `LOGIN_EMAIL` | — | API login email (required) |
| `LOGIN_PASSWORD` | — | API login password (required) |

```bash
THREADS=20 LOOPS=30 ./qa/jmeter/run-load-test.sh
```
