# syntax=docker/dockerfile:1

# ---- Front-end assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# ---- PHP dependencies + app source ----
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# ---- Runtime image ----
FROM php:8.4-cli
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev unzip git \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN chmod -R a+w storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["docker-entrypoint.sh"]
