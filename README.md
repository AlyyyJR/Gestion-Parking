# Système de Gestion de Parking
> Projet personnel — Aly KONATE | L2 Informatique  
> Développé en Java, Python, PHP et PostgreSQL

---

## Présentation du projet

Ce projet est un **système complet de gestion de parking** développé en L2 Informatique.  
L'objectif était de concevoir une application multi-couches réaliste, capable de gérer l'accès des véhicules à un parking via un protocole réseau TCP, tout en offrant une interface web de supervision.

Le système repose sur **trois composants indépendants qui communiquent ensemble** :

- Un **serveur Java** qui écoute les connexions réseau et gère les entrées/sorties
- Un **client Python** en ligne de commande qui envoie des commandes au serveur
- Un **site web PHP** connecté à la même base de données pour la supervision

---

## Objectifs du projet

- Implémenter un **protocole de communication TCP** personnalisé entre un client et un serveur
- Appliquer une **architecture en couches** (réseau → service → données)
- Gérer une **base de données relationnelle PostgreSQL** avec des contraintes métier
- Construire une **interface web sécurisée** avec gestion des sessions et des rôles
- Produire un code **propre, documenté et maintenable**

---

## Stack technique

| Technologie | Usage |
|---|---|
| **Java 23** | Serveur TCP multi-threadé |
| **Python 3.13** | Client terminal interactif |
| **PHP 8.4** | Site web (interface client + admin) |
| **PostgreSQL 14** | Base de données relationnelle |
| **JDBC (postgresql-42.7.7.jar)** | Connexion Java → PostgreSQL |
| **PDO** | Connexion PHP → PostgreSQL |
| **Chart.js** | Graphiques dans le dashboard admin |

---

## Structure du projet

```
projet_parking/
│
├── Serveurs/                        # Composant 1 : Serveur TCP + Client Python
│   ├── MainServer.java              # Point d'entrée du serveur, boucle d'écoute TCP
│   ├── ParkingService.java          # Logique métier (validation entrées/sorties)
│   ├── ParkingRepository.java       # Accès base de données (requêtes SQL)
│   ├── client.py                    # Client terminal interactif (Python)
│   ├── postgresql-42.7.7.jar        # Driver JDBC PostgreSQL
│   └── server.log                   # Logs d'activité du serveur (généré automatiquement)
│
├── siteWeb/                         # Composant 2 : Interface web PHP
│   ├── index.php                    # Page de connexion
│   ├── dashboard.php                # Tableau de bord (client + admin)
│   ├── abonnement.php               # Gestion des abonnements
│   └── secret/
│       ├── bd_conf.php              # Configuration connexion PostgreSQL (à créer)
│       └── functions.php            # Fonctions SQL réutilisables
│
├── bdd/                             # Composant 3 : Base de données
│   ├── schema_postgresql.sql        # Schéma PostgreSQL à utiliser pour l'installation
│   ├── RequettesSelect.sql          # Requêtes SQL d'analyse et de statistiques
│   └── dump-parking_bd-202511222318.sql  # Archive MySQL originale (ne pas utiliser)
│
└── README.md                        # Ce fichier
```

---

##  Architecture technique

```
┌──────────────────┐     TCP :8009      ┌───────────────────────┐
│   client.py      │ ────────────────▶  │    MainServer.java    │
│ (Python terminal)│ ◀──────────────── │  (Serveur TCP Java)   │
└──────────────────┘   Protocole texte  └───────────┬───────────┘
                                                   │
                                          ┌────────▼────────┐
                                          │ ParkingService  │
                                          │ (Logique métier)│
                                          └────────┬────────┘
                                                   │
                                          ┌────────▼────────┐
                                          │ParkingRepository│
                                          │  (Accès BDD)    │
                                          └────────┬────────┘
                                                   │
┌─────────────────┐       PDO          ┌───────────▼───────────┐
│  siteWeb (PHP)  │ ────────────────▶  │    PostgreSQL BDD     │
│dashboard/abonnmt│ ◀────────────────  │     parking_bd        │
└─────────────────┘                    └───────────────────────┘
```

