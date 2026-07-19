FROM php:8.5-cli-alpine AS base

RUN apk add --no-cache \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        sqlite-dev \
        git \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_sqlite \
        pdo_mysql \
        pdo_pgsql \
        intl \
        mbstring \
        zip \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress || true

COPY . .
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
