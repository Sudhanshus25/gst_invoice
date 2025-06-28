# Use an official PHP + Apache image
FROM php:8.2-apache

# Enable mod_rewrite (often needed for routing in PHP apps)
RUN a2enmod rewrite

# Install necessary tools and Composer
RUN apt-get update && apt-get install -y \
    unzip \
    curl \
    git \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy all files to the container
COPY . .

# Install PHP dependencies (if composer.json exists)
RUN if [ -f composer.json ]; then composer install; fi

# Expose port 80 for Apache (Render will map this automatically)
EXPOSE 80
