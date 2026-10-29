# Staging and production deploy / rollback

App servers are stateless except `storage/` and the database. Use the same commit on staging and production.

## Environment

| | Staging | Production |
|--|---------|------------|
| `APP_ENV` | `staging` | `production` |
| `APP_DEBUG` | `false` | `false` |
| `APP_URL` | staging host | public host |
| Database | `aqdar_eg_staging` | `aqdar_eg` |
| `FORCE_HTTPS` | `true` | `true` |

Never copy `.env` into git. Marketplace maintenance is the **ADM-13** `maintenance_mode` setting (API 503, Blade desk stays up, `/up` stays up). Laravel `php artisan down` is a separate infra switch.

## Deploy

```bash
git fetch && git checkout <tag-or-sha>
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci && npm run build
php artisan aqdar:openapi --path=docs/openapi.yaml
php artisan aqdar:backup-check
php artisan aqdar:backup
php artisan aqdar:ops-health
```

Restart PHP-FPM / Horizon after env or queue worker changes.

## Rollback

1. `git checkout` the previous release tag.
2. `composer install --no-dev --optimize-autoloader`
3. Restore the matching database dump **only if a migration in the bad release is unsafe to reverse**. Prefer `php artisan migrate:rollback --step=N` on staging first.
4. Restore `storage/app/private` from the same backup set if files were migrated.
5. Recache config/routes/views and restart workers.
6. Smoke UAT-20: `/up`, `/api/v1/config`, `/dashboard` login.

Do not `migrate:fresh` on production.
