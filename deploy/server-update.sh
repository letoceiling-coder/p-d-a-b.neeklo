#!/bin/bash
# Быстрое обновление кода на сервере (после git push).
# Запускать на сервере: cd /var/www/p-d-a-b.neeklo.ru && ./deploy/server-update.sh
# Или по SSH: ssh root@85.198.64.93 'cd /var/www/p-d-a-b.neeklo.ru && bash deploy/server-update.sh'

set -e
APP_DIR="${APP_DIR:-/var/www/p-d-a-b.neeklo.ru}"

echo "=== Обновление $APP_DIR ==="
cd "$APP_DIR"

# 1) Обновить код
if [ -d ".git" ]; then
  git pull
else
  echo "Не найден .git — пропуск git pull. Скопируйте файлы вручную."
fi

# 2) Зависимости и сборка
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --omit=dev 2>/dev/null || npm install --omit=dev
npm run build

# 3) Миграции
php artisan migrate --force

# 4) Кэш
php artisan config:cache
php artisan route:cache

# 5) Права
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 storage bootstrap/cache

echo "=== Готово ==="
