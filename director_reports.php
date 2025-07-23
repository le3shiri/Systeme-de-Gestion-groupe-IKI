<?php
session_start();

// Check if user is logged in and is a director
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'director') {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];
$error_message = '';
$success_message = '';

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
    
    // Get available reports
    $reports = [
        [
            'id' => 'student_performance',
            'title' => 'Performance des Étudiants',
            'description' => 'Rapport détaillé sur les performances académiques des étudiants par filière et module.',
            'icon' => 'chart-line',
            'color' => 'primary'
        ],
        [
            'id' => 'attendance',
            'title' => 'Rapport de Présence',
            'description' => 'Analyse des taux de présence et d\'absence par filière, module et période.',
            'icon' => 'calendar-check',
            'color' => 'success'
        ],
        [
            'id' => 'teacher_activity',
            'title' => 'Activité des Enseignants',
            'description' => 'Suivi de l\'activité des enseignants, des modules enseignés et des évaluations effectuées.',
            'icon' => 'chalkboard-teacher',
            'color' => 'info'
        ],
        [
            'id' => 'module_analysis',
            'title' => 'Analyse des Modules',
            'description' => 'Analyse comparative des résultats par module et identification des modules à problèmes.',
            'icon' => 'book',
            'color' => 'warning'
        ],
        [
            'id' => 'yearly_summary',
            'title' => 'Bilan Annuel',
            'description' => 'Synthèse annuelle des résultats académiques, taux de réussite et statistiques globales.',
            'icon' => 'file-alt',
            'color' => 'danger'
        ]
    ];
    
    // Handle report generation request
    if (isset($_POST['generate_report'])) {
        $report_id = $_POST['report_id'] ?? '';
        $report_format = $_POST['report_format'] ?? 'pdf';
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';
        $filiere_id = $_POST['filiere_id'] ?? '';
        
        // Here would be the actual report generation logic
        // For now, just set a success message
        $success_message = "Le rapport a été généré avec succès. Téléchargement en cours...";
    }
    
    // Get all filieres for filter dropdown
    $stmt = $pdo->query("SELECT id, name FROM filieres ORDER BY name");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    
    <style>
        .report-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .report-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 15px;
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
                            <a class="nav-link" href="view_schedules_director.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Emplois du Temps
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="director_reports.php">
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
                        <i class="fas fa-file-alt me-2"></i>
                        Rapports et Analyses
                    </h1>
                </div>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>

                <!-- Report Types -->
                <div class="row g-4 mb-4">
                    <?php foreach ($reports as $report): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card report-card h-100 border-0" data-bs-toggle="modal" data-bs-target="#reportModal" data-report-id="<?php echo $report['id']; ?>" data-report-title="<?php echo $report['title']; ?>">
                            <div class="card-body text-center">
                                <div class="report-icon bg-<?php echo $report['color']; ?> bg-opacity-10 text-<?php echo $report['color']; ?> mx-auto">
                                    <i class="fas fa-<?php echo $report['icon']; ?> fa-2x"></i>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($report['title']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($report['description']); ?></p>
                                <button class="btn btn-sm btn-outline-<?php echo $report['color']; ?>">
                                    <i class="fas fa-file-download me-2"></i>
                                    Générer le rapport
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Recent Reports -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Rapports Récents</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type de Rapport</th>
                                        <th>Généré par</th>
                                        <th>Format</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>23/07/2025</td>
                                        <td>Performance des Étudiants</td>
                                        <td><?php echo htmlspecialchars($director['prenom'] . ' ' . $director['nom']); ?></td>
                                        <td><span class="badge bg-danger">PDF</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>22/07/2025</td>
                                        <td>Rapport de Présence</td>
                                        <td><?php echo htmlspecialchars($director['prenom'] . ' ' . $director['nom']); ?></td>
                                        <td><span class="badge bg-success">Excel</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Report Generation Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Générer un Rapport</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="report_id" id="reportIdInput">
                        
                        <div class="mb-3">
                            <label for="reportTitle" class="form-label">Type de Rapport</label>
                            <input type="text" class="form-control" id="reportTitle" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reportFormat" class="form-label">Format</label>
                            <select class="form-select" id="reportFormat" name="report_format">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="dateStart" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="dateStart" name="date_start">
                        </div>
                        
                        <div class="mb-3">
                            <label for="dateEnd" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="dateEnd" name="date_end">
                        </div>
                        
                        <div class="mb-3">
                            <label for="filiereSelect" class="form-label">Filière (optionnel)</label>
                            <select class="form-select" id="filiereSelect" name="filiere_id">
                                <option value="">Toutes les filières</option>
                                <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?php echo $filiere['id']; ?>"><?php echo htmlspecialchars($filiere['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="generate_report" class="btn btn-primary">Générer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set up report modal
            var reportModal = document.getElementById('reportModal');
            if (reportModal) {
                reportModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    var reportId = button.getAttribute('data-report-id');
                    var reportTitle = button.getAttribute('data-report-title');
                    
                    var reportIdInput = document.getElementById('reportIdInput');
                    var reportTitleInput = document.getElementById('reportTitle');
                    
                    reportIdInput.value = reportId;
                    reportTitleInput.value = reportTitle;
                });
            }
        });
    </script>
</body>
</html>
