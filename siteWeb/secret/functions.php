<?php
require_once 'bd_conf.php';

/* ---- UTILISATEUR ----- */

function getUserByEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id_user, nom, prenom, role FROM utilisateur WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

/* ----- CLIENT ---- */

function getClientId($id_user) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id_client FROM client WHERE id_user_fk=? LIMIT 1");
    $stmt->execute([$id_user]);
    return $stmt->fetchColumn();
}

function getClientCars($id_user) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT v.plaque, v.marque, v.modele
        FROM voiture v
        JOIN client c ON v.id_client_fk = c.id_client
        WHERE c.id_user_fk = ?
    ");
    $stmt->execute([$id_user]);
    return $stmt->fetchAll();
}

function addCar(string $plaque, ?string $marque, ?string $modele, int $id_client, ?string &$error = null): bool {
    global $pdo;

    // Vérifier si la plaque existe déjà (pour eviter erreur sql)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM voiture WHERE plaque = ?");
    $stmt->execute([$plaque]);

    if ($stmt->fetchColumn() > 0) {
        $error = "Cette plaque est déjà enregistrée.";
        return false;
    }

    // Insérer la voiture (marque et modèle peuvent etre null)
    $stmt = $pdo->prepare("
        INSERT INTO voiture (plaque, marque, modele, id_client_fk)
        VALUES (?, ?, ?, ?)
    ");

    if ($stmt->execute([$plaque, $marque ?: null, $modele ?: null, $id_client])) {
        return true;
    }

    $error = "Erreur lors de l'enregistrement.";
    return false;
}



function deleteCar($plaque, $id_client) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM voiture WHERE id_client_fk=? AND plaque=?");
    return $stmt->execute([$id_client, $plaque]);
}

function getHistory($id_client) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT type_event, date_event, heure_event, message 
        FROM evenement_log 
        WHERE id_client_fk=? 
        ORDER BY date_event DESC, heure_event DESC");
    $stmt->execute([$id_client]);
    return $stmt->fetchAll();
}

/* ------ ADMIN ---- */

function getParkingStats() {
    global $pdo;
    return $pdo->query("
        SELECT p.nom AS parking, COUNT(pl.id_place) AS places_occupees
        FROM place pl
        JOIN parking p ON pl.id_parking_fk = p.id_parking
        WHERE pl.etat = 'occupée'
        GROUP BY p.nom
    ")->fetchAll();
}

function getActiveSubscriptions() {
    global $pdo;
    return $pdo->query("
        SELECT u.nom, u.prenom, a.type, a.date_fin
        FROM utilisateur u
        JOIN client c ON u.id_user = c.id_user_fk
        JOIN abonnement a ON c.id_client = a.id_client_fk
        WHERE a.statut = 'actif'
    ")->fetchAll();
}

function getLongParking() {
    global $pdo;
    return $pdo->query("
        SELECT v.plaque, e.duree AS duree_stationnement
        FROM voiture v
        JOIN evenement_log e ON v.id_client_fk = e.id_client_fk
        WHERE e.type_event = 'sortie'
        AND e.duree > INTERVAL '8 hours'
    ")->fetchAll();
}

function getFreeParkingSpots() {
    global $pdo;
    return $pdo->query("
        SELECT numero, id_parking_fk
        FROM place
        WHERE etat = 'libre'
    ")->fetchAll();
}

function getExpiringSubscriptions() {
    global $pdo;
    return $pdo->query("
        SELECT u.nom, u.prenom, a.date_fin
        FROM utilisateur u
        JOIN client c ON u.id_user = c.id_user_fk
        JOIN abonnement a ON c.id_client = a.id_client_fk
        WHERE a.date_fin BETWEEN CURRENT_DATE
                             AND (CURRENT_DATE + INTERVAL '7 days')
    ")->fetchAll();
}
