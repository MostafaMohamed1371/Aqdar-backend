# Scaling to 100k+ Users — Structer Laravel

> **Docs index:** [docs/README.md](docs/README.md) · [STARTER_GUIDE.md](docs/STARTER_GUIDE.md)

This document maps your architecture notes to concrete implementation in this project.

## Architecture overview

```
                    ┌─────────────────────────┐
                    │  Load Balancer (Nginx)  │
                    │  or AWS ALB             │
                    └───────────┬─────────────┘
                                │
           ┌────────────────────┼────────────────────┐
           ▼                    ▼                    ▼
    ┌─────────────┐      ┌─────────────┐      ┌─────────────┐
    │ App (PHP)   │      │ App (PHP)   │      │ App (PHP)   │
    │ Laravel     │      │ Laravel     │      │ Laravel     │
    └──────┬──────┘      └──────┬──────┘      └──────┬──────┘
           │                    │                    │
           └────────────────────┼────────────────────┘
                                ▼
                    ┌─────────────────────────┐
                    │  Redis (shared)         │
                    │  sessions + cache +     │
                    │  queue metadata         │
                    └───────────┬─────────────┘
                                │
                    ┌───────────▼─────────────┐
                    │  Horizon workers        │
                    │  payments → default →   │
                    │  emails                 │
                    └───────────┬─────────────┘
                                ▼
                    ┌─────────────────────────┐
                    │  MySQL                  │
                    │  write primary +        │
                    │  read replicas          │
                    └─────────────────────────┘
```

**Key rule:** App servers are **stateless**. Sessions, cache, and queues live in **Redis**, not on individual servers.

---

## Step-by-step (from your notes)

### 1. Database — read/write split + indexes

**Problem:** Many users writing orders/payments on one DB causes locks and slow queries.

**Solution in Structer:**

| Item | Location |
|------|----------|
| Read/write hosts | `config/database.php` → `pgsql.read` / `pgsql.write` |
| Env vars | `DB_WRITE_HOST`, `DB_READ_HOST` (fallback: `DB_HOST`) |
| Avoid N+1 / foreach queries | Use eager loading (`with()`), batch queries in repositories |
| Indexes | Add in module migrations for filtered/sorted columns |

```env
DB_CONNECTION=pgsql
DB_WRITE_HOST=postgres-primary.internal
DB_READ_HOST=postgres-replica.internal
DB_STICKY=true
```

Reads go to replicas; writes go to primary. `sticky` keeps reads on primary after a write in the same request.

---

### 2. Caching (Redis)

**Problem:** Every request hits DB for settings, categories, etc.

**Solution:**

| Item | Location |
|------|----------|
| Docker Redis | `docker-compose.yml` → `redis` service |
| Cache driver | `CACHE_STORE=redis` in `.env.docker.example` |
| Helper | `App\Core\Cache\CachedRepository` |

```php
$settings = (new CachedRepository('settings'))->remember('app', fn () => Setting::all());
```

Use Redis for rarely changing data. Invalidate with `forget()` when data changes.

---

### 3. Queues + Horizon (background work)

**Problem:** PDF, email, and payment work in the HTTP request blocks users.

**Solution:**

| Item | Location |
|------|----------|
| Queue driver | `QUEUE_CONNECTION=redis` |
| Horizon config | `config/horizon.php` |
| Email jobs | `App\Core\Jobs\SendEmailJob` → `emails` queue |
| Workers | `php artisan horizon` or `horizon` Docker service |

```php
ProcessPaymentJob::dispatch($orderId, $amountCents);
SendEmailJob::dispatch($email, $subject, $body);
```

Horizon dashboard: `/horizon` (local: open; production: set `HORIZON_ALLOWED_EMAILS`).

---

### 4. Database constraints (last line of defense)

**Problem:** Application bugs could set stock below zero.

**Solution:** Example migration with MySQL `CHECK` constraint:

`database/migrations/2026_08_31_120000_create_inventory_items_table.php`

Combine with transactions and `lockForUpdate()` in services.

