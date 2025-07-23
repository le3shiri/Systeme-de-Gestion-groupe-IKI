<?php
session_start();

// Check if user is logged in and is a director
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'director') {
    header('Location: login.php');
    exit();
}

// Database connection parameters
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

// Get user CNI from session
$user_cni = $_SESSION['user_cni'];

// Initialize variables
$absences = [];
$filieres = [];
$modules = [];
$search_term = '';
$filiere_filter = '';
$module_filter = '';
$date_filter = '';
$justified_filter = '';
$error_message = '';
$success_message = '';
$director = [
    'nom' => 'Directeur',
    'prenom' => '',
    'cni' => $user_cni
];

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get director details
    $stmt = $pdo->prepare("SELECT * FROM directors WHERE cni = ?");
    $stmt->execute([$user_cni]);
    $director = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If director not found, create a default array to avoid undefined variable errors
    if (!$director) {
        $director = [
            'nom' => 'Directeur',
            'prenom' => '',
            'cni' => $user_cni
        ];
    }
    
    // Get all filieres for filter dropdown
    $stmt = $pdo->query("SELECT id, name FROM filieres ORDER BY name");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all modules for filter dropdown
    $stmt = $pdo->query("SELECT id, name FROM modules ORDER BY name");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build the query based on filters
    $query = "
        SELECT a.*, s.nom, s.prenom, s.cni as student_cni, m.name as module_name, f.name as filiere_name
        FROM absences a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN modules m ON a.module_id = m.id
        LEFT JOIN filieres f ON s.filiere_id = f.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply search filter if provided
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = $_GET['search'];
        $query .= " AND (s.nom LIKE ? OR s.prenom LIKE ? OR s.cni LIKE ?)";
        $search_param = "%$search_term%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    // Apply filiere filter if provided
    if (isset($_GET['filiere']) && !empty($_GET['filiere'])) {
        $filiere_filter = $_GET['filiere'];
        $query .= " AND s.filiere_id = ?";
        $params[] = $filiere_filter;
    }
    
    // Apply module filter if provided
    if (isset($_GET['module']) && !empty($_GET['module'])) {
        $module_filter = $_GET['module'];
        $query .= " AND a.module_id = ?";
        $params[] = $module_filter;
    }
    
    // Apply date filter if provided
    if (isset($_GET['date']) && !empty($_GET['date'])) {
        $date_filter = $_GET['date'];
        $query .= " AND DATE(a.created_at) = ?";
        $params[] = $date_filter;
    }
    
    // Apply status filter if provided
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $status_filter = $_GET['status'];
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY a.created_at DESC";
    
    // Execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get absence statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_absences,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            COUNT(DISTINCT student_id) as students_with_absences
        FROM absences
    ");
    $absence_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get top 5 modules with most absences
    $stmt = $pdo->query("
        SELECT m.name, COUNT(a.id) as absence_count
        FROM absences a
        JOIN modules m ON a.module_id = m.id
        GROUP BY a.module_id
        ORDER BY absence_count DESC
        LIMIT 5
    ");
    $top_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get absences by month for the last 12 months
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            DATE_FORMAT(created_at, '%b %Y') as month_name,
            COUNT(*) as absence_count
        FROM absences
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
        AND status = 'absent'
        GROUP BY month, month_name
        ORDER BY month ASC
    ");
    $monthly_absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Absences - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        .stats-card {
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }
        .badge-justified {
            background-color: #28a745;
        }
        .badge-unjustified {
            background-color: #dc3545;
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
                            <a class="nav-link active" href="director_absences.php">
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
                        <i class="fas fa-calendar-times me-2"></i>
                        Gestion des Absences
                    </h1>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Absence Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-danger text-white mx-auto">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <h5 class="card-title">Total Absences</h5>
                                <h2 class="display-6 fw-bold"><?php echo $absence_stats['total_absences'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-info text-white mx-auto">
                                    <i class="fas fa-list-check"></i>
                                </div>
                                <h5 class="card-title">Statuts d'Absences</h5>
                                <div class="d-flex justify-content-around mt-3">
                                    <div>
                                        <h4 class="mb-0 fw-bold"><?php echo $absence_stats['absent_count'] ?? 0; ?></h4>
                                        <small class="text-muted"><span class="badge bg-danger">Absents</span></small>
                                    </div>
                                    <div>
                                        <h4 class="mb-0 fw-bold"><?php echo $absence_stats['late_count'] ?? 0; ?></h4>
                                        <small class="text-muted"><span class="badge bg-warning">Retards</span></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-info text-white mx-auto">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h5 class="card-title">Étudiants Concernés</h5>
                                <h2 class="display-6 fw-bold"><?php echo $absence_stats['students_with_absences'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- Top Modules with Absences -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Modules avec le Plus d'Absences</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="topModulesChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Absences -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Absences par Mois</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyAbsencesChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Rechercher un étudiant" value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="filiere" class="form-select">
                                    <option value="">Toutes les filières</option>
                                    <?php foreach ($filieres as $filiere): ?>
                                        <option value="<?php echo $filiere['id']; ?>" <?php echo ($filiere_filter == $filiere['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($filiere['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="module" class="form-select">
                                    <option value="">Tous les modules</option>
                                    <?php foreach ($modules as $module): ?>
                                        <option value="<?php echo $module['id']; ?>" <?php echo ($module_filter == $module['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($module['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">Tous les statuts</option>
                                    <option value="absent" <?php echo (isset($_GET['status']) && $_GET['status'] === 'absent') ? 'selected' : ''; ?>>Absent</option>
                                    <option value="late" <?php echo (isset($_GET['status']) && $_GET['status'] === 'late') ? 'selected' : ''; ?>>Retard</option>
                                    <option value="present" <?php echo (isset($_GET['status']) && $_GET['status'] === 'present') ? 'selected' : ''; ?>>Présent</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Absences List -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Liste des Absences</h5>
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
                                        <th>Statut</th>
                                        <th>Raison</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($absences) > 0): ?>
                                        <?php foreach ($absences as $absence): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($absence['created_at'])); ?></td>
                                                <td>
                                                    <a href="view_student.php?cni=<?php echo $absence['student_cni']; ?>">
                                                        <?php echo htmlspecialchars($absence['prenom'] . ' ' . $absence['nom']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($absence['filiere_name'] ?? 'Non assigné'); ?></td>
                                                <td><?php echo htmlspecialchars($absence['module_name'] ?? 'Non assigné'); ?></td>
                                                <td>
                                                    <?php if ($absence['status'] == 'late'): ?>
                                                        <span class="badge bg-warning">Retard</span>
                                                    <?php elseif ($absence['status'] == 'present'): ?>
                                                        <span class="badge bg-success">Présent</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Absent</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($absence['status'] == 'late'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Retard">
                                                            Retard
                                                        </button>
                                                    <?php elseif ($absence['status'] == 'present'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Présent">
                                                            Présent
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Absent">
                                                            Absent
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="view_student_absences.php?cni=<?php echo $absence['student_cni']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-history"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucune absence trouvée</td>
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Top Modules Chart
            var topModulesCtx = document.getElementById('topModulesChart').getContext('2d');
            var topModulesChart = new Chart(topModulesCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php 
                        foreach ($top_modules as $module) {
                            echo "'" . addslashes($module['name']) . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Nombre d\'absences',
                        data: [
                            <?php 
                            foreach ($top_modules as $module) {
                                echo $module['absence_count'] . ", ";
                            }
                            ?>
                        ],
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
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Monthly Absences Chart (real data from database)
            var monthlyAbsencesCtx = document.getElementById('monthlyAbsencesChart').getContext('2d');
            var monthlyAbsencesChart = new Chart(monthlyAbsencesCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php 
                        if (!empty($monthly_absences)) {
                            foreach ($monthly_absences as $month_data) {
                                echo "'" . addslashes($month_data['month_name']) . "', ";
                            }
                        } else {
                            // Si aucune donnée, afficher un message
                            echo "'Aucune donnée'";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Absences par mois',
                        data: [
                            <?php 
                            if (!empty($monthly_absences)) {
                                foreach ($monthly_absences as $month_data) {
                                    echo $month_data['absence_count'] . ", ";
                                }
                            } else {
                                echo "0";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
