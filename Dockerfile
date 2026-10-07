# ==============================================================================
# Stage 1: Build Dependencies
# ==============================================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-reqs \
    --no-scripts

# ==============================================================================
# Stage 2: Runtime Environment (FrankenPHP)
# ==============================================================================
FROM dunglas/frankenphp:1-php8.4-alpine

ENV PORT=10000
ENV SERVER_NAME=":10000"

# Install system dependencies and required PHP extensions
RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pdo_mysql \
        zip \
        intl \
        bcmath \
        opcache \
        gd

WORKDIR /app

# Copy Vendor and Application Source Code
COPY --from=vendor /app/vendor /app/vendor
COPY . /app

# Optimize Autoloader
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev && rm /usr/bin/composer

# Storage Link & Permissions Setup
RUN rm -rf /app/public/storage \
    && php artisan storage:link \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/public \
    && chmod -R 775 /app/storage /app/bootstrap/cache /app/public

EXPOSE 10000

CMD ["frankenphp", "php-server", "--root", "public"]