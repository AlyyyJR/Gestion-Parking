<?php
session_start();
require_once 'secret/bd_conf.php';

// Sécurité : accès réservé au client connecté
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'client') {
    header("Location: index.php");
    exit;
}

$email = $_SESSION['email'];
$message = "";

// Récupérer id utilisateur
$stmt = $pdo->prepare("SELECT id_user, nom, prenom FROM utilisateur WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();
$id_user = $user['id_user'];
$nom = htmlspecialchars($user['nom']);
$prenom = htmlspecialchars($user['prenom']);

// Récupérer id_client
$stmt = $pdo->prepare("SELECT id_client FROM client WHERE id_user_fk=? LIMIT 1");
$stmt->execute([$id_user]);
$id_client = $stmt->fetchColumn();

// Gestion actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ajouter abonnement
    if ($_POST['action'] === 'new_subscription') {
        
        $type = $_POST['type'];
        $date_debut = date('Y-m-d');
        
        // Durée selon type
        $duree = match($type) {
            "mensuel" => "+1 month",
            "annuel"  => "+1 year",
            "hebdomadaire" => "+1 week",
            default => "+30 days"
        };

        $date_fin = date('Y-m-d', strtotime($duree));

        $stmt = $pdo->prepare("
            INSERT INTO abonnement(type, date_debut, date_fin, statut, id_client_fk)
            VALUES (?, ?, ?, 'actif', ?)
        ");
        $stmt->execute([$type, $date_debut, $date_fin, $id_client]);

        $message = "Nouvel abonnement ajouté.";
    }

    // Désactiver un abonnement
    if ($_POST['action'] === 'disable_abo') {
        $id = (int) $_POST['abo_id'];
        $stmt = $pdo->prepare("
            UPDATE abonnement 
            SET statut='expiré' 
            WHERE id_abonnement=? AND id_client_fk=?
        ");
        $stmt->execute([$id, $id_client]);
        $message = "Abonnement désactivé.";
    }

    // Renouveler un abonnement
    if ($_POST['action'] === 'renew_abo') {
        $id = (int) $_POST['abo_id'];

        // Récupérer type
        $stmt = $pdo->prepare("
            SELECT type 
            FROM abonnement 
            WHERE id_abonnement=? AND id_client_fk=?
        ");
        $stmt->execute([$id, $id_client]);
        $type = $stmt->fetchColumn();

        if ($type) {
            $date_debut = date('Y-m-d');
            $renew_end = match($type) {
                "mensuel" => "+1 month",
                "annuel"  => "+1 year",
                "hebdomadaire" => "+1 week",
                default => "+30 days"
            };

            $date_fin = date('Y-m-d', strtotime($renew_end));

            $stmt = $pdo->prepare("
                UPDATE abonnement 
                SET date_debut=?, date_fin=?, statut='actif'
                WHERE id_abonnement=? AND id_client_fk=?
            ");
            $stmt->execute([$date_debut, $date_fin, $id, $id_client]);

            $message = "Abonnement renouvelé.";
        } else {
            $message = "Abonnement introuvable.";
        }
    }

    // SUPPRIMER un abonnement
    if ($_POST['action'] === 'delete_abo') {
        $id = (int) $_POST['abo_id'];
        $stmt = $pdo->prepare("
            DELETE FROM abonnement 
            WHERE id_abonnement=? AND id_client_fk=?
        ");
        $stmt->execute([$id, $id_client]);
        $message = "Abonnement supprimé.";
    }
}

// Charger abonnements
$stmt = $pdo->prepare("
    SELECT id_abonnement, type, date_debut, date_fin, statut 
    FROM abonnement 
    WHERE id_client_fk=? 
    ORDER BY date_fin DESC
");
$stmt->execute([$id_client]);
$subscriptions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
<title>Gestion des abonnements</title>
<style>
body { font-family: Arial; padding: 20px; }
.container { max-width: 700px; margin: auto; }
button,input,select { padding: 8px; margin-top: 5px; cursor:pointer; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
td,th { border: 1px solid #ccc; padding: 6px; text-align: left;}
.msg { color:green; margin-top:10px; }
.actions form { display:inline; margin-right: 4px; }
</style>
</head>
<body>

<div class="container">

<h2>Gestion de vos abonnements</h2>
<p><?= $prenom . " " . $nom ?></p>

<?php if ($message): ?>
<p class="msg"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<hr>

<h3>Créer un nouvel abonnement</h3>

<form method="POST">
    <select name="type" required>
        <option value="mensuel">Mensuel</option>
        <option value="annuel">Annuel</option>
        <option value="hebdomadaire">Hebdomadaire</option>
    </select>
    <button name="action" value="new_subscription">Ajouter</button>
</form>

<hr>

<h3>Vos abonnements</h3>

<?php if (count($subscriptions) === 0): ?>
<p>Aucun abonnement enregistré.</p>

<?php else: ?>

<table>
<tr>
    <th>Type</th>
    <th>Début</th>
    <th>Fin</th>
    <th>Statut</th>
    <th>Actions</th>
</tr>

<?php foreach ($subscriptions as $abo): ?>
<tr>
    <td><?= htmlspecialchars($abo['type']) ?></td>
    <td><?= htmlspecialchars($abo['date_debut']) ?></td>
    <td><?= htmlspecialchars($abo['date_fin']) ?></td>
    <td><?= htmlspecialchars($abo['statut']) ?></td>
    <td class="actions">
        <?php if ($abo['statut'] === 'actif'): ?>
            <form method="POST">
                <input type="hidden" name="abo_id" value="<?= $abo['id_abonnement'] ?>">
                <button name="action" value="disable_abo">Désactiver</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="abo_id" value="<?= $abo['id_abonnement'] ?>">
                <button name="action" value="renew_abo">Renouveler</button>
            </form>
        <?php endif; ?>

        <!-- Bouton SUPPRIMER tjrs valable  -->
        <form method="POST" onsubmit="return confirm('Supprimer cet abonnement ?');">
            <input type="hidden" name="abo_id" value="<?= $abo['id_abonnement'] ?>">
            <button name="action" value="delete_abo">Supprimer</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>

<?php endif; ?>

<hr>
<a href="dashboard.php">⬅ Retour au tableau de bord</a>

</div>
</body>
</html>
