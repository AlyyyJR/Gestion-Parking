import java.io.*;
import java.net.*;
import java.sql.Connection;
import java.sql.DriverManager;
import java.time.LocalTime;
import java.util.logging.Logger;
import java.util.logging.FileHandler;
import java.util.logging.SimpleFormatter;

public class MainServer {

    private static final int PORT = 8009;
    private static final int TIMEOUT_MS = 30000; // 30 secondes
    private static final String HOST_DEFAULT = "0.0.0.0"; // toutes interfaces
    private static final int MAX_MESSAGE_LENGTH = 50; // Taille max pour les messages
    private static Logger logger = Logger.getLogger("ParkingServerLog");

    public static void main(String[] args) {
        System.out.println("Serveur Parking démarré...");

        int port = PORT;
        String host = HOST_DEFAULT;

        if (args.length >= 1) host = args[0];
        if (args.length >= 2) {
            try {
                port = Integer.parseInt(args[1]);
            } catch (NumberFormatException e) {
                System.out.println("Port invalide, utilisation du port par défaut :" + port);
                port = PORT;
            }
        }

        // Charger le driver PostgreSQL
        try {
            Class.forName("org.postgresql.Driver");
        } catch (ClassNotFoundException e) {
            System.out.println("Driver PostgreSQL non trouvé !");
            e.printStackTrace();
            return;
        }

        // Connexion à la base
        Connection conn;
        try {
            conn = DriverManager.getConnection(
                "lien bd",
                "username",
                "password"
            );
            System.out.println("Connexion PostgreSQL OK");
        } catch (Exception e) {
            System.out.println("Erreur de connexion à la base : " + e.getMessage());
            e.printStackTrace();
            return;
        }

        ParkingRepository repo = new ParkingRepository(conn);
        ParkingService service = new ParkingService(conn, repo);

        // Créer le serveur
        ServerSocket server;

        try {
            server = new ServerSocket();
            server.bind(new InetSocketAddress(host, port));
            System.out.println("[" + LocalTime.now() + "] En attente de clients sur " + host + ":" + port + "...");
        } catch (BindException e) {
            System.out.println("Erreur : le port " + port + " est déjà utilisé !");
            return;
        } catch (IOException e) {
            System.out.println("Erreur réseau lors de la création du serveur : " + e.getMessage());
            return;
        }

        // Configuration du logger
        try {
            FileHandler fh = new FileHandler("server.log", true); // true = append
            fh.setFormatter(new SimpleFormatter());
            logger.addHandler(fh);
            logger.info("===== Serveur démarré =====");
        } catch (Exception e) {
            e.printStackTrace();
        }

        // Boucle principale
        while (true) {
            try {
                Socket clientSocket = server.accept();
                logger.info("Connexion d’un client : " + clientSocket.getRemoteSocketAddress());
                clientSocket.setSoTimeout(TIMEOUT_MS);

                System.out.println("[" + LocalTime.now() + "] Client connecté : "
                        + clientSocket.getInetAddress() + ":" + clientSocket.getPort());

                new Thread(() -> handleClient(clientSocket, service)).start();

            } catch (IOException e) {
                System.out.println("Erreur lors de l'acceptation d'un client : " + e.getMessage());
            }
        }
    }

    // Gestion d'un client

    private static void handleClient(Socket client, ParkingService service) {
        try (BufferedReader in = new BufferedReader(new InputStreamReader(client.getInputStream()));
             BufferedWriter out = new BufferedWriter(new OutputStreamWriter(client.getOutputStream()))) {

            out.write("READY\n");
            out.flush();

            logger.info("Client READY : " + client.getRemoteSocketAddress());

            String expected = "NONE";
            String line;

            while ((line = in.readLine()) != null) {
                line = line.trim();

                // Taille max
                if (line.length() > MAX_MESSAGE_LENGTH) {
                    logger.warning("Message trop long (" + line.length() + " chars) de " +
                            client.getRemoteSocketAddress());

                    out.write("ERROR Message too long\n");
                    out.flush();
                    expected = "NONE";
                    continue;
                }

                logger.info("Commande reçue : " + line);
                String response = "ERROR Unknown";

                if (expected.equals("WAIT_ENTER")) {
                    response = service.confirmEnter(line);
                    expected = "NONE";

                } else if (expected.equals("WAIT_EXIT")) {
                    response = service.confirmExit(line);
                    expected = "NONE";

                } else {
                    switch (line.toUpperCase()) {
                        case "HELLO" -> response = service.hello();

                        case "ENTER" -> {
                            response = service.startEnter();
                            if (response.equals("PLATE?")) expected = "WAIT_ENTER";
                        }

                        case "EXIT" -> {
                            response = service.startExit();
                            expected = "WAIT_EXIT";
                        }

                        case "STATUS" -> response = service.status();

                        case "BYE" -> {
                            response = "BYE_ACK";
                            out.write(response + "\n");
                            out.flush();
                            logger.info("Client déconnecté : " + client.getRemoteSocketAddress());
                            return;
                        }

                        default -> response = "ERROR Unknown command";
                    }
                }

                out.write(response + "\n");
                out.flush();

                logger.info("Réponse envoyée → " + response);
            }

        } catch (SocketTimeoutException e) {
            logger.warning("Client inactif -> fermeture : " + client.getRemoteSocketAddress());

        } catch (IOException e) {
            logger.severe("Erreur réseau : " + client.getRemoteSocketAddress() + " -> " + e.getMessage());

        } finally {
            try {
                if (!client.isClosed()) client.close();
                logger.info("Connexion fermée : " + client.getRemoteSocketAddress());
            } catch (IOException e) {
                logger.severe("Erreur fermeture client : " + e.getMessage());
            }
        }
    }
}
