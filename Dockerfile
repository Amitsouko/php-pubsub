FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libonig-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

CMD ["php-fpm"]