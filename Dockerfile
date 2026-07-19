FROM php:8.4-fpm

# Installer les dépendances système + extensions PHP en une seule couche
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    wget \
    zip \
    unzip \
    git \
    gnupg \
    libicu-dev \
    libpq-dev \
    default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql intl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# La limite par défaut (128M) est insuffisante pour cache:clear en dev
RUN echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/zz-memory-limit.ini

# Installer Composer depuis l'image officielle (plus fiable que le script)
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Définir le répertoire de travail
WORKDIR /usr/src/api

# Copier uniquement les fichiers de dépendances d'abord (cache Docker)
COPY composer.json composer.lock ./

# Installer les dépendances PHP sans les scripts (pas besoin de l'app complète)
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copier le reste du code source
COPY . .

# Finaliser l'autoloader
RUN composer dump-autoload --optimize

EXPOSE 9000
