# API et back-office : PHP 8.3 + Apache.
#
# Deux cibles :
# - « dev » : le code est partagé depuis le poste (services api, queue et
#   scheduler de docker-compose.yml, à la racine du dépôt) ;
# - « production » (par défaut) : code, dépendances et styles du back-office
#   compris dans l'image. docker build -t findsolux-api backend

# 1. Base commune : PHP, Apache et les extensions de l'application.
FROM php:8.3-apache AS base

# gd : photos (JPEG, PNG, WebP) ; exif : orientation des photos prises au
# téléphone ; pdo_mysql : MySQL 8 ; zip, unzip et git : Composer.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd exif pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Photos jusqu'à 8 Mo (config/findsolux.php) et mémoire pour les redimensionner.
RUN { \
        echo 'upload_max_filesize = 10M'; \
        echo 'post_max_size = 12M'; \
        echo 'memory_limit = 256M'; \
    } > "$PHP_INI_DIR/conf.d/findsolux.ini"

# Apache sert le dossier « public » de Laravel.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
EXPOSE 80

# 2. Développement : réglages PHP de développement, rien d'autre à copier.
FROM base AS dev
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# 3. Dépendances PHP de production (couche en cache tant que composer.lock ne change pas).
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

# 4. Styles du back-office (Tailwind, Vite). Tailwind lit aussi les vues de
#    pagination de Laravel, dans vendor/.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
    ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# 5. Production (cible par défaut).
FROM base AS production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && docker-php-ext-install opcache
COPY --from=vendor /var/www/html/vendor ./vendor
# Le .dockerignore écarte les secrets (.env, compte Firebase), les photos et
# les caches du poste.
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache
