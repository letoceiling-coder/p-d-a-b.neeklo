#!/bin/bash
# Запускать на сервере от root (или с sudo).
# Использование: ./server-deploy.sh

set -e
SITE_DOMAIN="p-d-a-b.neeklo.ru"
APP_DIR="/var/www/${SITE_DOMAIN}"
PHP_VER="8.3"

echo "=== Деплой ${SITE_DOMAIN} ==="

# 1) Убедиться, что установлены nginx, php-fpm, mysql, certbot
if ! command -v nginx &>/dev/null; then
    apt-get update && apt-get install -y nginx
fi
if ! command -v php &>/dev/null; then
    apt-get update && apt-get install -y php${PHP_VER}-fpm php${PHP_VER}-mysql php${PHP_VER}-xml php${PHP_VER}-mbstring php${PHP_VER}-curl php${PHP_VER}-zip unzip
fi
if ! command -v mysql &>/dev/null; then
    apt-get update && apt-get install -y mysql-server
fi
if ! command -v certbot &>/dev/null; then
    apt-get update && apt-get install -y certbot python3-certbot-nginx
fi
if ! command -v composer &>/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
if ! command -v node &>/dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs
fi

# 2) Каталог приложения
mkdir -p "$APP_DIR"
cd "$APP_DIR"

# 3) Composer (без dev)
if [ -f "composer.json" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# 4) Node: сборка фронта
if [ -f "package.json" ]; then
    npm ci --omit=dev || npm install --omit=dev
    npm run build
fi

# 5) .env
if [ ! -f ".env" ]; then
    if [ -f "deploy/env.production.example" ]; then
        cp deploy/env.production.example .env
        echo "Создан .env из примера. Отредактируйте .env: APP_KEY, DB_*"
    else
        echo "Файл .env отсутствует. Скопируйте deploy/.env.production.example в .env и настройте."
        exit 1
    fi
fi

# 6) APP_KEY
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# 7) Миграции
php artisan migrate --force

# 8) Кэш конфига и маршрутов
php artisan config:cache
php artisan route:cache

# 9) Права
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# 10) Nginx
NGINX_CONF="/etc/nginx/sites-available/${SITE_DOMAIN}"
if [ -f "deploy/nginx-p-d-a-b.neeklo.ru.conf" ]; then
    sed "s|/var/www/p-d-a-b.neeklo.ru|$APP_DIR|g" deploy/nginx-p-d-a-b.neeklo.ru.conf > "$NGINX_CONF"
    # PHP-FPM socket: подставить версию PHP
    sed -i "s|php8.3-fpm|php${PHP_VER}-fpm|g" "$NGINX_CONF"
fi
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/ 2>/dev/null || true
nginx -t && systemctl reload nginx

# 11) SSL (если ещё нет сертификата для домена)
if [ ! -d "/etc/letsencrypt/live/${SITE_DOMAIN}" ]; then
    certbot --nginx -d "$SITE_DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email || true
fi

echo "=== Готово. Проверьте https://${SITE_DOMAIN} ==="
