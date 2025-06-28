# Use official PHP with Apache
FROM php:8.2-apache

# Enable mod_rewrite (optional, good for routing)
RUN a2enmod rewrite

# Install dependencies needed for Composer
RUN apt-get update && apt-get install -y \
    unzip \
    curl \
    git \
    zip

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy all files to the container
COPY . .

# Run composer install if composer.json exists
RUN test -f composer.json && composer install --no-interaction --prefer-dist --optimize-autoloader || true

# Expose Apache port
EXPOSE 80
