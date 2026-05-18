<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générateur Pictionary</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding-top: 50px; background-color: #f4f4f9; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); display: inline-block; }
        h1 { color: #333; }
        .mot { font-size: 2em; font-weight: bold; color: #0056b3; margin: 20px 0; }
        button { padding: 10px 20px; font-size: 1em; cursor: pointer; background-color: #0056b3; color: white; border: none; border-radius: 5px; }
        button:hover { background-color: #004494; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Mot Pictionary 🎨</h1>
        <?php
        $host = getenv('DB_HOST') ?: 'db';
        $db   = getenv('DB_NAME') ?: 'pictionary';
        $user = getenv('DB_USER') ?: 'utilisateur';
        $pass = getenv('DB_PASS') ?: 'motdepasse';

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Requête pour récupérer un mot au hasard
            $stmt = $pdo->query("SELECT mot FROM mots ORDER BY RAND() LIMIT 1");
            $result = $stmt->fetch();

            if ($result) {
                echo "<div class='mot'>" . htmlspecialchars($result['mot']) . "</div>";
            } else {
                echo "<div class='mot'>Aucun mot trouvé dans la base.</div>";
            }
        } catch (PDOException $e) {
            echo "<div style='color: red;'>Erreur de connexion à la base de données.</div>";
            // En production, on ne masque les détails de l'erreur, mais ici c'est utile pour le debug :
            // echo "<p>" . $e->getMessage() . "</p>"; 
        }
        ?>
        <form method="GET">
            <button type="submit">Générer un autre mot</button>
        </form>
    </div>
</body>
</html>
