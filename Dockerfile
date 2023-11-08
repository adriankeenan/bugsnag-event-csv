FROM php:8.0-cli

RUN apt-get update
RUN apt-get install -y libzip-dev
RUN docker-php-ext-configure zip
RUN docker-php-ext-install zip

COPY . /usr/src/app
WORKDIR /usr/src/app

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --no-dev \
    --prefer-dist

ENTRYPOINT [ "php", "./bin/main.php" ]
CMD [ "php", "./bin/main.php" ]