
FROM php:8.5-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        graphviz \
        unzip \
        git \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        gd \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* 

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/assets/uploads \
    && chown -R www-data:www-data /var/www/html/assets/uploads \
    && printf 'output_buffering=4096\n' > /usr/local/etc/php/conf.d/zz-my-school.ini

EXPOSE 80