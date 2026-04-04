FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 \
    php8.1-mysql \
    php8.1-gd \
    php8.1-curl \
    php8.1-mbstring \
    libapache2-mod-php8.1 \
    && apt-get clean

RUN a2enmod rewrite php8.1 \
    && a2dismod mpm_event \
    && a2enmod mpm_prefork

RUN echo '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>' \
    >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
RUN rm -f /var/www/html/index.html

COPY . .

RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p assets/uploads/students \
    && chmod -R 777 assets/uploads

EXPOSE 80

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
