# =============================================================================
# Structer Laravel — multi-stage Dockerfile
# Targets: development | production
# =============================================================================

# --- Frontend assets -----------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# --- Composer base (vendor install stages) -------------------------------------
# Symfony 8.1 (locked) requires PHP >= 8.4.1 — keep Docker/CI on PHP 8.4+.
FROM php:8.4-cli-alpine AS composer-base

RUN apk add --no-cache \
        git \
        unzip \
        curl \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" bcmath intl mbstring pcntl zip \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

# --- Composer dependencies (production) --------------------------------------
FROM composer-base AS vendor-prod

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# --- Composer dependencies (development) ---------------------------------------
FROM composer-base AS vendor-dev

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-scripts \
    --prefer-dist

# --- Base PHP image ------------------------------------------------------------
FROM php:8.4-fpm-alpine AS php-base

RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpq-dev \
        postgresql-client \
        $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-network $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# --- Shared final setup (avoids artisan during image build) ---------------------
# composer dump-autoload runs post-autoload-dump → `php artisan package:discover`,
# which exits 255 without APP_KEY/.env. Use --no-scripts; entrypoint discovers packages.
# --- Development target --------------------------------------------------------
FROM php-base AS development

COPY --from=vendor-dev /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY . .

RUN composer dump-autoload --optimize --no-scripts \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["php-fpm"]

# --- Production target ---------------------------------------------------------
FROM php-base AS production

COPY --from=vendor-prod /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY . .

RUN composer dump-autoload --optimize --no-scripts \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["php-fpm"]