#!/bin/bash
# Запускать на сервере в каталоге приложения после git pull.
# Использование: cd /var/www/p-d-a-b.neeklo.ru && ./deploy/pull-and-build.sh

set -e
cd "$(dirname "$0")/.."

echo "=== git pull ==="
git pull origin main

echo "=== composer install ==="
composer install --no-dev --optimize-autoloader --no-interaction

echo "=== npm ci + build ==="
npm ci
npm run build

echo "=== Laravel cache ==="
php artisan config:cache
php artisan route:cache

echo "=== права ==="
chown -R www-data:www-data .
chmod -R 775 storage bootstrap/cache

echo "=== Готово ==="
