# Starter Guide — Use Structer for a New Project

This guide explains how to turn **Structer Laravel** into the foundation for your next application.

---

## What is Structer?

Structer is a **general-purpose Laravel starter**, not a finished product. It provides:

- A proven **HMVC folder structure** (`app/Modules/`)
- **Auth** (API tokens + web sessions) out of the box
- An **admin dashboard** pattern (Settings, Categories as examples)
- **Scaling primitives** (Redis cache, Horizon queues, read/write DB)
- **QA tooling** (tests, security check, JMeter)

You clone it, rename it, add your domain modules, and ship.

---

## Step 1 — Fork or clone

```bash
git clone <structer-repo> acme-shop-api
cd acme-shop-api
rm -rf .git && git init   # optional: fresh git history
```

Update project identity:

| File | Change |
|------|--------|
| `composer.json` | `"name": "acme/shop-api"` |
| `.env` | `APP_NAME="Acme Shop"` |
| `config/app.php` | name if hardcoded |
| Dashboard views | Logo, title in `app/Modules/Dashboard/Resources/views/` |

---

## Step 2 — Decide which modules to keep

Default modules:

| Module | Keep if… | Remove/disable if… |
|--------|----------|-------------------|
| **Auth** | Almost always | Never (core) |
| **User** | You need users | You replace with another identity model |
| **Setting** | You need app config | You use env-only config |
| **Category** | You have categories/taxonomy | Not relevant |
| **Dashboard** | You want admin UI | API-only project |

**Disable without deleting:**

```php
// config/structer.php
'disabled_modules' => ['Category'],
```

**Remove completely:** delete `app/Modules/Category/` and run `composer dump-autoload`.

---

## Step 3 — Create your domain modules

Each business feature gets its own module:

```bash
php artisan make:module Product
php artisan make:module Order
php artisan make:module Payment
```

Each module includes:

```
app/Modules/Product/
├── Providers/ProductModuleServiceProvider.php
├── Http/Controllers/ProductController.php
├── Http/Requests/StoreProductRequest.php
├── Http/Requests/UpdateProductRequest.php
├── Http/Resources/ProductResource.php
├── Models/Product.php
├── Services/ProductService.php
├── Repositories/ProductRepository.php
├── Routes/api.php
├── Routes/web.php
└── Database/Migrations/
```

### Checklist per module

1. **Migration** — define table schema in `Database/Migrations/`
2. **Model** — `$fillable`, relationships, casts
3. **Repository** — extend `AbstractRepository`, query methods
4. **Service** — business logic; inject other module services if needed
5. **FormRequests** — validation via `ValidationRules` or custom rules
6. **Resource** — JSON output shape for API
7. **Routes** — register in `Routes/api.php` and/or `web.php`
8. **Tests** — add to `tests/Integration/`

Run migrations:

```bash
php artisan migrate
```

---

## Step 4 — Follow HMVC rules

### Do

- Controllers call **one Service method** per action
- Services call **Repositories** and other **Services**
- Validation in **FormRequest** classes
- API responses via **JsonResource**
- Cross-module access via **dependency injection** of Services

### Don't

- Call another module's Controller from your Controller
- Put business logic in Controllers or Repositories
- Use raw `DB::` queries — use Eloquent via Repositories
- Skip FormRequest validation on write endpoints

### Example: Order needs User

```php
// app/Modules/Order/Services/OrderService.php
public function __construct(
    private OrderRepository $orders,
    private UserService $users,   // from User module
) {}
```

---

## Step 5 — Configure routes

### Default API pattern

Modules auto-register:

- API: `/api/{prefix}/{resource}` → `/api/v1/products`
- Web: `/{module-kebab}/` → `/product`

### Custom prefix (Auth & Dashboard pattern)

Auth and Dashboard register at app root (`/login`, `/dashboard`). To do the same for a module, customize its `ModuleServiceProvider`:

```php
// Register routes without module prefix
Route::middleware('web')->group(base_path('app/Modules/Shop/Routes/web.php'));
```

See `app/Modules/Auth/Providers/AuthModuleServiceProvider.php` for reference.

---

## Step 6 — Add caching (high-traffic reads)

For catalog-style data, wrap the repository with `CachedRepository`:

```php
// Already used in Setting and Category modules
$this->cache->remember('settings:all', fn () => $this->repository->all());
```

Invalidate cache on write in the Service layer.

---

## Step 7 — Queues & background jobs

For emails, payments, notifications:

```php
// app/Core/Jobs/SendEmailJob.php — example pattern
SendEmailJob::dispatch($user)->onQueue(config('structer.queues.emails'));
```

Configure Redis + Horizon for production. See [SCALING.md](../SCALING.md).

---

## Step 8 — Security defaults

Already included — verify before launch:

```bash
php artisan structer:security-check --audit
```

Production checklist:

```env
APP_ENV=production
APP_DEBUG=false
FORCE_HTTPS=true
SECURITY_HSTS=true
SESSION_ENCRYPT=true
```

---

## Step 9 — Update Postman & API docs

After adding endpoints:

1. Add requests to `postman/Structer-API.postman_collection.json`
2. Document in [docs/API.md](API.md)
3. Add PHPUnit integration tests

---

## Step 10 — Deploy

| Environment | Approach |
|-------------|----------|
| Local dev | `php artisan serve` + `npm run dev` |
| Staging | Docker Compose (`docker-compose.yml`) |
| Production | `docker-compose.prod.yml` + load balancer overlay |

See [DOCKER.md](../DOCKER.md) and [SCALING.md](../SCALING.md).

---

## File reference for new projects

| Path | Purpose |
|------|---------|
| `app/Core/` | Shared code — extend, don't duplicate |
| `app/Modules/` | Your features live here |
| `config/structer.php` | App-specific config |
| `lang/` | Translations (en, ar, fr, es, de) |
| `resources/css/app.css` | Tailwind + dark mode |
| `resources/js/theme.js` | Theme toggle |
| `tests/Integration/` | Feature tests |
| `qa/` | Python BDD + JMeter |
| `.github/workflows/` | CI/CD |

---

## Example: API-only SaaS

1. Keep: Auth, User, Setting
2. Disable: Category, Dashboard (or keep Dashboard for internal admin)
3. Add: `Tenant`, `Subscription`, `Invoice` modules
4. Use Sanctum tokens only — no web routes except password reset
5. Enable Redis + Horizon from day one

## Example: E-commerce

1. Keep: Auth, User, Category, Setting, Dashboard
2. Add: `Product`, `Cart`, `Order`, `Payment` modules
3. Use `ReservationService` for stock holds (see SCALING.md)
4. Queue payment processing on `payments` queue
5. Scale with Docker load balancer + read replicas

---

## Getting help

- Architecture: [STRUCTER.md](../STRUCTER.md)
- API details: [API.md](API.md)
- Tests: [QA.md](../QA.md)
- Commands: `php artisan list structer`
