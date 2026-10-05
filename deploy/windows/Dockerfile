FROM php:8.4-cli-bookworm AS vendor
RUN apt-get update && apt-get install -y libzip-dev libonig-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip git && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install mbstring exif bcmath intl gd zip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs && chmod -R ug+rwx bootstrap/cache storage
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install --no-audit --no-fund
COPY . .
RUN npm run build
FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev libicu-dev unzip git && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY deploy/windows/docker-entrypoint.sh /usr/local/bin/tagore-entrypoint
RUN chmod +x /usr/local/bin/tagore-entrypoint
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/js ./public/js
COPY --from=assets /app/public/css ./public/css
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs && chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwx storage bootstrap/cache
ENTRYPOINT ["tagore-entrypoint"]
CMD ["php-fpm"]