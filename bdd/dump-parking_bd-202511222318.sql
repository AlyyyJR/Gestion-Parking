DROP TABLE IF EXISTS `abonnement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `abonnement` (
  `id_abonnement` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `statut` enum('actif','expiré') NOT NULL,
  `id_client_fk` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id_abonnement`),
  KEY `id_client_fk` (`id_client_fk`),
  CONSTRAINT `abonnement_ibfk_1` FOREIGN KEY (`id_client_fk`) REFERENCES `client` (`id_client`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `abonnement`
--

LOCK TABLES `abonnement` WRITE;
/*!40000 ALTER TABLE `abonnement` DISABLE KEYS */;
INSERT INTO `abonnement` VALUES (1,'mensuel','2025-09-01','2025-09-30','expiré',1),(2,'annuel','2025-01-01','2025-12-31','actif',2),(3,'hebdomadaire','2025-10-10','2025-10-17','actif',3),(4,'mensuel','2025-10-01','2025-10-31','actif',4),(5,'quotidien','2025-10-14','2025-10-15','actif',5),(6,'annuel','2025-11-01','2026-10-31','actif',2);
/*!40000 ALTER TABLE `abonnement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `administrateur`
--

DROP TABLE IF EXISTS `administrateur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `administrateur` (
  `id_adm` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `poste` varchar(50) NOT NULL,
  `date_embauche` date NOT NULL,
  `id_user_fk` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_adm`),
  KEY `id_user_fk` (`id_user_fk`),
  CONSTRAINT `administrateur_ibfk_1` FOREIGN KEY (`id_user_fk`) REFERENCES `utilisateur` (`id_user`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administrateur`
--

LOCK TABLES `administrateur` WRITE;
/*!40000 ALTER TABLE `administrateur` DISABLE KEYS */;
INSERT INTO `administrateur` VALUES (1,'Responsable Parking République','2023-05-12',3),(2,'Gestionnaire Parking Gare du Nord','2024-03-01',6),(3,'Superviseur Parking Bastille','2023-08-15',3),(4,'Chef Maintenance Parking Montparnasse','2024-01-10',6),(5,'Adjoint Direction','2025-02-01',3);
/*!40000 ALTER TABLE `administrateur` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client`
--

DROP TABLE IF EXISTS `client`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client` (
  `id_client` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type_abonnement` varchar(50) DEFAULT NULL,
  `qr_code` text NOT NULL,
  `id_user_fk` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_client`),
  KEY `id_user_fk` (`id_user_fk`),
  CONSTRAINT `client_ibfk_1` FOREIGN KEY (`id_user_fk`) REFERENCES `utilisateur` (`id_user`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client`
--

LOCK TABLES `client` WRITE;
/*!40000 ALTER TABLE `client` DISABLE KEYS */;
INSERT INTO `client` VALUES (1,'mensuel','QR123ABC',1),(2,'annuel','QR456DEF',2),(3,'hebdomadaire','QR789GHI',4),(4,'mensuel','QR741JKL',5),(5,'quotidien','QR852MNO',1),(6,'annuel','QR963PQR',2);
/*!40000 ALTER TABLE `client` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evenement_log`
--

DROP TABLE IF EXISTS `evenement_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evenement_log` (
  `id_event` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type_event` enum('entrée','sortie','paiement','autre') NOT NULL,
  `date_event` date NOT NULL,
  `heure_event` time NOT NULL,
  `message` text NOT NULL,
  `duree` time DEFAULT NULL,
  `id_client_fk` bigint(20) unsigned DEFAULT NULL,
  `id_place_fk` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_event`),
  KEY `id_client_fk` (`id_client_fk`),
  KEY `id_place_fk` (`id_place_fk`),
  CONSTRAINT `evenement_log_ibfk_1` FOREIGN KEY (`id_client_fk`) REFERENCES `client` (`id_client`),
  CONSTRAINT `evenement_log_ibfk_2` FOREIGN KEY (`id_place_fk`) REFERENCES `place` (`id_place`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evenement_log`
--

LOCK TABLES `evenement_log` WRITE;
/*!40000 ALTER TABLE `evenement_log` DISABLE KEYS */;
INSERT INTO `evenement_log` VALUES (1,'entrée','2025-10-14','08:15:00','Voiture AB-123-CD entrée au parking République','09:15:00',1,2),(2,'sortie','2025-10-14','17:30:00','Voiture AB-123-CD sortie du parking République','09:15:00',1,2),(3,'entrée','2025-10-13','09:00:00','Voiture EF-456-GH entrée au parking Gare du Nord','08:30:00',2,4),(4,'sortie','2025-10-13','18:00:00','Voiture EF-456-GH sortie du parking Gare du Nord','08:30:00',2,4),(5,'paiement','2025-10-10','10:00:00','Paiement effectué par le client Emma Bernard',NULL,3,NULL),(6,'entrée','2025-10-14','07:45:00','Voiture IJ-789-KL entrée au parking Saint-Lazare','07:45:00',3,6),(7,'sortie','2025-10-14','15:00:00','Voiture IJ-789-KL sortie du parking Saint-Lazare','07:15:00',3,6),(8,'entrée','2025-10-13','08:30:00','Voiture MN-321-OP entrée au parking Bastille','05:30:00',4,8),(9,'sortie','2025-10-13','14:00:00','Voiture MN-321-OP sortie du parking Bastille','05:30:00',4,8),(10,'paiement','2025-10-12','09:00:00','Paiement effectué par le client Luc Petit',NULL,5,NULL);
/*!40000 ALTER TABLE `evenement_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paiement`
--

DROP TABLE IF EXISTS `paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paiement` (
  `id_paiement` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `montant` decimal(10,2) NOT NULL,
  `date_paiement` date NOT NULL,
  `mode_paiement` enum('carte','espece') NOT NULL,
  `id_client_fk` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id_paiement`),
  KEY `id_client_fk` (`id_client_fk`),
  CONSTRAINT `paiement_ibfk_1` FOREIGN KEY (`id_client_fk`) REFERENCES `client` (`id_client`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paiement`
--

LOCK TABLES `paiement` WRITE;
/*!40000 ALTER TABLE `paiement` DISABLE KEYS */;
INSERT INTO `paiement` VALUES (1,45.00,'2025-09-01','carte',1),(2,480.00,'2025-01-01','carte',2),(3,10.00,'2025-10-10','espece',3),(4,45.00,'2025-10-01','carte',4),(5,5.00,'2025-10-14','espece',5),(6,60.00,'2025-11-01','carte',2);
/*!40000 ALTER TABLE `paiement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parking`
--

DROP TABLE IF EXISTS `parking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parking` (
  `id_parking` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `adresse` text NOT NULL,
  `capacite` int(11) NOT NULL,
  PRIMARY KEY (`id_parking`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parking`
--

LOCK TABLES `parking` WRITE;
/*!40000 ALTER TABLE `parking` DISABLE KEYS */;
INSERT INTO `parking` VALUES (1,'Parking République','10 Rue de la République, Paris',120),(2,'Parking Gare du Nord','5 Rue de Dunkerque, Paris',200),(3,'Parking Saint-Lazare','14 Rue d’Amsterdam, Paris',150),(4,'Parking Bastille','25 Boulevard Beaumarchais, Paris',100),(5,'Parking Montparnasse','3 Rue du Départ, Paris',180);
/*!40000 ALTER TABLE `parking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `place`
--

DROP TABLE IF EXISTS `place`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `place` (
  `id_place` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(6) DEFAULT NULL,
  `etat` enum('libre','occupée') NOT NULL,
  `id_parking_fk` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id_place`),
  KEY `id_parking_fk` (`id_parking_fk`),
  CONSTRAINT `place_ibfk_1` FOREIGN KEY (`id_parking_fk`) REFERENCES `parking` (`id_parking`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `place`
--

LOCK TABLES `place` WRITE;
/*!40000 ALTER TABLE `place` DISABLE KEYS */;
INSERT INTO `place` VALUES (1,'A101','libre',1),(2,'A102','occupée',1),(3,'B201','libre',2),(4,'B202','occupée',2),(5,'C301','libre',3),(6,'C302','occupée',3),(7,'D401','libre',4),(8,'D402','occupée',4),(9,'E501','libre',5),(10,'E502','occupée',5);
/*!40000 ALTER TABLE `place` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `utilisateur` (
  `id_user` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('administrateur','client') NOT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `email` (`email`),
  CONSTRAINT `CONSTRAINT_1` CHECK (`email` like '%@%.%')
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utilisateur`
--

LOCK TABLES `utilisateur` WRITE;
/*!40000 ALTER TABLE `utilisateur` DISABLE KEYS */;
INSERT INTO `utilisateur` VALUES (1,'Dupont','Jean','jean.dupont@example.com','mdp123','client'),(2,'Martin','Sophie','sophie.martin@example.com','mdp456','client'),(3,'Durand','Paul','paul.durand@example.com','mdp789','administrateur'),(4,'Bernard','Emma','emma.bernard@example.com','mdp321','client'),(5,'Petit','Luc','luc.petit@example.com','mdp654','client'),(6,'Moreau','Alice','alice.moreau@example.com','mdp987','administrateur');
/*!40000 ALTER TABLE `utilisateur` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voiture`
--

DROP TABLE IF EXISTS `voiture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voiture` (
  `id_voiture` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plaque` varchar(20) NOT NULL,
  `marque` varchar(50) DEFAULT NULL,
  `modele` varchar(50) DEFAULT NULL,
  `id_client_fk` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id_voiture`),
  KEY `id_client_fk` (`id_client_fk`),
  CONSTRAINT `voiture_ibfk_1` FOREIGN KEY (`id_client_fk`) REFERENCES `client` (`id_client`),
  CONSTRAINT `CONSTRAINT_1` CHECK (`plaque` regexp '^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$')
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voiture`
--

LOCK TABLES `voiture` WRITE;
/*!40000 ALTER TABLE `voiture` DISABLE KEYS */;
INSERT INTO `voiture` VALUES (1,'AB-123-CD','Peugeot','208',1),(2,'EF-456-GH','Renault','Clio',2),(3,'IJ-789-KL','Tesla','Model 3',3),(4,'MN-321-OP','Citroen','C3',4),(5,'QR-654-ST','Dacia','Sandero',5),(6,'UV-987-WX','Toyota','Yaris',1);
/*!40000 ALTER TABLE `voiture` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'parking_bd'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-22 23:18:50
