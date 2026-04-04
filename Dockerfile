FROM debian:bullseye-slim

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 php7.4 php7.4-mysql php7.4-gd \
    php7.4-curl php7.4-mbstring libapache2-mod-php7.4 \
    && rm -rf /var/lib/apt/lists/*

RUN rm -f /etc/apache2/mods-enabled/mpm_* \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/ \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/

RUN a2enmod rewrite php7.4 \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . .
RUN rm -f index.html

RUN mkdir -p assets/uploads/students \
    && chmod -R 777 assets/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["/bin/bash", "start.sh"]
