#!/bin/bash
set -e
DB_PASS="PdaB$(date +%s)Neeklo"
mysql -u root <<EOSQL
CREATE DATABASE IF NOT EXISTS parser_bot_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'parser_bot_admin'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON parser_bot_admin.* TO 'parser_bot_admin'@'localhost';
FLUSH PRIVILEGES;
EOSQL
echo "$DB_PASS"
