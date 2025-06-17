FROM php:8.2-apache

# Install MySQL extensions and client
RUN docker-php-ext-install mysqli pdo pdo_mysql && \
    apt-get update && apt-get install -y default-mysql-client

COPY . /var/www/html/
