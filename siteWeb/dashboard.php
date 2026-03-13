<?php
// ================================================================
// FICHIER : dashboard.php
// Projet  : Système de Gestion de Parking
// Auteur  : Aly KONATE — L2 Informatique
// ================================================================
// Tableau de bord principal du site web de gestion de parking
//
// Rôle de dashboard.php :
//   - Vérifier que l'utilisateur est bien connecté (session active)
//   - Dispatcher les actions POST selon le rôle (client / admin)
//   - Afficher l'interface adaptée au rôle de l'utilisateur
//   - Déléguer toutes les requêtes BDD à functions.php
//
// Architecture :
//   Ce fichier joue le rôle de Contrôleur (MVC).
//   Il reçoit les actions POST, les traite via functions.php,
//   puis passe les données à la vue HTML en bas de fichier.
//
// Actions disponibles selon le rôle :
//
//   [CLIENT]
//   add_vehicle    → ajoute une voiture (validation format plaque)
//   remove_vehicle → supprime une voiture du compte
//   show_cars      → affiche la liste des voitures
//   hide_cars      → masque la liste des voitures
//   client_stats   → affiche l'historique des passages
//
//   [ADMINISTRATEUR]
//   stats_place    → occupation des parkings + graphique Chart.js
//   stats_abos     → liste des abonnements actifs
//   expiring       → abonnements expirant dans 7 jours
//   free_spots     → places disponibles par parking
//   long_parking   → stationnements dépassant 8 heures
//
//   [COMMUN]
//   logout         → destruction de la session + redirection index.php
// ================================================================

session_start();
require_once 'secret/functions.php';

// ================================================================
// Sécurité : redirection si l'utilisateur n'est pas connecté
// ================================================================
// Toute tentative d'accès sans session active est redirigée
// vers la page de connexion index.php
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit;
}

// ================================================================
// Initialisation des variables de la vue
// ================================================================
$email           = $_SESSION['email'];
$message         = "";       // Message de retour affiché à l'utilisateur
$resultats       = null;     // Résultats SQL à afficher dans le tableau
$afficher_voitures = false;  // Contrôle l'affichage du tableau de voitures
$lastAction      = null;     // Dernière action exécutée (pour le graphique admin)

// ================================================================
// Chargement du profil utilisateur
// ================================================================
$user    = getUserByEmail($email);
$id_user = $user['id_user'];
$nom     = htmlspecialchars($user['nom']);
$prenom  = htmlspecialchars($user['prenom']);
$role    = $user['role'];  // 'client' ou 'administrateur'

// Récupération de l'id_client (uniquement si l'utilisateur est un client)
$id_client = null;
if ($role === "client") {
    $id_client = getClientId($id_user);
}

// ================================================================
// Traitement des actions POST
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    switch ($_POST['action']) {

        // ----------------------------------------------------
        // Afficher / masquer la liste des voitures du client
        // ----------------------------------------------------
        case "show_cars":
            $afficher_voitures = true;
            break;

        case "hide_cars":
            $afficher_voitures = false;
            break;

        // ----------------------------------------------------
        // Ajouter une voiture au compte du client
        // Validation du format de plaque avant insertion
        // ----------------------------------------------------
        case "add_vehicle":
            if ($role === "client") {
                $plaque = strtoupper(trim($_POST['plaque']));
                $marque = trim($_POST['marque']);
                $modele = trim($_POST['modele']);

                // Vérification du format AA-123-BB côté serveur
                if (!preg_match('/^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$/', $plaque)) {
                    $message = "Format de plaque invalide (exemple : AB-123-CD)";
                    break;
                }

                $err = null;
                if (addCar($plaque, $marque, $modele, $id_client, $err)) {
                    $message = "Voiture ajoutée avec succès.";
                } else {
                    $message = $err;
                }
            }
            break;

        // ----------------------------------------------------
        // Supprimer une voiture du compte du client
        // ----------------------------------------------------
        case "remove_vehicle":
            if ($role === "client") {
                $plaque = strtoupper(trim($_POST['plaque']));
                deleteCar($plaque, $id_client);
                $message = "Voiture supprimée.";
            }
            break;

        // ----------------------------------------------------
        // Afficher l'historique des passages du client
        // ----------------------------------------------------
        case "client_stats":
            if ($role === "client") {
                $resultats  = getHistory($id_client);
                $lastAction = "client_stats";
            }
            break;

        // ----------------------------------------------------
        // [ADMIN] Occupation des places par parking
        // Alimente aussi le graphique Chart.js en bas de page
        // ----------------------------------------------------
        case "stats_place":
            if ($role === "administrateur") {
                $resultats  = getParkingStats();
                $lastAction = "stats_place";
            }
            break;

        // ----------------------------------------------------
        // [ADMIN] Liste des abonnements actifs
        // ----------------------------------------------------
        case "stats_abos":
            if ($role === "administrateur") {
                $resultats  = getActiveSubscriptions();
                $lastAction = "stats_abos";
            }
            break;

        // ----------------------------------------------------
        // [ADMIN] Abonnements expirant dans les 7 prochains jours
        // ----------------------------------------------------
        case "expiring":
            if ($role === "administrateur") {
                $resultats = getExpiringSubscriptions();
            }
            break;

        // ----------------------------------------------------
        // [ADMIN] Places disponibles dans tous les parkings
        // ----------------------------------------------------
        case "free_spots":
            if ($role === "administrateur") {
                $resultats = getFreeParkingSpots();
            }
            break;

        // ----------------------------------------------------
        // [ADMIN] Stationnements dépassant 8 heures
        // ----------------------------------------------------
        case "long_parking":
            if ($role === "administrateur") {
                $resultats = getLongParking();
            }
            break;

        // ----------------------------------------------------
        // Déconnexion : destruction de la session
        // ----------------------------------------------------
        case "logout":
            session_destroy();
            header("Location: index.php");
            exit;
    }
}

