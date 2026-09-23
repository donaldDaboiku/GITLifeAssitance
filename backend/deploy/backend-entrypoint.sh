#!/bin/sh
set -e

cd /app

if [ ! -f .env ] && [ -f .env.production ]; then
  cp .env.production .env
fi

php artisan config:clear || true
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
