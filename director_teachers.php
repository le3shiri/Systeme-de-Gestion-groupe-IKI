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
$teachers = [];
$modules = [];
$search_term = '';
$module_filter = '';
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
    
    // Get all modules for filter dropdown
    $stmt = $pdo->query("SELECT id, name FROM modules ORDER BY name");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build the query based on filters
    $query = "
        SELECT t.*, GROUP_CONCAT(DISTINCT m.name SEPARATOR ', ') as modules
        FROM teachers t
        LEFT JOIN teacher_module_assignments tm ON t.id = tm.teacher_id
        LEFT JOIN modules m ON tm.module_id = m.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply search filter if provided
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = $_GET['search'];
        $query .= " AND (t.nom LIKE ? OR t.prenom LIKE ? OR t.cni LIKE ? OR t.email LIKE ?)";
        $search_param = "%$search_term%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    // Apply module filter if provided
    if (isset($_GET['module']) && !empty($_GET['module'])) {
        $module_filter = $_GET['module'];
        $query .= " AND EXISTS (SELECT 1 FROM teacher_module_assignments tm WHERE tm.teacher_id = t.id AND tm.module_id = ?)";
        $params[] = $module_filter;
    }
    
    $query .= " GROUP BY t.id ORDER BY t.nom, t.prenom";
    
    // Execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get teacher statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM teachers");
    $teacher_count = $stmt->fetchColumn();
    
    // Get module assignment statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT teacher_id) as assigned_teachers,
            COUNT(DISTINCT module_id) as assigned_modules
        FROM teacher_module_assignments
    ");
    $assignment_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Enseignants - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        .teacher-card {
            transition: transform 0.2s;
        }
        .teacher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
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
                            <a class="nav-link active" href="director_teachers.php">
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
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        Gestion des Enseignants
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

                <!-- Teacher Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-primary text-white mx-auto">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <h5 class="card-title">Total Enseignants</h5>
                                <h2 class="display-6 fw-bold"><?php echo $teacher_count; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-success text-white mx-auto">
                                    <i class="fas fa-book"></i>
                                </div>
                                <h5 class="card-title">Modules Assignés</h5>
                                <h2 class="display-6 fw-bold"><?php echo $assignment_stats['assigned_modules'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-info text-white mx-auto">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <h5 class="card-title">Enseignants Assignés</h5>
                                <h2 class="display-6 fw-bold"><?php echo $assignment_stats['assigned_teachers'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Rechercher par nom, prénom, CNI ou email" value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
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
                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Teachers List -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Liste des Enseignants</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>CNI</th>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Modules</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($teachers) > 0): ?>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($teacher['cni']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['nom']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['prenom']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['email'] ?? 'Non renseigné'); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['telephone'] ?? 'Non renseigné'); ?></td>
                                                <td>
                                                    <?php if (!empty($teacher['modules'])): ?>
                                                        <?php echo htmlspecialchars($teacher['modules']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Aucun module assigné</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="view_teacher.php?cni=<?php echo $teacher['cni']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="assign_teacher.php?teacher_id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-tasks"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucun enseignant trouvé</td>
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
</body>
</html>
