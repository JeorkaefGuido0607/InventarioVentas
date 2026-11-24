FROM php:8.5-apache
WORKDIR /var/www/html

RUN a2enmod rewrite
RUN a2enmod actions

# Composer se toma de la imagen oficial (mismo resultado que el instalador
# con verificación sha384 de CitasMedicas, sin depender de un hash que cambia).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY ./Business/composer.json .
COPY ./Business/.env .
COPY ./Business/phpunit.xml .

RUN apt update && apt install -y zip
RUN apt update && apt install -y git

RUN composer install

RUN composer dump-autoload

RUN sed -i 's/DocumentRoot.*$/DocumentRoot \/var\/www\/html\/public/' /etc/apache2/sites-enabled/000-default.conf