Le serveur Java et le site PHP accèdent **indépendamment** à la même base PostgreSQL.

---

## Protocole TCP

Le client et le serveur communiquent via un protocole texte simple (une commande par ligne) :

| Commande | Réponse(s) possible(s) | Description |
|---|---|---|
| `HELLO` | `HELLO_ACK` | Test de connexion |
| `ENTER` | `PLATE?` / `DENIED FULL` | Demande d'entrée |
| `ENTER` + plaque | `OK_ENTER place=N` / `DENIED INVALID_FORMAT` / `DENIED UNKNOWN_PLATE` / `DENIED ALREADY_INSIDE` / `DENIED NO_SUBSCRIPTION` | Validation de l'entrée |
| `EXIT` | `PLATE?` | Demande de sortie |
| `EXIT` + plaque | `OK_EXIT freed=N` / `DENIED NOT_INSIDE` / `DENIED INVALID_FORMAT` | Validation de la sortie |
| `STATUS` | `FREE N` | Nombre de places libres |
| `BYE` | `BYE_ACK` | Fermeture propre |

---

## Schéma de la base de données

```
utilisateur (id_user, nom, prenom, email, mot_de_passe, role)
     │
     ├──▶ client (id_client, type_abonnement, qr_code, id_user_fk)
     │         │
     │         ├──▶ voiture (id_voiture, plaque, marque, modele, id_client_fk)
     │         ├──▶ abonnement (id_abonnement, type, date_debut, date_fin, statut, id_client_fk)
     │         ├──▶ evenement_log (id_event, type_event, date_event, heure_event, message, duree, id_client_fk, id_place_fk)
     │         └──▶ paiement (id_paiement, montant, date_paiement, mode_paiement, id_client_fk)
     │
     └──▶ administrateur (id_adm, poste, date_embauche, id_user_fk)

parking (id_parking, nom, adresse, capacite)
     └──▶ place (id_place, numero, etat, id_parking_fk)
```

---

## Installation et lancement

### Prérequis

Vérifie que ces outils sont installés :

```bash
java -version      # Java 17+ requis
python3 --version  # Python 3.8+ requis
psql --version     # PostgreSQL 14+ requis
php --version      # PHP 8.0+ requis
```

Sur macOS avec Homebrew :
```bash
brew install openjdk postgresql@14 php
```

---

### Étape 1 — Créer et initialiser la base de données

```bash
# Démarrer PostgreSQL
brew services start postgresql@14

# Créer la base
psql -U $(whoami) -d postgres -c "CREATE DATABASE parking_bd;"

# Importer le schéma PostgreSQL (tables + données de test)
psql -d parking_bd -f bdd/schema_postgresql.sql
```

---

### Étape 2 — Configurer la connexion BDD dans le serveur Java

Ouvre `Serveurs/MainServer.java` et remplace les lignes de connexion :

```java
conn = DriverManager.getConnection(
    "jdbc:postgresql://localhost:5432/parking_bd",
    "TON_USERNAME_MAC",   // ← résultat de : whoami
    "TON_MOT_DE_PASSE"    // ← mot de passe PostgreSQL (vide si non configuré)
);
```

---

### Étape 3 — Configurer la connexion BDD pour le site PHP

Crée le fichier `siteWeb/secret/bd_conf.php` :

```php
<?php
$pdo = new PDO(
    'pgsql:host=localhost;port=5432;dbname=parking_bd',
    'TON_USERNAME_MAC',   // ← résultat de : whoami
    'TON_MOT_DE_PASSE'    // ← mot de passe PostgreSQL
);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

---

### Étape 4 — Compiler et lancer le serveur Java

```bash
# Se placer dans le dossier Serveurs
cd Serveurs

# Compiler tous les fichiers Java avec le driver PostgreSQL
javac -cp postgresql-42.7.7.jar *.java

# Lancer le serveur (port 8009 par défaut)
java -cp .:postgresql-42.7.7.jar MainServer

