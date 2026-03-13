import socket
import time
import sys

HOST = '127.0.0.1'
PORT = 8009
TIMEOUT = 30  # secondes
RETRIES = 5   # nombre de tentatives de connexion

# Lecture des arguments (IP et port)
if len(sys.argv) >= 2:
    HOST = sys.argv[1]  # IP passée en argument

if len(sys.argv) >= 3:
    try:
        PORT = int(sys.argv[2])
    except ValueError:
        print(" Port invalide, utilisation du port par défaut :", PORT)

print(f"Connexion à {HOST}:{PORT}")

def creer_client():
    """Crée et connecte le socket TCP avec retries"""
    for attempt in range(RETRIES):
        try:
            client_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            client_socket.settimeout(TIMEOUT)
            client_socket.connect((HOST, PORT))
            print(" Connecté au serveur.\n")
            return client_socket
        except socket.gaierror:
            print(f" IP/DNS invalide ou introuvable : {HOST}")
            sys.exit(1)
        except ConnectionRefusedError:
            print(f" Serveur non disponible, tentative {attempt+1}/{RETRIES} dans 2s...")
        except TimeoutError:
            print(f" Timeout lors de la connexion, tentative {attempt+1}/{RETRIES} dans 2s...")
        except OSError as e:
            print(f" Erreur réseau : {e}, tentative {attempt+1}/{RETRIES} dans 2s...")
        time.sleep(2)
    print(" Impossible de se connecter au serveur. Fin du client.")
    sys.exit(1)

client = creer_client()

def recevoir():
    """Lit une réponse du serveur avec gestion du timeout et des déconnexions"""
    try:
        data = client.recv(1024).decode().strip()
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

# Lecture du premier message du serveur
try:
    recevoir()
except Exception:
    pass

# Boucle principale avec gestion de Ctrl+C
try:
    while True:
        print("\n---- MENU ----")
        print("1 - Envoyer HELLO")
        print("2 - ENTER (demande entrée)")
        print("3 - EXIT (sortie parking)")
        print("4 - STATUS (places restantes)")
        print("5 - BYE (fermer connexion)")
        print("----------------")
        
        choix = input("Choix : ").strip()

        try:
            if choix == "HELLO":
                client.sendall(b"HELLO\n")
                recevoir()

            elif choix == "ENTER":
                client.sendall(b"ENTER\n")
                response = recevoir()
                if response == "PLATE?":
                    plate = input("Entrez la plaque (format AB-123-CD) : ").strip()
                    client.sendall((plate + "\n").encode())
                    recevoir()

            elif choix == "EXIT":
                client.sendall(b"EXIT\n")
                response = recevoir()
                if response == "PLATE?":
                    plate = input("Plaque : ").strip()
                    client.sendall((plate + "\n").encode())
                    recevoir()

            elif choix == "STATUS":
                client.sendall(b"STATUS\n")
                recevoir()

            elif choix == "BYE":
                client.sendall(b"BYE\n")
                recevoir()
                print("Connexion fermée.")
                break

            else:
                print("Option inconnue.")

        except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError, OSError) as e:
            print(f" Le serveur a fermé la connexion ou erreur réseau : {e}")
            break

except KeyboardInterrupt:
    print("\n Interruption clavier détectée. Fermeture du client...")

finally:
    try:
        client.close()
    except Exception:
        pass
    print("Client terminé proprement.")
