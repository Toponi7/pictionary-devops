<?php
// Connexion à la base de données MySQL via les variables d'environnement Docker
$host     = getenv('DB_HOST')     ?: 'db';
$user     = getenv('DB_USER')     ?: 'pictionary_user';
$password = getenv('DB_PASSWORD') ?: '';
$dbname   = getenv('DB_NAME')     ?: 'pictionary';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Si le JavaScript demande un mot (requête AJAX)
if (isset($_GET['action']) && $_GET['action'] == 'get_word') {
    $query  = $db->query("SELECT texte FROM mots ORDER BY RAND() LIMIT 1");
    $result = $query->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['mot' => $result ? $result['texte'] : "Base vide !"]);
    exit;
}

// Page HTML principale
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pictionary !</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #f9c74f 0%, #f3722c 25%, #f94144 50%, #43aa8b 75%, #577590 100%);
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .confetti {
            position: fixed;
            border-radius: 2px;
            animation: fall linear infinite;
            pointer-events: none;
        }

        @keyframes fall {
            0%   { transform: translateY(-20px) rotate(0deg); opacity: 1; }
            100% { transform: translateY(110vh) rotate(720deg); opacity: 0; }
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 50px 60px;
            box-shadow: 0 12px 0 #c0392b, 0 16px 30px rgba(0,0,0,0.3);
            border: 5px solid #fff;
            outline: 4px solid rgba(255,255,255,0.5);
            max-width: 500px;
            width: 100%;
            text-align: center;
            position: relative;
            animation: cardPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes cardPop {
            from { transform: scale(0.6) rotate(-5deg); opacity: 0; }
            to   { transform: scale(1) rotate(0deg); opacity: 1; }
        }

        .badge {
            position: absolute;
            top: -22px;
            left: 50%;
            transform: translateX(-50%);
            background: #f9c74f;
            border: 4px solid white;
            border-radius: 50px;
            padding: 6px 20px;
            font-size: 0.85em;
            font-weight: 900;
            color: #333;
            white-space: nowrap;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        h1 {
            font-family: 'Fredoka One', cursive;
            font-size: 2.8em;
            color: #f3722c;
            text-shadow: 3px 3px 0 #ffd166, 5px 5px 0 rgba(0,0,0,0.1);
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .subtitle {
            color: #aaa;
            font-size: 0.95em;
            margin-bottom: 30px;
        }

        .mot-container {
            background: linear-gradient(135deg, #fff5f5, #fff0fa);
            border: 3px dashed #f94144;
            border-radius: 16px;
            padding: 28px 20px;
            margin: 24px 0;
            position: relative;
            min-height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mot-container::before {
            content: "✏️";
            position: absolute;
            top: -18px;
            left: -18px;
            font-size: 2em;
            filter: drop-shadow(2px 2px 2px rgba(0,0,0,0.2));
        }

        .mot-container::after {
            content: "🎨";
            position: absolute;
            bottom: -18px;
            right: -18px;
            font-size: 2em;
            filter: drop-shadow(2px 2px 2px rgba(0,0,0,0.2));
        }

        #word-display {
            font-family: 'Fredoka One', cursive;
            font-size: 2.8em;
            color: #f94144;
            text-shadow: 2px 2px 0 #ffd166;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: opacity 0.2s ease;
        }

        #word-display.hidden { opacity: 0; }

        @keyframes wordPop {
            from { transform: scale(0.5); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        #word-display.pop {
            animation: wordPop 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            color: #ddd;
            font-size: 1.2em;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(to right, transparent, #ddd, transparent);
        }

        button {
            font-family: 'Fredoka One', cursive;
            font-size: 1.4em;
            cursor: pointer;
            background: linear-gradient(145deg, #43aa8b, #277e65);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px 40px;
            box-shadow: 0 6px 0 #1a5e45, 0 8px 15px rgba(0,0,0,0.2);
            transition: transform 0.1s, box-shadow 0.1s;
            letter-spacing: 1px;
        }

        button:hover { background: linear-gradient(145deg, #4ebfa0, #2d9170); }

        button:active {
            transform: translateY(4px);
            box-shadow: 0 2px 0 #1a5e45, 0 4px 8px rgba(0,0,0,0.2);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .stars {
            font-size: 1.2em;
            color: #f9c74f;
            margin-top: 20px;
            letter-spacing: 4px;
        }
    </style>
</head>
<body>

    <script>
        const colors = ['#f94144','#f3722c','#f9c74f','#43aa8b','#577590','#a8dadc'];
        for (let i = 0; i < 18; i++) {
            const el = document.createElement('div');
            el.className = 'confetti';
            el.style.cssText = `
                left: ${Math.random() * 100}vw;
                width: ${6 + Math.random() * 10}px;
                height: ${6 + Math.random() * 10}px;
                background: ${colors[Math.floor(Math.random() * colors.length)]};
                animation-duration: ${3 + Math.random() * 5}s;
                animation-delay: ${Math.random() * 5}s;
                border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
            `;
            document.body.appendChild(el);
        }
    </script>

    <div class="card">
        <div class="badge">🎲 Tour en cours !</div>

        <h1>Pictionary !</h1>
        <p class="subtitle">Faites deviner le mot à votre équipe ✨</p>

        <div class="mot-container">
            <div id="word-display">?</div>
        </div>

        <div class="divider">🎴</div>

        <button id="btn" onclick="generateWord()">🎲 Nouveau mot !</button>

        <div class="stars">★ ★ ★ ★ ★</div>
    </div>

    <script>
        async function generateWord() {
            const display = document.getElementById('word-display');
            const btn = document.getElementById('btn');

            btn.disabled = true;
            display.classList.remove('pop');
            display.classList.add('hidden');

            try {
                const response = await fetch('?action=get_word');
                const data = await response.json();

                setTimeout(() => {
                    display.textContent = data.mot;
                    display.classList.remove('hidden');
                    void display.offsetWidth; // force reflow pour relancer l'animation
                    display.classList.add('pop');
                    btn.disabled = false;
                }, 200);
            } catch (error) {
                display.textContent = "Erreur 😢";
                display.classList.remove('hidden');
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
