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
    
    // Get total counts for dashboard
    $stats = [
        'students' => 0,
        'teachers' => 0,
        'filieres' => 0,
        'modules' => 0,
        'classes' => 0,
        'absences' => 0,
        'absences_month' => 0,
        'messages' => 0
    ];
    
    // Count students
    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $stats['students'] = $stmt->fetchColumn();
    
    // Count teachers
    $stmt = $pdo->query("SELECT COUNT(*) FROM teachers");
    $stats['teachers'] = $stmt->fetchColumn();
    
    // Count filieres
    $stmt = $pdo->query("SELECT COUNT(*) FROM filieres");
    $stats['filieres'] = $stmt->fetchColumn();
    
    // Count modules
    $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
    $stats['modules'] = $stmt->fetchColumn();
    
    // Count classes
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes");
    $stats['classes'] = $stmt->fetchColumn();
    
    // Count total absences
    $stmt = $pdo->query("SELECT COUNT(*) FROM absences");
    $stats['absences'] = $stmt->fetchColumn();
    
    // Count absences this month
    $stmt = $pdo->query("SELECT COUNT(*) FROM absences WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stats['absences_month'] = $stmt->fetchColumn();
    
    // Count messages
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages");
    $stats['messages'] = $stmt->fetchColumn();
    
    // Get student distribution by filiere
    $stmt = $pdo->query("
        SELECT f.name, COUNT(s.cni) as student_count 
        FROM filieres f 
        LEFT JOIN students s ON f.id = s.filiere_id 
        GROUP BY f.id 
        ORDER BY student_count DESC
    ");
    $student_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get average grades by filiere
    $stmt = $pdo->query("
        SELECT f.name, AVG(g.grade) as avg_grade 
        FROM filieres f
        JOIN modules m ON m.filiere_id = f.id
        JOIN grades g ON g.module_id = m.id
        GROUP BY f.id
        ORDER BY avg_grade DESC
    ");
    $avg_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get absence rate by filiere (absences per student)
    $stmt = $pdo->query("
        SELECT f.name, COUNT(a.id) as total_absences, COUNT(DISTINCT s.cni) as student_count,
               CASE WHEN COUNT(DISTINCT s.cni) > 0 THEN COUNT(a.id)/COUNT(DISTINCT s.cni) ELSE 0 END as absence_rate
        FROM filieres f
        JOIN students s ON f.id = s.filiere_id
        LEFT JOIN absences a ON a.id IS NOT NULL
        GROUP BY f.id
        ORDER BY absence_rate DESC
    ");
    $absence_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent absences
    $stmt = $pdo->query("
        SELECT a.created_at, s.nom, s.prenom, f.name as filiere, m.name as module
        FROM absences a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN filieres f ON s.filiere_id = f.id
        LEFT JOIN modules m ON a.module_id = m.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $recent_absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get grade distribution
    $stmt = $pdo->query("
        SELECT 
            CASE 
                WHEN grade < 8 THEN 'Très faible (<8)'
                WHEN grade >= 8 AND grade < 10 THEN 'Faible (8-10)'
                WHEN grade >= 10 AND grade < 12 THEN 'Moyen (10-12)'
                WHEN grade >= 12 AND grade < 14 THEN 'Assez bien (12-14)'
                WHEN grade >= 14 AND grade < 16 THEN 'Bien (14-16)'
                WHEN grade >= 16 THEN 'Très bien (16+)'
            END as grade_range,
            COUNT(*) as count
        FROM grades
        GROUP BY grade_range
        ORDER BY MIN(grade)
    ");
    $grade_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Directeur - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    
    <style>
        .stats-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 15px;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
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
                            <a class="nav-link active" href="dashboard_director.php">
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
                            <a class="nav-link" href="view_schedules_director.php">
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
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Tableau de Bord Directeur
                    </h1>
                    <div>
                        <button class="btn btn-outline-dark d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <!-- Welcome Message -->
                <div class="alert alert-dark" role="alert">
                    <i class="fas fa-hand-wave me-2"></i>
                    <strong>Bienvenue, <?php echo isset($director) ? htmlspecialchars($director['prenom'] . ' ' . $director['nom']) : 'Directeur'; ?>!</strong> 
                    <br>
                    <small>
                        Voici un aperçu des statistiques et indicateurs clés de l'établissement.
                    </small>
                </div>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <!-- Students Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-primary bg-opacity-10 text-primary mx-auto">
                                    <i class="fas fa-user-graduate fa-2x"></i>
                                </div>
                                <div class="stats-number text-primary"><?php echo number_format($stats['students']); ?></div>
                                <div class="stats-label">Étudiants</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Teachers Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-success bg-opacity-10 text-success mx-auto">
                                    <i class="fas fa-chalkboard-teacher fa-2x"></i>
                                </div>
                                <div class="stats-number text-success"><?php echo number_format($stats['teachers']); ?></div>
                                <div class="stats-label">Enseignants</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filieres Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-info bg-opacity-10 text-info mx-auto">
                                    <i class="fas fa-graduation-cap fa-2x"></i>
                                </div>
                                <div class="stats-number text-info"><?php echo number_format($stats['filieres']); ?></div>
                                <div class="stats-label">Filières</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modules Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-warning bg-opacity-10 text-warning mx-auto">
                                    <i class="fas fa-book fa-2x"></i>
                                </div>
                                <div class="stats-number text-warning"><?php echo number_format($stats['modules']); ?></div>
                                <div class="stats-label">Modules</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- Classes Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-secondary bg-opacity-10 text-secondary mx-auto">
                                    <i class="fas fa-school fa-2x"></i>
                                </div>
                                <div class="stats-number text-secondary"><?php echo number_format($stats['classes']); ?></div>
                                <div class="stats-label">Classes</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Absences Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-danger bg-opacity-10 text-danger mx-auto">
                                    <i class="fas fa-calendar-times fa-2x"></i>
                                </div>
                                <div class="stats-number text-danger"><?php echo number_format($stats['absences']); ?></div>
                                <div class="stats-label">Absences Totales</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Absences Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-danger bg-opacity-10 text-danger mx-auto">
                                    <i class="fas fa-calendar-day fa-2x"></i>
                                </div>
                                <div class="stats-number text-danger"><?php echo number_format($stats['absences_month']); ?></div>
                                <div class="stats-label">Absences ce mois</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messages Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-primary bg-opacity-10 text-primary mx-auto">
                                    <i class="fas fa-envelope fa-2x"></i>
                                </div>
                                <div class="stats-number text-primary"><?php echo number_format($stats['messages']); ?></div>
                                <div class="stats-label">Messages</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <!-- Student Distribution Chart -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    Distribution des Étudiants par Filière
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="studentDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grade Distribution Chart -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Distribution des Notes
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="gradeDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- Average Grades Chart -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Moyenne des Notes par Filière
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="avgGradesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Absence Rate Chart -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Taux d'Absences par Filière
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="absenceRateChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Absences Table -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-times me-2"></i>
                            Absences Récentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Étudiant</th>
                                        <th>Filière</th>
                                        <th>Module</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($recent_absences) && count($recent_absences) > 0): ?>
                                        <?php foreach ($recent_absences as $absence): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($absence['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($absence['prenom'] . ' ' . $absence['nom']); ?></td>
                                                <td><?php echo htmlspecialchars($absence['filiere'] ?? 'Non assigné'); ?></td>
                                                <td><?php echo htmlspecialchars($absence['module'] ?? 'Non spécifié'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">Aucune absence récente</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Charts JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Student Distribution Chart
            <?php if (isset($student_distribution) && count($student_distribution) > 0): ?>
            var studentCtx = document.getElementById('studentDistributionChart').getContext('2d');
            var studentDistributionChart = new Chart(studentCtx, {
                type: 'pie',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item['name']) . '"'; }, $student_distribution)); ?>],
                    datasets: [{
                        data: [<?php echo implode(', ', array_map(function($item) { return $item['student_count']; }, $student_distribution)); ?>],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 159, 64, 0.7)',
                            'rgba(199, 199, 199, 0.7)',
                            'rgba(83, 102, 255, 0.7)',
                            'rgba(40, 159, 64, 0.7)',
                            'rgba(210, 199, 199, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Grade Distribution Chart
            <?php if (isset($grade_distribution) && count($grade_distribution) > 0): ?>
            var gradeCtx = document.getElementById('gradeDistributionChart').getContext('2d');
            var gradeDistributionChart = new Chart(gradeCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item['grade_range']) . '"'; }, $grade_distribution)); ?>],
                    datasets: [{
                        label: 'Nombre d\'étudiants',
                        data: [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $grade_distribution)); ?>],
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Average Grades Chart
            <?php if (isset($avg_grades) && count($avg_grades) > 0): ?>
            var avgGradesCtx = document.getElementById('avgGradesChart').getContext('2d');
            var avgGradesChart = new Chart(avgGradesCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item['name']) . '"'; }, $avg_grades)); ?>],
                    datasets: [{
                        label: 'Moyenne des notes',
                        data: [<?php echo implode(', ', array_map(function($item) { return round($item['avg_grade'], 2); }, $avg_grades)); ?>],
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 20
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Absence Rate Chart
            <?php if (isset($absence_rates) && count($absence_rates) > 0): ?>
            var absenceRateCtx = document.getElementById('absenceRateChart').getContext('2d');
            var absenceRateChart = new Chart(absenceRateCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item['name']) . '"'; }, $absence_rates)); ?>],
                    datasets: [{
                        label: 'Absences par étudiant',
                        data: [<?php echo implode(', ', array_map(function($item) { return round($item['absence_rate'], 2); }, $absence_rates)); ?>],
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
