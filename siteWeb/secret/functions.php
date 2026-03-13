<?php
// ================================================================
// FICHIER : functions.php
// Projet  : Système de Gestion de Parking
// Auteur  : Aly KONATE — L2 Informatique
// ================================================================
// Bibliothèque de fonctions métier du site web de gestion de parking
//
// Rôle de functions.php :
//   - Centraliser toutes les requêtes SQL utilisées par le site
//   - Fournir des fonctions réutilisables à dashboard.php
//   - Séparer la logique BDD de l'affichage HTML (principe MVC)
//   - Gérer les accès clients (voitures, historique, abonnements)
//   - Fournir les statistiques pour l'interface administrateur
//
// Principe de conception :
//   Toutes les fonctions utilisent la variable globale $pdo
//   injectée par bd_conf.php. Les requêtes sont préparées
//   (PreparedStatement) pour éviter les injections SQL.
//
// Fonctions disponibles :
//
//   [Utilisateur]
//   getUserByEmail($email)          → données d'un utilisateur par email
//
//   [Client]
//   getClientId($id_user)           → id_client à partir de l'id_user
//   getClientCars($id_user)         → liste des voitures d'un client
//   addCar($plaque, ...)            → ajoute une voiture en base
//   deleteCar($plaque, $id_client)  → supprime une voiture
//   getHistory($id_client)          → historique des passages
//
//   [Administrateur]
//   getParkingStats()               → occupation par parking
//   getActiveSubscriptions()        → abonnements actifs
//   getLongParking()                → stationnements > 8 heures
//   getFreeParkingSpots()           → liste des places libres
//   getExpiringSubscriptions()      → abonnements expirant sous 7 jours
// ================================================================

require_once 'bd_conf.php';


// ================================================================
// SECTION 1 — Gestion des utilisateurs
// ================================================================

/**
 * Récupère les informations d'un utilisateur à partir de son email.
 *
 * Utilisé au moment de la connexion pour charger le profil
 * et déterminer le rôle (client ou administrateur).
 *
 * @param string $email Adresse email de l'utilisateur
 * @return array|false  Tableau associatif (id_user, nom, prenom, role)
 *                      ou false si l'email n'existe pas
 */
function getUserByEmail(string $email) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id_user, nom, prenom, role
        FROM utilisateur
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    return $stmt->fetch();
}


// ================================================================
// SECTION 2 — Gestion des clients
// ================================================================

/**
 * Récupère l'identifiant client (id_client) à partir de l'id utilisateur.
 *
 * La table client est distincte de la table utilisateur.
 * Cette fonction fait le lien entre les deux via id_user_fk.
 *
 * @param int $id_user Identifiant de l'utilisateur connecté
 * @return mixed       id_client (int) ou false si introuvable
 */
function getClientId(int $id_user) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id_client
        FROM client
        WHERE id_user_fk = ?
        LIMIT 1
    ");
    $stmt->execute([$id_user]);
    return $stmt->fetchColumn();
}

/**
 * Récupère la liste des voitures enregistrées par un client.
 *
 * Fait la jointure entre voiture et client via id_user_fk
 * pour retrouver toutes les plaques du compte connecté.
 *
 * @param int   $id_user Identifiant de l'utilisateur connecté
 * @return array         Tableau de voitures (plaque, marque, modele)
 */
