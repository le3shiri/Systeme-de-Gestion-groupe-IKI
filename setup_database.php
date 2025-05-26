<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];
$success_message = '';
$error_message = '';

// Database connection
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['setup_database'])) {
    try {
        // Connect to MySQL server (without database)
        $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8 COLLATE utf8_general_ci");
        
        // Connect to the specific database
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create tables in correct order (referenced tables first)
        $sql = "
        -- Create admins table
        CREATE TABLE IF NOT EXISTS `admins` (
            `cni` varchar(20) NOT NULL PRIMARY KEY,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- Create filieres table (must be created before classes and modules)
        CREATE TABLE IF NOT EXISTS `filieres` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar(255) NOT NULL,
            `description` text,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- Create classes table (depends on filieres)
        CREATE TABLE IF NOT EXISTS `classes` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar(255) NOT NULL,
            `filiere_id` int(11),
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_filiere_id` (`filiere_id`),
            FOREIGN KEY (`filiere_id`) REFERENCES `filieres`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- Create modules table (depends on filieres)
        CREATE TABLE IF NOT EXISTS `modules` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar(255) NOT NULL,
            `description` text,
            `filiere_id` int(11),
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_module_filiere_id` (`filiere_id`),
            FOREIGN KEY (`filiere_id`) REFERENCES `filieres`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- Create students table (depends on filieres)
        CREATE TABLE IF NOT EXISTS `students` (
            `cni` varchar(20) NOT NULL PRIMARY KEY,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `date_naissance` date DEFAULT NULL,
            `lieu_naissance` varchar(255) DEFAULT NULL,
            `adresse` text DEFAULT NULL,
            `date_inscription` date DEFAULT NULL,
            `niveau` enum('technicien','technicien_specialise','qualifiant') DEFAULT NULL,
            `num_telephone` varchar(20) DEFAULT NULL,
            `email` varchar(255) DEFAULT NULL,
            `filiere_id` int(11) DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_student_filiere_id` (`filiere_id`),
            FOREIGN KEY (`filiere_id`) REFERENCES `filieres`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- Create teachers table (no foreign key dependencies)
        CREATE TABLE IF NOT EXISTS `teachers` (
            `cni` varchar(20) NOT NULL PRIMARY KEY,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `adresse` text DEFAULT NULL,
            `type_contrat` varchar(100) DEFAULT NULL,
            `date_embauche` date DEFAULT NULL,
            `dernier_diplome` varchar(255) DEFAULT NULL,
            `num_telephone` varchar(20) DEFAULT NULL,
            `email` varchar(255) DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
        
        // Execute the first batch of SQL commands
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        // Now create tables that depend on the above tables
        $sql2 = "
        -- Create teacher_module_assignments table (depends on teachers, modules, classes)
        CREATE TABLE IF NOT EXISTS `teacher_module_assignments` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `teacher_cni` varchar(20) NOT NULL,
            `module_id` int(11) NOT NULL,
            `class_id` int(11) NOT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_teacher_cni` (`teacher_cni`),
            INDEX `idx_module_id` (`module_id`),
            INDEX `idx_class_id` (`class_id`),
            FOREIGN KEY (`teacher_cni`) REFERENCES `teachers`(`cni`) ON DELETE CASCADE,
            FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_assignment` (`teacher_cni`, `module_id`, `class_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- Create grades table (depends on students, modules, teachers)
        CREATE TABLE IF NOT EXISTS `grades` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `student_cni` varchar(20) NOT NULL,
            `module_id` int(11) NOT NULL,
            `grade` decimal(5,2) NOT NULL,
            `grade_type` enum('exam','homework','project','quiz') DEFAULT 'exam',
            `date_recorded` date NOT NULL,
            `teacher_cni` varchar(20) DEFAULT NULL,
            `comments` text DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_student_cni` (`student_cni`),
            INDEX `idx_grade_module_id` (`module_id`),
            INDEX `idx_grade_teacher_cni` (`teacher_cni`),
            FOREIGN KEY (`student_cni`) REFERENCES `students`(`cni`) ON DELETE CASCADE,
            FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`teacher_cni`) REFERENCES `teachers`(`cni`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- Create absences table (depends on students, modules, teachers)
        CREATE TABLE IF NOT EXISTS `absences` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `student_cni` varchar(20) NOT NULL,
            `date_absence` date NOT NULL,
            `module_id` int(11) DEFAULT NULL,
            `reason` text DEFAULT NULL,
            `is_justified` boolean DEFAULT FALSE,
            `recorded_by` varchar(20) DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_absence_student_cni` (`student_cni`),
            INDEX `idx_absence_module_id` (`module_id`),
            INDEX `idx_recorded_by` (`recorded_by`),
            FOREIGN KEY (`student_cni`) REFERENCES `students`(`cni`) ON DELETE CASCADE,
            FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`recorded_by`) REFERENCES `teachers`(`cni`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- Create messages table (no strict foreign key dependencies)
        CREATE TABLE IF NOT EXISTS `messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `sender_cni` varchar(20) NOT NULL,
            `sender_type` enum('admin','teacher','student') NOT NULL,
            `recipient_cni` varchar(20) DEFAULT NULL,
            `recipient_type` enum('admin','teacher','student','all') DEFAULT NULL,
            `subject` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `is_read` boolean DEFAULT FALSE,
            `sent_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_sender_cni` (`sender_cni`),
            INDEX `idx_recipient_cni` (`recipient_cni`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
        
        // Execute the second batch of SQL commands
        $statements2 = explode(';', $sql2);
        foreach ($statements2 as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        // Insert default admin if not exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE cni = ?");
        $stmt->execute(['AA123456']);
        if ($stmt->fetchColumn() == 0) {
            $admin_password = password_hash('password', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (cni, nom, prenom, password) VALUES (?, ?, ?, ?)");
            $stmt->execute(['AA123456', 'Admin', 'System', $admin_password]);
        }
        
        // Insert sample data if tables are empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM filieres");
        if ($stmt->fetchColumn() == 0) {
            // Insert sample filieres
            $sample_filieres = [
                ['Informatique', 'Formation en développement informatique'],
                ['Électronique', 'Formation en systèmes électroniques'],
                ['Mécanique', 'Formation en mécanique industrielle'],
                ['Gestion', 'Formation en gestion et administration']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO filieres (name, description) VALUES (?, ?)");
            foreach ($sample_filieres as $filiere) {
                $stmt->execute($filiere);
            }
            
            // Insert sample classes
            $sample_classes = [
                ['INFO-T1', 1], ['INFO-T2', 1], ['INFO-TS1', 1], ['INFO-TS2', 1],
                ['ELEC-T1', 2], ['ELEC-T2', 2], ['ELEC-TS1', 2],
                ['MECA-T1', 3], ['MECA-T2', 3],
                ['GEST-T1', 4], ['GEST-T2', 4]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO classes (name, filiere_id) VALUES (?, ?)");
            foreach ($sample_classes as $class) {
                $stmt->execute($class);
            }
            
            // Insert sample modules
            $sample_modules = [
                ['Programmation Web', 1], ['Base de Données', 1], ['Réseaux', 1],
                ['Circuits Électroniques', 2], ['Microprocesseurs', 2],
                ['Mécanique Générale', 3], ['Usinage', 3],
                ['Comptabilité', 4], ['Marketing', 4]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO modules (name, filiere_id) VALUES (?, ?)");
            foreach ($sample_modules as $module) {
                $stmt->execute($module);
            }
        }
        
        $success_message = 'Database setup completed successfully! All tables have been created and sample data has been inserted.';
        
    } catch (PDOException $e) {
        $error_message = 'Database setup failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="dashboard-page">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard_admin.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <span class="fw-bold">Groupe IKI</span>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard_admin.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-database me-2"></i>
                            Database Setup
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <div class="mt-3">
                                <a href="manage_users.php" class="btn btn-success">
                                    <i class="fas fa-users me-2"></i>Go to Manage Users
                                </a>
                                <a href="dashboard_admin.php" class="btn btn-outline-primary ms-2">
                                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (empty($success_message)): ?>
                        <div class="mb-4">
                            <h5>What this setup will do:</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Create the 'groupe_iki' database if it doesn't exist
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Create all required tables (students, teachers, filieres, etc.)
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Insert sample data for testing
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Create default admin account (AA123456 / password)
                                </li>
                            </ul>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Make sure XAMPP/WAMP is running and MySQL service is started before proceeding.
                        </div>

                        <form method="POST" action="setup_database.php">
                            <div class="d-grid">
                                <button type="submit" name="setup_database" class="btn btn-primary btn-lg">
                                    <i class="fas fa-cogs me-2"></i>
                                    Setup Database Now
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
