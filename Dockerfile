FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY app ./app
COPY database ./database
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM node:22-alpine AS frontend

WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js . ./
COPY --from=dependencies /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm run build

FROM php:8.2-cli-alpine

WORKDIR /var/www/html

RUN apk add --no-cache icu-libs libzip oniguruma sqlite-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS curl-dev icu-dev libzip-dev oniguruma-dev sqlite-dev \
    && docker-php-ext-install bcmath curl intl mbstring pdo_sqlite \
    && apk del .build-deps

COPY --from=dependencies /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache database

EXPOSE 8000

CMD ["sh", "-c", "set -eu; key_file=/var/lib/mt5-config/app.key; mkdir -p /var/lib/mt5-config; if [ -n \"${APP_KEY:-}\" ]; then printf '%s' \"$APP_KEY\" > \"$key_file\"; elif [ ! -s \"$key_file\" ]; then php -r 'echo \"base64:\".base64_encode(random_bytes(32));' > \"$key_file\"; fi; export APP_KEY=\"$(cat \"$key_file\")\"; php artisan migrate --force; if [ \"${SEED_DEFAULT_USER:-false}\" = \"true\" ]; then php artisan db:seed --class=UserSeeder --force; fi; php artisan config:cache; php artisan route:cache; exec php artisan serve --host=0.0.0.0 --port=8000"]