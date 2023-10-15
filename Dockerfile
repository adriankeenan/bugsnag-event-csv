FROM php:8.2-cli as build

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update
RUN apt-get install -y libzip-dev
RUN docker-php-ext-configure zip
RUN docker-php-ext-install zip

RUN echo "phar.readonly = Off" > "$PHP_INI_DIR/php.ini"

COPY . /usr/src/app
WORKDIR /usr/src/app

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

RUN composer build-phar

FROM php:8.2-cli-alpine
COPY --from=build /usr/src/app/dist/bugsnag-event-csv .

ENTRYPOINT ["./bugsnag-event-csv"]

