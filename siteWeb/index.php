<?php
session_start();
require_once 'secret/bd_conf.php';

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $foundUser = null;

    if ($email === '' || $password === '') {
        $errorMessage = "Veuillez entrer un email et un mot de passe.";
    } else {

        try {
            $stmt = $pdo->prepare("
                SELECT nom, prenom, email, mot_de_passe, role
                FROM utilisateur
                WHERE LOWER(email) = LOWER(?)
            ");

            $stmt->execute([$email]);
            $foundUser = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $errorMessage = "Erreur de connexion à la base de données.";
        }

        // vérification du mdp
        if ($foundUser && password_verify($password, $foundUser['mot_de_passe'])) {

            // création de la session utilisateur
            $_SESSION['email']  = $foundUser['email'];
            $_SESSION['nom']    = $foundUser['nom'];
            $_SESSION['prenom'] = $foundUser['prenom'];
            $_SESSION['role']   = $foundUser['role'];

            header('Location: dashboard.php');
            exit;
        } else {
            $errorMessage = "Identifiants incorrects.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #ececec;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-box {
            background: white;
            padding: 25px;
            width: 320px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0px 4px 10px rgba(0,0,0,0.1);
        }
        input {
            width: 100%;
            margin: 8px 0;
            padding: 10px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #4b79d8;
            border: none;
            color: white;
            cursor: pointer;
            margin-top: 10px;
            border-radius: 5px;
        }
        button:hover { background: #3a62b8; }
        .error { color: red; margin-bottom: 10px; }
    </style>
</head>

<body>
    <section class="login-box">
        <h2>Connexion</h2>

        <?php if (!empty($errorMessage)): ?>
            <p class="error"><?= htmlspecialchars($errorMessage) ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="Adresse e-mail" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
    </section>
</body>
</html>