# Ou avec un host/port personnalisé
java -cp .:postgresql-42.7.7.jar MainServer 0.0.0.0 8009
```

Le serveur est prêt quand tu vois :
```
Serveur Parking démarré...
Connexion PostgreSQL OK
En attente de clients sur 0.0.0.0:8009...
```

---

### Étape 5 — Lancer le client Python

Ouvre un **nouveau terminal** (le serveur doit rester actif) :

```bash
cd Serveurs

# Connexion au serveur local
python3 client.py

# Ou avec un serveur distant
python3 client.py 192.168.1.10 8009
```

---

### Étape 6 — Lancer le site web PHP

Ouvre un **nouveau terminal** :

```bash
cd siteWeb
php -S localhost:8080
```

Ouvre ensuite **http://localhost:8080** dans ton navigateur.

---

## Comptes de test

| Nom | Email | Mot de passe | Rôle |
|---|---|---|---|
| Jean Dupont | `jean.dupont@example.com` | `password` | Client |
| Sophie Martin | `sophie.martin@example.com` | `password` | Client |
| Paul Durand | `paul.durand@example.com` | `password` | Administrateur |
| Emma Bernard | `emma.bernard@example.com` | `password` | Client |
| Alice Moreau | `alice.moreau@example.com` | `password` | Administrateur |

### Plaques de test disponibles

| Plaque | Propriétaire | Abonnement |
|---|---|---|
| `AB-123-CD` | Jean Dupont | ✅ Actif (jusqu'en 2027) |
| `EF-456-GH` | Sophie Martin | ✅ Actif |
| `IJ-789-KL` | Emma Bernard | ✅ Actif |
| `MN-321-OP` | Luc Petit | ✅ Actif |
| `QR-654-ST` | Luc Petit | ✅ Actif |
| `UV-987-WX` | Toyota Yaris | ✅ Actif |

---

## Fonctionnalités

### Espace Client (site web)
- Connexion sécurisée par email + mot de passe (bcrypt)
- Ajout et suppression de véhicules
- Consultation de l'historique des passages
- Gestion des abonnements (créer, renouveler, désactiver, supprimer)

### Espace Administrateur (site web)
- Supervision de l'occupation des parkings en temps réel
- Graphique Chart.js de l'occupation par parking
- Liste des abonnements actifs
- Alertes abonnements expirant sous 7 jours
- Consultation des places disponibles
- Détection des stationnements dépassant 8 heures

### Client terminal (Python)
- Connexion TCP avec retry automatique (5 tentatives)
- Commandes HELLO / ENTER / EXIT / STATUS / BYE
- Saisie et validation de la plaque d'immatriculation

---

## Logs du serveur

Chaque événement est tracé dans `Serveurs/server.log` :

```
INFO: ===== Serveur démarré =====
INFO: Connexion d'un client : /127.0.0.1:52286
INFO: Client READY : /127.0.0.1:52286
INFO: Commande reçue : ENTER
INFO: Réponse envoyée → PLATE?
INFO: Commande reçue : AB-123-CD
INFO: Réponse envoyée → OK_ENTER place=9
WARNING: Client inactif -> fermeture : /127.0.0.1:52286
```

---

## Sécurités implémentées

- **Injections SQL** : toutes les requêtes sont préparées (PreparedStatement Java / PDO PHP)
- **XSS** : `htmlspecialchars()` sur tous les affichages PHP
- **Mots de passe** : hashés en bcrypt (`password_hash` / `password_verify`)
- **Sessions PHP** : vérification du rôle à chaque page
- **Format plaque** : validation regex `^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$` côté serveur ET client
- **Accès cross-compte** : double condition `id + id_client_fk` sur tous les UPDATE/DELETE
- **Timeout réseau** : déconnexion automatique après 30 secondes d'inactivité
- **Messages trop longs** : rejet des commandes dépassant 50 caractères

---

## Auteur

**Aly KONATE** — L2 Informatique  
GitHub : [@AlyyyJR](https://github.com/AlyyyJR)