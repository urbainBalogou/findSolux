# 1. Image de base avec PHP 8.3 et un serveur web Apache
FROM php:8.3-apache

# 2. Installation des extensions requises par Laravel et la base de données
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git \
    && docker-php-ext-install pdo_mysql zip

# 3. Configuration d'Apache pour pointer sur le dossier "public" de Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite

# 4. Définition du dossier de travail
WORKDIR /var/www/html

# 5. Récupération de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Copie de tout le code de ton API (le .dockerignore va filtrer)
COPY . .

# 7. Installation propre des dépendances Laravel à l'intérieur du conteneur
RUN composer install --no-dev --optimize-autoloader

# 8. Attribution des droits d'écriture à Apache pour que Laravel puisse fonctionner
RUN chown -R www-data:www-data storage bootstrap/cache

# 9. Ouverture du port 80 (port web standard)
EXPOSE 80