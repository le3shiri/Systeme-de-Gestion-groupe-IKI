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
    
    // Get average grades by filiere
    $stmt = $pdo->query("
        SELECT f.name, AVG(g.grade) as avg_grade 
        FROM filieres f
        JOIN modules m ON m.filiere_id = f.id
        JOIN grades g ON g.module_id = m.id
        GROUP BY f.id
        ORDER BY avg_grade DESC
    ");
    $avg_grades_by_filiere = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get average grades by module
    $stmt = $pdo->query("
        SELECT m.name, AVG(g.grade) as avg_grade 
        FROM grades g
        JOIN modules m ON g.module_id = m.id
        GROUP BY m.id
        ORDER BY avg_grade DESC
    ");
    $avg_grades_by_module = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    
    // Get top 10 students
    $stmt = $pdo->query("
        SELECT s.cni, s.nom, s.prenom, f.name as filiere, AVG(g.grade) as avg_grade
        FROM students s
        JOIN grades g ON s.id = g.student_id
        JOIN filieres f ON s.filiere_id = f.id
        GROUP BY s.cni
        ORDER BY avg_grade DESC
        LIMIT 10
    ");
    $top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent grades
    $stmt = $pdo->query("
        SELECT g.grade, g.created_at as date_added, s.nom, s.prenom, m.name as module
        FROM grades g
        JOIN students s ON g.student_id = s.id
        JOIN modules m ON g.module_id = m.id
        ORDER BY g.created_at DESC
        LIMIT 10
    ");
    $recent_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Notes - Groupe IKI</title>
    
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
                            <a class="nav-link active" href="director_grades.php">
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
                        <i class="fas fa-chart-line me-2"></i>
                        Statistiques des Notes
                    </h1>
                </div>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <!-- Average Grades by Filiere Chart -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Moyenne des Notes par Filière
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="avgGradesByFiliereChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grade Distribution Chart -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
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
                    <!-- Average Grades by Module Chart -->
                    <div class="col-md-12">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Moyenne des Notes par Module
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="avgGradesByModuleChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- Top Students Table -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-trophy me-2"></i>
                                    Top 10 des Étudiants
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Étudiant</th>
                                                <th>Filière</th>
                                                <th>Moyenne</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (isset($top_students) && count($top_students) > 0): ?>
                                                <?php $rank = 1; ?>
                                                <?php foreach ($top_students as $student): ?>
                                                    <tr>
                                                        <td><?php echo $rank++; ?></td>
                                                        <td><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></td>
                                                        <td><?php echo htmlspecialchars($student['filiere']); ?></td>
                                                        <td>
                                                            <span class="badge bg-success">
                                                                <?php echo number_format($student['avg_grade'], 2); ?>/20
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Aucune donnée disponible</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Grades Table -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    Notes Récentes
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Étudiant</th>
                                                <th>Module</th>
                                                <th>Note</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (isset($recent_grades) && count($recent_grades) > 0): ?>
                                                <?php foreach ($recent_grades as $grade): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y', strtotime($grade['date_added'])); ?></td>
                                                        <td><?php echo htmlspecialchars($grade['prenom'] . ' ' . $grade['nom']); ?></td>
                                                        <td><?php echo htmlspecialchars($grade['module']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $badge_class = 'bg-danger';
                                                            if ($grade['grade'] >= 10 && $grade['grade'] < 12) {
                                                                $badge_class = 'bg-warning';
                                                            } elseif ($grade['grade'] >= 12 && $grade['grade'] < 14) {
                                                                $badge_class = 'bg-info';
                                                            } elseif ($grade['grade'] >= 14) {
                                                                $badge_class = 'bg-success';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $badge_class; ?>">
                                                                <?php echo number_format($grade['grade'], 2); ?>/20
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Aucune donnée disponible</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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
            // Average Grades by Filiere Chart
            <?php if (isset($avg_grades_by_filiere) && count($avg_grades_by_filiere) > 0): ?>
            var avgGradesByFiliereCtx = document.getElementById('avgGradesByFiliereChart').getContext('2d');
            var avgGradesByFiliereChart = new Chart(avgGradesByFiliereCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item['name']) . '"'; }, $avg_grades_by_filiere)); ?>],
                    datasets: [{
                        label: 'Moyenne des notes',
                        data: [<?php echo implode(', ', array_map(function($item) { return round($item['avg_grade'], 2); }, $avg_grades_by_filiere)); ?>],
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
            
            // Grade Distribution Chart
            <?php if (isset($grade_distribution) && count($grade_distribution) > 0): ?>
            var gradeDistributionCtx = document.getElementById('gradeDistributionChart').getContext('2d');
            var gradeDistributionChart = new Chart(gradeDistributionCtx, {
                type: 'pie',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item['grade_range']) . '"'; }, $grade_distribution)); ?>],
                    datasets: [{
                        data: [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $grade_distribution)); ?>],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 159, 64, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(153, 102, 255, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            <?php endif; ?>
            
            // Average Grades by Module Chart
            <?php if (isset($avg_grades_by_module) && count($avg_grades_by_module) > 0): ?>
            var avgGradesByModuleCtx = document.getElementById('avgGradesByModuleChart').getContext('2d');
            var avgGradesByModuleChart = new Chart(avgGradesByModuleCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item['name']) . '"'; }, $avg_grades_by_module)); ?>],
                    datasets: [{
                        label: 'Moyenne des notes',
                        data: [<?php echo implode(', ', array_map(function($item) { return round($item['avg_grade'], 2); }, $avg_grades_by_module)); ?>],
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
                            beginAtZero: true,
                            max: 20
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
