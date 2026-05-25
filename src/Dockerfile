# Image de base PHP avec serveur web Apache
FROM php:8.2-apache

# Installation de l'extension PDO MySQL requise pour communiquer avec la DB
RUN docker-php-ext-install pdo pdo_mysql

# Copie du code source dans le dossier par défaut d'Apache

COPY . /var/www/html/


# Expose le port 80
EXPOSE 80
