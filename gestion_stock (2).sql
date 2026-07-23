-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 06 sep. 2025 à 18:06
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_stock`
--

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;                                                                             

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `nom`, `description`) VALUES
(1, 'Informatique', 'Ordinateurs, périphériques et composants informatiques'),
(2, 'Bureautique', 'Fournitures et équipements de bureau'),
(3, 'Téléphonie', 'Smartphones, tablettes et accessoires'),
(4, 'Réseau', 'Équipements réseau et connectivité'),
(5, 'Consommables', 'Cartouches d\'encre, papier et fournitures'),
(6, 'Santé', 'Équipements et scanners médicaux');

-- --------------------------------------------------------

--
-- Structure de la table `emplacements`
--

CREATE TABLE `emplacements` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `emplacements`
--

INSERT INTO `emplacements` (`id`, `site_id`, `nom`) VALUES
(1, 1, 'BLOC A - Douala'),
(2, 2, 'BLOC A - Yaoundé'),
(3, 3, 'BLOC A - Bafoussam');

-- --------------------------------------------------------

--
-- Structure de la table `emplacement_details`
--

CREATE TABLE `emplacement_details` (
  `id` int(11) NOT NULL,
  `emplacement_id` int(11) NOT NULL,
  `colonne` varchar(20) NOT NULL,
  `ligne` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `emplacement_details`
--

INSERT INTO `emplacement_details` (`id`, `emplacement_id`, `colonne`, `ligne`) VALUES
(1, 1, '2', '2'),
(2, 2, '3', '2');

-- --------------------------------------------------------

--
-- Structure de la table `factures`
--

CREATE TABLE `factures` (
  `id` int(11) NOT NULL,
  `numero_facture` varchar(20) NOT NULL,
  `date_facture` datetime DEFAULT current_timestamp(),
  `client_nom` varchar(100) NOT NULL,
  `client_contact` varchar(50) DEFAULT NULL,
  `montant_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remise` decimal(10,2) DEFAULT 0.00,
  `montant_final` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mode_paiement` enum('cash','card','transfer','check') NOT NULL,
  `notes` text DEFAULT NULL,
  `utilisateur_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `factures`
--

INSERT INTO `factures` (`id`, `numero_facture`, `date_facture`, `client_nom`, `client_contact`, `montant_total`, `remise`, `montant_final`, `mode_paiement`, `notes`, `utilisateur_id`) VALUES
(4, '', '2025-07-10 06:52:29', 'kayo joel', '678903456', 5000.00, 0.00, 5000.00, 'cash', '', 8),
(6, 'FAC000001', '2024-01-15 10:30:00', 'Client A', 'contact@client-a.com', 1500.00, 100.00, 1400.00, 'transfer', 'Facture pour projet web', 1),
(7, 'FAC000002', '2025-07-10 06:55:54', 'kayo joel', '678903456', 5000.00, 0.00, 5000.00, 'cash', '', 8),
(8, 'FAC000003', '2025-07-10 11:28:10', 'kayo joel', '678904567', 5000.00, 0.00, 5000.00, 'cash', 'chargeur d\'origine noir rehure blanche', 8),
(9, 'FAC000004', '2025-07-18 09:30:09', 'kayo joel', '6890345676', 9500.00, 0.00, 9500.00, 'cash', 'montre connecter 3.4 T800 ', 8),
(10, 'FAC000005', '2025-07-19 12:47:39', 'Mr Pablo Escobar ', '+33 456-456-346', 10000.00, 500.00, 9500.00, 'card', '', 8),
(11, 'FAC000006', '2025-07-19 12:47:59', 'Mr Pablo Escobar ', '+33 456-456-346', 2500.00, 500.00, 2000.00, 'card', '', 8),
(12, 'FAC000007', '2025-07-19 12:53:40', 'Theo ', '6894560', 9500.00, 0.00, 9500.00, 'card', '', 8),
(13, 'FAC000008', '2025-07-19 12:55:41', 'mike', '6890345', 3000.00, 0.00, 3000.00, 'card', '', 8);

--
-- Déclencheurs `factures`
--
DELIMITER $$
CREATE TRIGGER `before_facture_insert` BEFORE INSERT ON `factures` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    -- Vérifier d'abord si le numéro est déjà fourni
    IF NEW.numero_facture IS NULL OR NEW.numero_facture = '' THEN
        -- Trouver le maximum numérique existant + 1
        SELECT IFNULL(MAX(CAST(SUBSTRING(numero_facture, 4) AS UNSIGNED)), 0) + 1 
        INTO next_num 
        FROM factures;
        SET NEW.numero_facture = CONCAT('FAC', LPAD(next_num, 6, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `facture_items`
--

CREATE TABLE `facture_items` (
  `id` int(11) NOT NULL,
  `facture_id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `facture_items`
