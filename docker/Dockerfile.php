# syntax=docker/dockerfile:1.9
FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
    freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev libzip-dev \
    icu-dev oniguruma-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j"$(nproc)" \
        gd intl mbstring opcache pdo_mysql zip

COPY docker/php/php.ini /usr/local/etc/php/conf.d/blocky.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

FROM base AS development
RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

FROM base AS production
ENV PHP_OPCACHE_ENABLE=1
