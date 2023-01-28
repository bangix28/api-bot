FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
curl \
wget \
zip \
git

RUN apt install gnupg -y
RUN apt-get install default-mysql-client -y
RUN docker-php-ext-install pdo pdo_mysql
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer



ADD ./app /usr/src/myapp

EXPOSE 9000

WORKDIR /usr/src/myapp