--

INSERT INTO `facture_items` (`id`, `facture_id`, `produit_id`, `quantite`, `prix_unitaire`) VALUES
(1, 4, 5, 1, 5000.00),
(2, 7, 5, 1, 5000.00),
(3, 8, 5, 1, 5000.00),
(4, 9, 7, 1, 9500.00),
(5, 10, 5, 1, 5000.00),
(6, 10, 8, 1, 5000.00),
(7, 11, 11, 1, 2500.00),
(8, 12, 7, 1, 9500.00),
(9, 13, 6, 1, 3000.00);

-- --------------------------------------------------------

--
-- Structure de la table `fournisseurs`
--

CREATE TABLE `fournisseurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `domaine_activite` varchar(100) NOT NULL DEFAULT 'Autre'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--- Déchargement des données de la table `fournisseurs`
--

INSERT INTO `fournisseurs` (`id`, `nom`, `contact`, `telephone`, `email`, `adresse`, `created_at`, `domaine_activite`) VALUES
(1, 'TECH DISTRIBUTION', 'M. Diallo', '771234567', 'contact@techdistrib.com', '123 Avenue Liberté, Dakar', '2025-07-08 20:53:55', 'Autre'),
(2, 'BUREAU PRO', 'Mme Ndiaye', '761234567', 'ventes@bureaupro.sn', '456 Rue du Commerce, Dakar', '2025-07-08 20:53:55', 'Autre'),
(6, 'we', NULL, '657678234', 'we@gmail.com', 'yaounde', '2025-07-09 10:35:58', 'Autre'),
(7, 'kamdem', NULL, '678903456', 'kamdem@gmail.com', 'baffousam carrefour monsieur le maire', '2025-07-10 21:13:46', 'Autre'),
(8, 'kayo joel', NULL, '677678909', 'kayoJoel60@gmail.com', 'bafoussam', '2025-07-20 15:22:52', 'Electronique');

-- --------------------------------------------------------

--
-- Structure de la table `historique_connexions`
--

CREATE TABLE `historique_connexions` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,                          
  `date_connexion` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inventaire_produit`
--