---

### 5. Reservation pattern (10-minute hold)

**Problem:** User reaches checkout but item sold out.

**Solution:** `App\Core\Inventory\ReservationService` — Redis TTL holds per SKU.

```php
$reservations->reserve('SKU-123', 1, $cartId);
// On payment success: decrement DB stock + release reservation
// On timeout: Redis key expires automatically
```

Configure: `STRUCTER_RESERVATION_MINUTES=10`

---

### 6. Horizontal scaling + load balancer

**Problem:** One server cannot serve 100k concurrent users.

**Solution:**

| Environment | Command |
|-------------|---------|
| Local (3 app containers) | `docker compose -f docker-compose.yml -f docker-compose.scale.yml up -d --scale app=3` |
| Production | `docker compose -f docker-compose.prod.yml up -d --scale app=3` |

Nginx load-balances PHP-FPM via `docker/nginx/load-balancer.conf` (Docker DNS round-robin on `app:9000`).

**Auto-scaling:** On AWS, scale app tasks when CPU > 70% (ECS/EKS/ASG).

---

### 7. Shared Redis (sessions + cache + queues)

**Problem:** Sticky sessions on one server break when scaling horizontally.

**Solution:**

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
```

All app replicas share the same Redis instance/cluster.

---

### 8. Cloud storage (S3)

**Problem:** Local disk on app servers is not shared when scaled.

**Solution:**

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
```

Configured in `config/filesystems.php`.

---

### 9. Rate limiting on payment pages

**Problem:** Fraud — brute-forcing cards on payment endpoints.

**Solution:** `payment` rate limiter in `AppServiceProvider` — **10 requests/minute per user/IP**.

Apply on payment routes:

```php
Route::post('/pay', ...)->middleware('throttle:payment');
```

Env: `PAYMENT_MAX_ATTEMPTS=10`, `PAYMENT_DECAY_MINUTES=1`

---

### 10. Race conditions

**Problem:** Two users pay at the same time → double charge or oversell.

**Solution:**

| Tool | Location |
|------|----------|
| Distributed lock | `App\Core\Concurrency\DistributedLock` |
| DB row lock | `Model::lockForUpdate()` inside `DB::transaction()` |
| Idempotent payments | Unique constraint on `order_id` in payments table |

```php
$lock->block("order:{$orderId}", fn () => $this->processPayment($order));
```

---

### 11. Payment gateways — Interface vs Abstract

| Pattern | Use when |
|---------|----------|
| `PaymentGatewayInterface` | Each gateway exposes `charge()` / `refund()` signatures |
| `AbstractPaymentGateway` | Shared API key headers and common logic |

See `App\Core\Contracts\PaymentGatewayInterface` and `App\Core\Payments\AbstractPaymentGateway`.

---

## Verify setup

```bash
# Local Docker with Redis + Horizon + 3 app servers
cp .env.docker.example .env.docker
docker compose -f docker-compose.yml -f docker-compose.scale.yml up -d --build --scale app=3

# Inside any app container
docker compose exec app php artisan structer:scaling-check
```

Horizon UI: http://localhost:8080/horizon

---

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Redis cluster or managed Redis (ElastiCache)
- [ ] `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`
- [ ] Horizon running as separate service (not on web workers)
- [ ] Nginx/ALB in front of 2+ app containers
- [ ] MySQL primary + read replica(s)
- [ ] `FILESYSTEM_DISK=s3`
- [ ] `HORIZON_ALLOWED_EMAILS` set
- [ ] Payment routes use `throttle:payment`
- [ ] DB indexes on hot query columns
- [ ] No queries inside `foreach` over large collections

---

## Related files

| File | Purpose |
|------|---------|
| `docker-compose.scale.yml` | Redis, Horizon, load-balanced nginx |
| `docker-compose.prod.yml` | Production stack |
| `config/horizon.php` | Queue workers and priorities |
| `config/structer.php` | Scaling + payment rate limits |
| `DOCKER.md` | Docker usage |
