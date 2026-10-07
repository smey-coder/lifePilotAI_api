# Stage 1: Vendor Dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --ignore-platform-reqs --no-scripts

# Stage 2: Runtime Environment
FROM php:8.4-fpm-alpine

ENV PORT=10000

RUN apk add --no-cache \
        nginx \
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

COPY --from=vendor /app/vendor /app/vendor
COPY . /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev && rm /usr/bin/composer

RUN rm -rf /app/public/storage \
    && php artisan storage:link \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/public \
    && chmod -R 775 /app/storage /app/bootstrap/cache /app/public

RUN echo 'server { \
    listen 10000; \
    root /app/public; \
    index index.php; \
    location / { \
        try_files $uri $uri/ /index.php?$query_string; \
    } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
        include fastcgi_params; \
    } \
}' > /etc/nginx/http.d/default.conf

EXPOSE 10000

CMD php-fpm -D && nginx -g "daemon off;"