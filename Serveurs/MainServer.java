// ================================================================
// FICHIER : MainServer.java
// Projet  : Système de Gestion de Parking
// Auteur  : Aly KONATE — L2 Informatique
// ================================================================
// Serveur principal TCP du système de gestion de parking
//
// Rôle de MainServer :
//   - Charger le driver PostgreSQL et établir la connexion à la BDD
//   - Créer et ouvrir le ServerSocket sur le port configuré
//   - Écouter en boucle les connexions entrantes des clients
//   - Déléguer chaque client à un thread dédié (ClientHandler)
//   - Configurer le logger pour tracer les événements serveur
//   - Gérer les arguments en ligne de commande (host, port)
//
// Architecture :
//   MainServer instancie ParkingRepository (accès BDD)
//   et ParkingService (logique métier), puis les passe
//   à chaque thread client via handleClient().
//
// Protocole supporté (TCP texte, une commande par ligne) :
//   HELLO   → HELLO_ACK
//   ENTER   → PLATE? puis OK_ENTER / DENIED ...
//   EXIT    → PLATE? puis OK_EXIT  / DENIED ...
//   STATUS  → FREE <n>
//   BYE     → BYE_ACK + fermeture propre
// ================================================================

import java.io.*;
import java.net.*;
import java.sql.Connection;
import java.sql.DriverManager;
import java.time.LocalTime;
import java.util.logging.FileHandler;
import java.util.logging.Logger;
import java.util.logging.SimpleFormatter;

public class MainServer {

    // ============================================================
    // Constantes de configuration réseau
    // ============================================================
    /** Port d'écoute par défaut du serveur */
    private static final int PORT = 8009;

    /** Timeout d'inactivité d'un client (en ms) — 30 secondes */
    private static final int TIMEOUT_MS = 30000;

    /** Adresse d'écoute par défaut : toutes les interfaces réseau */
    private static final String HOST_DEFAULT = "0.0.0.0";

    /** Taille maximale autorisée d'un message reçu (en caractères) */
    private static final int MAX_MESSAGE_LENGTH = 50;

    // ============================================================
    // Logger global du serveur
    // ============================================================
    /** Logger Java pour tracer les événements dans server.log */
    private static final Logger logger = Logger.getLogger("ParkingServerLog");

    // ============================================================
    // Point d'entrée principal
    // ============================================================
    /**
     * Lance le serveur de gestion de parking.
     *
     * Étapes d'initialisation :
     *   1. Lecture des arguments (host, port optionnels)
     *   2. Chargement du driver PostgreSQL
     *   3. Connexion à la base de données
     *   4. Création du ServerSocket et mise en écoute
     *   5. Configuration du logger fichier
     *   6. Boucle d'acceptation des clients (un thread par client)
     *
     * @param args args[0] = host (optionnel), args[1] = port (optionnel)
     */
    public static void main(String[] args) {
        System.out.println("Serveur Parking démarré...");

        // --- 1) Lecture des arguments ---
        int port    = PORT;
        String host = HOST_DEFAULT;

        if (args.length >= 1) host = args[0];
        if (args.length >= 2) {
            try {
                port = Integer.parseInt(args[1]);
            } catch (NumberFormatException e) {
                System.out.println("Port invalide, utilisation du port par défaut : " + port);
            }
        }

        // --- 2) Chargement du driver PostgreSQL ---
        try {
            Class.forName("org.postgresql.Driver");
        } catch (ClassNotFoundException e) {
            System.out.println("Driver PostgreSQL non trouvé !");
            e.printStackTrace();
            return;
        }

        // --- 3) Connexion à la base de données ---
        Connection conn;
        try {
            conn = DriverManager.getConnection(
                "jdbc:postgresql://localhost:5432/parking_bd",
                "alyyyjr",
                "alyyyjr123"
            );
            System.out.println("Connexion PostgreSQL OK");
        } catch (Exception e) {
            System.out.println("Erreur de connexion à la base : " + e.getMessage());
            e.printStackTrace();
            return;
        }

        // --- Instanciation des couches métier ---
        ParkingRepository repo    = new ParkingRepository(conn);
        ParkingService    service = new ParkingService(conn, repo);

        // --- 4) Création et ouverture du ServerSocket ---
        ServerSocket server;
        try {
            server = new ServerSocket();
            server.bind(new InetSocketAddress(host, port));
            System.out.println("[" + LocalTime.now() + "] En attente de clients sur "
                    + host + ":" + port + "...");
        } catch (BindException e) {
            System.out.println("Erreur : le port " + port + " est déjà utilisé !");
            return;
        } catch (IOException e) {
            System.out.println("Erreur réseau lors de la création du serveur : " + e.getMessage());
            return;
        }

        // --- 5) Configuration du logger fichier ---
        try {
            FileHandler fh = new FileHandler("server.log", true); // true = append
            fh.setFormatter(new SimpleFormatter());
            logger.addHandler(fh);
            logger.info("===== Serveur démarré =====");
        } catch (Exception e) {
            e.printStackTrace();
        }

        // --- 6) Boucle principale d'acceptation des clients ---
        // Chaque client est traité dans son propre thread
        // pour permettre les connexions simultanées
        while (true) {
            try {
                Socket clientSocket = server.accept();
                logger.info("Connexion d'un client : " + clientSocket.getRemoteSocketAddress());

                // Activation du timeout d'inactivité
                clientSocket.setSoTimeout(TIMEOUT_MS);

                System.out.println("[" + LocalTime.now() + "] Client connecté : "
                        + clientSocket.getInetAddress() + ":" + clientSocket.getPort());

                // Lancement d'un thread dédié pour ce client
                new Thread(() -> handleClient(clientSocket, service)).start();

            } catch (IOException e) {
                System.out.println("Erreur lors de l'acceptation d'un client : " + e.getMessage());
            }
        }
    }

