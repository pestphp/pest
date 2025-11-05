ARG PHP=8.3
FROM php:${PHP}-cli-alpine

RUN apk update && apk add --no-cache \
    zip libzip-dev icu-dev git \
    linux-headers autoconf build-base

RUN docker-php-ext-install zip intl sockets
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENTRYPOINT ["php"]
