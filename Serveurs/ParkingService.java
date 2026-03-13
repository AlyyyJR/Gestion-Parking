// ================================================================
// FICHIER : ParkingService.java
// Projet  : Système de Gestion de Parking
// Auteur  : Aly KONATE — L2 Informatique
// ================================================================
// Couche métier (Service) du système de gestion de parking
//
// Rôle de ParkingService :
//   - Centraliser toute la logique métier du parking
//   - Valider les données reçues (format plaque, état du client)
//   - Orchestrer les appels à ParkingRepository (BDD)
//   - Retourner des réponses protocolaires claires au serveur
//
// Principe de conception :
//   ParkingService est la couche intermédiaire entre le réseau
//   (MainServer) et la base de données (ParkingRepository).
//   Elle ne lit ni n'écrit jamais directement en BDD — tout
//   passe par le repository.
//
// Réponses possibles par commande :
//
//   hello()        → HELLO_ACK
//
//   startEnter()   → PLATE?            (parking non plein)
//                  → DENIED FULL       (plus de place)
//
//   confirmEnter() → OK_ENTER place=N  (entrée validée)
//                  → DENIED INVALID_FORMAT   (plaque malformée)
//                  → DENIED UNKNOWN_PLATE    (plaque inconnue)
//                  → DENIED ALREADY_INSIDE   (véhicule déjà présent)
//                  → DENIED NO_SUBSCRIPTION  (abonnement invalide)
//                  → DENIED FULL             (plus de place)
//                  → ERROR                   (erreur BDD)
//
//   startExit()    → PLATE?
//
//   confirmExit()  → OK_EXIT freed=N   (sortie validée)
//                  → DENIED INVALID_FORMAT   (plaque malformée)
//                  → DENIED NOT_INSIDE       (véhicule absent)
//                  → ERROR SQL               (erreur BDD)
//
//   status()       → FREE N            (N = nombre de places libres)
//                  → ERROR DB          (erreur BDD)
// ================================================================

import java.sql.Connection;
import java.sql.SQLException;

public class ParkingService {

    // ============================================================
    // Attributs principaux
    // ============================================================
    /** Repository pour tous les accès à la base de données */
    private final ParkingRepository repo;

    /** Connexion JDBC (conservée pour les éventuelles transactions futures) */
    private final Connection conn;

    /** Expression régulière du format de plaque française : AA-123-BB */
    private static final String PLATE_REGEX = "^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$";

    // ============================================================
    // Constructeur
    // ============================================================
    /**
     * Initialise le service avec la connexion BDD et le repository.
     *
     * @param conn connexion JDBC PostgreSQL active
     * @param repo instance du repository pour les accès BDD
     */
    public ParkingService(Connection conn, ParkingRepository repo) {
        this.conn = conn;
        this.repo = repo;
    }

    // ============================================================
    // Commande HELLO
    // ============================================================
    /**
     * Répond à la commande HELLO du client.
     *
     * Sert à vérifier que la connexion est bien établie
     * et que le serveur est opérationnel.
     *
     * @return "HELLO_ACK"
     */
    public String hello() {
        return "HELLO_ACK";
    }

    // ============================================================
    // Commande ENTER — Phase 1 : vérification de disponibilité
    // ============================================================
    /**
     * Démarre la procédure d'entrée d'un véhicule.
     *
     * Vérifie uniquement si une place est disponible.
     * Si oui, demande la plaque au client (PLATE?).
     * La validation complète est effectuée dans confirmEnter().
     *
     * @return "PLATE?"      si le parking a de la place
     *         "DENIED FULL" si le parking est complet
     */
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

