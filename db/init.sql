-- Création de la table 'mots'
CREATE TABLE IF NOT EXISTS mots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    texte VARCHAR(255) NOT NULL UNIQUE
);

-- Insertion de la liste de mots pour le Pictionary
INSERT INTO mots (texte) VALUES 
('Éléphant'), ('Guitare'), ('Arc-en-ciel'), ('Hélicoptère'), ('Pyramide'),
('Chaussette'), ('Montgolfière'), ('Tour Eiffel'), ('Tracteur'), ('Panda'),
('Brosse à dents'), ('Microscope'), ('Sirène'), ('Tornade'), ('Dinosaure'),
('Château de sable'), ('Astronaute'), ('Pizza'), ('Trottinette'), ('Papillon');
