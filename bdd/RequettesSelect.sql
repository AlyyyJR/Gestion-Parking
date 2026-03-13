-- ================================================================
-- FICHIER : RequettesSelect.sql
-- Projet  : Système de Gestion de Parking
-- Auteur  : Aly KONATE — L2 Informatique
-- ================================================================
-- Requêtes SQL d'analyse et de supervision du parking
--
-- Rôle de ce fichier :
--   - Fournir des requêtes prêtes à l'emploi pour interroger la BDD
--   - Couvrir les cas d'usage métier les plus courants
--   - Servir de référence pour les requêtes utilisées dans le code
--
-- Base de données : PostgreSQL
-- Pour exécuter : psql -d parking_bd -f RequettesSelect.sql
--   ou copier-coller directement dans psql
--
-- Tables impliquées :
--   place, parking, utilisateur, client, voiture,
--   abonnement, evenement_log, paiement
-- ================================================================


-- ================================================================
-- SECTION 1 — REQUÊTES MÉTIER PRINCIPALES
-- ================================================================

-- ----------------------------------------------------------------
-- Requête 1 : Places libres dans tous les parkings
-- ----------------------------------------------------------------
-- Retourne le numéro et l'identifiant du parking de chaque place
-- dont l'état est 'libre'. Utilisé pour afficher la disponibilité
-- en temps réel.
-- ----------------------------------------------------------------
SELECT "numero", "id_parking_fk"
FROM "place"
WHERE "etat" = 'libre';


-- ----------------------------------------------------------------
-- Requête 2 : Vérification d'abonnement actif pour une plaque
-- ----------------------------------------------------------------
-- Vérifie si le propriétaire de la plaque AB-123-CD possède
-- un abonnement actif à la date du jour.
-- Enchaîne 4 jointures : utilisateur → client → voiture → abonnement
-- Utilisé par ParkingService.confirmEnter() pour valider l'accès.
-- ----------------------------------------------------------------
SELECT u.nom, u.prenom, a.statut, a.date_fin
FROM utilisateur u
JOIN client c   ON u.id_user    = c.id_user_fk
JOIN voiture v  ON c.id_client  = v.id_client_fk
JOIN abonnement a ON c.id_client = a.id_client_fk
WHERE v.plaque = 'AB-123-CD'
  AND a.statut = 'actif'
  AND CURRENT_DATE BETWEEN a.date_debut AND a.date_fin;


-- ----------------------------------------------------------------
-- Requête 3 : Places occupées par parking
-- ----------------------------------------------------------------
-- Compte le nombre de places occupées dans chaque parking.
-- Résultat groupé par nom de parking.
-- Utilisé dans le tableau de bord administrateur (dashboard.php)
-- et pour générer le graphique Chart.js.
-- ----------------------------------------------------------------
SELECT p.nom AS parking,
       COUNT(pl.id_place) AS places_occupees
FROM place pl
JOIN parking p ON pl.id_parking_fk = p.id_parking
WHERE pl.etat = 'occupée'
GROUP BY p.nom;


-- ----------------------------------------------------------------
-- Requête 4 : Clients actuellement garés dans le parking
-- ----------------------------------------------------------------
-- Identifie les véhicules présents en ce moment.
-- Principe : pour chaque (client, place), on récupère le dernier
-- événement enregistré. Si c'est une 'entrée', le véhicule est
-- toujours là. Si c'est une 'sortie', il est parti.
-- Utilise une CTE (WITH) et une fonction de fenêtre (ROW_NUMBER).
-- ----------------------------------------------------------------
WITH dernier_evenement AS (
    SELECT id_client_fk,
           id_place_fk,
           type_event,
           ROW_NUMBER() OVER (
               PARTITION BY id_client_fk, id_place_fk
               ORDER BY date_event DESC, heure_event DESC
           ) AS rn
    FROM evenement_log
    WHERE type_event IN ('entrée', 'sortie')
)
SELECT u.id_user,
       u.nom,
       u.prenom,
       c.id_client,
       p.numero AS place
FROM dernier_evenement e
JOIN client      c ON c.id_client  = e.id_client_fk
JOIN utilisateur u ON u.id_user    = c.id_user_fk
JOIN place       p ON p.id_place   = e.id_place_fk
WHERE e.rn = 1
  AND e.type_event = 'entrée';


-- ----------------------------------------------------------------
-- Requête 5 : Abonnements expirant dans les 7 prochains jours
-- ----------------------------------------------------------------
-- Alerte préventive : liste les clients dont l'abonnement arrive
-- à expiration sous 7 jours. Permet à l'administrateur de les
-- contacter pour un renouvellement.
-- Utilisé dans dashboard.php via getExpiringSubscriptions().
-- ----------------------------------------------------------------
SELECT u.nom, u.prenom, a.date_fin
FROM utilisateur u
JOIN client     c ON u.id_user    = c.id_user_fk
JOIN abonnement a ON c.id_client  = a.id_client_fk
WHERE a.date_fin BETWEEN CURRENT_DATE
                     AND (CURRENT_DATE + INTERVAL '7 days');


-- ----------------------------------------------------------------
-- Requête 6 : Total des paiements du mois en cours
-- ----------------------------------------------------------------
-- Calcule le chiffre d'affaires mensuel du parking.
-- Filtre sur le mois ET l'année courants avec EXTRACT()
-- pour ne pas mélanger les mois de différentes années.
-- ----------------------------------------------------------------
SELECT SUM(montant) AS total_paiements_mensuels
FROM paiement
WHERE EXTRACT(MONTH FROM date_paiement) = EXTRACT(MONTH FROM CURRENT_DATE)
  AND EXTRACT(YEAR  FROM date_paiement) = EXTRACT(YEAR  FROM CURRENT_DATE);


