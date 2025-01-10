FROM php:8.3-fpm

# Mettre à jour les paquets et installer les dépendances nécessaires
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    zip \
    git \
    gnupg \
    libicu-dev \
    libpq-dev \
    default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql

RUN apt update && apt install -y libicu-dev && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install intl
# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Ajouter le dépôt de Node.js et installer Node.js et NPM
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get install -y nodejs

# Ajouter le code source de l'application
ADD . /usr/src/api

# Exposer le port 9000
EXPOSE 9000

# Définir le répertoire de travail
WORKDIR /usr/src/api
