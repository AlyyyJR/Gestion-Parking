// ParkingService.java

import java.sql.Connection;
import java.sql.SQLException;

public class ParkingService {

    private final ParkingRepository repo;
    private final Connection conn;

    public ParkingService(Connection conn, ParkingRepository repo) {
        this.conn = conn;
        this.repo = repo;
    }

    /** Simple réponse protocolaire */
    public String hello() {
        return "HELLO_ACK";
    }

    /** Quand on reçoit ENTER */
    public String startEnter() {
        try {
            if (!repo.hasFreePlace()) {
                return "DENIED FULL";
            }
            return "PLATE?";
        } catch (Exception e) {
            return "ERROR DB";
        }
    }

    /** Validation de la plaque et entrée du véhicule */
    public String confirmEnter(String plate) {
        try {

            //  Format plaque
            if (!plate.matches("^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$")) {
                return "DENIED INVALID_FORMAT";
            }

            //  Le client existe ?
            Integer clientId = repo.findClientByPlate(plate);
            if (clientId == null) {
                return "DENIED UNKNOWN_PLATE";
            }

            //  Déjà à l'intérieur ?
            if (repo.isInside(plate)) {
                return "DENIED ALREADY_INSIDE";
            }

            //  Abonnement valide ?
            if (!repo.hasActiveSubscription(clientId)) {
                return "DENIED NO_SUBSCRIPTION";
            }

            //  Trouver une place
            Integer placeId = repo.getFreePlace();
            if (placeId == null) {
                return "DENIED FULL";
            }

            //  Mise à jour
            repo.occupyPlace(placeId);
            repo.logEnter(clientId, placeId, plate);

            return "OK_ENTER place=" + placeId;

        } catch (Exception e) {
            return "ERROR";
        }
    }

    /** Début procédure EXIT */
    public String startExit() {
        return "PLATE?";
    }

    /** Confirmation de sortie */
    public String confirmExit(String plate) {
        try {

            // format
            if (!plate.matches("^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$")) {
                return "DENIED INVALID_FORMAT";
            }

            // vérifier si dedans
            if (!repo.isInside(plate)) {
                return "DENIED NOT_INSIDE";
            }

            // récupérer client et place
            Integer clientId = repo.findClientByPlate(plate);
            Integer placeId = repo.getLastUsedPlace(plate);

            if (clientId == null || placeId == null) {
                return "ERROR";
            }

            // libérer place + log
            repo.freePlace(placeId);
            repo.logExit(clientId, placeId, plate);

            return "OK_EXIT freed=" + placeId;

        } catch (SQLException e) {
            return "ERROR SQL";
        }
    }

    /** État parking */
    public String status() {
        try {
            return "FREE " + repo.getFreePlacesCount();
        } catch (SQLException e) {
            return "ERROR DB";
        }
    }
}
