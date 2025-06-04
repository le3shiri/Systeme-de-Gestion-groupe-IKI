-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 04, 2025 at 01:41 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `groupe_iki`
--

-- --------------------------------------------------------

--
-- Table structure for table `absences`
--

CREATE TABLE `absences` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('absent','present','late') DEFAULT 'absent',
  `recorded_by_teacher_id` int(11) DEFAULT NULL,
  `recorded_by_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `absences`
--

INSERT INTO `absences` (`id`, `student_id`, `module_id`, `date`, `status`, `recorded_by_teacher_id`, `recorded_by_admin_id`, `created_at`) VALUES
(1, 1, 2, '2025-06-04', 'absent', NULL, 2, '2025-06-03 23:40:17');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `cni` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `account_status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `nom`, `prenom`, `cni`, `password`, `account_status`, `created_at`) VALUES
(1, 'Admin', 'System', 'admin123', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-06-03 23:11:16'),
(2, 'Admin', 'System', 'AA123456', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-06-03 23:11:40');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `filiere_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `filieres`
--

CREATE TABLE `filieres` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `filieres`
--

INSERT INTO `filieres` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Développement Digital', 'Formation en développement web et mobile', '2025-06-03 23:11:16'),
(2, 'Réseaux et Systèmes', 'Administration des réseaux et systèmes informatiques', '2025-06-03 23:11:16'),
(3, 'Gestion des Entreprises', 'Techniques de gestion et management', '2025-06-03 23:11:16'),
(4, 'Comptabilité', 'Comptabilité et gestion financière', '2025-06-03 23:11:16'),
(5, 'Développement Digital', 'Formation en développement web et mobile', '2025-06-03 23:11:40'),
(6, 'Réseaux et Systèmes', 'Administration réseaux et systèmes informatiques', '2025-06-03 23:11:40'),
(7, 'Gestion Commerciale', 'Techniques de vente et gestion commerciale', '2025-06-03 23:11:40');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `grade_type` enum('cc1','cc2','cc3','theorique','pratique','pfe','stage') NOT NULL,
  `grade` decimal(4,2) NOT NULL CHECK (`grade` >= 0 and `grade` <= 20),
  `date` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `student_id`, `module_id`, `grade_type`, `grade`, `date`, `created_at`) VALUES
(1, 1, 2, 'cc1', 10.00, '2025-06-04', '2025-06-03 23:38:03'),
(2, 1, 2, 'cc2', 10.00, '2025-06-04', '2025-06-03 23:38:03'),
(3, 1, 2, 'cc3', 10.00, '2025-06-04', '2025-06-03 23:38:03'),
(4, 1, 2, 'theorique', 10.00, '2025-06-04', '2025-06-03 23:38:03'),
(5, 1, 2, 'pratique', 10.00, '2025-06-04', '2025-06-03 23:38:03');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_cni` varchar(50) NOT NULL,
  `target_cni` varchar(50) DEFAULT NULL,
  `target_classe_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `type` enum('message','announcement') DEFAULT 'message',
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `filiere_id` int(11) DEFAULT NULL,
  `type` enum('standard','pfe','stage') DEFAULT 'standard',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `name`, `filiere_id`, `type`, `created_at`) VALUES
