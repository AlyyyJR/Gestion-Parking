-- ================================================================
-- FICHIER : schema_postgresql.sql
-- Projet  : Système de Gestion de Parking
-- Auteur  : Aly KONATE — L2 Informatique
-- ================================================================
-- Schéma PostgreSQL officiel du système de gestion de parking
--
-- Rôle de ce fichier :
--   - Créer toutes les tables de la base de données
--   - Insérer les données de test pour le développement
--   - Remplace le dump MySQL original (incompatible PostgreSQL)
--
-- Installation en une commande :
--   psql -d parking_bd -f schema_postgresql.sql
--
-- Prérequis :
--   La base parking_bd doit exister :
--   psql -U <user> -d postgres -c "CREATE DATABASE parking_bd;"
--
-- Architecture de la base :
--   utilisateur  → client / administrateur (héritage par rôle)
--   client       → voiture, abonnement
--   parking      → place
--   evenement_log → historique des entrées/sorties
--   paiement     → transactions financières
-- ================================================================


-- ================================================================
-- Suppression des tables existantes (ordre inverse des dépendances)
-- ================================================================
DROP TABLE IF EXISTS evenement_log CASCADE;
DROP TABLE IF EXISTS paiement      CASCADE;
DROP TABLE IF EXISTS abonnement    CASCADE;
DROP TABLE IF EXISTS voiture       CASCADE;
DROP TABLE IF EXISTS place         CASCADE;
DROP TABLE IF EXISTS parking       CASCADE;
DROP TABLE IF EXISTS client        CASCADE;
DROP TABLE IF EXISTS administrateur CASCADE;
DROP TABLE IF EXISTS utilisateur   CASCADE;


-- ================================================================
-- TABLE : utilisateur
-- ================================================================
-- Compte de base pour tous les utilisateurs du système.
-- Deux rôles possibles : 'client' ou 'administrateur'.
-- Le mot de passe est stocké hashé (bcrypt via password_hash PHP).
-- ================================================================
CREATE TABLE utilisateur (
    id_user      BIGSERIAL    PRIMARY KEY,
    nom          VARCHAR(50)  NOT NULL,
    prenom       VARCHAR(50)  NOT NULL,
    email        VARCHAR(100) NOT NULL UNIQUE
                              CHECK (email LIKE '%@%.%'),
    mot_de_passe VARCHAR(255) NOT NULL,
    role         VARCHAR(20)  NOT NULL
                              CHECK (role IN ('administrateur', 'client'))
);


-- ================================================================
-- TABLE : client
-- ================================================================
-- Extension de utilisateur pour les clients du parking.
-- Contient le type d'abonnement et un QR code d'identification.
-- ================================================================
CREATE TABLE client (
    id_client       BIGSERIAL   PRIMARY KEY,
    type_abonnement VARCHAR(50),
    qr_code         TEXT        NOT NULL,
    id_user_fk      BIGINT      REFERENCES utilisateur(id_user)
);


-- ================================================================
-- TABLE : administrateur
-- ================================================================
-- Extension de utilisateur pour le personnel d'administration.
-- Contient le poste et la date d'embauche.
-- ================================================================
CREATE TABLE administrateur (
    id_adm        BIGSERIAL   PRIMARY KEY,
    poste         VARCHAR(50) NOT NULL,
    date_embauche DATE        NOT NULL,
    id_user_fk    BIGINT      REFERENCES utilisateur(id_user)
);


-- ================================================================
-- TABLE : parking
-- ================================================================
-- Représente un parking physique avec son nom, adresse et capacité.
-- Un parking contient plusieurs places (relation 1→N avec place).
-- ================================================================
CREATE TABLE parking (
    id_parking BIGSERIAL   PRIMARY KEY,
    nom        VARCHAR(50) NOT NULL,
    adresse    TEXT        NOT NULL,
    capacite   INT         NOT NULL
);


