-- Sample data for testing the Groupe IKI system
USE groupe_iki;

-- Insert sample filieres
INSERT INTO filieres (name, description) VALUES
('Informatique', 'Formation en développement informatique et systèmes'),
('Électronique', 'Formation en électronique et systèmes embarqués'),
('Mécanique', 'Formation en mécanique industrielle'),
('Gestion', 'Formation en gestion et administration des entreprises');

-- Insert sample modules
INSERT INTO modules (name, filiere_id) VALUES
-- Informatique modules
('Programmation Web', 1),
('Base de Données', 1),
('Réseaux Informatiques', 1),
('Développement Mobile', 1),
-- Électronique modules
('Circuits Électroniques', 2),
('Microcontrôleurs', 2),
('Systèmes Embarqués', 2),
-- Mécanique modules
('Mécanique des Fluides', 3),
('Résistance des Matériaux', 3),
('CAO/DAO', 3),
-- Gestion modules
('Comptabilité', 4),
('Marketing', 4),
('Ressources Humaines', 4);

-- Insert sample admins (passwords are hashed version of 'password')
INSERT INTO admins (nom, prenom, cni, password) VALUES
('Admin', 'Principal', 'AA123456', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Directeur', 'Adjoint', 'AA789012', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample teachers (passwords are hashed version of 'password')
INSERT INTO teachers (nom, prenom, cni, adresse, type_contrat, date_embauche, dernier_diplome, num_telephone, email, password) VALUES
('Benali', 'Ahmed', 'BB123456', '123 Rue Mohammed V, Casablanca', 'CDI', '2020-09-01', 'Master en Informatique', '0612345678', 'ahmed.benali@groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Alami', 'Fatima', 'BB234567', '456 Avenue Hassan II, Rabat', 'CDI', '2019-09-01', 'Ingénieur Électronique', '0623456789', 'fatima.alami@groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Tazi', 'Omar', 'BB345678', '789 Boulevard Zerktouni, Marrakech', 'CDD', '2021-02-15', 'Master en Mécanique', '0634567890', 'omar.tazi@groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Idrissi', 'Aicha', 'BB456789', '321 Rue Allal Ben Abdellah, Fès', 'CDI', '2018-09-01', 'Master en Gestion', '0645678901', 'aicha.idrissi@groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample students (passwords are hashed version of 'password')
INSERT INTO students (nom, prenom, date_naissance, lieu_naissance, cni, adresse, date_inscription, niveau, num_telephone, email, password, filiere_id) VALUES
('Amrani', 'Youssef', '2000-05-15', 'Casablanca', 'EE123456', '12 Rue des Écoles, Casablanca', '2022-09-01', 'technicien_specialise', '0656789012', 'youssef.amrani@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Benjelloun', 'Salma', '2001-03-22', 'Rabat', 'EE234567', '34 Avenue de la Liberté, Rabat', '2022-09-01', 'technicien_specialise', '0667890123', 'salma.benjelloun@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Chakir', 'Mehdi', '1999-11-08', 'Marrakech', 'EE345678', '56 Quartier Gueliz, Marrakech', '2021-09-01', 'technicien_specialise', '0678901234', 'mehdi.chakir@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
('Daoudi', 'Nadia', '2000-07-12', 'Fès', 'EE456789', '78 Médina, Fès', '2022-09-01', 'technicien', '0689012345', 'nadia.daoudi@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('El Fassi', 'Karim', '2001-01-30', 'Tanger', 'EE567890', '90 Zone Industrielle, Tanger', '2023-09-01', 'qualifiant', '0690123456', 'karim.elfassi@student.groupeiki.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4);

-- Insert sample grades
INSERT INTO grades (student_id, module_id, grade, date, recorded_by_teacher_id) VALUES
-- Grades for Youssef Amrani (Informatique)
(1, 1, 16.50, '2023-11-15', 1), -- Programmation Web
(1, 2, 14.25, '2023-11-20', 1), -- Base de Données
(1, 3, 15.75, '2023-12-01', 1), -- Réseaux Informatiques
-- Grades for Salma Benjelloun (Informatique)
(2, 1, 18.00, '2023-11-15', 1), -- Programmation Web
(2, 2, 17.50, '2023-11-20', 1), -- Base de Données
(2, 4, 16.25, '2023-12-05', 1), -- Développement Mobile
-- Grades for Mehdi Chakir (Électronique)
(3, 5, 13.75, '2023-11-18', 2), -- Circuits Électroniques
(3, 6, 15.50, '2023-11-25', 2), -- Microcontrôleurs
-- Grades for Nadia Daoudi (Mécanique)
(4, 8, 12.25, '2023-11-22', 3), -- Mécanique des Fluides
(4, 9, 14.00, '2023-12-02', 3), -- Résistance des Matériaux
-- Grades for Karim El Fassi (Gestion)
(5, 11, 15.25, '2023-11-28', 4), -- Comptabilité
(5, 12, 13.50, '2023-12-03', 4); -- Marketing

-- Insert sample absences
INSERT INTO absences (student_id, module_id, date, status, recorded_by_teacher_id) VALUES
-- Attendance for November 2023
(1, 1, '2023-11-01', 'present', 1),
(1, 1, '2023-11-08', 'present', 1),
(1, 1, '2023-11-15', 'absent', 1),
(1, 2, '2023-11-02', 'present', 1),
(1, 2, '2023-11-09', 'present', 1),
(1, 2, '2023-11-16', 'present', 1),
(2, 1, '2023-11-01', 'present', 1),
(2, 1, '2023-11-08', 'present', 1),
(2, 1, '2023-11-15', 'present', 1),
(2, 2, '2023-11-02', 'present', 1),
(2, 2, '2023-11-09', 'absent', 1),
(3, 5, '2023-11-03', 'present', 2),
(3, 5, '2023-11-10', 'present', 2),
(3, 5, '2023-11-17', 'absent', 2),
(4, 8, '2023-11-04', 'present', 3),
(4, 8, '2023-11-11', 'present', 3),
(5, 11, '2023-11-05', 'present', 4),
(5, 11, '2023-11-12', 'absent', 4);

-- Insert sample messages
INSERT INTO messages (sender_cni, target_cni, target_classe_id, module_id, content, date, type) VALUES
-- Admin announcements
('AA123456', NULL, NULL, NULL, 'Bienvenue dans la nouvelle année académique 2023-2024. Nous vous souhaitons une excellente année d\'études.', '2023-09-01 09:00:00', 'announcement'),
('AA123456', NULL, 1, NULL, 'Réunion importante pour tous les étudiants de la filière Informatique le vendredi 15 décembre à 14h en salle de conférence.', '2023-12-10 10:30:00', 'announcement'),
-- Teacher messages to students
('BB123456', 'EE123456', NULL, 1, 'Félicitations pour votre excellent travail en Programmation Web. Continuez ainsi!', '2023-11-16 15:30:00', 'message'),
('BB234567', 'EE345678', NULL, 5, 'Votre projet en Circuits Électroniques nécessite quelques améliorations. Venez me voir pendant mes heures de bureau.', '2023-11-19 11:15:00', 'message'),
-- General announcements
('AA123456', NULL, NULL, NULL, 'Les examens de fin de semestre auront lieu du 18 au 22 décembre 2023. Consultez le planning détaillé sur le tableau d\'affichage.', '2023-12-01 08:00:00', 'announcement');
