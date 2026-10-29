#!/bin/sh
set -e

cd /var/www/html

# Wait for PostgreSQL when DB_CONNECTION is pgsql
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "pgsql" ]; then
    echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."
    until php -r "
        try {
            new PDO(
                'pgsql:host=${DB_HOST};port=${DB_PORT:-5432};dbname=${DB_DATABASE}',
                '${DB_USERNAME}',
                '${DB_PASSWORD}'
            );
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    "; do
        sleep 2
    done
    echo "PostgreSQL is ready."
fi

# Wait for Redis when REDIS_HOST is set (sessions, cache, queues)
if [ -n "$REDIS_HOST" ]; then
    echo "Waiting for Redis at ${REDIS_HOST}:${REDIS_PORT:-6379}..."
    until php -r "
        try {
            \$redis = new Redis();
            \$redis->connect('${REDIS_HOST}', (int) ('${REDIS_PORT:-6379}'), 2);
            if ('${REDIS_PASSWORD}' !== '') {
                \$redis->auth('${REDIS_PASSWORD}');
            }
            echo \$redis->ping() ? 'PONG' : '';
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    "; do
        sleep 2
    done
    echo "Redis is ready."
fi

# Generate key if missing (first run / dev)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --force --ansi
fi

# Rebuild package discovery (skipped at image build with composer --no-scripts)
php artisan package:discover --ansi --no-interaction 2>/dev/null || true

php artisan migrate --force --ansi

if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache --ansi
    php artisan route:cache --ansi
    php artisan view:cache --ansi
    php artisan event:cache --ansi 2>/dev/null || true
else
    php artisan config:clear --ansi
    php artisan route:clear --ansi
    php artisan view:clear --ansi
fi

php artisan storage:link --force --ansi 2>/dev/null || true

exec "$@"
