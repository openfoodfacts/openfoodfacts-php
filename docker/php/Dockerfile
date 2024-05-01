# syntax=docker/dockerfile:experimental
FROM composer:2 AS COMPOSER
ENV COMPOSER_HOME=/usr/config/composer
ENV GITHUB_TOKEN=""
RUN if [[ -z "$GITHUB_TOKEN" ]] ; then echo Github Token not provided ; else composer config -g github-oauth.github.com $GITHUB_TOKEN; fi

FROM php:8.1-fpm

ENV COMPOSER_HOME=/usr/config/composer
ARG USER_ID
ARG GROUP_ID
RUN usermod -u $USER_ID www-data && groupmod -g $GROUP_ID www-data

# Install php-src extensions
RUN apt-get -qq update && apt-get -qq --no-install-recommends install \
         libzip-dev \
         unzip \
         libpng-dev \
         libjpeg-dev \
         mariadb-client \
         git > /dev/null && \
     pecl install xdebug-3.1.6 > /dev/null && \
     docker-php-ext-enable xdebug > /dev/null && \
     pecl install -o -f redis-5.3.4  > /dev/null && \
     docker-php-ext-install \
         -j$(nproc) \
         zip \
         gd \
         bcmath \
         pdo_mysql > /dev/null &&  \
     rm -rf /tmp/pear  > /dev/null && \
     rm -rf /var/lib/apt/lists/*
RUN mkdir /tmp/profile; chmod 755 /tmp/profile

# Install composer and dependencies
COPY --chown=root:root --from=COMPOSER --chmod=755 /usr/bin/composer /usr/bin/composer


ENV GITHUB_TOKEN=""
###COPY --chown=www-data:www-data --from=COMPOSER /usr/config/composer /usr/config/composer
#Re-enable aboce COPY when Token is set

# Make sure composer can checkout github
RUN mkdir -p /var/www/.ssh/ && \
    touch /var/www/.ssh/known_hosts && \
    ssh-keyscan github.com >> /var/www/.ssh/known_hosts

WORKDIR /var/www/html

USER www-data
