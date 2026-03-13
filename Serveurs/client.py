# ================================================================
# FICHIER : client.py
# Projet  : Système de Gestion de Parking
# Auteur  : Aly KONATE — L2 Informatique
# ================================================================
# Client TCP en ligne de commande du système de gestion de parking
#
# Rôle de client.py :
#   - Se connecter au serveur MainServer via un socket TCP
#   - Proposer un menu interactif à l'utilisateur dans le terminal
#   - Envoyer les commandes du protocole (HELLO, ENTER, EXIT, STATUS, BYE)
#   - Recevoir et afficher les réponses du serveur
#   - Gérer les erreurs réseau et les tentatives de reconnexion
#
# Protocole supporté (TCP texte, une commande par ligne) :
#   1 → HELLO   : vérifie la connexion  → HELLO_ACK
#   2 → ENTER   : demande d'entrée      → PLATE? puis OK_ENTER / DENIED ...
#   3 → EXIT    : demande de sortie     → PLATE? puis OK_EXIT  / DENIED ...
#   4 → STATUS  : places disponibles    → FREE <n>
#   5 → BYE     : fermeture propre      → BYE_ACK
#
# Utilisation :
#   python3 client.py                        → connexion par défaut (127.0.0.1:8009)
#   python3 client.py <host>                 → IP personnalisée
#   python3 client.py <host> <port>          → IP et port personnalisés
#
# Gestion des erreurs :
#   - Retry automatique (5 tentatives, 2 secondes entre chaque)
#   - Timeout de 30 secondes sur les réponses serveur
#   - Interruption clavier (Ctrl+C) gérée proprement
# ================================================================

import socket
import time
import sys

# ================================================================
# Configuration réseau par défaut
# ================================================================
HOST    = '127.0.0.1'  # Adresse IP du serveur (localhost par défaut)
PORT    = 8009          # Port d'écoute du serveur
TIMEOUT = 30            # Délai max d'attente d'une réponse (en secondes)
RETRIES = 5             # Nombre de tentatives de connexion avant abandon

# ================================================================
# Lecture des arguments en ligne de commande
# ================================================================
# Permet de lancer le client vers un serveur distant :
#   python3 client.py 192.168.1.10 9000
if len(sys.argv) >= 2:
    HOST = sys.argv[1]          # IP ou nom d'hôte passé en argument

if len(sys.argv) >= 3:
    try:
        PORT = int(sys.argv[2]) # Port passé en argument
    except ValueError:
        print(f" Port invalide, utilisation du port par défaut : {PORT}")

print(f"Connexion à {HOST}:{PORT}")


# ================================================================
# Création et connexion du socket TCP
# ================================================================
def creer_client():
    """
    Crée un socket TCP et tente de se connecter au serveur.

    Effectue jusqu'à RETRIES tentatives avec 2 secondes d'attente
    entre chaque essai. Quitte le programme si toutes échouent.

    Erreurs gérées :
        - socket.gaierror       : IP ou DNS invalide
        - ConnectionRefusedError : serveur non démarré ou port fermé
        - TimeoutError          : serveur injoignable dans le délai imparti
        - OSError               : erreur réseau générique

    Returns:
        socket.socket : socket TCP connecté et prêt à l'emploi
    """
    for attempt in range(RETRIES):
        try:
            client_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            client_socket.settimeout(TIMEOUT)
            client_socket.connect((HOST, PORT))
            print(" Connecté au serveur.\n")
            return client_socket

        except socket.gaierror:
            # IP ou nom DNS introuvable — inutile de réessayer
            print(f" IP/DNS invalide ou introuvable : {HOST}")
            sys.exit(1)

        except ConnectionRefusedError:
            print(f" Serveur non disponible, tentative {attempt + 1}/{RETRIES} dans 2s...")

        except TimeoutError:
            print(f" Timeout lors de la connexion, tentative {attempt + 1}/{RETRIES} dans 2s...")

        except OSError as e:
            print(f" Erreur réseau : {e}, tentative {attempt + 1}/{RETRIES} dans 2s...")

        time.sleep(2)

    # Toutes les tentatives ont échoué
    print(" Impossible de se connecter au serveur. Fin du client.")
    sys.exit(1)


