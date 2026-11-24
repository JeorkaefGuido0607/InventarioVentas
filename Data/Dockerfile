FROM php:8.5-apache
WORKDIR /var/www/html

RUN a2enmod rewrite
RUN a2enmod actions
RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY ./Data/composer.json .
COPY ./Data/.env .

RUN apt update && apt install -y zip
RUN apt update && apt install -y git

RUN composer install

RUN composer dump-autoload

RUN sed -i 's/DocumentRoot.*$/DocumentRoot \/var\/www\/html\/public/' /etc/apache2/sites-enabled/000-default.conf
