# Structer — HMVC Architecture

> **Starter template docs:** [README.md](README.md) · [docs/STARTER_GUIDE.md](docs/STARTER_GUIDE.md) · [docs/API.md](docs/API.md) · [Postman](postman/)

This project uses **Structer**, a modular HMVC layout where every feature is a self-contained module with its own service layer.

## Folder Layout

```
app/
├── Core/                          # Shared infrastructure (do not put features here)
│   ├── Contracts/                 # Interfaces (RepositoryContract, …)
│   ├── Http/Controllers/          # BaseController
│   ├── Repositories/              # AbstractRepository
│   ├── Services/                  # AbstractService
│   ├── Module/                    # Module auto-loading
│   └── Console/Commands/          # make:module scaffold command
│
├── Modules/                       # ← Every feature lives here
│   └── User/                      # Example module
│       ├── Providers/             # ModuleServiceProvider (bindings + boot)
│       ├── Http/
│       │   ├── Controllers/       # Thin controllers (C in HMVC)
│       │   ├── Requests/          # Form validation
│       │   └── Resources/         # API responses (V for JSON)
│       ├── Models/                # Eloquent models (M)
│       ├── Services/              # Business logic — main entry point
│       ├── Repositories/          # Database access
│       ├── Routes/
│       │   ├── api.php            # /api/v1/users
│       │   └── web.php            # /user
│       ├── Resources/views/       # Blade views (V for web)
│       ├── Database/
│       │   ├── Migrations/
│       │   ├── Factories/
│       │   └── Seeders/
│       └── Tests/
│
├── Models/                        # Legacy aliases only (e.g. User → Module\User)
└── Providers/
    └── StructerServiceProvider.php
```

## HMVC Flow

```
Request → Route → Controller → Service → Repository → Model → Database
                      ↓
                   Resource / View
```

| Layer | Responsibility |
|-------|----------------|
| **Controller** | HTTP only — receives request, returns response |
| **Service** | Business rules, orchestrates repositories & other services |
| **Repository** | Queries and persistence |
| **Model** | Data structure and relationships |
| **Request** | Input validation |
| **Resource / View** | Output formatting |

### Cross-module calls

Modules talk to each other through **Services**, not controllers:

```php
// In OrderService — inject another module's service
public function __construct(
    private OrderRepository $orders,
    private UserService $users,   // ← from User module
) {}
```

## Create a New Module

```bash
php artisan make:module Product
```

This scaffolds:

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
└── Routes/api.php + web.php
```

Then:

1. Define `$fillable` and relationships in `Models/Product.php`
2. Add a migration in `Database/Migrations/`
3. Run `php artisan migrate`

Modules are **auto-discovered** — no manual registration needed.

## Routes

| Type | Pattern | Example |
|------|---------|---------|
| API | `/api/v1/{resource}` | `GET /api/v1/users` |
| Web | `/{module}` | `GET /user` |

Change the API prefix in `.env`:

```env
STRUCTER_API_PREFIX=v1
```

## Disable a Module

In `config/structer.php`:

```php
'disabled_modules' => ['LegacyFeature'],
```

## Auth API

| Method | URL | Auth |
|--------|-----|------|
| POST | `/api/v1/auth/register` | No |
| POST | `/api/v1/auth/login` | No |
| POST | `/api/v1/auth/forgot-password` | No |
| POST | `/api/v1/auth/reset-password` | No |
| GET | `/api/v1/auth/me` | Bearer token |
| POST | `/api/v1/auth/logout` | Bearer token |

Password reset emails link to the web reset page at `/reset-password/{token}`.

## Web Dashboard

| URL | Description |
|-----|-------------|
| `/dashboard` | Login form (guest) or dashboard (authenticated) |
| `/login` | Redirects to `/dashboard` |

Run `npm run dev` alongside `php artisan serve` for live CSS updates.

## Validation

Shared rules live in `app/Core/Validation/ValidationRules.php`:

| Method | Use for |
|--------|---------|
| `name()` | Person name (letters, 2–255 chars) |
| `email()` | Email with uniqueness support |
| `password()` | Min 8 chars, letters + numbers |
| `text()` | General text fields |
| `number()` | Numeric values |
| `integer()` | Whole numbers |

All API and dashboard forms extend `BaseFormRequest` for consistent messages and JSON error responses.

## Localization (laravel-lang)

Package: `laravel-lang/common` — installs translations for **en, ar, fr, es, de** via:

```bash
php artisan lang:add ar en fr es de
php artisan lang:update   # update language packs
```

### API — `Accept-Language` header

```http
POST /api/v1/auth/login
Accept-Language: ar
Content-Type: application/json
```

Responses include `Content-Language: ar` and translated validation/auth messages.

### Dashboard — language switcher

Use the language links in the header, or visit `/locale/ar`, `/locale/en`, etc.

Supported locales: `en`, `ar`, `fr`, `es`, `de` (config: `structer.available_locales`).

### Remember me

Check **Remember me** on the dashboard login form to stay signed in via a persistent cookie.