# ================================================================
# Réception d'une réponse du serveur
# ================================================================
def recevoir():
    """
    Lit et retourne une ligne de réponse envoyée par le serveur.

    Gère les cas d'erreur suivants :
        - Timeout : le serveur ne répond pas dans le délai TIMEOUT
        - Connexion fermée : le serveur a coupé la connexion proprement
        - Erreur réseau : déconnexion inattendue

    Returns:
        str  : la réponse reçue (sans le \\n final), ou None en cas de timeout
    """
    try:
        data = client.recv(1024).decode().strip()

        # Si la réponse est vide, le serveur a fermé la connexion
        if not data:
            print(" Connexion fermée par le serveur.")
            sys.exit(0)

        print("Serveur:", data)
        return data

    except socket.timeout:
        print(" Timeout : le serveur ne répond pas.")
        return None

    except (ConnectionResetError, ConnectionAbortedError, OSError) as e:
        print(f" Connexion interrompue : {e}")
        sys.exit(0)


# ================================================================
# Connexion initiale au serveur
# ================================================================
client = creer_client()

# Lecture du message READY envoyé par le serveur à la connexion
try:
    recevoir()
except Exception:
    pass


# ================================================================
# Boucle principale du menu interactif
# ================================================================
try:
    while True:
        # Affichage du menu
        print("\n---- MENU ----")
        print("1 - Envoyer HELLO")
        print("2 - ENTER (demande entrée)")
        print("3 - EXIT  (sortie parking)")
        print("4 - STATUS (places restantes)")
        print("5 - BYE   (fermer connexion)")
        print("----------------")

        choix = input("Choix : ").strip()

        try:
            # ----------------------------------------
            # Option 1 : HELLO — test de connexion
            # ----------------------------------------
            if choix == "1":
                client.sendall(b"HELLO\n")
                recevoir()

            # ----------------------------------------
            # Option 2 : ENTER — demande d'entrée
            # Si le serveur répond PLATE?, on saisit la plaque
            # ----------------------------------------
            elif choix == "2":
                client.sendall(b"ENTER\n")
                response = recevoir()
                if response == "PLATE?":
                    plate = input("Entrez la plaque (format AB-123-CD) : ").strip().upper()
                    client.sendall((plate + "\n").encode())
                    recevoir()

            # ----------------------------------------
            # Option 3 : EXIT — demande de sortie
            # Le serveur demande toujours la plaque
            # ----------------------------------------
            elif choix == "3":
                client.sendall(b"EXIT\n")
                response = recevoir()
                if response == "PLATE?":
                    plate = input("Plaque du véhicule sortant : ").strip().upper()
                    client.sendall((plate + "\n").encode())
                    recevoir()

            # ----------------------------------------
            # Option 4 : STATUS — nombre de places libres
            # ----------------------------------------
            elif choix == "4":
                client.sendall(b"STATUS\n")
                recevoir()

            # ----------------------------------------
            # Option 5 : BYE — fermeture propre
            # ----------------------------------------
            elif choix == "5":
                client.sendall(b"BYE\n")
                recevoir()
                print("Connexion fermée proprement.")
                break

            else:
                print(" Option inconnue. Choisissez entre 1 et 5.")

        except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError, OSError) as e:
            # Le serveur a coupé la connexion de manière inattendue
            print(f" Le serveur a fermé la connexion ou erreur réseau : {e}")
            break

# ================================================================
# Gestion de l'interruption clavier (Ctrl+C)
# ================================================================
except KeyboardInterrupt:
    print("\n Interruption clavier détectée. Fermeture du client...")

# ================================================================
# Fermeture propre du socket dans tous les cas
# ================================================================
finally:
    try:
        client.close()
    except Exception:
        pass
    print("Client terminé proprement.")