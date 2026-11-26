# ---- Front-end assets (React + TypeScript through Vite) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources resources
COPY vite.config.js tsconfig.json components.json ./
RUN npm run build

# ---- Runtime image ----
FROM php:8.4-cli

# pdo_pgsql / pdo_mysql: Render's Postgres or MySQL. gd + zip: PhpSpreadsheet and mPDF
# (mbstring, dom, xml, xmlwriter, iconv, fileinfo and zlib ship with the base image).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_mysql zip gd opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first so source edits reuse this layer. Installed here (not in a
# composer image) so the platform check sees the real extensions.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
COPY --from=assets /app/public/build public/build
RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/mpdf storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R a+w storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# The built-in server answers several requests at once with this many workers.
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8080
ENTRYPOINT ["docker-entrypoint.sh"]
