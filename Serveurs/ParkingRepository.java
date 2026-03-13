// ================================================================
// FICHIER : ParkingRepository.java
// Projet  : Système de Gestion de Parking
// Auteur  : Aly KONATE — L2 Informatique
// ================================================================
// Couche d'accès aux données (DAO) du système de parking
//
// Rôle de ParkingRepository :
//   - Encapsuler toutes les requêtes SQL vers la base PostgreSQL
//   - Fournir des méthodes claires et atomiques à ParkingService
//   - Gérer la vérification des places (libres / occupées)
//   - Rechercher les clients et vérifier leurs abonnements
//   - Enregistrer les événements d'entrée et de sortie
//
// Principe de conception :
//   Cette classe ne contient AUCUNE logique métier.
//   Elle ne fait que lire et écrire en base de données.
//   Toute décision (autoriser / refuser) appartient à ParkingService.
//
// Tables utilisées :
//   - place          : état des places (libre / occupée)
//   - voiture        : association plaque <-> client
//   - abonnement     : abonnements actifs des clients
//   - evenement_log  : historique complet des entrées/sorties
// ================================================================

import java.sql.*;

public class ParkingRepository {

    // ============================================================
    // Attribut principal
    // ============================================================
    /** Connexion JDBC partagée avec MainServer et ParkingService */
    private final Connection conn;

    // ============================================================
    // Constructeur
    // ============================================================
    /**
     * Initialise le repository avec la connexion à la base de données.
     *
     * @param conn Connexion JDBC PostgreSQL active
     */
    public ParkingRepository(Connection conn) {
        this.conn = conn;
    }

    // ============================================================
    // Gestion des places
    // ============================================================