    // ============================================================
    // Commande ENTER — Phase 2 : validation et enregistrement
    // ============================================================
    /**
     * Valide l'entrée d'un véhicule après réception de sa plaque.
     *
     * Chaîne de vérifications (dans l'ordre) :
     *   1. Format de la plaque (regex AA-123-BB)
     *   2. Plaque connue dans la base (table voiture)
     *   3. Véhicule pas déjà à l'intérieur (evenement_log)
     *   4. Abonnement actif du client (table abonnement)
     *   5. Place disponible (table place)
     *
     * Si toutes les vérifications passent :
     *   - La place est marquée comme occupée
     *   - Un événement 'entrée' est enregistré dans le log
     *
     * @param plate plaque d'immatriculation envoyée par le client
     * @return "OK_ENTER place=N"       si l'entrée est acceptée
     *         "DENIED INVALID_FORMAT"  si la plaque est mal formée
     *         "DENIED UNKNOWN_PLATE"   si la plaque est inconnue
     *         "DENIED ALREADY_INSIDE"  si le véhicule est déjà dans le parking
     *         "DENIED NO_SUBSCRIPTION" si l'abonnement est invalide ou expiré
     *         "DENIED FULL"            si plus aucune place disponible
     *         "ERROR"                  en cas d'erreur BDD imprévue
     */
    public String confirmEnter(String plate) {
        try {
            // --- 1) Vérification du format de la plaque ---
            if (!plate.matches(PLATE_REGEX)) {
                return "DENIED INVALID_FORMAT";
            }

            // --- 2) La plaque est-elle enregistrée dans la base ? ---
            Integer clientId = repo.findClientByPlate(plate);
            if (clientId == null) {
                return "DENIED UNKNOWN_PLATE";
            }

            // --- 3) Le véhicule est-il déjà à l'intérieur ? ---
            if (repo.isInside(plate)) {
                return "DENIED ALREADY_INSIDE";
            }

            // --- 4) Le client a-t-il un abonnement actif ? ---
            if (!repo.hasActiveSubscription(clientId)) {
                return "DENIED NO_SUBSCRIPTION";
            }

            // --- 5) Y a-t-il encore une place disponible ? ---
            Integer placeId = repo.getFreePlace();
            if (placeId == null) {
                return "DENIED FULL";
            }

            // --- Tout est valide : enregistrement de l'entrée ---
            repo.occupyPlace(placeId);
            repo.logEnter(clientId, placeId, plate);

            return "OK_ENTER place=" + placeId;

        } catch (Exception e) {
            return "ERROR";
        }
    }

    // ============================================================
    // Commande EXIT — Phase 1 : demande de plaque
    // ============================================================
    /**
     * Démarre la procédure de sortie d'un véhicule.
     *
     * Demande simplement la plaque au client.
     * La validation complète est effectuée dans confirmExit().
     *
     * @return "PLATE?" systématiquement
     */
    public String startExit() {
        return "PLATE?";
    }

    // ============================================================
    // Commande EXIT — Phase 2 : validation et libération
    // ============================================================
    /**
     * Valide la sortie d'un véhicule après réception de sa plaque.
     *
     * Chaîne de vérifications (dans l'ordre) :
     *   1. Format de la plaque (regex AA-123-BB)
     *   2. Véhicule effectivement présent dans le parking
     *
     * Si toutes les vérifications passent :
     *   - La place est libérée dans la table place
     *   - Un événement 'sortie' est enregistré avec la durée calculée
     *
     * @param plate plaque d'immatriculation envoyée par le client
     * @return "OK_EXIT freed=N"        si la sortie est acceptée (N = place libérée)
     *         "DENIED INVALID_FORMAT"  si la plaque est mal formée
     *         "DENIED NOT_INSIDE"      si le véhicule n'est pas dans le parking
     *         "ERROR SQL"              en cas d'erreur BDD
     *         "ERROR"                  en cas d'erreur inattendue
     */
    public String confirmExit(String plate) {
        try {
            // --- 1) Vérification du format de la plaque ---
            if (!plate.matches(PLATE_REGEX)) {
                return "DENIED INVALID_FORMAT";
            }

            // --- 2) Le véhicule est-il bien dans le parking ? ---
            if (!repo.isInside(plate)) {
                return "DENIED NOT_INSIDE";
            }

            // --- Récupération du client et de la place associée ---
            Integer clientId = repo.findClientByPlate(plate);
            Integer placeId  = repo.getLastUsedPlace(plate);

            // Sécurité : ne devrait pas arriver si isInside() retourne true
            if (clientId == null || placeId == null) {
                return "ERROR";
            }

            // --- Libération de la place + enregistrement de la sortie ---
            repo.freePlace(placeId);
            repo.logExit(clientId, placeId, plate);

            return "OK_EXIT freed=" + placeId;

        } catch (SQLException e) {
            return "ERROR SQL";
        }
    }

    // ============================================================
    // Commande STATUS
    // ============================================================
    /**
     * Retourne le nombre de places libres dans le parking.
     *
     * Répond à la commande STATUS envoyée par le client.
     * Utile pour consulter la disponibilité sans tenter d'entrer.
     *
     * @return "FREE N"    où N est le nombre de places libres
     *         "ERROR DB"  en cas d'erreur BDD
     */
    public String status() {
        try {
            return "FREE " + repo.getFreePlacesCount();
        } catch (SQLException e) {
            return "ERROR DB";
        }
    }
}