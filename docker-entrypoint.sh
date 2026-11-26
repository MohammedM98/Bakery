#!/bin/sh
set -e

# Runs only the migrations not yet applied (the movements backfill runs once, on the first deploy that has it).
php artisan migrate --force

# Demo accounts (admin@bakery.test / owner@bakery.test, password "password") are created only
# when no super admin exists yet. Set SEED_DEMO_DATA=false to skip it on a real installation.
if [ "${SEED_DEMO_DATA:-true}" = "true" ]; then
    php artisan db:seed --force --class=DatabaseSeeder
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# --no-reload lets PHP_CLI_SERVER_WORKERS (set in the Dockerfile) start several workers.
exec php artisan serve --no-reload --host=0.0.0.0 --port="${PORT:-8080}"
