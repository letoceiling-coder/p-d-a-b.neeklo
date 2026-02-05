# Деплой на сервер 89.169.39.244 (p-d-a-b.neeklo.ru)

## Обновление через Git (рекомендуется)

Репозиторий: **https://github.com/letoceiling-coder/p-d-a-b.neeklo**

**С ПК:** пушите изменения в GitHub:
```bash
git add .
git commit -m "описание изменений"
git push origin main
```

**На сервере:** подтянуть код и пересобрать:
```bash
ssh root@89.169.39.244
cd /var/www/p-d-a-b.neeklo.ru
./deploy/pull-and-build.sh
```

Или вручную:
```bash
cd /var/www/p-d-a-b.neeklo.ru
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan config:cache && php artisan route:cache
chown -R www-data:www-data .
```

Файл `.env` на сервере не трогается при `git pull` (он в .gitignore).

---

## Требования на сервере
- Ubuntu/Debian
- SSH доступ: `root@89.169.39.244`
- DNS: `p-d-a-b.neeklo.ru` → A 89.169.39.244 (уже настроен)

## 1. Загрузка файлов на сервер (с вашего ПК)

Из папки проекта выполните (подставьте свой путь к проекту):

```bash
# Вариант 1: rsync (исключаем node_modules, .git, vendor)
rsync -avz --progress \
  --exclude 'node_modules' \
  --exclude '.git' \
  --exclude 'vendor' \
  --exclude '.env' \
  --exclude 'storage/logs/*' \
  --exclude 'storage/framework/cache/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  ./ root@89.169.39.244:/var/www/p-d-a-b.neeklo.ru/
```

Или через SCP (если rsync нет):

```bash
scp -r app bootstrap config database public resources routes deploy artisan composer.json composer.lock package.json package-lock.json vite.config.js phpunit.xml root@89.169.39.244:/var/www/p-d-a-b.neeklo.ru/
```

## 2. На сервере: MySQL

Уже настроено при первом деплое:
- БД: `parser_bot_admin`
- Пользователь: `parser_bot_admin`
- Пароль записан в `/var/www/p-d-a-b.neeklo.ru/.env` (переменная `DB_PASSWORD`). При необходимости смените пароль в MySQL и в `.env`.

Для ручной настройки (новый сервер):

```bash
ssh root@89.169.39.244
mysql -u root -e "CREATE DATABASE parser_bot_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'parser_bot_admin'@'localhost' IDENTIFIED BY 'ВашПароль'; GRANT ALL ON parser_bot_admin.* TO 'parser_bot_admin'@'localhost'; FLUSH PRIVILEGES;"
```

## 3. На сервере: .env и первый деплой

```bash
ssh root@89.169.39.244
cd /var/www/p-d-a-b.neeklo.ru

# Создать .env из примера
cp deploy/env.production.example .env

# Отредактировать .env: задать DB_PASSWORD и при необходимости APP_KEY
nano .env
# Сохранить: Ctrl+O, Enter, Ctrl+X

# Запустить скрипт деплоя (установка пакетов, composer, npm build, миграции, nginx, certbot)
chmod +x deploy/server-deploy.sh
./deploy/server-deploy.sh
```

Если PHP на сервере не 8.3 — отредактируйте в `deploy/server-deploy.sh` переменную `PHP_VER` (например, `8.2`).

## 4. Создать пользователя админки

На сервере:

```bash
cd /var/www/p-d-a-b.neeklo.ru
php artisan user:create
```

## 5. SSL (Let's Encrypt)

Скрипт `server-deploy.sh` сам запускает certbot. Если нужно вручную:

```bash
certbot --nginx -d p-d-a-b.neeklo.ru
```

Проверка: откройте в браузере https://p-d-a-b.neeklo.ru и войдите (dsc-23@yandex.ru / 123123123).

Если после деплоя появляется 500 «Please provide a valid cache path» — на сервере выполните:
`cd /var/www/p-d-a-b.neeklo.ru && php artisan config:clear && php artisan view:clear`

## Кратко: одна команда с ПК (после настройки SSH)

После того как один раз создали каталог и настроили MySQL + .env на сервере, можно обновлять код так:

```bash
rsync -avz --exclude 'node_modules' --exclude '.git' --exclude 'vendor' --exclude '.env' ./ root@89.169.39.244:/var/www/p-d-a-b.neeklo.ru/
ssh root@89.169.39.244 "cd /var/www/p-d-a-b.neeklo.ru && composer install --no-dev --optimize-autoloader && npm ci --omit=dev && npm run build && php artisan migrate --force && php artisan config:cache && chown -R www-data:www-data ."
```
