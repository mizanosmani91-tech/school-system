FROM php:8.2-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli gd \
    && apt-get clean

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all project files
COPY . .

# Create uploads directory and set permissions
RUN mkdir -p assets/uploads/students \
    && chmod -R 755 assets/uploads \
    && chown -R www-data:www-data /var/www/html

# Apache config - allow .htaccess
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/school.conf \
    && a2enconf school

# Expose port
EXPOSE 80

# Copy and make start script executable
RUN chmod +x /var/www/html/start.sh

CMD ["/bin/bash", "/var/www/html/start.sh"]
