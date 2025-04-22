FROM php:8.3-fpm

USER root

WORKDIR /var/www

# Extensions
RUN apt-get update && apt-get install -y \
    git unzip zip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

CMD bash -c "php-fpm"
