<?php
session_start();
require_once 'secret/functions.php';

// Sécurité : accès réservé aux utilisateurs connectes
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit;
}

$email = $_SESSION['email'];
$message = "";
$resultats = null;
$afficher_voitures = false;
$lastAction = null; // pour savoir quelle action a produit $resultats

// --- info du user ---
$user = getUserByEmail($email);
$id_user = $user['id_user'];
$nom = htmlspecialchars($user['nom']);
$prenom = htmlspecialchars($user['prenom']);
$role = $user['role'];

// info du client
$id_client = null;

if ($role === "client") {
    $id_client = getClientId($id_user);
}

// actions possibles
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    switch ($_POST['action']) {

        case "show_cars":
            $afficher_voitures = true;
            break;

        case "hide_cars":
            $afficher_voitures = false;
            break;

        case "add_vehicle":
            if ($role === "client") {

                $plaque = strtoupper(trim($_POST['plaque']));
                $marque = trim($_POST['marque']);
                $modele = trim($_POST['modele']);

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

        case "remove_vehicle":
            if ($role === "client") {
                $plaque = strtoupper(trim($_POST['plaque']));
                deleteCar($plaque, $id_client);
                $message = "Voiture supprimée.";
            }
            break;

        case "client_stats":
            if ($role === "client") {
                $resultats = getHistory($id_client);
                $lastAction = "client_stats";
            }
            break;

        case "stats_place":
            if ($role === "administrateur") {
                $resultats = getParkingStats();
                $lastAction = "stats_place";
            }
            break;

        case "stats_abos":
            if ($role === "administrateur") {
                $resultats = getActiveSubscriptions();
                $lastAction = "stats_abos";
            }
            break;

        case "expiring":
            if ($role === "administrateur") 
            $resultats = getExpiringSubscriptions();
            break;

        case "free_spots":
            if ($role === "administrateur") 
            $resultats = getFreeParkingSpots();
            break;

        case "long_parking":
            if ($role === "administrateur") 
            $resultats = getLongParking();
            break;

        case "logout":
            session_destroy();
            header("Location: index.php");
            exit;



    }
}

// Recharger les voitures si demandé
$voitures = ($afficher_voitures && $role === "client") ? getClientCars($id_user) : [];
?>

<!DOCTYPE html>
<html>
<head>
<title>Tableau de Bord</title>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.container { max-width: 900px; margin: auto; }
input { padding: 6px; margin-top: 5px; }
button { padding: 8px; margin: 6px 0; cursor:pointer; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
td,th { border: 1px solid #ccc; padding: 6px; text-align: left;}
.chart-container { margin-top: 20px; }
</style>
<!-- Chart.js pour le graphique de l'admin -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<div class="container">

<h2>Bienvenue <?= $prenom . " " . $nom ?></h2>
<p>Rôle : <strong><?= htmlspecialchars($role) ?></strong></p>

<?php if ($message): ?>
<p style="color:green;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<hr>

<!---  CLIENT -->
<?php if ($role === "client"): ?>
<h3>Espace Client</h3>

<form method="GET" action="abonnement.php">
    <button>Gérer mes abonnements</button>
</form>

<form method="POST">
    <input type="text" name="plaque" placeholder="AA-123-BB" required>
    <input type="text" name="marque" placeholder="Marque (ex : Renault)">
    <input type="text" name="modele" placeholder="Modèle (ex : Clio)">
    <button name="action" value="add_vehicle">Ajouter une voiture</button>
</form>

<form method="POST">
    <input type="text" name="plaque" placeholder="AA-123-BB" required>
    <button name="action" value="remove_vehicle">Supprimer une voiture</button>
</form>

<form method="POST">
    <button name="action" value="client_stats">Afficher historique parking</button>
</form>

<br>

<form method="POST">
    <?php if ($afficher_voitures): ?>
        <button name="action" value="hide_cars">Masquer voitures</button>
    <?php else: ?>
        <button name="action" value="show_cars">Afficher mes voitures</button>
    <?php endif; ?>
</form>

<?php if ($afficher_voitures): ?>
<table>
<tr><th>Plaque</th><th>Marque</th><th>Modèle</th></tr>
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

<!----- ADMIN -->
<?php if ($role === "administrateur"): ?>
<h3>Administration</h3>

<form method="POST">
    <button name="action" value="stats_place">Occupation des parkings</button>
</form>

<form method="POST">
    <button name="action" value="stats_abos">Abonnements actifs</button>
</form>

<form method="POST"><button name="action" value="expiring">Abonnements expirant bientôt (7 jours)</button></form>
<form method="POST"><button name="action" value="free_spots">Places disponibles</button></form>
<form method="POST"><button name="action" value="long_parking">Stationnements > 8h</button></form>


<?php endif; ?>



<!-- Résultats SQL -->
<?php if ($resultats): ?>
<table>
<tr>
<?php foreach(array_keys($resultats[0]) as $col): ?>
    <th><?= htmlspecialchars($col) ?></th>
<?php endforeach; ?>
</tr>
<?php foreach($resultats as $row): ?>
<tr>
    <?php foreach($row as $value): ?>
        <td><?= htmlspecialchars($value) ?></td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<!-- Graphique pour l'admin : pour afficher l'occupation des parkings  -->
<?php if ($role === "administrateur" && $lastAction === "stats_place" && $resultats): ?>
<?php
    // données pour chart.js
    $labels = array_column($resultats, 'parking');
    $values = array_map('intval', array_column($resultats, 'places_occupees'));
?>
<div class="chart-container">
    <h3>Graphique : places occupées par parking</h3>
    <canvas id="placesChart"></canvas>
</div>

<script>
const ctx = document.getElementById('placesChart').getContext('2d');
const placesChart = new Chart(ctx, {
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

<form method="POST">
    <button name="action" value="logout">Déconnexion</button>
</form>

</div>
</body>
</html>