-- ----------------------------------------------------------------
-- Requête 7 : Véhicules ayant stationné plus de 8 heures
-- ----------------------------------------------------------------
-- Détecte les stationnements anormalement longs (> 8h).
-- Se base sur la colonne duree de evenement_log, calculée
-- automatiquement lors de la sortie par ParkingRepository.logExit().
-- Utilisé dans dashboard.php via getLongParking().
-- ----------------------------------------------------------------
SELECT v.plaque, e.duree AS duree_stationnement
FROM voiture v
JOIN evenement_log e ON v.id_client_fk = e.id_client_fk
WHERE e.type_event = 'sortie'
  AND e.duree > INTERVAL '8 hours';


-- ----------------------------------------------------------------
-- Requête 8 : Tous les événements du jour
-- ----------------------------------------------------------------
-- Récapitulatif de l'activité du parking pour la journée en cours.
-- Retourne les entrées, sorties et paiements d'aujourd'hui.
-- ----------------------------------------------------------------
SELECT type_event, duree, message
FROM evenement_log
WHERE date_event = CURRENT_DATE;


-- ----------------------------------------------------------------
-- Requête 9 : Abonnements actifs avec leurs clients
-- ----------------------------------------------------------------
-- Liste complète des abonnés actifs à la date du jour.
-- Vérifie que la date courante est bien dans la période de validité.
-- Utilisé dans dashboard.php via getActiveSubscriptions().
-- ----------------------------------------------------------------
SELECT u.nom, u.prenom, a.type, a.date_fin
FROM utilisateur u
JOIN client     c ON u.id_user   = c.id_user_fk
JOIN abonnement a ON c.id_client = a.id_client_fk
WHERE a.statut = 'actif'
  AND CURRENT_DATE BETWEEN a.date_debut AND a.date_fin;


-- ----------------------------------------------------------------
-- Requête 10 : Dernier véhicule entré dans le parking
-- ----------------------------------------------------------------
-- Retrouve l'entrée la plus récente enregistrée dans le log.
-- Trié par date puis heure décroissante, limité à 1 résultat.
-- ----------------------------------------------------------------
SELECT type_event, message, duree
FROM evenement_log
WHERE type_event = 'entrée'
ORDER BY date_event DESC, heure_event DESC
LIMIT 1;


-- ================================================================
-- SECTION 2 — REQUÊTES STATISTIQUES AVANCÉES
-- ================================================================

-- ----------------------------------------------------------------
-- Statistiques globales du parking (tableau de bord synthétique)
-- ----------------------------------------------------------------
-- Vue d'ensemble en une seule requête :
-- places libres, occupées, total, véhicules et clients inscrits.
-- Utilise des sous-requêtes scalaires pour chaque compteur.
-- ----------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM place WHERE etat = 'libre')    AS places_libres,
    (SELECT COUNT(*) FROM place WHERE etat = 'occupée')  AS places_occupees,
    (SELECT COUNT(*) FROM place)                         AS total_places,
    (SELECT COUNT(*) FROM voiture)                       AS vehicules_enregistres,
    (SELECT COUNT(*) FROM client)                        AS clients_inscrits;


-- ----------------------------------------------------------------
-- Revenus mensuels détaillés sur toute la période
-- ----------------------------------------------------------------
-- Agrège les paiements par mois et par année.
-- Permet de suivre l'évolution du chiffre d'affaires dans le temps.
-- Trié du plus récent au plus ancien.
-- ----------------------------------------------------------------
SELECT
    EXTRACT(YEAR  FROM date_paiement) AS annee,
    EXTRACT(MONTH FROM date_paiement) AS mois,
    SUM(montant)                      AS revenus_mensuels,
    COUNT(*)                          AS nombre_paiements
FROM paiement
GROUP BY annee, mois
ORDER BY annee DESC, mois DESC;


-- ----------------------------------------------------------------
-- Classement des véhicules les plus fréquents
-- ----------------------------------------------------------------
-- Compte le nombre de passages (entrées) par véhicule.
-- Permet d'identifier les clients les plus fidèles du parking.
-- Trié par nombre de passages décroissant.
-- ----------------------------------------------------------------
SELECT
    v.plaque,
    v.marque,
    v.modele,
    COUNT(e.id_event) AS nombre_passages
FROM voiture v
JOIN evenement_log e ON v.id_client_fk = e.id_client_fk
WHERE e.type_event = 'entrée'
GROUP BY v.plaque, v.marque, v.modele
ORDER BY nombre_passages DESC;


-- ----------------------------------------------------------------
-- Taux d'occupation par parking (en pourcentage)
-- ----------------------------------------------------------------
-- Calcule le taux d'occupation de chaque parking :
--   (places occupées / total places) × 100
-- Utilise ROUND() pour afficher 2 décimales.
-- Trié par taux décroissant pour voir les parkings les plus chargés.
-- ----------------------------------------------------------------
SELECT
    p.nom                                                          AS parking,
    COUNT(pl.id_place)                                             AS total_places,
    COUNT(CASE WHEN pl.etat = 'occupée' THEN 1 END)               AS places_occupees,
    ROUND(
        COUNT(CASE WHEN pl.etat = 'occupée' THEN 1 END) * 100.0
        / COUNT(pl.id_place),
        2
    )                                                              AS taux_occupation
FROM parking p
JOIN place pl ON p.id_parking = pl.id_parking_fk
GROUP BY p.nom
ORDER BY taux_occupation DESC;