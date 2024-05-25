FROM php:8.1-apache

# Copy vào thư mục gốc của apache
COPY . /var/www/html

# Set quyền cho thư mục đảm bảo apache có thể đọc được
RUN chown -R www-data:www-data /var/www/html

# Cài đặt các dependencies cần thiết cho PHP và PostgreSQL
RUN apt-get update && \
    apt-get install -y libpq-dev && \
    docker-php-ext-install pdo pdo_pgsql pgsql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer && chmod +x /usr/bin/composer

# Enable mod_rewrite
RUN a2enmod rewrite

EXPOSE 80

CMD ["apache2-foreground"]
