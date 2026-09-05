# GamoryID backend image — used for the app / worker / scheduler services.
# Multi-stage: install PHP deps, build the admin panel's Vite assets, then
# ship a slim FrankenPHP runtime with only the built artifacts.

# --- 1. PHP dependencies -----------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev

# --- 2. Admin panel assets (Blade + Vite + Tailwind) ------------------------
FROM node:20-alpine AS admin-assets
WORKDIR /app
COPY backend/package.json backend/package-lock.json ./
RUN npm ci
COPY backend/ ./
RUN npm run build

# --- 3. Runtime --------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.2 AS runtime
WORKDIR /app

# PHP extensions Laravel needs beyond FrankenPHP's defaults.
RUN install-php-extensions pdo_mysql gd intl zip opcache

COPY --from=vendor /app /app
COPY --from=admin-assets /app/public/build /app/public/build

RUN php artisan storage:link || true \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

ENV SERVER_NAME=:8000
EXPOSE 8000

# Runs Laravel on plain HTTP inside the docker network — the edge Caddy
# container (Dockerfile.web) is the only thing that terminates public TLS.
CMD ["frankenphp", "php-server", "--listen", ":8000", "--root", "/app/public", "--no-compress"]
