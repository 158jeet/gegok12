#!/bin/sh
# entrypoint.sh

# Copy template .env if not exists
if [ ! -f /var/www/.env ]; then
    cp /var/www/.env.example /var/www/.env

    # Set DB variables from Docker Compose environment
    sed -i "s/DB_HOST=.*/DB_HOST=${DB_HOST}/" /var/www/.env
    sed -i "s/DB_PORT=.*/DB_PORT=${DB_PORT}/" /var/www/.env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE}/" /var/www/.env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME}/" /var/www/.env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" /var/www/.env
fi

# Run composer install (optional if not done in Dockerfile)
composer install --no-interaction --optimize-autoloader

# Run the main command
exec "$@"
