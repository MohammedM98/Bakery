#!/bin/sh
set -e

php artisan migrate --force
php artisan db:seed --force --class=DatabaseSeeder
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
