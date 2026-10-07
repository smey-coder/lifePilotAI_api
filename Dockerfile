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
FROM dunglas/frankenphp:latest-php8.4-alpine

ENV PORT=10000
ENV SERVER_NAME=":10000"

# ដំឡើងដេប៉ង់ដង់ និង PHP Extensions សម្រាប់ PostgreSQL & LifePilot AI
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

# ចម្លង Vendor និងប្រភពកូដចូលក្នុង Container
COPY --from=vendor /app/vendor /app/vendor
COPY . /app

# Optimize Autoloader
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev && rm /usr/bin/composer

# រៀបចំ Storage Link និង Permissions
RUN rm -rf /app/public/storage \
    && php artisan storage:link \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/public \
    && chmod -R 775 /app/storage /app/bootstrap/cache /app/public

EXPOSE 10000

# រត់ FrankenPHP Server Directly លើ Folder public
CMD ["frankenphp", "php-server", "--root", "public"]