CREATE TABLE `inventaire_produit` (
  `id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `emplacement_detail_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `inventaire_produit`
--

INSERT INTO `inventaire_produit` (`id`, `produit_id`, `emplacement_detail_id`, `quantite`) VALUES
(1, 10, 1, 1),
(2, 5, 2, 0);

-- --------------------------------------------------------

--
-- Structure de la table `mouvements`
--

CREATE TABLE `mouvements` (
  `id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `type` enum('entree','sortie') NOT NULL,
  `quantite` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `motif` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `mouvements`
--

INSERT INTO `mouvements` (`id`, `produit_id`, `type`, `quantite`, `utilisateur_id`, `notes`, `created_at`, `motif`) VALUES
(1, 1, 'entree', 5, 1, 'Stock initial', '2025-07-08 20:53:55', NULL),
(2, 2, 'entree', 3, 1, 'Stock initial', '2025-07-08 20:53:55', NULL),
(4, 5, 'entree', 3, 8, NULL, '2025-07-18 16:49:20', 'du magasin vers la boutique du marcher A'),
(5, 6, 'sortie', 3, 8, NULL, '2025-07-19 11:14:16', 'de la boutiques vers le client Leo'),
(6, 13, 'entree', 6, 8, NULL, '2025-07-20 15:47:13', 'Stock initial');

-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

CREATE TABLE `paiements` (
  `id` int(11) NOT NULL,
  `facture_id` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_paiement` datetime DEFAULT current_timestamp(),
  `mode_paiement` enum('cash','card','transfer','check') NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

CREATE TABLE `produits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `categorie_id` int(11) DEFAULT NULL,
  `fournisseur_id` int(11) DEFAULT NULL,
  `prix_achat` decimal(10,2) DEFAULT NULL,
  `prix_vente` decimal(10,2) DEFAULT NULL,
  `seuil_alerte` int(11) DEFAULT 5,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id`, `reference`, `nom`, `description`, `categorie_id`, `fournisseur_id`, `prix_achat`, `prix_vente`, `seuil_alerte`, `image_path`, `created_at`) VALUES
(1, 'ORD-001', 'Ordinateur Portable HP', 'HP EliteBook 840 G6, 14\", i5, 8GB, 256GB SSD', 1, 1, 450000.00, 550000.00, 3, NULL, '2025-07-08 20:53:55'),
(2, 'ORD-002', 'PC Bureau Dell', 'Dell OptiPlex 3080, i3, 4GB, 1TB HDD', 1, 1, 350000.00, 420000.00, 2, NULL, '2025-07-08 20:53:55'),
(3, 'kit-1003', 'kit bleuthoot', 'kit bleuthoot 3.0 version water pouf resistant a l\'eau ', 1, NULL, 3000.00, 5000.00, 5, NULL, '2025-07-09 02:41:26'),
(4, 'ORD-03', 'souris Dell', 'souris dell 3.4 lumineux', 1, NULL, 4000.00, 6000.00, 5, NULL, '2025-07-09 11:09:29'),
(5, 'ORD-004', 'chageur hp', 'chageur hp bout roond 3.0\r\n', 5, NULL, 2500.00, 5000.00, 3, NULL, '2025-07-10 10:13:17'),
(6, 'ORD-005', 'pochette iphone', 'pochet iphone 12x3,13x3,23x2', 3, NULL, 1600.00, 3000.00, 2, NULL, '2025-07-10 20:54:03'),
(7, 'ORD-006', 'montres intelilligentes', 'appel watch X4 ,smat watchx3', 1, NULL, 4500.00, 9500.00, 2, NULL, '2025-07-10 20:58:05'),
(8, 'ORD-007', 'la carte wifi', 'carte wifie d\'origine water puf ???? ', 4, NULL, 2000.00, 5000.00, 2, 'uploads/products/6870b6ef7f3eb.png', '2025-07-11 07:02:07'),
(9, 'ORD-008', 'uniter centrale lenevo', 'core i3,3 eme generation,2,7GHz,8G de RAM,500G HDD,Nvidia 2Gdd\r\n', 1, NULL, 35000.00, 50000.00, 3, NULL, '2025-07-18 19:49:38'),
(10, 'ORD-009', 'ecrant incurvee', '', 1, NULL, 40000.00, 55000.00, 1, NULL, '2025-07-19 11:47:27'),
(11, 'ORD-010', 'cable DVI', 'cables DVI couleur bleux foncer', 1, NULL, 1500.00, 2500.00, 2, NULL, '2025-07-19 13:17:46'),
(12, 'ORD-011', 'imprimente blud reed', 'couleur noirX3 couleur rougesX4', 2, NULL, 150000.00, 200000.00, 1, NULL, '2025-07-20 14:33:23'),
(13, 'ORD-012', 'cate graphique', 'couleur noir et rouges', 4, 8, 15000.00, 20000.00, 2, NULL, '2025-07-20 15:47:13');

-- --------------------------------------------------------

--
-- Structure de la table `sites`
--

CREATE TABLE `sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `adresse` text NOT NULL,
  `ville` varchar(50) NOT NULL,
  `pays` varchar(50) NOT NULL,
  `lat` decimal(10,8) NOT NULL,
  `lng` decimal(11,8) NOT NULL,
  `responsable` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `date_creation` datetime NOT NULL,
  `date_modification` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sites`
--

INSERT INTO `sites` (`id`, `nom`, `adresse`, `ville`, `pays`, `lat`, `lng`, `responsable`, `telephone`, `date_creation`, `date_modification`) VALUES
(1, 'Entrepot A', 'Carrefour Mr le mair', 'Baffoussam', 'Cameroun', 99.99999999, 123.00000000, 'Mr kayo', '6778998', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'kayo joel', ' carrefour monsieur le mair', 'Baffousam', 'Cameroon ', 0.00000000, 0.00000000, 'Mr roger', '6789065642', '2025-07-20 01:27:06', NULL),
(10, 'kayo joel', 'carrefour monsieur le mair', 'Baffousam', 'Cameroun', 5.48628928, 10.40907976, 'Mr roger', '6789065642', '2025-07-20 06:48:47', NULL),
(11, 'Entrepot D', 'Ngambou', 'bafoussam', 'Cameroun', 5.65916871, 10.51861540, 'Mr anges', '+237682260418', '2025-07-21 12:22:55', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `site_inventaire`
--

CREATE TABLE `site_inventaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stock`
--

CREATE TABLE `stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produit_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `stock`
--

INSERT INTO `stock` (`id`, `produit_id`, `quantite`, `updated_at`) VALUES
(1, 1, 5, '2025-07-08 20:53:55'),
(2, 2, 3, '2025-07-08 20:53:55'),
(3, 3, 0, '2025-07-09 02:41:26'),
(4, 4, 0, '2025-07-09 11:09:29'),
(5, 5, 2, '2025-07-19 16:47:40'),
(6, 6, 4, '2025-07-19 16:55:41'),
(7, 7, 5, '2025-07-19 16:53:40'),
(8, 8, 5, '2025-07-19 16:47:40'),
(9, 9, 10, '2025-07-18 19:49:38'),
(10, 10, 4, '2025-07-19 11:47:27'),
(11, 11, 6, '2025-07-19 16:47:59'),
(12, 12, 7, '2025-07-20 14:33:23'),
(13, 13, 6, '2025-07-20 15:47:13');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `fournisseur_id` int(11) DEFAULT NULL,
  `site_id` int(11) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','superviseur','caissier','gestionnaire','utilisateur','fournisseur') DEFAULT 'utilisateur',
  `theme_pref` enum('light','dark') DEFAULT 'light',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `derniere_connexion` datetime DEFAULT NULL,
  `nombre_connexions` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `email`, `password`, `role`, `theme_pref`, `created_at`, `last_login`, `derniere_connexion`, `nombre_connexions`) VALUES
(1, 'Admin System', 'admin@stock.com', 'adam', 'admin', 'dark', '2025-07-08 20:53:55', NULL, NULL, 0),
(4, '1', '1@gmail.com', '$2y$10$HnI2C6cn31fiTFXn5AGSoeyaG50yi3ROUz8uqdVV441QqRubgJqJG', '', 'light', '2025-07-09 02:32:08', NULL, NULL, 0),
(5, 'kayo joel', 'Lemonstre@gmail.com', '$2y$10$AsvBjd3ULfHVnRUiFh7kA.SnSRl6Gt.HgJsEYc5u9yijvdWwlM6IO', '', 'light', '2025-07-10 02:51:40', NULL, NULL, 0),
(6, 'Admin', 'admin', 'motdepasse', 'admin', 'light', '2025-07-10 03:12:49', NULL, NULL, 0),
(8, 'mike', 'mike@gmail.com', '$2y$10$EG7LDrhnfZPAAWb6JLs8AuwVZaUlkjxUYht8cbXqjyXqtyron5xFe', 'admin', 'light', '2025-07-10 03:32:04', NULL, NULL, 0),
(9, 'morex', 'morex@gmail.com', '$2y$10$0PxJinw7HixzVYMPp8sLOuyUdp996pJkOiBpUiCnUBAOvBhSX2Uoe', '', 'light', '2025-07-10 21:52:59', NULL, NULL, 0),
(10, 'Clarissa', 'Clarissa@gmail.com', '$2y$10$S6wVlWNDmgDY9CD/nNuoFOWym2KvHHNcRLYkF.bj1iDTY.3lg4eqO', '', 'light', '2025-07-19 16:59:04', NULL, NULL, 0);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `emplacements`
--
ALTER TABLE `emplacements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `site_id` (`site_id`);

--
-- Index pour la table `emplacement_details`
--
ALTER TABLE `emplacement_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `emplacement_id` (`emplacement_id`);

--
-- Index pour la table `factures`
--
ALTER TABLE `factures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_facture` (`numero_facture`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `facture_items`
--
ALTER TABLE `facture_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `facture_id` (`facture_id`),
  ADD KEY `produit_id` (`produit_id`);

--
-- Index pour la table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `historique_connexions`
--
ALTER TABLE `historique_connexions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `inventaire_produit`
--
ALTER TABLE `inventaire_produit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produit_id` (`produit_id`),
  ADD KEY `emplacement_detail_id` (`emplacement_detail_id`);

--
-- Index pour la table `mouvements`
--
ALTER TABLE `mouvements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produit_id` (`produit_id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `facture_id` (`facture_id`);

--
-- Index pour la table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `categorie_id` (`categorie_id`),
  ADD KEY `fournisseur_id` (`fournisseur_id`);

--
-- Index pour la table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `site_inventaire`
--
ALTER TABLE `site_inventaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `produit_id` (`produit_id`);

--
-- Index pour la table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produit_id` (`produit_id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `emplacements`
--
ALTER TABLE `emplacements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `emplacement_details`
--
ALTER TABLE `emplacement_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `factures`
--
ALTER TABLE `factures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `facture_items`
--
ALTER TABLE `facture_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `historique_connexions`
--
ALTER TABLE `historique_connexions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `inventaire_produit`
--
ALTER TABLE `inventaire_produit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `mouvements`
--
ALTER TABLE `mouvements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `produits`
--
ALTER TABLE `produits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `site_inventaire`
--
ALTER TABLE `site_inventaire`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `emplacements`
--
ALTER TABLE `emplacements`
  ADD CONSTRAINT `emplacements_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`);

--
-- Contraintes pour la table `emplacement_details`
--
ALTER TABLE `emplacement_details`
  ADD CONSTRAINT `emplacement_details_ibfk_1` FOREIGN KEY (`emplacement_id`) REFERENCES `emplacements` (`id`);

--
-- Contraintes pour la table `factures`
--
ALTER TABLE `factures`
  ADD CONSTRAINT `factures_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `facture_items`
--
ALTER TABLE `facture_items`
  ADD CONSTRAINT `facture_items_ibfk_1` FOREIGN KEY (`facture_id`) REFERENCES `factures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `facture_items_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`);

--
-- Contraintes pour la table `historique_connexions`
--
ALTER TABLE `historique_connexions`
  ADD CONSTRAINT `historique_connexions_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `inventaire_produit`
--
ALTER TABLE `inventaire_produit`
  ADD CONSTRAINT `inventaire_produit_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`),
  ADD CONSTRAINT `inventaire_produit_ibfk_2` FOREIGN KEY (`emplacement_detail_id`) REFERENCES `emplacement_details` (`id`);

--
-- Contraintes pour la table `mouvements`
--
ALTER TABLE `mouvements`
  ADD CONSTRAINT `mouvements_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`),
  ADD CONSTRAINT `mouvements_ibfk_2` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`facture_id`) REFERENCES `factures` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `produits`
