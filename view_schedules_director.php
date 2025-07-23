<?php
session_start();

// Check if user is logged in and is a director
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'director') {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];
$error_message = '';

// Database connection
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get director details
    $stmt = $pdo->prepare("SELECT * FROM directors WHERE cni = ?");
    $stmt->execute([$user_cni]);
    $director = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all schedules grouped by filiere
    $stmt = $pdo->query("
        SELECT s.*, f.name as filiere_name 
        FROM schedules s
        JOIN filieres f ON s.filiere_id = f.id
        ORDER BY f.name, s.active DESC, s.uploaded_at DESC
    ");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group schedules by filiere
    $schedules_by_filiere = [];
    foreach ($schedules as $schedule) {
        $filiere_id = $schedule['filiere_id'];
        if (!isset($schedules_by_filiere[$filiere_id])) {
            $schedules_by_filiere[$filiere_id] = [
                'name' => $schedule['filiere_name'],
                'schedules' => []
            ];
        }
        $schedules_by_filiere[$filiere_id]['schedules'][] = $schedule;
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
    <title>Emplois du Temps - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    
    <style>
        .schedule-card {
            transition: transform 0.3s ease;
        }
        
        .schedule-card:hover {
            transform: translateY(-5px);
        }
        
        .active-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard_director.php">
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
                            <i class="fas fa-user-tie me-2"></i>
                            Directeur (<?php echo htmlspecialchars($user_cni); ?>)
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
                            <a class="nav-link" href="dashboard_director.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de Bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="director_students.php">
                                <i class="fas fa-user-graduate me-2"></i>
                                Étudiants
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="director_teachers.php">
                                <i class="fas fa-chalkboard-teacher me-2"></i>
                                Enseignants
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="director_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                Statistiques des Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="director_absences.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                Statistiques des Absences
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="view_schedules_director.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Emplois du Temps
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="director_reports.php">
                                <i class="fas fa-file-alt me-2"></i>
                                Rapports
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
                        Emplois du Temps
                    </h1>
                </div>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Vue d'ensemble des emplois du temps
                                </h5>
                            </div>
                            <div class="card-body">
                                <p>Cette page vous permet de consulter tous les emplois du temps de l'établissement par filière. Les emplois du temps actifs sont marqués et apparaissent en premier.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($schedules_by_filiere)): ?>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    Aucun emploi du temps n'a été trouvé.
                </div>
                <?php else: ?>
                    <?php foreach ($schedules_by_filiere as $filiere_id => $filiere_data): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="fas fa-graduation-cap me-2"></i>
                                <?php echo htmlspecialchars($filiere_data['name']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <?php foreach ($filiere_data['schedules'] as $schedule): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 schedule-card position-relative">
                                        <?php if ($schedule['is_active']): ?>
                                        <span class="badge bg-success active-badge">Actif</span>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                                <?php echo htmlspecialchars($schedule['title']); ?>
                                            </h5>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Ajouté le: <?php echo date('d/m/Y', strtotime($schedule['upload_date'])); ?>
                                                </small>
                                            </p>
                                            <div class="d-grid gap-2">
                                                <a href="uploads/schedules/<?php echo htmlspecialchars($schedule['file_path']); ?>" class="btn btn-primary" target="_blank">
                                                    <i class="fas fa-eye me-2"></i>
                                                    Voir
                                                </a>
                                                <a href="uploads/schedules/<?php echo htmlspecialchars($schedule['file_path']); ?>" class="btn btn-outline-secondary" download>
                                                    <i class="fas fa-download me-2"></i>
                                                    Télécharger
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
