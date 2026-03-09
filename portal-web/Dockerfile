FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libldap2-dev \
    libpq-dev \
    curl \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mysqli \
        ldap \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

COPY src/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
