FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    curl \
    zip \
    libxml2-dev \
    libicu-dev \
    libssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Give PHP-FPM enough workers for concurrent school operations.
# The upstream image defaults to only five children, which caused 504s
# under the live role/navigation acceptance sweep.
RUN sed -ri \
    -e 's/^pm.max_children = .*/pm.max_children = 12/' \
    -e 's/^pm.start_servers = .*/pm.start_servers = 4/' \
    -e 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 3/' \
    -e 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 8/' \
    -e 's/^;?pm.max_requests = .*/pm.max_requests = 500/' \
    /usr/local/etc/php-fpm.d/www.conf \
    && php-fpm -tt

# ----------------------------
# Install Node.js (LTS version)
# ----------------------------
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && node -v \
    && npm -v

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Fix Git "dubious ownership" issue automatically
RUN git config --system --add safe.directory /var/www

# Ensure correct permissions for Laravel
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www

# Switch to www-data user (recommended for Laravel)
USER www-data
