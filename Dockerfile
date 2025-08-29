FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libonig-dev \
    unzip \
    git \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install redis-6.0.2 && docker-php-ext-enable redis


RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


WORKDIR /var/www/html


EXPOSE 9000

CMD ["php-fpm"]