// Chargement des voitures si l'affichage est activé
$voitures = ($afficher_voitures && $role === "client") ? getClientCars($id_user) : [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de Bord — Parking</title>
    <style>
        body            { font-family: Arial, sans-serif; padding: 20px; }
        .container      { max-width: 900px; margin: auto; }
        input           { padding: 6px; margin-top: 5px; }
        button          { padding: 8px; margin: 6px 0; cursor: pointer; }
        table           { width: 100%; border-collapse: collapse; margin-top: 15px; }
        td, th          { border: 1px solid #ccc; padding: 6px; text-align: left; }
        .chart-container { margin-top: 20px; }
        .msg-success    { color: green; margin-top: 10px; }
        .msg-error      { color: red;   margin-top: 10px; }
    </style>
    <!-- Chart.js : bibliothèque de graphiques pour l'interface admin -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<div class="container">

    <h2>Bienvenue <?= $prenom . " " . $nom ?></h2>
    <p>Rôle : <strong><?= htmlspecialchars($role) ?></strong></p>

    <!-- Message de retour (succès ou erreur) -->
    <?php if ($message): ?>
        <p class="msg-success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <hr>

    <!-- ============================================================ -->
    <!-- ESPACE CLIENT                                                 -->
    <!-- ============================================================ -->
    <?php if ($role === "client"): ?>
        <h3>Espace Client</h3>

        <!-- Lien vers la gestion des abonnements -->
        <form method="GET" action="abonnement.php">
            <button>Gérer mes abonnements</button>
        </form>

        <!-- Formulaire d'ajout de voiture -->
        <form method="POST">
            <input type="text"   name="plaque" placeholder="AB-123-CD" required>
            <input type="text"   name="marque" placeholder="Marque (ex : Renault)">
            <input type="text"   name="modele" placeholder="Modèle (ex : Clio)">
            <button name="action" value="add_vehicle">Ajouter une voiture</button>
        </form>

        <!-- Formulaire de suppression de voiture -->
        <form method="POST">
            <input type="text" name="plaque" placeholder="AB-123-CD" required>
            <button name="action" value="remove_vehicle">Supprimer une voiture</button>
        </form>

        <!-- Historique des passages -->
        <form method="POST">
            <button name="action" value="client_stats">Afficher historique parking</button>
        </form>

        <br>

        <!-- Afficher / masquer les voitures enregistrées -->
        <form method="POST">
            <?php if ($afficher_voitures): ?>
                <button name="action" value="hide_cars">Masquer mes voitures</button>
            <?php else: ?>
                <button name="action" value="show_cars">Afficher mes voitures</button>
            <?php endif; ?>
        </form>

        <!-- Tableau des voitures (visible si show_cars actif) -->
        <?php if ($afficher_voitures): ?>
            <table>
                <tr>
                    <th>Plaque</th>
                    <th>Marque</th>
                    <th>Modèle</th>
                </tr>
                <?php foreach ($voitures as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['plaque']) ?></td>
                        <td><?= htmlspecialchars($v['marque'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['modele'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- ESPACE ADMINISTRATEUR                                         -->
    <!-- ============================================================ -->
    <?php if ($role === "administrateur"): ?>
        <h3>Administration</h3>

        <form method="POST">
            <button name="action" value="stats_place">Occupation des parkings</button>
        </form>
        <form method="POST">
            <button name="action" value="stats_abos">Abonnements actifs</button>
        </form>
        <form method="POST">
            <button name="action" value="expiring">Abonnements expirant bientôt (7 jours)</button>
        </form>
        <form method="POST">
            <button name="action" value="free_spots">Places disponibles</button>
        </form>
        <form method="POST">
            <button name="action" value="long_parking">Stationnements > 8h</button>
        </form>

    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- TABLEAU DE RÉSULTATS SQL                                      -->
    <!-- Affiché dynamiquement pour toutes les requêtes               -->
    <!-- ============================================================ -->
    <?php if ($resultats): ?>
        <table>
            <tr>
                <?php foreach (array_keys($resultats[0]) as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
            </tr>
            <?php foreach ($resultats as $row): ?>
                <tr>
                    <?php foreach ($row as $value): ?>
                        <td><?= htmlspecialchars($value) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- GRAPHIQUE ADMIN — Occupation des parkings (Chart.js)         -->
    <!-- Affiché uniquement après l'action "stats_place"              -->
    <!-- ============================================================ -->
    <?php if ($role === "administrateur" && $lastAction === "stats_place" && $resultats): ?>
        <?php
            // Extraction des labels (noms de parkings) et valeurs (places occupées)
            $labels = array_column($resultats, 'parking');
            $values = array_map('intval', array_column($resultats, 'places_occupees'));
        ?>
        <div class="chart-container">
            <h3>Graphique : places occupées par parking</h3>
            <canvas id="placesChart"></canvas>
        </div>

        <script>
            const ctx = document.getElementById('placesChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        label: 'Places occupées',
                        data: <?= json_encode($values, JSON_NUMERIC_CHECK) ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        </script>
    <?php endif; ?>

    <hr>

    <!-- Bouton de déconnexion -->
    <form method="POST">
        <button name="action" value="logout">Déconnexion</button>
    </form>

</div>
</body>
</html>