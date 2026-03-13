/**
 * ParkingRepository.java 
 * Toutes les données d'occupation sont déterminées via EVENEMENT_LOG.
 */

import java.sql.*;

public class ParkingRepository {

    private final Connection conn;

    public ParkingRepository(Connection conn) {
        this.conn = conn;
    }

    /** Retourne true si au moins une place est libre */
    public boolean hasFreePlace() throws SQLException {
        String sql = "SELECT COUNT(*) FROM place WHERE etat='libre'";
        try (PreparedStatement ps = conn.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            rs.next();
            return rs.getInt(1) > 0;
        }
    }

    /** Retourne l'id client à partir d'une plaque */
    public Integer findClientByPlate(String plate) throws SQLException {
        String sql = "SELECT id_client_fk FROM voiture WHERE plaque=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, plate);
            ResultSet rs = ps.executeQuery();
            return rs.next() ? rs.getInt(1) : null;
        }
    }

    /** Vérifie si un client a un abonnement actif */
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

    /** Retourne une place libre */
    public Integer getFreePlace() throws SQLException {
        String sql = "SELECT id_place FROM place WHERE etat='libre' LIMIT 1";
        try (PreparedStatement ps = conn.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            return rs.next() ? rs.getInt(1) : null;
        }
    }

    /** Met à jour état place : occupée */
    public void occupyPlace(int placeId) throws SQLException {
        String sql = "UPDATE place SET etat='occupée' WHERE id_place=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, placeId);
            ps.executeUpdate();
        }
    }

    /** Libère une place */
    public void freePlace(int placeId) throws SQLException {
        String sql = "UPDATE place SET etat='libre' WHERE id_place=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, placeId);
            ps.executeUpdate();
        }
    }

    /** Vérifie si une voiture est déjà dans le parking */
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

    /** Retourne la place actuelle du véhicule depuis le dernier événement entrée */
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

    /** Log entrée */
    public void logEnter(int clientId, int placeId, String plate) throws SQLException {
        String sql = """
            INSERT INTO evenement_log(type_event,date_event,heure_event,message,id_client_fk,id_place_fk)
            VALUES ('entrée', CURRENT_DATE, CURRENT_TIME, ?, ?, ?)
        """;
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, "Entrée véhicule " + plate);
            ps.setInt(2, clientId);
            ps.setInt(3, placeId);
            ps.executeUpdate();
        }
    }

    /** Log sortie + durée */
    public void logExit(int clientId, int placeId, String plate) throws SQLException {
        String sql = """
            INSERT INTO evenement_log(type_event,date_event,heure_event,message,duree,id_client_fk,id_place_fk)
            VALUES ('sortie', CURRENT_DATE, CURRENT_TIME, ?, 
                (SELECT CURRENT_TIMESTAMP - (SELECT MAX(date_event || ' ' || heure_event)::timestamp 
                FROM evenement_log WHERE id_client_fk = ? AND type_event='entrée')),
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

    /** Nombre de places libres */
    public int getFreePlacesCount() throws SQLException {
        String sql = "SELECT COUNT(*) FROM place WHERE etat='libre'";
        try (PreparedStatement ps = conn.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            rs.next();
            return rs.getInt(1);
        }
    }
}
