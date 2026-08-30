FROM php:8.2-apache

# Install PDO and MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite (useful for routing/clean URLs)
RUN a2enmod rewrite

# Copy website files to Apache web root
COPY . /var/www/html/

# Configure Apache to listen on Render's dynamic PORT variable
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 80