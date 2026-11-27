#!/bin/sh
set -e

# Runs only the migrations not yet applied (the movements backfill runs once, on the first deploy that has it).
php artisan migrate --force

# The first super admin comes from ADMIN_EMAIL / ADMIN_PASSWORD; nothing happens once one exists.
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    php artisan app:create-admin
fi

# Demo accounts (admin@bakery.test / owner@bakery.test, password "password") only when asked for
# with SEED_DEMO_DATA=true, and only while no super admin exists.
if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    php artisan db:seed --force --class=DatabaseSeeder
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# --no-reload lets PHP_CLI_SERVER_WORKERS (set in the Dockerfile) start several workers.
exec php artisan serve --no-reload --host=0.0.0.0 --port="${PORT:-8080}"
