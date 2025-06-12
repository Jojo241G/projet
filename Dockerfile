# Utilise l'image de base officielle de PHP 8.2 avec Apache
FROM php:8.2-apache

# Met à jour les paquets et installe les dépendances système nécessaires
# libpq-dev est la bibliothèque de développement pour le client PostgreSQL
# C'est cette ligne qui manquait et qui corrige l'erreur
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installe les extensions PHP pour PostgreSQL
# Maintenant, cette commande trouvera les fichiers nécessaires comme libpq-fe.h
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Copie les fichiers de votre application dans le répertoire web du conteneur
COPY . /var/www/html/

# Donne la propriété du répertoire à l'utilisateur du serveur web (Apache)
RUN chown -R www-data:www-data /var/www/html

# Active le module de réécriture d'URL d'Apache (utile pour les frameworks comme Laravel ou Symfony)
RUN a2enmod rewrite

# Expose le port 80 pour que le conteneur soit accessible
EXPOSE 80
