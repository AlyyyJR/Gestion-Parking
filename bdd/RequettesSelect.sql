-- ======================================================
-- REQUÊTES SQL POUR LE SYSTÈME DE GESTION DE PARKING
-- ======================================================

-- Question 1 : Places libres dans tous les parkings
SELECT "numero", "id_parking_fk"
FROM "place"
WHERE "etat" = 'libre';

-- Question 2 : Vérification abonnement actif pour plaque AB-123-CD
SELECT u.nom, u.prenom, a.statut, a.date_fin
FROM utilisateur u
JOIN client c ON u.id_user = c.id_user_fk
JOIN voiture v ON c.id_client = v.id_client_fk
JOIN abonnement a ON c.id_client = a.id_client_fk
WHERE v.plaque = 'AB-123-CD'
AND a.statut = 'actif'
AND CURRENT_DATE BETWEEN a.date_debut AND a.date_fin;

-- Question 3 : Places occupées par parking
SELECT p.nom AS parking,
       COUNT(pl.id_place) AS places_occupees
FROM place pl
JOIN parking p ON pl.id_parking_fk = p.id_parking
WHERE pl.etat = 'occupée'
GROUP BY p.nom;

-- Question 4 : Clients actuellement garés
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
JOIN client c ON c.id_client = e.id_client_fk
JOIN utilisateur u ON u.id_user = c.id_user_fk
JOIN place p ON p.id_place = e.id_place_fk
WHERE e.rn = 1
  AND e.type_event = 'entrée';

-- Question 5 : Abonnements expirant dans 7 jours
SELECT u.nom, u.prenom, a.date_fin
FROM utilisateur u
JOIN client c ON u.id_user = c.id_user_fk
JOIN abonnement a ON c.id_client = a.id_client_fk
WHERE a.date_fin BETWEEN CURRENT_DATE 
                     AND (CURRENT_DATE + INTERVAL '7 days');

-- Question 6 : Total des paiements ce mois-ci
SELECT SUM(montant) AS total_paiements_mensuels
FROM paiement
WHERE EXTRACT(MONTH FROM date_paiement) = EXTRACT(MONTH FROM CURRENT_DATE)
  AND EXTRACT(YEAR FROM date_paiement)  = EXTRACT(YEAR FROM CURRENT_DATE);

-- Question 7 : Véhicules stationnés plus de 8 heures
SELECT v.plaque, e.duree AS duree_stationnement
FROM voiture v
JOIN evenement_log e ON v.id_client_fk = e.id_client_fk
WHERE e.type_event = 'sortie' 
AND e.duree > INTERVAL '8 hours';

-- Question 8 : Événements d'aujourd'hui
SELECT type_event, duree, message
FROM evenement_log
WHERE date_event = CURRENT_DATE;

-- Question 9 : Abonnements actifs avec clients
SELECT u.nom, u.prenom, a.type, a.date_fin
FROM utilisateur u
JOIN client c ON u.id_user = c.id_user_fk
JOIN abonnement a ON c.id_client = a.id_client_fk
WHERE a.statut = 'actif'
AND CURRENT_DATE BETWEEN a.date_debut AND a.date_fin;

-- Question 10 : Dernière voiture entrée
SELECT type_event, message, duree
FROM evenement_log
WHERE type_event = 'entrée'
ORDER BY date_event DESC, heure_event DESC
LIMIT 1;

-- ======================================================
-- REQUÊTES SUPPLÉMENTAIRES UTILES
-- ======================================================

-- Statistiques globales du parking
SELECT 
    (SELECT COUNT(*) FROM place WHERE etat = 'libre') AS places_libres,
    (SELECT COUNT(*) FROM place WHERE etat = 'occupée') AS places_occupees,
    (SELECT COUNT(*) FROM place) AS total_places,
    (SELECT COUNT(*) FROM voiture) AS vehicules_enregistres,
    (SELECT COUNT(*) FROM client) AS clients_inscrits;

-- Revenus mensuels détaillés
SELECT 
    EXTRACT(YEAR FROM date_paiement) AS annee,
    EXTRACT(MONTH FROM date_paiement) AS mois,
    SUM(montant) AS revenus_mensuels,
    COUNT(*) AS nombre_paiements
FROM paiement 
GROUP BY annee, mois 
ORDER BY annee DESC, mois DESC;

-- Véhicules les plus fréquents
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

-- Taux d'occupation par parking
SELECT 
    p.nom AS parking,
    COUNT(pl.id_place) AS total_places,
    COUNT(CASE WHEN pl.etat = 'occupée' THEN 1 END) AS places_occupees,
    ROUND(
        COUNT(CASE WHEN pl.etat = 'occupée' THEN 1 END) * 100.0 / COUNT(pl.id_place), 
        2
    ) AS taux_occupation
FROM parking p
JOIN place pl ON p.id_parking = pl.id_parking_fk
GROUP BY p.nom
ORDER BY taux_occupation DESC;