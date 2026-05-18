<?php
// 1. Connexion à la base de données MySQL
$host = 'localhost';
$user = 'root';
$password = 'ton_mot_de_passe'; // À modifier
$dbname = 'pictionary';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 2. Si le JavaScript demande un mot (requête AJAX)
if (isset($_GET['action']) && $_GET['action'] == 'get_word') {
    $query = $db->query("SELECT texte FROM mots ORDER BY RAND() LIMIT 1");
    $result = $query->fetch(PDO:: some_content_type_here);
    
    header('Content-Type: application/json');
    echo json_encode(['mot' => $result ? $result['texte'] : "Base vide !"]);
    exit; // On arrête le script ici pour ne pas envoyer le reste du HTML
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générateur Pictionary</title>
    <style>
        body {
            font-family: 'Comic Sans MS', 'Chalkboard SE', sans-serif;
            background: linear-gradient(135deg, #FF9A9E 0%, #FECFEF 99%, #FECFEF 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            color: #333;
        }
        .container {
            background-color: white;
            padding: 40px;
            border-radius: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            text-align: center;
            max-width: 400px;
            width: 90%;
            border: 4px solid #FFF;
        }
        h1 {
            color: #FF6B6B;
            font-size: 2.5em;
            margin-top: 0;
            text-shadow: 2px 2px 0px #FFE66D;
        }
        .word-display {
            font-size: 3em;
            font-weight: bold;
            color: #4ECDC4;
            min-height: 80px;
            margin: 30px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        button {
            background-color: #FF6B6B;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.2em;
            font-weight: bold;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 6px 0 #D93838;
            transition: all 0.1s ease;
            font-family: inherit;
        }
        button:active { transform: translateY(6px); box-shadow: 0 0px 0 #D93838; }
        button:hover { background-color: #FF5252; }
        p { color: #888; font-size: 0.9em; }
    </style>
</head>
<body>

    <div class="container">
        <h1>Pictionary</h1>
        <p>Clique sur le bouton pour obtenir un mot à dessiner !</p>
        <div class="word-display" id="word-display">?</div>
        <button onclick="generateWord()">Générer un mot</button>
    </div>

    <script>
        async function generateWord() {
            const wordDisplay = document.getElementById('word-display');
            try {
                // On appelle le même fichier index.php, mais avec le paramètre action
                const response = await fetch('index.php?action=get_word');
                const data = await response.json();
                
                wordDisplay.style.opacity = '0';
                setTimeout(() => {
                    wordDisplay.textContent = data.mot;
                    wordDisplay.style.opacity = '1';
                    wordDisplay.style.transition = 'opacity 0.3s ease';
                }, 150);
            } catch (error) {
                console.error("Erreur :", error);
                wordDisplay.textContent = "Erreur 😢";
            }
        }
    </script>
</body>
</html>
