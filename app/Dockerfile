# app/Dockerfile
# Utilisation de l'image officielle PHP avec Apache
FROM php:8.2-apache

# Installation de l'extension PDO MySQL requise par ton code PHP
RUN docker-php-ext-install pdo pdo_mysql

# Copie du code source dans le dossier d'Apache
COPY index.php /var/www/html/

# Exposition du port 80
EXPOSE 80
