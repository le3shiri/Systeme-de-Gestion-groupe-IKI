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
$students = [];
$filieres = [];
$search_term = '';
$filiere_filter = '';
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
    
    // Build the query based on filters
    $query = "
        SELECT s.*, f.name as filiere_name
        FROM students s
        LEFT JOIN filieres f ON s.filiere_id = f.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply search filter if provided
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = $_GET['search'];
        $query .= " AND (s.nom LIKE ? OR s.prenom LIKE ? OR s.cni LIKE ? OR s.email LIKE ?)";
        $search_param = "%$search_term%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    // Apply filiere filter if provided
    if (isset($_GET['filiere']) && !empty($_GET['filiere'])) {
        $filiere_filter = $_GET['filiere'];
        $query .= " AND s.filiere_id = ?";
        $params[] = $filiere_filter;
    }
    
    $query .= " ORDER BY s.nom, s.prenom";
    
    // Execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Étudiants - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        .student-card {
            transition: transform 0.2s;
        }
        .student-card:hover {
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
                            <a class="nav-link active" href="director_students.php">
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
                        <i class="fas fa-user-graduate me-2"></i>
                        Gestion des Étudiants
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

                <!-- Student Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-primary text-white mx-auto">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h5 class="card-title">Total Étudiants</h5>
                                <h2 class="display-6 fw-bold"><?php echo count($students); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-info text-white mx-auto">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <h5 class="card-title">Filières</h5>
                                <h2 class="display-6 fw-bold"><?php echo count($filieres); ?></h2>
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
                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Students List -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Liste des Étudiants</h5>
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
                                        <th>Filière</th>
                                        <th>Niveau</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($students) > 0): ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['cni']); ?></td>
                                                <td><?php echo htmlspecialchars($student['nom']); ?></td>
                                                <td><?php echo htmlspecialchars($student['prenom']); ?></td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                <td><?php echo htmlspecialchars($student['filiere_name'] ?? 'Non assigné'); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $student['niveau'])) ?? 'Non assigné'); ?></td>
                                                <td>
                                                    <a href="view_student.php?cni=<?php echo $student['cni']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="view_grades.php?student_cni=<?php echo $student['cni']; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-chart-bar"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucun étudiant trouvé</td>
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
</body>
</html>
