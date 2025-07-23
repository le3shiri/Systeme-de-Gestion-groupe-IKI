<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'student') {
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
    
    // Get student details including filiere_id
    $stmt = $pdo->prepare("
        SELECT s.nom, s.prenom, s.filiere_id, f.name as filiere_name 
        FROM students s 
        LEFT JOIN filieres f ON s.filiere_id = f.id 
        WHERE s.cni = ?
    ");
    $stmt->execute([$user_cni]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student && $student['filiere_id']) {
        // Get active schedule for student's filiere
        $stmt = $pdo->prepare("
            SELECT * FROM schedules 
            WHERE filiere_id = ? AND active = 1 
            ORDER BY uploaded_at DESC LIMIT 1
        ");
        $stmt->execute([$student['filiere_id']]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error_message = "Vous n'êtes pas assigné à une filière. Veuillez contacter l'administration.";
    }
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}

// Dashboard link and navbar color for student
$dashboard_link = 'dashboard_student.php';
$navbar_color = 'bg-info';
$user_icon = 'fa-user-graduate';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emploi du Temps - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    
    <style>
        .schedule-container {
            min-height: 600px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .schedule-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            margin-bottom: 1rem;
        }
        
        .pdf-container {
            height: 800px;
            width: 100%;
            border: none;
        }
        
        .no-schedule {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 400px;
            color: #6c757d;
        }
        
        .no-schedule i {
            font-size: 5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg <?php echo $navbar_color; ?> navbar-light bg-white fixed-top border-bottom shadow-sm">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $dashboard_link; ?>">
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
                            <i class="fas <?php echo $user_icon; ?> me-2"></i>
                            Étudiant (<?php echo htmlspecialchars($user_cni); ?>)
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
                            <a class="nav-link" href="<?php echo $dashboard_link; ?>">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de Bord
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="view_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                Relevé des Notes
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="student_absences.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                Mes Absences
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="view_schedule.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Emploi du Temps
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="view_messages.php">
                                <i class="fas fa-inbox me-2"></i>
                                Messages
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
                        Emploi du Temps
                    </h1>
                    <div>
                        <button class="btn btn-outline-primary me-2" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Imprimer
                        </button>
                        <button class="btn btn-outline-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>

                <?php if (isset($error_message) && !empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <!-- Schedule Display -->
                <div class="schedule-container">
                    <?php if (isset($student) && isset($schedule)): ?>
                    <div class="schedule-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h2 class="mb-1">Emploi du Temps</h2>
                                <h4 class="mb-0"><?php echo htmlspecialchars($student['filiere_name']); ?></h4>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-0"><small>Dernière mise à jour:</small></p>
                                <p class="mb-0"><strong><?php echo date('d/m/Y', strtotime($schedule['uploaded_at'])); ?></strong></p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <?php if (file_exists($schedule['file_path'])): ?>
                        <div class="ratio ratio-16x9">
                            <iframe class="pdf-container" src="<?php echo htmlspecialchars($schedule['file_path']); ?>" allowfullscreen></iframe>
                        </div>
                        <div class="mt-3 text-center">
                            <a href="<?php echo htmlspecialchars($schedule['file_path']); ?>" class="btn btn-primary" download>
                                <i class="fas fa-download me-2"></i>Télécharger l'emploi du temps
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="no-schedule">
                            <i class="fas fa-exclamation-circle"></i>
                            <h4>Fichier non trouvé</h4>
                            <p>Le fichier de l'emploi du temps n'est pas disponible. Veuillez contacter l'administration.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php elseif (isset($student)): ?>
                    <div class="schedule-header">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <h2 class="mb-1">Emploi du Temps</h2>
                                <h4 class="mb-0"><?php echo htmlspecialchars($student['filiere_name'] ?? 'Filière non assignée'); ?></h4>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="no-schedule">
                            <i class="fas fa-calendar-times"></i>
                            <h4>Aucun emploi du temps disponible</h4>
                            <p>L'emploi du temps pour votre filière n'a pas encore été téléchargé. Veuillez vérifier ultérieurement.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