(1, 'Programmation Web', 1, 'standard', '2025-06-03 23:11:16'),
(2, 'Base de Données', 1, 'standard', '2025-06-03 23:11:16'),
(3, 'Développement Mobile', 1, 'standard', '2025-06-03 23:11:16'),
(4, 'Projet de Fin d\'Études (PFE)', 1, 'pfe', '2025-06-03 23:11:16'),
(5, 'Stage Professionnel', 1, 'stage', '2025-06-03 23:11:16'),
(6, 'Administration Réseaux', 2, 'standard', '2025-06-03 23:11:16'),
(7, 'Sécurité Informatique', 2, 'standard', '2025-06-03 23:11:16'),
(8, 'Systèmes d\'Exploitation', 2, 'standard', '2025-06-03 23:11:16'),
(9, 'Projet de Fin d\'Études (PFE)', 2, 'pfe', '2025-06-03 23:11:16'),
(10, 'Stage Professionnel', 2, 'stage', '2025-06-03 23:11:16'),
(11, 'Management', 3, 'standard', '2025-06-03 23:11:16'),
(12, 'Marketing', 3, 'standard', '2025-06-03 23:11:16'),
(13, 'Ressources Humaines', 3, 'standard', '2025-06-03 23:11:16'),
(14, 'Projet de Fin d\'Études (PFE)', 3, 'pfe', '2025-06-03 23:11:16'),
(15, 'Stage Professionnel', 3, 'stage', '2025-06-03 23:11:16'),
(16, 'Comptabilité Générale', 4, 'standard', '2025-06-03 23:11:16'),
(17, 'Comptabilité Analytique', 4, 'standard', '2025-06-03 23:11:16'),
(18, 'Fiscalité', 4, 'standard', '2025-06-03 23:11:16'),
(19, 'Projet de Fin d\'Études (PFE)', 4, 'pfe', '2025-06-03 23:11:16'),
(20, 'Stage Professionnel', 4, 'stage', '2025-06-03 23:11:16'),
(21, 'Programmation Web', 1, 'standard', '2025-06-03 23:11:40'),
(22, 'Base de Données', 1, 'standard', '2025-06-03 23:11:40'),
(23, 'Développement Mobile', 1, 'standard', '2025-06-03 23:11:40'),
(24, 'Projet de Fin d\'Études', 1, 'pfe', '2025-06-03 23:11:40'),
(25, 'Stage Professionnel', 1, 'stage', '2025-06-03 23:11:40'),
(26, 'Administration Linux', 2, 'standard', '2025-06-03 23:11:40'),
(27, 'Sécurité Réseaux', 2, 'standard', '2025-06-03 23:11:40'),
(28, 'Virtualisation', 2, 'standard', '2025-06-03 23:11:40'),
(29, 'Projet de Fin d\'Études', 2, 'pfe', '2025-06-03 23:11:40'),
(30, 'Stage Professionnel', 2, 'stage', '2025-06-03 23:11:40'),
(31, 'Techniques de Vente', 3, 'standard', '2025-06-03 23:11:40'),
(32, 'Marketing Digital', 3, 'standard', '2025-06-03 23:11:40'),
(33, 'Gestion de Stock', 3, 'standard', '2025-06-03 23:11:40'),
(34, 'Projet de Fin d\'Études', 3, 'pfe', '2025-06-03 23:11:40'),
(35, 'Stage Professionnel', 3, 'stage', '2025-06-03 23:11:40'),
(36, 'Projet de Fin d\'Études (PFE)', 5, 'pfe', '2025-06-03 23:12:43'),
(37, 'Stage Professionnel', 5, 'stage', '2025-06-03 23:12:43'),
(38, 'Projet de Fin d\'Études (PFE)', 6, 'pfe', '2025-06-03 23:12:43'),
(39, 'Stage Professionnel', 6, 'stage', '2025-06-03 23:12:43'),
(40, 'Projet de Fin d\'Études (PFE)', 7, 'pfe', '2025-06-03 23:12:43'),
(41, 'Stage Professionnel', 7, 'stage', '2025-06-03 23:12:43');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `cni` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `lieu_naissance` varchar(100) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `date_inscription` date DEFAULT curdate(),
  `niveau` enum('technicien','technicien_specialise','qualifiant') DEFAULT 'technicien',
  `num_telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `filiere_id` int(11) DEFAULT NULL,
  `account_status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `nom`, `prenom`, `cni`, `password`, `date_naissance`, `lieu_naissance`, `adresse`, `date_inscription`, `niveau`, `num_telephone`, `email`, `filiere_id`, `account_status`, `created_at`) VALUES
