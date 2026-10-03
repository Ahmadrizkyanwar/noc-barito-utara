# NOC Kabupaten Barito Utara — Laravel 12 (Inertia + Vue 3)
# Host tidak punya PHP/Composer → semua build lewat container.
# Multi-stage: node (Vite build) → php:8.2-fpm + nginx (1 container = 1 app).
#
# Ekstensi: pdo_mysql (DB), snmp (probe SNMP), sockets (RouterOS API),
#           zip/gd/opcache. Binary `ping` + setcap cap_net_raw → probe ICMP
#           bisa jalan sebagai www-data.

# ── Stage 1: build aset frontend ───────────────────────────────────────────
FROM node:24 AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ── Stage 2: runtime ──────────────────────────────────────────────────────
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        curl \
        git \
        unzip \
        iputils-ping \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libsnmp-dev \
    && rm -rf /var/lib/apt/lists/*

# Ekstensi PHP: pdo_mysql, snmp, zip, gd, sockets, opcache
# (mbstring sudah bawaan image php:8.2-fpm)
RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql snmp zip gd sockets opcache

# ping wajib punya cap_net_raw supaya www-data (php-fpm) bisa probe ICMP
RUN setcap cap_net_raw+ep /usr/bin/ping

RUN { \
      echo 'expose_php = Off'; \
      echo 'memory_limit = 512M'; \
      echo 'upload_max_filesize = 10M'; \
      echo 'post_max_size = 12M'; \
      echo 'max_execution_time = 120'; \
      echo 'date.timezone = Asia/Pontianak'; \
    } > /usr/local/etc/php/conf.d/app.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# ── Layer dependency (cache) ──────────────────────────────────────────────
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts

# ── Kode aplikasi ─────────────────────────────────────────────────────────
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions \
               storage/framework/views storage/framework/testing storage/logs \
               storage/app/uploads \
               bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# ── nginx ─────────────────────────────────────────────────────────────────
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up >/dev/null || exit 1

ENTRYPOINT ["/entrypoint.sh"]