--
ALTER TABLE `produits`
  ADD CONSTRAINT `produits_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `produits_ibfk_2` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`);

--
-- Contraintes pour la table `site_inventaire`
--
ALTER TABLE `site_inventaire`
  ADD CONSTRAINT `site_inventaire_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `site_inventaire_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`);

--
-- Contraintes pour la table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`);


-- --------------------------------------------------------
-- Villes du Cameroun (référence)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `villes_cameroun` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(80) NOT NULL,
  `region` varchar(80) DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `villes_cameroun` (`nom`, `region`, `lat`, `lng`) VALUES
('Douala', 'Littoral', 4.05110000, 9.76790000),
('Yaoundé', 'Centre', 3.84800000, 11.50210000),
('Bafoussam', 'Ouest', 5.47780000, 10.41760000),
('Garoua', 'Nord', 9.30150000, 13.39270000),
('Bamenda', 'Nord-Ouest', 5.95970000, 10.14600000),
('Maroua', 'Extrême-Nord', 10.59100000, 14.31580000),
('Ngaoundéré', 'Adamaoua', 7.31670000, 13.58330000),
('Kribi', 'Sud', 2.93700000, 9.90770000),
('Limbé', 'Sud-Ouest', 4.02250000, 9.20800000),
('Bertoua', 'Est', 4.57730000, 13.68460000),
('Ebolowa', 'Sud', 2.90000000, 11.15000000),
('Buea', 'Sud-Ouest', 4.15500000, 9.24100000),
('Edéa', 'Littoral', 3.80000000, 10.13330000),
('Kumba', 'Sud-Ouest', 4.63630000, 9.44650000),
('Dschang', 'Ouest', 5.45000000, 10.06670000),
('Foumban', 'Ouest', 5.72930000, 10.90000000),
('Nkongsamba', 'Littoral', 4.95470000, 9.94040000),
('Sangmélima', 'Sud', 2.93330000, 11.98330000),
('Loum', 'Littoral', 4.71830000, 9.73510000),
('Mbalmayo', 'Centre', 3.51670000, 11.50000000)
ON DUPLICATE KEY UPDATE region=VALUES(region), lat=VALUES(lat), lng=VALUES(lng);

