FROM php:8.1-apache

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Enable Apache modules
RUN a2enmod rewrite headers

# Set environment variable for port (Render uses dynamic port)
ENV PORT=8080
RUN sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf && \
    sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Copy application
COPY . .

# Set permissions
RUN mkdir -p backups && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod 666 movies.csv users.json bot_stats.json movie_requests.json bot_activity.log 2>/dev/null || true && \
    chmod 777 backups

EXPOSE ${PORT}
CMD ["apache2-foreground"]