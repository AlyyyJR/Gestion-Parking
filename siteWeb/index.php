<?php
// ================================================================
// FICHIER : index.php
// Projet  : Système de Gestion de Parking
// Auteur  : Aly KONATE — L2 Informatique
// ================================================================
// Page de connexion du site web de gestion de parking
//
// Rôle de index.php :
//   - Afficher le formulaire de connexion (email + mot de passe)
//   - Vérifier les identifiants saisis contre la base de données
//   - Créer la session utilisateur en cas de succès
//   - Rediriger vers dashboard.php après connexion réussie
//   - Afficher un message d'erreur en cas d'identifiants incorrects
//
// Sécurités implémentées :
//   - Requête préparée (PDO) : protection contre les injections SQL
//   - password_verify() : vérification du hash bcrypt du mot de passe
//   - htmlspecialchars() : protection contre les failles XSS
//   - Comparaison email insensible à la casse (LOWER())
//   - Champs vides détectés avant toute requête BDD
//
// Flux de connexion :
//   1. L'utilisateur soumet le formulaire (POST)
//   2. Vérification que les champs ne sont pas vides
//   3. Recherche de l'utilisateur par email en base
//   4. Vérification du mot de passe avec password_verify()
//   5. Création de la session et redirection vers dashboard.php
//      OU affichage du message d'erreur
// ================================================================

session_start();
require_once 'secret/bd_conf.php';

// ================================================================
// Initialisation du message d'erreur
// ================================================================
$errorMessage = '';

// ================================================================
// Traitement du formulaire de connexion (méthode POST uniquement)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Récupération et nettoyage des champs ---
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $foundUser = null;

    // --- Vérification que les champs ne sont pas vides ---
    if ($email === '' || $password === '') {
        $errorMessage = "Veuillez entrer un email et un mot de passe.";

    } else {

        // --- Recherche de l'utilisateur par email en base ---
        // La comparaison est insensible à la casse grâce à LOWER()
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

        // --- Vérification du mot de passe avec bcrypt ---
        // password_verify() compare le mot de passe saisi
        // avec le hash stocké en base (format bcrypt)
        if ($foundUser && password_verify($password, $foundUser['mot_de_passe'])) {

            // --- Création de la session utilisateur ---
            // Les données de session sont utilisées dans tout le site
            $_SESSION['email']  = $foundUser['email'];
            $_SESSION['nom']    = $foundUser['nom'];
            $_SESSION['prenom'] = $foundUser['prenom'];
            $_SESSION['role']   = $foundUser['role'];  // 'client' ou 'administrateur'

            // --- Redirection vers le tableau de bord ---
            header('Location: dashboard.php');
            exit;

        } else {
            // Identifiants incorrects (email inconnu ou mauvais mot de passe)
            // Message volontairement générique pour ne pas indiquer
            // si c'est l'email ou le mot de passe qui est faux
            $errorMessage = "Identifiants incorrects.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion — Parking</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #ececec;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        /* Boîte de connexion centrée */
        .login-box {
            background: white;
            padding: 25px;
            width: 320px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }

        input {
            width: 100%;
            margin: 8px 0;
            padding: 10px;
            box-sizing: border-box;
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
            font-size: 15px;
        }

        button:hover { background: #3a62b8; }

        /* Message d'erreur affiché si identifiants incorrects */
        .error {
            color: red;
            margin-bottom: 10px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <section class="login-box">
        <h2>Connexion</h2>

        <!-- Affichage du message d'erreur si présent -->
        <?php if (!empty($errorMessage)): ?>
            <p class="error"><?= htmlspecialchars($errorMessage) ?></p>
        <?php endif; ?>

        <!-- Formulaire de connexion -->
        <form method="POST">
            <input type="email"
                   name="email"
                   placeholder="Adresse e-mail"
                   required
                   autocomplete="email">

            <input type="password"
                   name="password"
                   placeholder="Mot de passe"
                   required
                   autocomplete="current-password">

            <button type="submit">Se connecter</button>
        </form>
    </section>
</body>
</html>