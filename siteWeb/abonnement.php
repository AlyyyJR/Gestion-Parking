<?php
// ================================================================
// FICHIER : abonnement.php
// Projet  : Système de Gestion de Parking
// Auteur  : Aly KONATE — L2 Informatique
// ================================================================
// Page de gestion des abonnements du client connecté
//
// Rôle de abonnement.php :
//   - Permettre au client de créer un nouvel abonnement
//   - Afficher la liste de tous ses abonnements (actifs et expirés)
//   - Désactiver un abonnement actif
//   - Renouveler un abonnement expiré
//   - Supprimer définitivement un abonnement
//
// Sécurités implémentées :
//   - Accès réservé aux clients connectés (vérification session + rôle)
//   - Toutes les requêtes SQL sont préparées (protection injection SQL)
//   - Double condition (id_abonnement + id_client_fk) sur les UPDATE/DELETE
//     → un client ne peut jamais modifier l'abonnement d'un autre
//   - htmlspecialchars() sur tous les affichages (protection XSS)
//   - Confirmation JavaScript avant suppression
//
// Types d'abonnements disponibles et leurs durées :
//   - mensuel      → +1 mois
//   - annuel       → +1 an
//   - hebdomadaire → +1 semaine
//   - (autre)      → +30 jours par défaut
//
// Actions POST disponibles :
//   new_subscription → crée un nouvel abonnement à partir d'aujourd'hui
//   disable_abo      → passe le statut à 'expiré'
//   renew_abo        → recrée une période à partir d'aujourd'hui
//   delete_abo       → suppression définitive de la ligne en base
// ================================================================

session_start();
require_once 'secret/bd_conf.php';

// ================================================================
// Sécurité : accès réservé aux clients connectés
// ================================================================
// Redirige vers index.php si l'utilisateur n'est pas connecté
// ou s'il n'a pas le rôle 'client' (ex : administrateur)
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'client') {
    header("Location: index.php");
    exit;
}

// ================================================================
// Chargement du profil de l'utilisateur connecté
// ================================================================
$email   = $_SESSION['email'];
$message = "";

// Récupération des infos de l'utilisateur (nom, prénom, id)
$stmt = $pdo->prepare("SELECT id_user, nom, prenom FROM utilisateur WHERE email = ?");
$stmt->execute([$email]);
$user    = $stmt->fetch();
$id_user = $user['id_user'];
$nom     = htmlspecialchars($user['nom']);
$prenom  = htmlspecialchars($user['prenom']);

// Récupération de l'id_client associé à cet utilisateur
$stmt = $pdo->prepare("SELECT id_client FROM client WHERE id_user_fk = ? LIMIT 1");
$stmt->execute([$id_user]);
$id_client = $stmt->fetchColumn();