-- ================================================================
-- TABLE : place
-- ================================================================
-- Une place de stationnement dans un parking.
-- État possible : 'libre' ou 'occupée'.
-- Mise à jour par ParkingRepository.occupyPlace() / freePlace().
-- ================================================================
CREATE TABLE place (
    id_place       BIGSERIAL  PRIMARY KEY,
    numero         VARCHAR(6),
    etat           VARCHAR(10) NOT NULL
                               CHECK (etat IN ('libre', 'occupée')),
    id_parking_fk  BIGINT      NOT NULL REFERENCES parking(id_parking)
);


-- ================================================================
-- TABLE : abonnement
-- ================================================================
-- Abonnement d'un client avec sa période de validité.
-- Vérifié par ParkingRepository.hasActiveSubscription() à chaque entrée.
-- Types disponibles : mensuel, annuel, hebdomadaire, quotidien.
-- ================================================================
CREATE TABLE abonnement (
    id_abonnement BIGSERIAL   PRIMARY KEY,
    type          VARCHAR(20) NOT NULL,
    date_debut    DATE        NOT NULL,
    date_fin      DATE        NOT NULL,
    statut        VARCHAR(10) NOT NULL
                              CHECK (statut IN ('actif', 'expiré')),
    id_client_fk  BIGINT      NOT NULL REFERENCES client(id_client)
);


-- ================================================================
-- TABLE : voiture
-- ================================================================
-- Véhicule enregistré sur le compte d'un client.
-- La plaque doit respecter le format français : AA-123-BB.
-- Vérifiée par ParkingService avec la regex ^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$
-- ================================================================
CREATE TABLE voiture (
    id_voiture   BIGSERIAL   PRIMARY KEY,
    plaque       VARCHAR(20) NOT NULL
                             CHECK (plaque ~ '^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$'),
    marque       VARCHAR(50),
    modele       VARCHAR(50),
    id_client_fk BIGINT      NOT NULL REFERENCES client(id_client)
);


-- ================================================================
-- TABLE : evenement_log
-- ================================================================
-- Historique complet de toutes les activités du parking.
-- Chaque entrée, sortie et paiement génère une ligne dans ce log.
-- La durée est calculée automatiquement par ParkingRepository.logExit().
-- ================================================================
CREATE TABLE evenement_log (
    id_event     BIGSERIAL   PRIMARY KEY,
    type_event   VARCHAR(10) NOT NULL
                             CHECK (type_event IN ('entrée', 'sortie', 'paiement', 'autre')),
    date_event   DATE        NOT NULL,
    heure_event  TIME        NOT NULL,
    message      TEXT        NOT NULL,
    duree        INTERVAL,
    id_client_fk BIGINT      REFERENCES client(id_client),
    id_place_fk  BIGINT      REFERENCES place(id_place)
);


-- ================================================================
-- TABLE : paiement
-- ================================================================
-- Enregistrement des transactions financières des clients.
-- Mode de paiement : 'carte' ou 'espece'.
-- ================================================================
CREATE TABLE paiement (
    id_paiement   BIGSERIAL      PRIMARY KEY,
    montant       DECIMAL(10, 2) NOT NULL,
    date_paiement DATE           NOT NULL,
    mode_paiement VARCHAR(10)    NOT NULL
                                 CHECK (mode_paiement IN ('carte', 'espece')),
    id_client_fk  BIGINT         NOT NULL REFERENCES client(id_client)
);


-- ================================================================
-- DONNÉES DE TEST
-- ================================================================
-- Jeu de données minimal pour tester le système en local.
-- Mot de passe de tous les comptes : "password" (hashé bcrypt)
-- ================================================================

