#!/usr/bin/env bash
# Skrip update aplikasi di server (jalankan dari folder proyek sebagai user deploy).
set -euo pipefail

php artisan down --retry=15 || true

git pull --ff-only 2>/dev/null || echo "Lewati git pull (bukan repo git)"
composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
echo "Selesai."