(1, 'Idrissi', 'Youssef', 'S123456', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, '2023-09-01', 'technicien_specialise', NULL, 'youssef.idrissi@student.groupeiki.ma', 1, 'active', '2025-06-03 23:11:40'),
(2, 'Tazi', 'Aicha', 'S234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, '2023-09-01', 'technicien_specialise', NULL, 'aicha.tazi@student.groupeiki.ma', 1, 'active', '2025-06-03 23:11:40'),
(3, 'Mansouri', 'Omar', 'S345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, '2023-09-01', 'technicien', NULL, 'omar.mansouri@student.groupeiki.ma', 2, 'active', '2025-06-03 23:11:40'),
(4, 'Fassi', 'Salma', 'S456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, '2023-09-01', 'qualifiant', NULL, 'salma.fassi@student.groupeiki.ma', 3, 'active', '2025-06-03 23:11:40');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `cni` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `adresse` text DEFAULT NULL,
  `type_contrat` varchar(50) DEFAULT NULL,
  `date_embauche` date DEFAULT curdate(),
  `dernier_diplome` varchar(100) DEFAULT NULL,
  `num_telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `account_status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `nom`, `prenom`, `cni`, `password`, `adresse`, `type_contrat`, `date_embauche`, `dernier_diplome`, `num_telephone`, `email`, `account_status`, `created_at`) VALUES
(1, 'Alami', 'Mohammed', 'T123456', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2023-01-15', NULL, NULL, 'mohammed.alami@groupeiki.ma', 'active', '2025-06-03 23:11:40'),
(2, 'Benali', 'Fatima', 'T234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2023-02-01', NULL, NULL, 'fatima.benali@groupeiki.ma', 'active', '2025-06-03 23:11:40'),
(3, 'Chakir', 'Ahmed', 'T345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2023-03-10', NULL, NULL, 'ahmed.chakir@groupeiki.ma', 'active', '2025-06-03 23:11:40');

-- --------------------------------------------------------

--
-- Stand-in structure for view `teacher_assignments_view`
-- (See below for the actual view)
--
CREATE TABLE `teacher_assignments_view` (
`id` int(11)
,`teacher_id` int(11)
,`teacher_nom` varchar(50)
,`teacher_prenom` varchar(50)
,`teacher_cni` varchar(50)
,`teacher_email` varchar(100)
,`module_id` int(11)
,`module_name` varchar(100)
,`module_type` enum('standard','pfe','stage')
,`filiere_id` int(11)
,`filiere_name` varchar(100)
,`assigned_date` date
,`notes` text
,`is_active` tinyint(1)
);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_module_assignments`
--

CREATE TABLE `teacher_module_assignments` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `teacher_cni` varchar(50) NOT NULL,
  `module_id` int(11) NOT NULL,
  `filiere_id` int(11) NOT NULL,
  `assigned_date` date DEFAULT curdate(),
  `assigned_by_admin_cni` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_module_assignments`
--

INSERT INTO `teacher_module_assignments` (`id`, `teacher_id`, `teacher_cni`, `module_id`, `filiere_id`, `assigned_date`, `assigned_by_admin_cni`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'T123456', 1, 1, '2025-06-04', 'AA123456', 1, NULL, '2025-06-03 23:11:40', '2025-06-03 23:11:40'),
(2, 1, 'T123456', 2, 1, '2025-06-04', 'AA123456', 1, NULL, '2025-06-03 23:11:40', '2025-06-03 23:11:40'),
(3, 2, 'T234567', 6, 2, '2025-06-04', 'AA123456', 1, NULL, '2025-06-03 23:11:40', '2025-06-03 23:11:40'),
(4, 2, 'T234567', 7, 2, '2025-06-04', 'AA123456', 1, NULL, '2025-06-03 23:11:40', '2025-06-03 23:11:40'),
(5, 3, 'T345678', 11, 3, '2025-06-04', 'AA123456', 1, NULL, '2025-06-03 23:11:40', '2025-06-03 23:11:40'),
(6, 3, 'T345678', 12, 3, '2025-06-04', 'AA123456', 1, NULL, '2025-06-03 23:11:40', '2025-06-03 23:11:40');

-- --------------------------------------------------------

--
-- Structure for view `teacher_assignments_view`
--
DROP TABLE IF EXISTS `teacher_assignments_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `teacher_assignments_view`  AS SELECT `tma`.`id` AS `id`, `tma`.`teacher_id` AS `teacher_id`, `t`.`nom` AS `teacher_nom`, `t`.`prenom` AS `teacher_prenom`, `t`.`cni` AS `teacher_cni`, `t`.`email` AS `teacher_email`, `tma`.`module_id` AS `module_id`, `m`.`name` AS `module_name`, `m`.`type` AS `module_type`, `tma`.`filiere_id` AS `filiere_id`, `f`.`name` AS `filiere_name`, `tma`.`assigned_date` AS `assigned_date`, `tma`.`notes` AS `notes`, `tma`.`is_active` AS `is_active` FROM (((`teacher_module_assignments` `tma` join `teachers` `t` on(`tma`.`teacher_id` = `t`.`id`)) join `modules` `m` on(`tma`.`module_id` = `m`.`id`)) join `filieres` `f` on(`tma`.`filiere_id` = `f`.`id`)) WHERE `tma`.`is_active` = 1 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absences`
--
ALTER TABLE `absences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_module_date` (`student_id`,`module_id`,`date`),
  ADD KEY `module_id` (`module_id`),
  ADD KEY `recorded_by_teacher_id` (`recorded_by_teacher_id`),
  ADD KEY `recorded_by_admin_id` (`recorded_by_admin_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cni` (`cni`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_filiere_id` (`filiere_id`);

--
-- Indexes for table `filieres`
--
ALTER TABLE `filieres`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_module_type` (`student_id`,`module_id`,`grade_type`),
  ADD KEY `module_id` (`module_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `target_classe_id` (`target_classe_id`),
  ADD KEY `module_id` (`module_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `filiere_id` (`filiere_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cni` (`cni`),
  ADD KEY `filiere_id` (`filiere_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cni` (`cni`);

--
-- Indexes for table `teacher_module_assignments`
--
ALTER TABLE `teacher_module_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_module_filiere` (`teacher_id`,`module_id`,`filiere_id`),
  ADD KEY `filiere_id` (`filiere_id`),
  ADD KEY `idx_teacher_cni` (`teacher_cni`),
  ADD KEY `idx_module_filiere` (`module_id`,`filiere_id`),
  ADD KEY `idx_active_assignments` (`is_active`,`teacher_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absences`
--
ALTER TABLE `absences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `filieres`
--
ALTER TABLE `filieres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teacher_module_assignments`
--
ALTER TABLE `teacher_module_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absences`
--
ALTER TABLE `absences`
  ADD CONSTRAINT `absences_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absences_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absences_ibfk_3` FOREIGN KEY (`recorded_by_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `absences_ibfk_4` FOREIGN KEY (`recorded_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`target_classe_id`) REFERENCES `filieres` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `teacher_module_assignments`
--
ALTER TABLE `teacher_module_assignments`
  ADD CONSTRAINT `teacher_module_assignments_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_module_assignments_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_module_assignments_ibfk_3` FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