function getClientCars(int $id_user): array {
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

/**
 * Ajoute une nouvelle voiture au compte d'un client.
 *
 * Vérifications effectuées avant l'insertion :
 *   - La plaque n'est pas déjà enregistrée en base (unicité)
 *
 * Note : La validation du format de la plaque est effectuée
 *        en amont dans dashboard.php avant d'appeler cette fonction.
 *
 * @param string      $plaque    Plaque au format AA-123-BB (déjà validée)
 * @param string|null $marque    Marque du véhicule (optionnelle)
 * @param string|null $modele    Modèle du véhicule (optionnel)
 * @param int         $id_client Identifiant du client propriétaire
 * @param string|null &$error    Message d'erreur passé par référence si échec
 * @return bool                  true si l'ajout réussit, false sinon
 */
function addCar(string $plaque, ?string $marque, ?string $modele, int $id_client, ?string &$error = null): bool {
    global $pdo;

    // Vérifier si la plaque est déjà enregistrée (évite une erreur SQL)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM voiture WHERE plaque = ?");
    $stmt->execute([$plaque]);

    if ($stmt->fetchColumn() > 0) {
        $error = "Cette plaque est déjà enregistrée.";
        return false;
    }

    // Insertion de la voiture (marque et modèle peuvent être null)
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

/**
 * Supprime une voiture du compte d'un client.
 *
 * La double condition (id_client_fk + plaque) empêche
 * un client de supprimer la voiture d'un autre compte.
 *
 * @param string $plaque    Plaque du véhicule à supprimer
 * @param int    $id_client Identifiant du client propriétaire
 * @return bool             true si la suppression réussit
 */
function deleteCar(string $plaque, int $id_client): bool {
    global $pdo;
    $stmt = $pdo->prepare("
        DELETE FROM voiture
        WHERE id_client_fk = ? AND plaque = ?
    ");
    return $stmt->execute([$id_client, $plaque]);
}

/**
 * Récupère l'historique complet des passages d'un client.
 *
 * Retourne tous les événements (entrées, sorties, paiements)
 * triés du plus récent au plus ancien.
 *
 * @param int   $id_client Identifiant du client
 * @return array           Tableau d'événements (type_event, date_event, heure_event, message)
 */
function getHistory(int $id_client): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT type_event, date_event, heure_event, message
        FROM evenement_log
        WHERE id_client_fk = ?
        ORDER BY date_event DESC, heure_event DESC
    ");
    $stmt->execute([$id_client]);
    return $stmt->fetchAll();
}


// ================================================================
// SECTION 3 — Statistiques administrateur
// ================================================================

/**
 * Retourne le nombre de places occupées par parking.
 *
 * Utilisé par l'administrateur pour visualiser l'occupation
 * globale et générer le graphique Chart.js dans dashboard.php.
 *
 * @return array Tableau (parking, places_occupees) groupé par parking
 */
function getParkingStats(): array {
    global $pdo;
    return $pdo->query("
        SELECT p.nom AS parking,
               COUNT(pl.id_place) AS places_occupees
        FROM place pl
        JOIN parking p ON pl.id_parking_fk = p.id_parking
        WHERE pl.etat = 'occupée'
        GROUP BY p.nom
    ")->fetchAll();
}

/**
 * Retourne la liste de tous les abonnements actuellement actifs.
 *
 * Affichée dans le tableau de bord administrateur pour
 * superviser les abonnés actifs du parking.
 *
 * @return array Tableau (nom, prenom, type, date_fin) des abonnés actifs
 */
function getActiveSubscriptions(): array {
    global $pdo;
    return $pdo->query("
        SELECT u.nom, u.prenom, a.type, a.date_fin
        FROM utilisateur u
        JOIN client c ON u.id_user = c.id_user_fk
        JOIN abonnement a ON c.id_client = a.id_client_fk
        WHERE a.statut = 'actif'
    ")->fetchAll();
}

/**
 * Retourne les véhicules ayant stationné plus de 8 heures.
 *
 * Permet à l'administrateur d'identifier les stationnements
 * anormalement longs à partir des événements de sortie.
 *
 * @return array Tableau (plaque, duree_stationnement) des longs stationnements
 */
function getLongParking(): array {
    global $pdo;
    return $pdo->query("
        SELECT v.plaque, e.duree AS duree_stationnement
        FROM voiture v
        JOIN evenement_log e ON v.id_client_fk = e.id_client_fk
        WHERE e.type_event = 'sortie'
        AND e.duree > INTERVAL '8 hours'
    ")->fetchAll();
}

/**
 * Retourne toutes les places actuellement libres.
 *
 * Permet à l'administrateur de consulter les places
 * disponibles dans chaque parking en temps réel.
 *
 * @return array Tableau (numero, id_parking_fk) des places libres
 */
function getFreeParkingSpots(): array {
    global $pdo;
    return $pdo->query("
        SELECT numero, id_parking_fk
        FROM place
        WHERE etat = 'libre'
    ")->fetchAll();
}

/**
 * Retourne les abonnements expirant dans les 7 prochains jours.
 *
 * Permet à l'administrateur d'anticiper les renouvellements
 * et de contacter les clients concernés à temps.
 *
 * @return array Tableau (nom, prenom, date_fin) des abonnements proches de l'expiration
 */
function getExpiringSubscriptions(): array {
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