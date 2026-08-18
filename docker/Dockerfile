# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage: vendor — install PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY app/composer.json app/composer.lock ./
RUN composer install \
    --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY app/ .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------------------------------------------------------------------------
# Stage: assets — build Tailwind/Vite frontend assets
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY app/package.json app/package-lock.json* ./
RUN npm install
COPY app/ .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---------------------------------------------------------------------------
# Stage: runtime — PHP-FPM application image
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        bash \
        icu-libs \
        libpq \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        sqlite-libs \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        sqlite-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pdo_mysql pdo_sqlite bcmath intl zip gd opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

WORKDIR /var/www/html

COPY app/ .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage: webserver — Nginx serving static assets + proxying PHP to runtime
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS webserver
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/html/public
