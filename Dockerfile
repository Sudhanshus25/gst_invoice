# Use the official PHP + Apache image
FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite

# Install required packages and Composer
RUN apt-get update && apt-get install -y \
    unzip \
    curl \
    git \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set the working directory
WORKDIR /var/www/html

# Copy your application files into the container
COPY . .

# Only run composer install if composer.json exists
RUN if [ -f composer.json ]; then composer install; fi

EXPOSE 80
