FROM php:8.2-apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev libcurl4-openssl-dev \
 && docker-php-ext-install mbstring curl \
 && a2enmod rewrite headers deflate \
 && sed -i 's#AllowOverride None#AllowOverride All#g' /etc/apache2/apache2.conf \
 && rm -rf /var/lib/apt/lists/*
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/system/data \
 && chmod -R 775 /var/www/html/system/data
EXPOSE 80
