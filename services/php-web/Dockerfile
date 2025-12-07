FROM php:8.3-fpm-alpine

# пакеты и расширения
RUN apk add --no-cache bash curl git unzip rsync postgresql-dev \
 && docker-php-ext-install pdo pdo_pgsql

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# каталоги
RUN mkdir -p /var/www/html /opt/laravel-patches
WORKDIR /var/www/html

# entrypoint - создаём скрипт прямо в Dockerfile чтобы избежать проблем с CRLF
RUN printf '#!/bin/sh\n\
set -e\n\
APP_DIR=/var/www/html\n\
PATCH_DIR=/opt/laravel-patches\n\
echo "[php] init start"\n\
if [ ! -f "$APP_DIR/artisan" ]; then\n\
  echo "[php] creating laravel skeleton"\n\
  composer create-project --no-interaction --prefer-dist laravel/laravel:^11 "$APP_DIR"\n\
  cp "$APP_DIR/.env.example" "$APP_DIR/.env" || true\n\
  php "$APP_DIR/artisan" key:generate || true\n\
fi\n\
if [ -d "$PATCH_DIR" ]; then\n\
  echo "[php] applying patches"\n\
  rsync -a "$PATCH_DIR/" "$APP_DIR/"\n\
fi\n\
chown -R www-data:www-data "$APP_DIR"\n\
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true\n\
echo "[php] starting php-fpm"\n\
php-fpm -F\n' > /usr/local/bin/php_entrypoint.sh \
  && chmod +x /usr/local/bin/php_entrypoint.sh

CMD ["/usr/local/bin/php_entrypoint.sh"]