    /**
     * Vérifie s'il reste au moins une place libre dans le parking.
     *
     * Requête : COUNT sur la table place avec etat = 'libre'
     *
     * @return true si au moins une place est disponible, false sinon
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public boolean hasFreePlace() throws SQLException {
        String sql = "SELECT COUNT(*) FROM place WHERE etat='libre'";
        try (PreparedStatement ps = conn.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            rs.next();
            return rs.getInt(1) > 0;
        }
    }

    /**
     * Retourne l'identifiant d'une place libre (la première trouvée).
     *
     * Utilisé juste avant d'attribuer une place à un véhicule entrant.
     *
     * @return l'id de la place libre, ou null si aucune place disponible
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public Integer getFreePlace() throws SQLException {
        String sql = "SELECT id_place FROM place WHERE etat='libre' LIMIT 1";
        try (PreparedStatement ps = conn.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            return rs.next() ? rs.getInt(1) : null;
        }
    }

    /**
     * Retourne le nombre total de places libres dans le parking.
     *
     * Utilisé par la commande STATUS pour informer le client.
     *
     * @return nombre de places actuellement libres
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public int getFreePlacesCount() throws SQLException {
        String sql = "SELECT COUNT(*) FROM place WHERE etat='libre'";
        try (PreparedStatement ps = conn.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            rs.next();
            return rs.getInt(1);
        }
    }

    /**
     * Marque une place comme occupée lors de l'entrée d'un véhicule.
     *
     * @param placeId identifiant de la place à occuper
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public void occupyPlace(int placeId) throws SQLException {
        String sql = "UPDATE place SET etat='occupée' WHERE id_place=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, placeId);
            ps.executeUpdate();
        }
    }

    /**
     * Libère une place lors de la sortie d'un véhicule.
     *
     * @param placeId identifiant de la place à libérer
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public void freePlace(int placeId) throws SQLException {
        String sql = "UPDATE place SET etat='libre' WHERE id_place=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, placeId);
            ps.executeUpdate();
        }
    }

    // ============================================================
    // Gestion des clients et véhicules
    // ============================================================

    /**
     * Recherche l'identifiant du client associé à une plaque d'immatriculation.
     *
     * Fait la jointure entre la table voiture et le client propriétaire.
     *
     * @param plate plaque au format AA-123-BB
     * @return l'id du client si la plaque est enregistrée, null sinon
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public Integer findClientByPlate(String plate) throws SQLException {
        String sql = "SELECT id_client_fk FROM voiture WHERE plaque=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, plate);
            ResultSet rs = ps.executeQuery();
            return rs.next() ? rs.getInt(1) : null;
        }
    }

    /**
     * Vérifie si un client possède un abonnement actif à la date du jour.
     *
     * Conditions vérifiées :
     *   - statut = 'actif'
     *   - date courante comprise entre date_debut et date_fin
     *
     * @param clientId identifiant du client à vérifier
     * @return true si un abonnement valide existe, false sinon
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public boolean hasActiveSubscription(int clientId) throws SQLException {
        String sql = """
            SELECT 1 FROM abonnement
            WHERE id_client_fk=?
            AND statut='actif'
            AND CURRENT_DATE BETWEEN date_debut AND date_fin
        """;
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, clientId);
            ResultSet rs = ps.executeQuery();
            return rs.next();
        }
    }

    // ============================================================
    // Vérification de présence dans le parking
    // ============================================================

    /**
     * Détermine si un véhicule est actuellement dans le parking.
     *
     * Principe :
     *   On regarde le dernier événement enregistré pour cette plaque.
     *   Si c'est une 'entrée', le véhicule est considéré comme présent.
     *   Si c'est une 'sortie' (ou aucun événement), il est absent.
     *
     * @param plate plaque du véhicule à vérifier
     * @return true si le véhicule est actuellement dans le parking
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public boolean isInside(String plate) throws SQLException {
        String sql = """
            SELECT type_event
            FROM evenement_log el
            JOIN voiture v ON v.id_client_fk = el.id_client_fk
            WHERE v.plaque = ?
            ORDER BY id_event DESC LIMIT 1
        """;
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, plate);
            ResultSet rs = ps.executeQuery();
            if (!rs.next()) return false;
            return rs.getString(1).equals("entrée");
        }
    }

    /**
     * Retrouve la dernière place utilisée par un véhicule lors de son entrée.
     *
     * Utilisé au moment de la sortie pour savoir quelle place libérer.
     *
     * @param plate plaque du véhicule concerné
     * @return l'id de la place associée au dernier événement 'entrée', null si introuvable
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public Integer getLastUsedPlace(String plate) throws SQLException {
        String sql = """
            SELECT id_place_fk FROM evenement_log el
            JOIN voiture v ON v.id_client_fk = el.id_client_fk
            WHERE v.plaque = ? AND type_event='entrée'
            ORDER BY id_event DESC LIMIT 1
        """;
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, plate);
            ResultSet rs = ps.executeQuery();
            return rs.next() ? rs.getInt(1) : null;
        }
    }

    // ============================================================
    // Enregistrement des événements (logs)
    // ============================================================

    /**
     * Enregistre un événement d'entrée dans la table evenement_log.
     *
     * Données enregistrées :
     *   - type_event  : 'entrée'
     *   - date_event  : date du jour (CURRENT_DATE)
     *   - heure_event : heure courante (CURRENT_TIME)
     *   - message     : "Entrée véhicule [plaque]"
     *   - id_client   : identifiant du client
     *   - id_place    : numéro de la place attribuée
     *
     * @param clientId identifiant du client
     * @param placeId  identifiant de la place attribuée
     * @param plate    plaque du véhicule entrant
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public void logEnter(int clientId, int placeId, String plate) throws SQLException {
        String sql = """
            INSERT INTO evenement_log(type_event, date_event, heure_event, message, id_client_fk, id_place_fk)
            VALUES ('entrée', CURRENT_DATE, CURRENT_TIME, ?, ?, ?)
        """;
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, "Entrée véhicule " + plate);
            ps.setInt(2, clientId);
            ps.setInt(3, placeId);
            ps.executeUpdate();
        }
    }

    /**
     * Enregistre un événement de sortie dans la table evenement_log.
     *
     * En plus des informations de base, calcule automatiquement la durée
     * de stationnement en faisant la différence entre l'heure courante
     * et le dernier événement 'entrée' du même client.
     *
     * @param clientId identifiant du client
     * @param placeId  identifiant de la place libérée
     * @param plate    plaque du véhicule sortant
     * @throws SQLException en cas d'erreur de communication avec la BDD
     */
    public void logExit(int clientId, int placeId, String plate) throws SQLException {
        String sql = """
            INSERT INTO evenement_log(type_event, date_event, heure_event, message, duree, id_client_fk, id_place_fk)
            VALUES ('sortie', CURRENT_DATE, CURRENT_TIME, ?,
                (SELECT CURRENT_TIMESTAMP - (
                    SELECT MAX(date_event || ' ' || heure_event)::timestamp
                    FROM evenement_log
                    WHERE id_client_fk = ? AND type_event='entrée'
                )),
                ?, ?
            )
        """;
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, "Sortie véhicule " + plate);
            ps.setInt(2, clientId);
            ps.setInt(3, clientId);
            ps.setInt(4, placeId);
            ps.executeUpdate();
        }
    }
}