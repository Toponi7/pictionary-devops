CREATE DATABASE IF NOT EXISTS pictionary;
USE pictionary;

CREATE TABLE IF NOT EXISTS mots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mot VARCHAR(255) NOT NULL
);

INSERT INTO mots (mot) VALUES 
('Chien'), ('Maison'), ('Voiture'), ('Tour Eiffel'), 
('Ordinateur'), ('Téléphone'), ('Arbre'), ('Soleil'), 
('Guitare'), ('Squelette'), ('Astronaute'), ('Plage');
