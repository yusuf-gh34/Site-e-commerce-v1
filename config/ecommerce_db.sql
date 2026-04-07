-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 07 avr. 2026 à 18:23
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
-- Base de données : `ecommerce_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `categorie`
--

CREATE TABLE `categorie` (
  `id_categorie` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categorie`
--

INSERT INTO `categorie` (`id_categorie`, `nom`) VALUES
(1, 'Électronique'),
(2, 'Vêtements et Accessoires'),
(3, 'Maison et Jardin'),
(4, 'Sports et Loisirs'),
(5, 'Beauté et Santé'),
(6, 'Livres'),
(7, 'Jouets et Jeux'),
(8, 'Alimentation');

-- --------------------------------------------------------

--
-- Structure de la table `commande`
--

CREATE TABLE `commande` (
  `id_commande` int(11) NOT NULL,
  `id_status` int(11) NOT NULL,
  `prix_totale` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date_commande` datetime DEFAULT current_timestamp(),
  `id_utilisateur` int(11) NOT NULL,
  `adresse_livraison` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `commande`
--

INSERT INTO `commande` (`id_commande`, `id_status`, `prix_totale`, `date_commande`, `id_utilisateur`, `adresse_livraison`) VALUES
(20, 5, 6000.00, '2026-04-07 15:40:21', 23, 'campus mghila'),
(21, 1, 598.00, '2026-04-07 16:07:26', 22, 'oued zem, Maroc');

-- --------------------------------------------------------

--
-- Structure de la table `contact`
--

CREATE TABLE `contact` (
  `id_contact` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `sujet` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `date_envoi` datetime DEFAULT current_timestamp(),
  `statut` enum('non lu','lu','répondu') DEFAULT 'non lu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `contact`
--

INSERT INTO `contact` (`id_contact`, `nom`, `email`, `telephone`, `sujet`, `message`, `date_envoi`, `statut`) VALUES
(18, 'Youssef Ghanem', 'ghanemyoussefut@gmail.com', '0626700251', 'Retour', 'pjipjijijop', '2026-04-07 12:42:25', 'lu'),
(19, 'Youssef Ghanem', 'ghanemyoussefut@gmail.com', '0626700251', 'Information produit', 'message de test', '2026-04-07 16:57:10', 'répondu');

-- --------------------------------------------------------

--
-- Structure de la table `ligne_commande`
--

CREATE TABLE `ligne_commande` (
  `id_ligne_commande` int(11) NOT NULL,
  `id_commande` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `ligne_commande`
--

INSERT INTO `ligne_commande` (`id_ligne_commande`, `id_commande`, `id_produit`, `quantite`, `prix_unitaire`) VALUES
(36, 20, 9, 1, 6000.00),
(41, 21, 11, 2, 299.00);

-- --------------------------------------------------------

--
-- Structure de la table `produit`
--

CREATE TABLE `produit` (
  `id_produit` int(11) NOT NULL,
  `nom` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `prix` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `id_categorie` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `produit`
--

INSERT INTO `produit` (`id_produit`, `nom`, `description`, `prix`, `stock`, `image`, `id_categorie`) VALUES
(8, 'Iphone 13', 'Iphone 13 normal 128GB', 3500.00, 9, '1775228025_69cfd47922551.webp', 1),
(9, 'Play Station 5 ', 'Console Ultra HD 8K - AMD Ryzen Zen 2 - AMD RDNA 2 10.28 TFLOPs - 16 Go GDDR6 - SSD 825 Go - son 3D - manette sans fil', 6000.00, 8, '1775468399_69d37f6fbfc87.jpg', 7),
(11, 'Adidas Chaussures Ultimashow 2.0 - IG4396', 'Sneaker légère conçue en partie avec des matériaux recyclés.', 299.00, 9, '1775574260_69d51cf4250da.jpg', 2);

-- --------------------------------------------------------

--
-- Structure de la table `reglement`
--

CREATE TABLE `reglement` (
  `id_reglement` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_paiement` datetime DEFAULT current_timestamp(),
  `Paye_a_livraison` tinyint(1) DEFAULT 0,
  `id_commande` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reglement`
--

INSERT INTO `reglement` (`id_reglement`, `montant`, `date_paiement`, `Paye_a_livraison`, `id_commande`) VALUES
(20, 6000.00, '2026-04-07 15:40:21', 1, 20),
(21, 598.00, '2026-04-07 16:07:26', 0, 21);

-- --------------------------------------------------------

--
-- Structure de la table `role`
--

CREATE TABLE `role` (
  `id_role` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `role`
--

INSERT INTO `role` (`id_role`, `nom`) VALUES
(1, 'admin'),
(2, 'gestionnaire'),
(3, 'client');

-- --------------------------------------------------------

--
-- Structure de la table `status`
--

CREATE TABLE `status` (
  `id_status` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `status`
--

INSERT INTO `status` (`id_status`, `nom`) VALUES
(1, 'En attente'),
(2, 'Confirmée'),
(3, 'En préparation'),
(4, 'Expédiée'),
(5, 'Livrée'),
(6, 'Annulée');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id_utilisateur` int(11) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `adresse` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_inscription` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id_utilisateur`, `telephone`, `email`, `nom`, `adresse`, `password`, `role_id`, `date_creation`, `date_inscription`) VALUES
(16, '0626700251', '', 'Youssef Ghanem', 'Casablanca,Maroc', '$2y$10$7YfkJ8J3RpW0vXEo4fVNyu6e2wOfxItTRSsF16/QgqYnAAPHTlfQe', 1, '2026-04-03 12:18:12', '2026-04-06 10:26:15'),
(20, '0612345678', NULL, 'amine ghanem', 'oulfa,Casablanca', '$2y$10$OqwWzhRHYatmcEs01YmYn.HQo8JtES7/j7hxBxCK5pL3/JBaE9pBK', 2, '2026-04-03 15:22:20', '2026-04-06 10:26:15'),
(21, '0687654321', NULL, 'adam ghanem', 'oulfa,Casablanca', '$2y$10$wDuBbTjXxw6gkpnK76YGyeDrVg4uHkvZLvlLMsRZSx6HYJ1rU/dG6', 2, '2026-04-03 15:41:26', '2026-04-06 10:26:15'),
(22, '0611111111', NULL, 'anas ilhami', 'campus mghila', '$2y$10$qdqJYCnxIVXWwOCqcdR3AOqwU0s3dck5QBWUiu4nCpwOplTfMdDwm', 3, '2026-04-03 17:14:54', '2026-04-06 10:26:15'),
(23, '0622222222', NULL, 'mohamed atfani', 'tadla,maroc', '$2y$10$RKoEYlnoh3.kfkTiO5/Mrez9MZHzJVZDWoZ2kSw51YSGIV0bnjDqe', 3, '2026-04-06 10:02:56', '2026-04-06 10:26:15');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `categorie`
--
ALTER TABLE `categorie`
  ADD PRIMARY KEY (`id_categorie`);

