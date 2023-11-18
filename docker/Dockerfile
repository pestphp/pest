ARG PHP=8.1
FROM php:${PHP}-cli-alpine

RUN apk update && apk add \
    zip libzip-dev icu-dev git \

RUN docker-php-ext-configure zip intl
RUN docker-php-ext-install zip intl
RUN docker-php-ext-enable zip intl

RUN apk add --no-cache linux-headers
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENTRYPOINT ["php"]
