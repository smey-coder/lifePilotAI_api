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
# Stage 2: Runtime Environment
# ==============================================================================
FROM php:8.4-cli-alpine

ENV PORT=10000

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

# Optimize Composer Autoloader
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev && rm /usr/bin/composer

# រៀបចំ Storage Link និងកំណត់សិទ្ធិ (Permissions)
RUN rm -rf /app/public/storage \
    && php artisan storage:link \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/public \
    && chmod -R 775 /app/storage /app/bootstrap/cache /app/public

# Optimize Laravel Runtime Caching ដើម្បីឱ្យការឆ្លើយតប Request លឿន
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 10000

# លុប Entrypoint ចាស់ចោល
ENTRYPOINT []

# ដំណើរការ Server ដោយប្រើ PHP Router ជំនួសឱ្យ artisan serve
CMD ["php", "-S", "0.0.0.0:10000", "-t", "public", "public/index.php"]