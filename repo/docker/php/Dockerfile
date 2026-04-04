FROM php:8.2-apache

RUN apt-get update \
  && apt-get install -y --no-install-recommends git unzip libzip-dev nodejs npm \
  && docker-php-ext-install pdo_mysql \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

COPY docker/php/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["bash", "-lc", "if [ ! -d vendor ]; then composer install --no-interaction --prefer-dist; fi && mkdir -p /var/www/html/runtime/log /var/www/html/runtime/cache /var/www/html/runtime/temp /var/www/html/runtime/uploads && chmod -R 0777 /var/www/html/runtime && php /var/www/html/scripts/wait_for_mysql.php --host=mysql --port=3306 --db=pantrypilot --user=pantry --pass=pantrypass --timeout=120 && apache2-foreground"]
