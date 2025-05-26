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
    
    // Fetch filieres with module count
    $stmt = $pdo->query("
        SELECT f.id, f.name, f.description, COUNT(m.id) as module_count
        FROM filieres f
        LEFT JOIN modules m ON f.id = m.filiere_id
        GROUP BY f.id, f.name, f.description
        ORDER BY f.name
    ");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch modules with filiere names
    $stmt = $pdo->query("
        SELECT m.id, m.name, f.name as filiere_name, f.id as filiere_id
        FROM modules m
        LEFT JOIN filieres f ON m.filiere_id = f.id
        ORDER BY f.name, m.name
    ");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database connection failed.';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_filiere') {
        $name = trim($_POST['filiere_name'] ?? '');
        $description = trim($_POST['filiere_description'] ?? '');
        
        if (empty($name)) {
            $error_message = 'Please enter a filière name.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO filieres (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $success_message = 'Filière added successfully!';
                
                // Refresh filieres list
                $stmt = $pdo->query("
                    SELECT f.id, f.name, f.description, COUNT(m.id) as module_count
                    FROM filieres f
                    LEFT JOIN modules m ON f.id = m.filiere_id
                    GROUP BY f.id, f.name, f.description
                    ORDER BY f.name
                ");
                $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                $error_message = 'Error adding filière. Please try again.';
            }
        }
    } elseif ($action === 'add_module') {
        $name = trim($_POST['module_name'] ?? '');
        $filiere_id = trim($_POST['module_filiere_id'] ?? '');
        
        if (empty($name) || empty($filiere_id)) {
            $error_message = 'Please fill in all required fields for the module.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO modules (name, filiere_id) VALUES (?, ?)");
                $stmt->execute([$name, $filiere_id]);
                $success_message = 'Module added successfully!';
                
                // Refresh modules list
                $stmt = $pdo->query("
                    SELECT m.id, m.name, f.name as filiere_name, f.id as filiere_id
                    FROM modules m
                    LEFT JOIN filieres f ON m.filiere_id = f.id
                    ORDER BY f.name, m.name
                ");
                $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Refresh filieres list to update module counts
                $stmt = $pdo->query("
                    SELECT f.id, f.name, f.description, COUNT(m.id) as module_count
                    FROM filieres f
                    LEFT JOIN modules m ON f.id = m.filiere_id
                    GROUP BY f.id, f.name, f.description
                    ORDER BY f.name
                ");
                $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                $error_message = 'Error adding module. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Filières & Modules - Groupe IKI</title>
    
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
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top border-bottom shadow-sm">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center" href="dashboard_admin.php">
                <!-- <i class="fas fa-graduation-cap me-2"></i> -->
                <!-- <span class="fw-bold">Groupe IKI</span> -->
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
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
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
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_filieres_modules.php">
                                <i class="fas fa-book me-2"></i>
                                Filières & Modules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                Manage Grades
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="record_absence.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                Manage Absences
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="send_message.php">
                                <i class="fas fa-paper-plane me-2"></i>
                                Send Messages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_messages.php">
                                <i class="fas fa-inbox me-2"></i>
                                View Messages
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-book me-2"></i>
                        Manage Filières & Modules
                    </h1>
                    <button class="btn btn-outline-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <!-- Success/Error Messages -->
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Add Forms -->
                    <div class="col-lg-4">
                        <!-- Add Filière Form -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus me-2"></i>
                                    Add New Filière
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="manage_filieres_modules.php">
                                    <input type="hidden" name="action" value="add_filiere">
                                    
                                    <div class="mb-3">
                                        <label for="filiere_name" class="form-label">
                                            <i class="fas fa-graduation-cap me-2"></i>Filière Name *
                                        </label>
                                        <input type="text" class="form-control" id="filiere_name" name="filiere_name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="filiere_description" class="form-label">
                                            <i class="fas fa-info-circle me-2"></i>Description
                                        </label>
                                        <textarea class="form-control" id="filiere_description" name="filiere_description" rows="3"></textarea>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Add Filière
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Add Module Form -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus me-2"></i>
                                    Add New Module
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="manage_filieres_modules.php">
                                    <input type="hidden" name="action" value="add_module">
                                    
                                    <div class="mb-3">
                                        <label for="module_name" class="form-label">
                                            <i class="fas fa-book me-2"></i>Module Name *
                                        </label>
                                        <input type="text" class="form-control" id="module_name" name="module_name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="module_filiere_id" class="form-label">
                                            <i class="fas fa-graduation-cap me-2"></i>Filière *
                                        </label>
                                        <select class="form-select" id="module_filiere_id" name="module_filiere_id" required>
                                            <option value="">Select Filière</option>
                                            <?php foreach ($filieres as $filiere): ?>
                                            <option value="<?php echo $filiere['id']; ?>">
                                                <?php echo htmlspecialchars($filiere['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-plus me-2"></i>Add Module
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Lists -->
                    <div class="col-lg-8">
                        <!-- Filières List -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-graduation-cap me-2"></i>
                                    Filières
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($filieres)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No filières found</h5>
                                    <p class="text-muted">Start by adding your first filière.</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Modules</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($filieres as $filiere): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($filiere['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($filiere['description'] ?: 'No description'); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo $filiere['module_count']; ?> modules
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Modules List -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-book me-2"></i>
                                    Modules
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($modules)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No modules found</h5>
                                    <p class="text-muted">Start by adding your first module.</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-success">
                                            <tr>
                                                <th>Module Name</th>
                                                <th>Filière</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($modules as $module): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-book me-2 text-primary"></i>
                                                    <strong><?php echo htmlspecialchars($module['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($module['filiere_name']): ?>
                                                        <span class="badge bg-secondary">
                                                            <?php echo htmlspecialchars($module['filiere_name']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">No filière assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/scripts.js"></script>
</body>
</html>
