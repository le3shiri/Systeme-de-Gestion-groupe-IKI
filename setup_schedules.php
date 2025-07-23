<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

$success_message = '';
$error_message = '';

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create schedules table if it doesn't exist
    $sql = "
    CREATE TABLE IF NOT EXISTS `schedules` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `filiere_id` int(11) NOT NULL,
        `filename` varchar(255) NOT NULL,
        `file_path` varchar(255) NOT NULL,
        `uploaded_by` varchar(20) NOT NULL,
        `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `active` boolean DEFAULT TRUE,
        INDEX `idx_filiere_id` (`filiere_id`),
        FOREIGN KEY (`filiere_id`) REFERENCES `filieres`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    
    $pdo->exec($sql);
    $success_message = "Schedules table created successfully!";
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/schedules';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
} catch (PDOException $e) {
    $error_message = 'Database setup failed: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Schedules - Groupe IKI</title>
    
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
                <img src="assets/logo-circle.jpg" alt="" width="120px">
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar Items -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield me-2"></i>
                            Admin (<?php echo htmlspecialchars($_SESSION['user_cni']); ?>)
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard_admin.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de Bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Gestion des Utilisateurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_filieres_modules.php">
                                <i class="fas fa-book me-2"></i>
                                Filières & Modules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                Gestion des Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="record_absence.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                Gestion des Absences
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="setup_schedules.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Emplois du Temps
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Configuration des Emplois du Temps
                    </h1>
                </div>

                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-database me-2"></i>
                            Configuration de la Base de Données
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>Cette page a créé la table <code>schedules</code> pour stocker les emplois du temps.</p>
                        <p>Vous pouvez maintenant gérer les emplois du temps depuis la page <a href="manage_schedules.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-calendar-alt me-2"></i>Gestion des Emplois du Temps
                        </a></p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
