# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Set environment variables
ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure Apache
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY composer.json ./
COPY composer.lock* ./

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    && composer clear-cache

# Copy application files
COPY . .

# Create necessary directories and set permissions
RUN mkdir -p temp_qr uploads && \
    chown -R www-data:www-data \
    /var/www/html && \
    chmod -R 755 \
    /var/www/html && \
    chmod -R 775 \
    /var/www/html/temp_qr \
    /var/www/html/uploads

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]