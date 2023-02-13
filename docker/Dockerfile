ARG PHP=8.1
FROM php:${PHP}-cli-alpine

RUN apk update \
    && apk add zip libzip-dev icu-dev

RUN docker-php-ext-configure zip
RUN docker-php-ext-install zip
RUN docker-php-ext-enable zip

RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl
RUN docker-php-ext-enable intl

RUN apk add --no-cache $PHPIZE_DEPS linux-headers
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENTRYPOINT ["php"]
