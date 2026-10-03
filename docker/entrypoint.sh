#!/bin/sh
# entrypoint: php-fpm (background) + nginx (foreground).
set -e

# Direktori runtime wajib ada (named volume dapat menimpa isi image).
mkdir -p \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/logs \
    /var/www/html/storage/app/uploads \
    /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# ── Migrasi + seed (sekali start; gagal = log, app tetap jalan) ────────────
# DB_HOST diambil dari env (container compose menyetelnya ke `mariadb`).
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    su -s /bin/sh www-data -c 'cd /var/www/html && php artisan migrate --force' \
        >> /var/www/html/storage/logs/migrate.log 2>&1 || echo "migrate: gagal (lihat storage/logs/migrate.log)"
    su -s /bin/sh www-data -c 'cd /var/www/html && php artisan db:seed --force' \
        >> /var/www/html/storage/logs/migrate.log 2>&1 || echo "seed: gagal (lihat storage/logs/migrate.log)"
fi

# ── Scheduler TANPA cron host (pola proyek monitoring yang sudah ada) ──────
# monitor:poll tiap 30 dtk + metrics:prune harian.
su -s /bin/sh www-data -c 'cd /var/www/html && HOME=/tmp exec php artisan schedule:work >> /var/www/html/storage/logs/schedule-work.log 2>&1' &

php-fpm -D

exec nginx -g 'daemon off;'