--
-- Index pour la table `commande`
--
ALTER TABLE `commande`
  ADD PRIMARY KEY (`id_commande`),
  ADD KEY `idx_commande_status` (`id_status`),
  ADD KEY `idx_commande_utilisateur` (`id_utilisateur`);

--
-- Index pour la table `contact`
--
ALTER TABLE `contact`
  ADD PRIMARY KEY (`id_contact`);

--
-- Index pour la table `ligne_commande`
--
ALTER TABLE `ligne_commande`
  ADD PRIMARY KEY (`id_ligne_commande`),
  ADD UNIQUE KEY `unique_ligne_commande` (`id_commande`,`id_produit`),
  ADD KEY `idx_ligne_commande_commande` (`id_commande`),
  ADD KEY `idx_ligne_commande_produit` (`id_produit`);

--
-- Index pour la table `produit`
--
ALTER TABLE `produit`
  ADD PRIMARY KEY (`id_produit`),
  ADD KEY `idx_produit_categorie` (`id_categorie`);

--
-- Index pour la table `reglement`
--
ALTER TABLE `reglement`
  ADD PRIMARY KEY (`id_reglement`),
  ADD UNIQUE KEY `id_commande` (`id_commande`);

--
-- Index pour la table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id_role`);

--
-- Index pour la table `status`
--
ALTER TABLE `status`
  ADD PRIMARY KEY (`id_status`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id_utilisateur`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_utilisateur_role` (`role_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `categorie`
--
ALTER TABLE `categorie`
  MODIFY `id_categorie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `commande`
--
ALTER TABLE `commande`
  MODIFY `id_commande` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `contact`
--
ALTER TABLE `contact`
  MODIFY `id_contact` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `ligne_commande`
--
ALTER TABLE `ligne_commande`
  MODIFY `id_ligne_commande` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT pour la table `produit`
--
ALTER TABLE `produit`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `reglement`
--
ALTER TABLE `reglement`
  MODIFY `id_reglement` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `role`
--
ALTER TABLE `role`
  MODIFY `id_role` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `status`
--
ALTER TABLE `status`
  MODIFY `id_status` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_utilisateur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `commande`
--
ALTER TABLE `commande`
  ADD CONSTRAINT `commande_ibfk_1` FOREIGN KEY (`id_status`) REFERENCES `status` (`id_status`),
  ADD CONSTRAINT `commande_ibfk_2` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`);

--
-- Contraintes pour la table `ligne_commande`
--
ALTER TABLE `ligne_commande`
  ADD CONSTRAINT `ligne_commande_ibfk_1` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`) ON DELETE CASCADE,
  ADD CONSTRAINT `ligne_commande_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produit` (`id_produit`);

--
-- Contraintes pour la table `produit`
--
ALTER TABLE `produit`
  ADD CONSTRAINT `produit_ibfk_1` FOREIGN KEY (`id_categorie`) REFERENCES `categorie` (`id_categorie`);

--
-- Contraintes pour la table `reglement`
--
ALTER TABLE `reglement`
  ADD CONSTRAINT `reglement_ibfk_1` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`);

--
-- Contraintes pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD CONSTRAINT `utilisateur_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`id_role`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
