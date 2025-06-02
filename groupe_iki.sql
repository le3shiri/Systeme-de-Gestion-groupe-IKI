-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 03, 2025 at 01:12 AM
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
  `student_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` enum('present','absent') NOT NULL,
  `recorded_by_teacher_id` int(11) DEFAULT NULL,
  `recorded_by_admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `absences`
--

INSERT INTO `absences` (`id`, `student_id`, `module_id`, `date`, `status`, `recorded_by_teacher_id`, `recorded_by_admin_id`) VALUES
(1, 1, 1, '2023-11-01', 'present', 1, NULL),
(2, 1, 1, '2023-11-08', 'present', 1, NULL),
(3, 1, 1, '2023-11-15', 'absent', 1, NULL),
(4, 1, 2, '2023-11-02', 'present', 1, NULL),
(5, 1, 2, '2023-11-09', 'present', 1, NULL),
(6, 1, 2, '2023-11-16', 'present', 1, NULL),
(7, 2, 1, '2023-11-01', 'present', 1, NULL),
(8, 2, 1, '2023-11-08', 'present', 1, NULL),
(9, 2, 1, '2023-11-15', 'present', 1, NULL),
(10, 2, 2, '2023-11-02', 'present', 1, NULL),
(11, 2, 2, '2023-11-09', 'absent', 1, NULL),
(12, 3, 5, '2023-11-03', 'present', 2, NULL),
(13, 3, 5, '2023-11-10', 'present', 2, NULL),
(14, 3, 5, '2023-11-17', 'absent', 2, NULL),
(15, 4, 8, '2023-11-04', 'present', 3, NULL),
(16, 4, 8, '2023-11-11', 'present', 3, NULL),
(17, 5, 11, '2023-11-05', 'present', 4, NULL),
(18, 5, 11, '2023-11-12', 'absent', 4, NULL),
(19, 3, 5, '2025-06-01', 'absent', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `cni` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `nom`, `prenom`, `cni`, `password`) VALUES
(1, 'Admin', 'Principal', 'AA123456', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Directeur', 'Adjoint', 'AA789012', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

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
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `filieres`
--

INSERT INTO `filieres` (`id`, `name`, `description`) VALUES
(1, 'Informatique', 'Formation en développement informatique et systèmes'),
(2, 'Électronique', 'Formation en électronique et systèmes embarqués'),
(3, 'Mécanique', 'Formation en mécanique industrielle'),
(4, 'Gestion', 'Formation en gestion et administration des entreprises');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `grade` decimal(4,2) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `recorded_by_teacher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `student_id`, `module_id`, `grade`, `date`, `recorded_by_teacher_id`) VALUES
(1, 1, 1, 16.50, '2023-11-15', 1),
(2, 1, 2, 14.25, '2023-11-20', 1),
(3, 1, 3, 15.75, '2023-12-01', 1),
(4, 2, 1, 18.00, '2023-11-15', 1),
(5, 2, 2, 17.50, '2023-11-20', 1),
(6, 2, 4, 16.25, '2023-12-05', 1),
(7, 3, 5, 13.75, '2023-11-18', 2),
(8, 3, 6, 15.50, '2023-11-25', 2),
(9, 4, 8, 12.25, '2023-11-22', 3),
(10, 4, 9, 14.00, '2023-12-02', 3),
(11, 5, 11, 15.25, '2023-11-28', 4),
(12, 5, 12, 13.50, '2023-12-03', 4),
(13, 1, 2, 14.40, '2025-06-01', NULL),
(14, 1, 4, 15.60, '2025-06-01', NULL),
(15, 1, 1, 17.20, '2025-06-01', NULL),
(16, 1, 2, 15.40, '2025-06-01', NULL),
(17, 3, 5, 0.00, '2025-06-01', NULL),
(18, 3, 5, 0.00, '2025-06-01', NULL),
(19, 3, 5, 0.00, '2025-06-01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `grades_backup`
--

CREATE TABLE `grades_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `student_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `grade` decimal(4,2) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `recorded_by_teacher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades_backup`
--

INSERT INTO `grades_backup` (`id`, `student_id`, `module_id`, `grade`, `date`, `recorded_by_teacher_id`) VALUES
(1, 1, 1, 16.50, '2023-11-15', 1),
(2, 1, 2, 14.25, '2023-11-20', 1),
(3, 1, 3, 15.75, '2023-12-01', 1),
(4, 2, 1, 18.00, '2023-11-15', 1),
(5, 2, 2, 17.50, '2023-11-20', 1),
(6, 2, 4, 16.25, '2023-12-05', 1),
(7, 3, 5, 13.75, '2023-11-18', 2),
(8, 3, 6, 15.50, '2023-11-25', 2),
(9, 4, 8, 12.25, '2023-11-22', 3),
(10, 4, 9, 14.00, '2023-12-02', 3),
(11, 5, 11, 15.25, '2023-11-28', 4),
(12, 5, 12, 13.50, '2023-12-03', 4),
(13, 1, 2, 14.40, '2025-06-01', NULL),
(14, 1, 4, 15.60, '2025-06-01', NULL),
(15, 1, 1, 17.20, '2025-06-01', NULL),
(16, 1, 2, 15.40, '2025-06-01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_cni` varchar(50) DEFAULT NULL,
  `target_cni` varchar(50) DEFAULT NULL,
  `target_classe_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `date` datetime DEFAULT NULL,
  `type` enum('message','announcement') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_cni`, `target_cni`, `target_classe_id`, `module_id`, `content`, `date`, `type`) VALUES
(1, 'AA123456', NULL, NULL, NULL, 'Bienvenue dans la nouvelle année académique 2023-2024. Nous vous souhaitons une excellente année d\'études.', '2023-09-01 09:00:00', 'announcement'),
(2, 'AA123456', NULL, 1, NULL, 'Réunion importante pour tous les étudiants de la filière Informatique le vendredi 15 décembre à 14h en salle de conférence.', '2023-12-10 10:30:00', 'announcement'),
(3, 'BB123456', 'EE123456', NULL, 1, 'Félicitations pour votre excellent travail en Programmation Web. Continuez ainsi!', '2023-11-16 15:30:00', 'message'),
(4, 'BB234567', 'EE345678', NULL, 5, 'Votre projet en Circuits Électroniques nécessite quelques améliorations. Venez me voir pendant mes heures de bureau.', '2023-11-19 11:15:00', 'message'),
(5, 'AA123456', NULL, NULL, NULL, 'Les examens de fin de semestre auront lieu du 18 au 22 décembre 2023. Consultez le planning détaillé sur le tableau d\'affichage.', '2023-12-01 08:00:00', 'announcement');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `filiere_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `name`, `filiere_id`) VALUES
(1, 'Programmation Web', 1),
(2, 'Base de Données', 1),
(3, 'Réseaux Informatiques', 1),
(4, 'Développement Mobile', 1),
(5, 'Circuits Électroniques', 2),
(6, 'Microcontrôleurs', 2),
(7, 'Systèmes Embarqués', 2),
(8, 'Mécanique des Fluides', 3),
(9, 'Résistance des Matériaux', 3),
(10, 'CAO/DAO', 3),
(11, 'Comptabilité', 4),
(12, 'Marketing', 4),
(13, 'Ressources Humaines', 4);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `lieu_naissance` varchar(255) DEFAULT NULL,
  `cni` varchar(50) NOT NULL,
  `adresse` text DEFAULT NULL,
  `date_inscription` date DEFAULT NULL,
  `niveau` enum('technicien','technicien_specialise','qualifiant') NOT NULL,
  `num_telephone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `filiere_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `nom`, `prenom`, `date_naissance`, `lieu_naissance`, `cni`, `adresse`, `date_inscription`, `niveau`, `num_telephone`, `email`, `password`, `filiere_id`) VALUES
(1, 'Amrani', 'Youssef', '2000-05-15', 'Casablanca', 'EE123456', '12 Rue des Écoles, Casablanca', '2022-09-01', 'technicien_specialise', '0656789012', 'youssef.amrani@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(2, 'Benjelloun', 'Salma', '2001-03-22', 'Rabat', 'EE234567', '34 Avenue de la Liberté, Rabat', '2022-09-01', 'technicien_specialise', '0667890123', 'salma.benjelloun@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(3, 'Chakir', 'Mehdi', '1999-11-08', 'Marrakech', 'EE345678', '56 Quartier Gueliz, Marrakech', '2021-09-01', 'technicien_specialise', '0678901234', 'mehdi.chakir@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
(4, 'Daoudi', 'Nadia', '2000-07-12', 'Fès', 'EE456789', '78 Médina, Fès', '2022-09-01', 'technicien', '0689012345', 'nadia.daoudi@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
(5, 'El Fassi', 'Karim', '2001-01-30', 'Tanger', 'EE567890', '90 Zone Industrielle, Tanger', '2023-09-01', 'qualifiant', '0690123456', 'karim.elfassi@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `cni` varchar(50) NOT NULL,
  `adresse` text DEFAULT NULL,
  `type_contrat` varchar(100) DEFAULT NULL,
  `date_embauche` date DEFAULT NULL,
  `dernier_diplome` varchar(255) DEFAULT NULL,
  `num_telephone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `nom`, `prenom`, `cni`, `adresse`, `type_contrat`, `date_embauche`, `dernier_diplome`, `num_telephone`, `email`, `password`) VALUES
