FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    zip unzip curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli gd \
    && apt-get clean

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -i 's/^LoadModule mpm_event/#LoadModule mpm_event/g' /etc/apache2/mods-enabled/*.load 2>/dev/null || true

WORKDIR /var/www/html
COPY . .

RUN mkdir -p assets/uploads/students \
    && chmod -R 777 assets/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