-- --------------------------------------------------------
-- Portail fournisseur B2B : demandes + rappels paiement
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fournisseur_cargaisons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fournisseur_id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `montant_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `montant_paye` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date_cargaison` date NOT NULL,
  `statut` enum('ouverte','partielle','soldee','retard') NOT NULL DEFAULT 'ouverte',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fournisseur` (`fournisseur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fournisseur_reglements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cargaison_id` int(11) NOT NULL,
  `fournisseur_id` int(11) NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `type_reglement` enum('paiement','avance','avoir') NOT NULL DEFAULT 'paiement',
  `date_reglement` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cargaison` (`cargaison_id`),
  KEY `idx_fournisseur` (`fournisseur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fournisseur_demandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fournisseur_id` int(11) NOT NULL,
  `titre` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `produit_id` int(11) DEFAULT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  `prix_unitaire` decimal(12,2) DEFAULT NULL,
  `statut` enum('brouillon','soumise','acceptee','refusee','livree') NOT NULL DEFAULT 'brouillon',
  `notes_admin` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `soumise_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fourn_dem` (`fournisseur_id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fournisseur_rappels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fournisseur_id` int(11) NOT NULL,
  `cargaison_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `canal` enum('interne','email','whatsapp') NOT NULL DEFAULT 'interne',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_fourn_rap` (`fournisseur_id`),
  KEY `idx_cargo_rap` (`cargaison_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fournisseur_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fournisseur_id` int(11) NOT NULL,
  `titre` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fourn_photo` (`fournisseur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fournisseur_demandes_paiement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fournisseur_id` int(11) NOT NULL,
  `cargaison_id` int(11) DEFAULT NULL,
  `montant` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tranche` varchar(80) NOT NULL DEFAULT 'Prochaine tranche',
  `message` text DEFAULT NULL,
  `statut` enum('soumise','en_cours','payee','refusee') NOT NULL DEFAULT 'soumise',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `traite_par` int(11) DEFAULT NULL,
  `traite_at` datetime DEFAULT NULL,
  `notes_traitement` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fourn_dp` (`fournisseur_id`),
  KEY `idx_statut_dp` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Colonnes paiement (si absentes à l'import manuel)
-- ALTER TABLE factures ADD COLUMN paiement_recu TINYINT(1) NOT NULL DEFAULT 0;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