-- Utilisateurs (clients + administrateurs)
INSERT INTO utilisateur VALUES (1,'Dupont','Jean','jean.dupont@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','client');
INSERT INTO utilisateur VALUES (2,'Martin','Sophie','sophie.martin@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','client');
INSERT INTO utilisateur VALUES (3,'Durand','Paul','paul.durand@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','administrateur');
INSERT INTO utilisateur VALUES (4,'Bernard','Emma','emma.bernard@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','client');
INSERT INTO utilisateur VALUES (5,'Petit','Luc','luc.petit@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','client');
INSERT INTO utilisateur VALUES (6,'Moreau','Alice','alice.moreau@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','administrateur');

-- Clients
INSERT INTO client VALUES (1,'mensuel','QR123ABC',1);
INSERT INTO client VALUES (2,'annuel','QR456DEF',2);
INSERT INTO client VALUES (3,'hebdomadaire','QR789GHI',4);
INSERT INTO client VALUES (4,'mensuel','QR741JKL',5);
INSERT INTO client VALUES (5,'quotidien','QR852MNO',1);
INSERT INTO client VALUES (6,'annuel','QR963PQR',2);

-- Parkings
INSERT INTO parking VALUES (1,'Parking République','10 Rue de la République, Paris',120);
INSERT INTO parking VALUES (2,'Parking Gare du Nord','5 Rue de Dunkerque, Paris',200);
INSERT INTO parking VALUES (3,'Parking Saint-Lazare','14 Rue Amsterdam, Paris',150);
INSERT INTO parking VALUES (4,'Parking Bastille','25 Boulevard Beaumarchais, Paris',100);
INSERT INTO parking VALUES (5,'Parking Montparnasse','3 Rue du Départ, Paris',180);

-- Places (5 parkings × 2 places = 10 places, alternance libre/occupée)
INSERT INTO place VALUES (1,'A101','libre',1);
INSERT INTO place VALUES (2,'A102','occupée',1);
INSERT INTO place VALUES (3,'B201','libre',2);
INSERT INTO place VALUES (4,'B202','occupée',2);
INSERT INTO place VALUES (5,'C301','libre',3);
INSERT INTO place VALUES (6,'C302','occupée',3);
INSERT INTO place VALUES (7,'D401','libre',4);
INSERT INTO place VALUES (8,'D402','occupée',4);
INSERT INTO place VALUES (9,'E501','libre',5);
INSERT INTO place VALUES (10,'E502','occupée',5);

-- Abonnements (dates mises à jour pour rester valides)
INSERT INTO abonnement VALUES (1,'mensuel','2025-09-01','2025-09-30','expiré',1);
INSERT INTO abonnement VALUES (2,'annuel','2025-01-01','2025-12-31','actif',2);
INSERT INTO abonnement VALUES (3,'hebdomadaire','2025-10-10','2025-10-17','actif',3);
INSERT INTO abonnement VALUES (4,'mensuel','2025-10-01','2025-10-31','actif',4);
INSERT INTO abonnement VALUES (5,'quotidien','2025-10-14','2025-10-15','actif',5);
INSERT INTO abonnement VALUES (6,'annuel','2026-01-01','2027-01-01','actif',1);

-- Voitures
INSERT INTO voiture VALUES (1,'AB-123-CD','Peugeot','208',1);
INSERT INTO voiture VALUES (2,'EF-456-GH','Renault','Clio',2);
INSERT INTO voiture VALUES (3,'IJ-789-KL','Tesla','Model 3',3);
INSERT INTO voiture VALUES (4,'MN-321-OP','Citroen','C3',4);
INSERT INTO voiture VALUES (5,'QR-654-ST','Dacia','Sandero',5);
INSERT INTO voiture VALUES (6,'UV-987-WX','Toyota','Yaris',1);

-- Séquences (évite les conflits d'ID après les INSERT manuels)
SELECT setval('utilisateur_id_user_seq', 6);
SELECT setval('client_id_client_seq', 6);
SELECT setval('parking_id_parking_seq', 5);
SELECT setval('place_id_place_seq', 10);
SELECT setval('abonnement_id_abonnement_seq', 6);
SELECT setval('voiture_id_voiture_seq', 6);