// ================================================================
// Traitement des actions POST
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------------------------------------------------
    // Action : Créer un nouvel abonnement
    // Calcule automatiquement la date de fin selon le type choisi
    // ------------------------------------------------------------
    if ($_POST['action'] === 'new_subscription') {

        $type       = $_POST['type'];
        $date_debut = date('Y-m-d');

        // Durée de l'abonnement selon le type sélectionné
        $duree = match($type) {
            "mensuel"      => "+1 month",
            "annuel"       => "+1 year",
            "hebdomadaire" => "+1 week",
            default        => "+30 days"   // durée par défaut si type inconnu
        };

        $date_fin = date('Y-m-d', strtotime($duree));

        $stmt = $pdo->prepare("
            INSERT INTO abonnement(type, date_debut, date_fin, statut, id_client_fk)
            VALUES (?, ?, ?, 'actif', ?)
        ");
        $stmt->execute([$type, $date_debut, $date_fin, $id_client]);
        $message = "Nouvel abonnement ajouté.";
    }

    // ------------------------------------------------------------
    // Action : Désactiver un abonnement actif
    // Passe le statut à 'expiré' sans supprimer la ligne
    // Double condition : id_abonnement + id_client_fk (sécurité)
    // ------------------------------------------------------------
    if ($_POST['action'] === 'disable_abo') {
        $id = (int) $_POST['abo_id'];

        $stmt = $pdo->prepare("
            UPDATE abonnement
            SET statut = 'expiré'
            WHERE id_abonnement = ? AND id_client_fk = ?
        ");
        $stmt->execute([$id, $id_client]);
        $message = "Abonnement désactivé.";
    }

    // ------------------------------------------------------------
    // Action : Renouveler un abonnement expiré
    // Repart à partir d'aujourd'hui avec la même durée que le type
    // Double condition : id_abonnement + id_client_fk (sécurité)
    // ------------------------------------------------------------
    if ($_POST['action'] === 'renew_abo') {
        $id = (int) $_POST['abo_id'];

        // Récupération du type pour calculer la nouvelle date de fin
        $stmt = $pdo->prepare("
            SELECT type FROM abonnement
            WHERE id_abonnement = ? AND id_client_fk = ?
        ");
        $stmt->execute([$id, $id_client]);
        $type = $stmt->fetchColumn();

        if ($type) {
            $date_debut = date('Y-m-d');
            $renew_end  = match($type) {
                "mensuel"      => "+1 month",
                "annuel"       => "+1 year",
                "hebdomadaire" => "+1 week",
                default        => "+30 days"
            };

            $date_fin = date('Y-m-d', strtotime($renew_end));

            $stmt = $pdo->prepare("
                UPDATE abonnement
                SET date_debut = ?, date_fin = ?, statut = 'actif'
                WHERE id_abonnement = ? AND id_client_fk = ?
            ");
            $stmt->execute([$date_debut, $date_fin, $id, $id_client]);
            $message = "Abonnement renouvelé.";

        } else {
            $message = "Abonnement introuvable.";
        }
    }

    // ------------------------------------------------------------
    // Action : Supprimer définitivement un abonnement
    // Suppression permanente de la ligne en base de données
    // Double condition : id_abonnement + id_client_fk (sécurité)
    // ------------------------------------------------------------
    if ($_POST['action'] === 'delete_abo') {
        $id = (int) $_POST['abo_id'];

        $stmt = $pdo->prepare("
            DELETE FROM abonnement
            WHERE id_abonnement = ? AND id_client_fk = ?
        ");
        $stmt->execute([$id, $id_client]);
        $message = "Abonnement supprimé.";
    }
}

// ================================================================
// Chargement de la liste des abonnements du client
// ================================================================
// Triés du plus récent au plus ancien (date_fin DESC)
$stmt = $pdo->prepare("
    SELECT id_abonnement, type, date_debut, date_fin, statut
    FROM abonnement
    WHERE id_client_fk = ?
    ORDER BY date_fin DESC
");
$stmt->execute([$id_client]);
$subscriptions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des abonnements — Parking</title>
    <style>
        body            { font-family: Arial, sans-serif; padding: 20px; }
        .container      { max-width: 700px; margin: auto; }
        button, input, select { padding: 8px; margin-top: 5px; cursor: pointer; }
        table           { width: 100%; border-collapse: collapse; margin-top: 15px; }
        td, th          { border: 1px solid #ccc; padding: 6px; text-align: left; }
        .msg            { color: green; margin-top: 10px; }
        .actions form   { display: inline; margin-right: 4px; }
    </style>
</head>

<body>
<div class="container">

    <h2>Gestion de vos abonnements</h2>
    <p><?= $prenom . " " . $nom ?></p>

    <!-- Message de retour après une action -->
    <?php if ($message): ?>
        <p class="msg"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <hr>

    <!-- ============================================================ -->
    <!-- Formulaire de création d'un nouvel abonnement                -->
    <!-- ============================================================ -->
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

    <!-- ============================================================ -->
    <!-- Tableau des abonnements existants                             -->
    <!-- ============================================================ -->
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
                        <!-- Désactiver un abonnement actif -->
                        <form method="POST">
                            <input type="hidden" name="abo_id" value="<?= $abo['id_abonnement'] ?>">
                            <button name="action" value="disable_abo">Désactiver</button>
                        </form>
                    <?php else: ?>
                        <!-- Renouveler un abonnement expiré -->
                        <form method="POST">
                            <input type="hidden" name="abo_id" value="<?= $abo['id_abonnement'] ?>">
                            <button name="action" value="renew_abo">Renouveler</button>
                        </form>
                    <?php endif; ?>

                    <!-- Suppression définitive — confirmation JS obligatoire -->
                    <form method="POST" onsubmit="return confirm('Supprimer cet abonnement définitivement ?');">
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