#!/bin/bash
set -e

echo "[SiNotaris] Memulai backend Laravel..."

cd /var/www/html

if [ ! -f ".env" ]; then
    cp .env.example .env
fi

if ! grep -q "APP_KEY=base64:" .env || grep -q "PLACEHOLDER" .env; then
    php artisan key:generate --force
fi

echo "[SiNotaris] Menunggu database..."
while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent 2>/dev/null; do
    sleep 2
done
echo "[SiNotaris] Database siap!"

php artisan storage:link --force 2>/dev/null || true
php artisan migrate --force
php artisan db:seed --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache

echo "[SiNotaris] Backend siap di port 8000"
exec "$@"
