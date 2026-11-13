# Тестовое окружение: PHP 8.2 + Apache (mod_rewrite) + pdo_mysql.
FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
