#!/bin/sh
set -eu

cd /app

if [ ! -f .env ]; then
  cp .env.example .env
fi

mkdir -p bootstrap/cache \
  storage/app \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  database

touch database/database.sqlite

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force --no-interaction
fi

if [ ! -f storage/app/.docker-initialized ]; then
  php artisan migrate:fresh --seed --force
  touch storage/app/.docker-initialized
else
  php artisan migrate --force
fi

exec php artisan serve --host=0.0.0.0 --port=8000