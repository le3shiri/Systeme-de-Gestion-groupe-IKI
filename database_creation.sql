-- Create database
CREATE DATABASE IF NOT EXISTS groupe_iki;
USE groupe_iki;

-- Create filieres table
CREATE TABLE filieres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT
);

-- Create modules table
CREATE TABLE modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    filiere_id INT,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL
);

-- Create students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    date_naissance DATE,
    lieu_naissance VARCHAR(255),
    cni VARCHAR(50) UNIQUE NOT NULL,
    adresse TEXT,
    date_inscription DATE,
    niveau ENUM('technicien', 'technicien_specialise', 'qualifiant') NOT NULL,
    num_telephone VARCHAR(20),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255) NOT NULL,
    filiere_id INT,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL
);

-- Create teachers table
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    cni VARCHAR(50) UNIQUE NOT NULL,
    adresse TEXT,
    type_contrat VARCHAR(100),
    date_embauche DATE,
    dernier_diplome VARCHAR(255),
    num_telephone VARCHAR(20),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Create admins table
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    cni VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Create grades table
CREATE TABLE grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    module_id INT,
    grade DECIMAL(4,2),
    date DATE,
    recorded_by_teacher_id INT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- Create absences table (with enhancements)
CREATE TABLE absences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    module_id INT,
    date DATE,
    status ENUM('present', 'absent') NOT NULL,
    recorded_by_teacher_id INT,
    recorded_by_admin_id INT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

-- Create messages table
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_cni VARCHAR(50),
    target_cni VARCHAR(50),
    target_classe_id INT,
    module_id INT,
    content TEXT NOT NULL,
    date DATETIME,
    type ENUM('message', 'announcement') NOT NULL,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL,
    FOREIGN KEY (target_classe_id) REFERENCES filieres(id) ON DELETE SET NULL
);