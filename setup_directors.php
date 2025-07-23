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

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create directors table if it doesn't exist
    $sql = "
    CREATE TABLE IF NOT EXISTS `directors` (
        `cni` varchar(20) NOT NULL PRIMARY KEY,
        `nom` varchar(100) NOT NULL,
        `prenom` varchar(100) NOT NULL,
        `password` varchar(255) NOT NULL,
        `email` varchar(255) DEFAULT NULL,
        `num_telephone` varchar(20) DEFAULT NULL,
        `position` varchar(100) DEFAULT 'Directeur',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    
    $pdo->exec($sql);
    
    // Insert default director if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM directors WHERE cni = ?");
    $stmt->execute(['DD123456']);
    if ($stmt->fetchColumn() == 0) {
        $default_password = password_hash('director123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO directors (cni, nom, prenom, password, email, position) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['DD123456', 'Directeur', 'Principal', $default_password, 'directeur@groupe-iki.ma', 'Directeur Général']);
        $success_message = "Table des directeurs créée et directeur par défaut ajouté avec succès.";
    } else {
        $success_message = "Table des directeurs vérifiée avec succès.";
    }
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration des Directeurs - Groupe IKI</title>
    
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
                            Admin (<?php echo htmlspecialchars($user_cni); ?>)
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
                            <a class="nav-link" href="manage_schedules.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Emplois du Temps
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="setup_directors.php">
                                <i class="fas fa-user-tie me-2"></i>
                                Configuration Directeurs
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-tie me-2"></i>
                        Configuration des Directeurs
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
                            <i class="fas fa-info-circle me-2"></i>
                            Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>Cette page a configuré la table des directeurs dans la base de données et créé un compte directeur par défaut avec les identifiants suivants :</p>
                        <ul>
                            <li><strong>CNI :</strong> DD123456</li>
                            <li><strong>Mot de passe :</strong> director123</li>
                        </ul>
                        <p>Vous pouvez gérer les comptes directeurs via la page de gestion des utilisateurs.</p>
                        
                        <div class="mt-3">
                            <a href="manage_users.php" class="btn btn-primary">
                                <i class="fas fa-users me-2"></i>
                                Gérer les Utilisateurs
                            </a>
                            <a href="dashboard_admin.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-arrow-left me-2"></i>
                                Retour au Tableau de Bord
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
