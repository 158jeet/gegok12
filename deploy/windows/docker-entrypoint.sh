#!/bin/sh
set -e

# app_storage is a persistent Docker volume mounted over /var/www/html/storage.
# Initialize its Laravel-required directory tree every time the container starts.
mkdir -p \
  /var/www/html/storage/app \
  /var/www/html/storage/framework/cache \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/logs \
  /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