(1, 'Benali', 'Ahmed', 'BB123456', '123 Rue Mohammed V, Casablanca', 'CDI', '2020-09-01', 'Master en Informatique', '0612345678', 'ahmed.benali@groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Alami', 'Fatima', 'BB234567', '456 Avenue Hassan II, Rabat', 'CDI', '2019-09-01', 'Ingénieur Électronique', '0623456789', 'fatima.alami@groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(3, 'Tazi', 'Omar', 'BB345678', '789 Boulevard Zerktouni, Marrakech', 'CDD', '2021-02-15', 'Master en Mécanique', '0634567890', 'omar.tazi@groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(4, 'Idrissi', 'Aicha', 'BB456789', '321 Rue Allal Ben Abdellah, Fès', 'CDI', '2018-09-01', 'Master en Gestion', '0645678901', 'aicha.idrissi@groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_module_assignments`
--

CREATE TABLE `teacher_module_assignments` (
  `id` int(11) NOT NULL,
  `teacher_cni` varchar(50) NOT NULL,
  `module_id` int(11) NOT NULL,
  `filiere_id` int(11) NOT NULL,
  `assigned_date` date DEFAULT curdate(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_module_assignments`
--

INSERT INTO `teacher_module_assignments` (`id`, `teacher_cni`, `module_id`, `filiere_id`, `assigned_date`, `is_active`) VALUES
(1, 'BB123456', 2, 1, '2025-06-01', 1),
(2, 'BB234567', 5, 2, '2025-06-01', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absences`
--
ALTER TABLE `absences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
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
  ADD KEY `student_id` (`student_id`),
  ADD KEY `module_id` (`module_id`),
  ADD KEY `recorded_by_teacher_id` (`recorded_by_teacher_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `module_id` (`module_id`),
  ADD KEY `target_classe_id` (`target_classe_id`);

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
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `filiere_id` (`filiere_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cni` (`cni`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `teacher_module_assignments`
--
ALTER TABLE `teacher_module_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`teacher_cni`,`module_id`,`filiere_id`),
  ADD KEY `module_id` (`module_id`),
  ADD KEY `filiere_id` (`filiere_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absences`
--
ALTER TABLE `absences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teacher_module_assignments`
--
ALTER TABLE `teacher_module_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absences`
--
ALTER TABLE `absences`
  ADD CONSTRAINT `absences_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absences_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
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
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`recorded_by_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`target_classe_id`) REFERENCES `filieres` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `teacher_module_assignments`
--
ALTER TABLE `teacher_module_assignments`
  ADD CONSTRAINT `teacher_module_assignments_ibfk_1` FOREIGN KEY (`teacher_cni`) REFERENCES `teachers` (`cni`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_module_assignments_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_module_assignments_ibfk_3` FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
