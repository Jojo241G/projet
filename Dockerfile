# Utiliser une image PHP officielle avec Apache
FROM php:8.2-apache

# Installer les extensions nécessaires
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Copier les fichiers du projet dans le dossier de l'image Docker
COPY . /var/www/html/

# Donner les bons droits
RUN chown -R www-data:www-data /var/www/html

# Activer le module rewrite d'Apache si tu utilises .htaccess
RUN a2enmod rewrite

# Changer les droits pour l'accès aux fichiers
RUN chmod -R 755 /var/www/html

# Exposer le port 80
EXPOSE 80