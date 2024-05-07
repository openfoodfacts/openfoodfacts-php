# syntax=docker/dockerfile:experimental
FROM composer:2 AS COMPOSER
WORKDIR /opt/app
COPY composer.json .
RUN composer install --no-scripts --ignore-platform-reqs

#no need php-fpm when all command use php-cli
FROM php:8-bookworm
WORKDIR /opt/app
# Install php-src extensions
RUN DEBIAN_FRONTEND=noninteractive apt-get -qq update && \
    apt-get -qq --no-install-recommends install libpng-dev libjpeg-dev
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
RUN docker-php-ext-install -j$(nproc) gd bcmath
RUN rm -rf /tmp/pear /var/lib/apt/lists/*
RUN mkdir /tmp/profile; chmod 755 /tmp/profile

USER nobody
COPY ./docker/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY --from=COMPOSER /opt/app /opt/app
# Not permited by Sonar cube even though there is .dockerignore to remove sensitive data
#COPY . .
COPY src .
COPY tests .
COPY .php-cs-fixer.dist.php .
COPY phpunit.xml .
COPY phpstan.neon.dist .
