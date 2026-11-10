# WPKG Express NG on PHP 8.5 / CakePHP 5.4.
FROM php:8.5.12-apache AS base
RUN a2enmod rewrite \
 && apt-get update \
 && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
 && docker-php-ext-install intl pdo_mysql zip \
 && apt-get purge -y --auto-remove libicu-dev libzip-dev \
 && apt-get install -y --no-install-recommends libicu76 libzip5 \
 && rm -rf /var/lib/apt/lists/*
COPY services/wpkg/php.ini /usr/local/etc/php/conf.d/wpkg.ini
WORKDIR /var/www/html

# Tooling image: bind-mounts ./web and carries composer for tests and migrations.
FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

FROM dev AS build
COPY web/ /var/www/html/
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM base AS prod
COPY --from=build --chown=www-data:www-data /var/www/html/ /var/www/html/
RUN mkdir -p tmp/cache/models tmp/cache/persistent tmp/cache/views tmp/sessions logs \
 && chown -R www-data:www-data tmp logs
EXPOSE 80
CMD ["apache2-foreground"]