    // ============================================================
    // Gestion complète d'un client connecté
    // ============================================================
    /**
     * Traite toutes les commandes d'un client dans un thread dédié.
     *
     * Protocole à états :
     *   - État NONE       : attend une commande principale (HELLO, ENTER, EXIT, STATUS, BYE)
     *   - État WAIT_ENTER : attend la plaque après un ENTER
     *   - État WAIT_EXIT  : attend la plaque après un EXIT
     *
     * Sécurités implémentées :
     *   - Timeout automatique si le client est inactif (TIMEOUT_MS)
     *   - Rejet des messages dépassant MAX_MESSAGE_LENGTH caractères
     *   - Fermeture propre du socket dans le bloc finally
     *
     * @param client  Socket TCP du client connecté
     * @param service Instance de ParkingService pour la logique métier
     */
    private static void handleClient(Socket client, ParkingService service) {
        try (
            BufferedReader in  = new BufferedReader(new InputStreamReader(client.getInputStream()));
            BufferedWriter out = new BufferedWriter(new OutputStreamWriter(client.getOutputStream()))
        ) {
            // Signal de disponibilité envoyé au client
            out.write("READY\n");
            out.flush();
            logger.info("Client READY : " + client.getRemoteSocketAddress());

            // État courant du protocole
            String expected = "NONE";
            String line;

            // Boucle de lecture des commandes du client
            while ((line = in.readLine()) != null) {
                line = line.trim();

                // --- Sécurité : rejet des messages trop longs ---
                if (line.length() > MAX_MESSAGE_LENGTH) {
                    logger.warning("Message trop long (" + line.length() + " chars) de "
                            + client.getRemoteSocketAddress());
                    out.write("ERROR Message too long\n");
                    out.flush();
                    expected = "NONE";
                    continue;
                }

                logger.info("Commande reçue : " + line);
                String response = "ERROR Unknown";

                // --- Traitement selon l'état du protocole ---
                if (expected.equals("WAIT_ENTER")) {
                    // On attend la plaque pour valider une entrée
                    response = service.confirmEnter(line);
                    expected = "NONE";

                } else if (expected.equals("WAIT_EXIT")) {
                    // On attend la plaque pour valider une sortie
                    response = service.confirmExit(line);
                    expected = "NONE";

                } else {
                    // Traitement des commandes principales
                    switch (line.toUpperCase()) {

                        case "HELLO" ->
                            response = service.hello();

                        case "ENTER" -> {
                            response = service.startEnter();
                            // Si le parking n'est pas plein, on passe en attente de plaque
                            if (response.equals("PLATE?")) expected = "WAIT_ENTER";
                        }

                        case "EXIT" -> {
                            response = service.startExit();
                            // Toujours en attente de la plaque pour sortie
                            expected = "WAIT_EXIT";
                        }

                        case "STATUS" ->
                            response = service.status();

                        case "BYE" -> {
                            // Fermeture propre de la connexion
                            response = "BYE_ACK";
                            out.write(response + "\n");
                            out.flush();
                            logger.info("Client déconnecté : " + client.getRemoteSocketAddress());
                            return;
                        }

                        default -> response = "ERROR Unknown command";
                    }
                }

                // Envoi de la réponse au client
                out.write(response + "\n");
                out.flush();
                logger.info("Réponse envoyée → " + response);
            }

        } catch (SocketTimeoutException e) {
            // Le client n'a pas envoyé de message dans le délai imparti
            logger.warning("Client inactif -> fermeture : " + client.getRemoteSocketAddress());

        } catch (IOException e) {
            // Erreur réseau inattendue
            logger.severe("Erreur réseau : " + client.getRemoteSocketAddress()
                    + " -> " + e.getMessage());

        } finally {
            // Fermeture garantie du socket dans tous les cas
            try {
                if (!client.isClosed()) client.close();
                logger.info("Connexion fermée : " + client.getRemoteSocketAddress());
            } catch (IOException e) {
                logger.severe("Erreur fermeture client : " + e.getMessage());
            }
        }
    }
}