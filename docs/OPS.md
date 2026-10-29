# Operations: monitoring, backups, restore drill

Staff see failed job and failed push counts on **ADM-01** when either is non-zero. CLI:

```bash
php artisan aqdar:ops-health
php artisan aqdar:prune-audit
php artisan queue:failed
```

`aqdar:ops-health` exits non-zero when `failed_jobs` or failed push deliveries exist. Schedule `aqdar:prune-audit` daily (already in `routes/console.php`). Retention is setting `audit_retention_months` (default 24). There is **no** HTTP delete for `audit_logs` or `access_logs`.

## Backups

| What | How |
|------|-----|
| PostgreSQL | `pg_dump -Fc aqdar_eg > /backups/aqdar_eg-$(date +%F).dump` |
| Private documents | copy `storage/app/private/documents` (and `private/media`) off-box |
| Public media | copy `storage/app/public/media` or the object-storage bucket |

`php artisan aqdar:backup-check` verifies those directories exist and are writable. `php artisan aqdar:backup` runs the check, copies `storage/app/private/{documents,media}` to `$AQDAR_BACKUP_PATH/files-YYYY-MM-DD/`, then `pg_dump -Fc` when the default connection is PostgreSQL. Both are scheduled daily; an empty `AQDAR_BACKUP_PATH` skips copy and dump (safe on local/dev).

## Restore drill (staging)

1. Restore Postgres: `pg_restore --clean --if-exists -d aqdar_eg_staging backup.dump`
2. Restore private files to the same relative paths.
3. `php artisan migrate --force` (should be no-op if dump is current).
4. Smoke: `GET /up`, `GET /api/v1/config`, staff login at `/dashboard` (UAT-20).
5. Confirm a signed document download still 403 without signature.

Keep production dumps off git. Rotate dump access with the same controls as `DB_PASSWORD`.
