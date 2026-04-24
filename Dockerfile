FROM php:8.2-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    && docker-php-ext-install mysqli zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app

# Set Apache document root to public
ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# Create env.local from env.local.example if it doesn't exist (for local testing)
RUN cp env.local.example env.local || true

# Set proper permissions
RUN chown -R www-data:www-data /app
RUN chmod -R 755 /app

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
