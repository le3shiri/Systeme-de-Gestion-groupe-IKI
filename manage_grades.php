<?php
session_start();

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_cni']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];
$user_role = $_SESSION['role'];
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
    
    // Get teacher ID if user is a teacher
    $teacher_id = null;
    if ($user_role === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE cni = ?");
        $stmt->execute([$user_cni]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        $teacher_id = $teacher['id'] ?? null;
    }
    
    // Fetch students for dropdown
    $stmt = $pdo->query("
        SELECT s.id, s.nom, s.prenom, s.cni, f.name as filiere_name 
        FROM students s 
        LEFT JOIN filieres f ON s.filiere_id = f.id 
        ORDER BY s.nom, s.prenom
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch modules for dropdown
    $stmt = $pdo->query("
        SELECT m.id, m.name, f.name as filiere_name 
        FROM modules m 
        LEFT JOIN filieres f ON m.filiere_id = f.id 
        ORDER BY f.name, m.name
    ");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch recent grades
    $stmt = $pdo->query("
        SELECT g.id, g.grade, g.date, 
               CONCAT(s.prenom, ' ', s.nom) as student_name, s.cni as student_cni,
               m.name as module_name, f.name as filiere_name,
               CONCAT(t.prenom, ' ', t.nom) as teacher_name
        FROM grades g
        JOIN students s ON g.student_id = s.id
        JOIN modules m ON g.module_id = m.id
        JOIN filieres f ON m.filiere_id = f.id
        LEFT JOIN teachers t ON g.recorded_by_teacher_id = t.id
        ORDER BY g.date DESC
        LIMIT 50
    ");
    $recent_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database connection failed.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $module_id = trim($_POST['module_id'] ?? '');
    $grade = trim($_POST['grade'] ?? '');
    $date = trim($_POST['date'] ?? '');
    
    if (empty($student_id) || empty($module_id) || empty($grade) || empty($date)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!is_numeric($grade) || $grade < 0 || $grade > 20) {
        $error_message = 'Grade must be a number between 0 and 20.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO grades (student_id, module_id, grade, date, recorded_by_teacher_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $module_id, $grade, $date, $teacher_id]);
            
            $success_message = 'Grade recorded successfully!';
            
            // Refresh recent grades
            $stmt = $pdo->query("
                SELECT g.id, g.grade, g.date, 
                       CONCAT(s.prenom, ' ', s.nom) as student_name, s.cni as student_cni,
                       m.name as module_name, f.name as filiere_name,
                       CONCAT(t.prenom, ' ', t.nom) as teacher_name
                FROM grades g
                JOIN students s ON g.student_id = s.id
                JOIN modules m ON g.module_id = m.id
                JOIN filieres f ON m.filiere_id = f.id
                LEFT JOIN teachers t ON g.recorded_by_teacher_id = t.id
                ORDER BY g.date DESC
                LIMIT 50
            ");
            $recent_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error_message = 'Error recording grade. Please try again.';
        }
    }
}

// Determine navbar color based on role
$navbar_color = $user_role === 'admin' ? 'bg-primary' : 'bg-success';
$user_icon = $user_role === 'admin' ? 'fa-user-shield' : 'fa-chalkboard-teacher';
$dashboard_link = $user_role === 'admin' ? 'dashboard_admin.php' : 'dashboard_teacher.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades - Groupe IKI</title>
    
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
    <nav class="navbar navbar-expand-lg navbar-dark <?php echo $navbar_color; ?> fixed-top">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $dashboard_link; ?>">
                <i class="fas fa-graduation-cap me-2"></i>
                <span class="fw-bold">Groupe IKI</span>
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
                            <?php echo ucfirst($user_role); ?> (<?php echo htmlspecialchars($user_cni); ?>)
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
                            <a class="nav-link" href="<?php echo $dashboard_link; ?>">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        
                        <?php if ($user_role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_filieres_modules.php">
                                <i class="fas fa-book me-2"></i>
                                Filières & Modules
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_grades.php">
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
                        <i class="fas fa-chart-line me-2"></i>
                        Manage Grades
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
    <?php if (!isset($_GET['filiere_id'])): ?>
    <!-- Filière Selection -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-book me-2"></i>
                    Select Filière to Manage Grades
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    // Fetch filières with student count
                    $stmt = $pdo->query("
                        SELECT f.id, f.name, f.description, COUNT(s.id) as student_count
                        FROM filieres f
                        LEFT JOIN students s ON f.id = s.filiere_id
                        GROUP BY f.id, f.name, f.description
                        ORDER BY f.name
                    ");
                    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($filieres as $filiere):
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 filiere-card" style="cursor: pointer;" onclick="selectFiliere(<?php echo $filiere['id']; ?>)">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-graduation-cap fa-3x text-primary"></i>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($filiere['name']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($filiere['description']); ?></p>
                                <div class="mt-3">
                                    <span class="badge bg-info fs-6">
                                        <i class="fas fa-users me-1"></i>
                                        <?php echo $filiere['student_count']; ?> Students
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($filieres)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Filières Found</h5>
                    <p class="text-muted">Please add filières first to manage grades.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php else: 
    // Get selected filière info
    $filiere_id = intval($_GET['filiere_id']);
    $stmt = $pdo->prepare("SELECT name FROM filieres WHERE id = ?");
    $stmt->execute([$filiere_id]);
    $selected_filiere = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$selected_filiere) {
        echo '<div class="alert alert-danger">Invalid filière selected.</div>';
        exit;
    }
    
    // Get students from selected filière
    $filiere_students = [];
    $search_term = isset($_GET['search_student']) ? $_GET['search_student'] : '';
    $module_filter = isset($_GET['filter_module']) ? $_GET['filter_module'] : '';

    $sql = "SELECT s.id, s.nom, s.prenom, s.cni FROM students s WHERE s.filiere_id = ?";
    $params = [$filiere_id];

    if (!empty($search_term)) {
        $sql .= " AND (s.nom LIKE ? OR s.prenom LIKE ? OR s.cni LIKE ?)";
        $search_param = '%' . $search_term . '%';
        $params = [$filiere_id, $search_param, $search_param, $search_param];
    }

    $sql .= " ORDER BY s.nom, s.prenom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filiere_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get modules from selected filière
    $stmt = $pdo->prepare("
        SELECT m.id, m.name
        FROM modules m 
        WHERE m.filiere_id = ?
        ORDER BY m.name
    ");
    $stmt->execute([$filiere_id]);
    $filiere_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent grades for this filière
    $stmt = $pdo->prepare("
        SELECT g.id, g.grade, g.date, 
               CONCAT(s.prenom, ' ', s.nom) as student_name, s.cni as student_cni,
               m.name as module_name,
               CONCAT(t.prenom, ' ', t.nom) as teacher_name
        FROM grades g
        JOIN students s ON g.student_id = s.id
        JOIN modules m ON g.module_id = m.id
        LEFT JOIN teachers t ON g.recorded_by_teacher_id = t.id
        WHERE s.filiere_id = ?
        ORDER BY g.date DESC
        LIMIT 30
    ");
    $stmt->execute([$filiere_id]);
    $filiere_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <!-- Breadcrumb -->
    <div class="col-12 mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="manage_grades.php" class="text-decoration-none">
                        <i class="fas fa-chart-line me-1"></i>Manage Grades
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <i class="fas fa-graduation-cap me-1"></i>
                    <?php echo htmlspecialchars($selected_filiere['name']); ?>
                </li>
            </ol>
        </nav>
    </div>
    
    <!-- Add Grade Form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Record Grade - <?php echo htmlspecialchars($selected_filiere['name']); ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="manage_grades.php?filiere_id=<?php echo $filiere_id; ?>" id="gradeForm" novalidate>
                    <div class="mb-3">
                        <label for="student_id" class="form-label">
                            <i class="fas fa-user-graduate me-2"></i>Student *
                        </label>
                        <select class="form-select" id="student_id" name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($filiere_students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom'] . ' (' . $student['cni'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a student.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="module_id" class="form-label">
                            <i class="fas fa-book me-2"></i>Module *
                        </label>
                        <select class="form-select" id="module_id" name="module_id" required>
                            <option value="">Select Module</option>
                            <?php foreach ($filiere_modules as $module): ?>
                            <option value="<?php echo $module['id']; ?>">
                                <?php echo htmlspecialchars($module['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a module.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="grade" class="form-label">
                            <i class="fas fa-star me-2"></i>Grade (0-20) *
                        </label>
                        <input type="number" 
                               class="form-control" 
                               id="grade" 
                               name="grade" 
                               min="0" 
                               max="20" 
                               step="0.25" 
                               required>
                        <div class="invalid-feedback">
                            Please enter a valid grade (0-20).
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="date" class="form-label">
                            <i class="fas fa-calendar me-2"></i>Date *
                        </label>
                        <input type="date" 
                               class="form-control" 
                               id="date" 
                               name="date" 
                               value="<?php echo date('Y-m-d'); ?>"
                               required>
                        <div class="invalid-feedback">
                            Please select a date.
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span class="btn-text">
                                <i class="fas fa-save me-2"></i>Record Grade
                            </span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Recording...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Grades for this Filière -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>
                    Recent Grades - <?php echo htmlspecialchars($selected_filiere['name']); ?>
                </h5>
                <span class="badge bg-primary">
                    <?php echo count($filiere_grades); ?> Records
                </span>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="" class="mb-3">
                    <input type="hidden" name="filiere_id" value="<?php echo $filiere_id; ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="search_student" class="form-label">Search Student</label>
                            <input type="text" class="form-control" id="search_student" name="search_student" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Name or CNI">
                        </div>
                        <div class="col-md-4">
                            <label for="filter_module" class="form-label">Filter by Module</label>
                            <select class="form-select" id="filter_module" name="filter_module">
                                <option value="">All Modules</option>
                                <?php foreach ($filiere_modules as $module): ?>
                                <option value="<?php echo $module['id']; ?>" <?php if ($module_filter == $module['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($module['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                            <a href="manage_grades.php?filiere_id=<?php echo $filiere_id; ?>" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>

                <?php if (empty($filiere_grades)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No grades recorded yet</h5>
                    <p class="text-muted">Start by recording grades for students in this filière.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>Student</th>
                                <th>Module</th>
                                <th>Grade</th>
                                <th>Date</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filiere_grades as $grade): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($grade['student_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($grade['student_cni']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <i class="fas fa-book me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($grade['module_name']); ?>
                                </td>
                                <td>
                                    <?php
                                    $gradeValue = floatval($grade['grade']);
                                    $badgeClass = 'bg-danger';
                                    if ($gradeValue >= 16) $badgeClass = 'bg-success';
                                    elseif ($gradeValue >= 12) $badgeClass = 'bg-warning';
                                    elseif ($gradeValue >= 10) $badgeClass = 'bg-info';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> fs-6">
                                        <?php echo number_format($gradeValue, 2); ?>/20
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-calendar me-2 text-muted"></i>
                                    <?php echo date('d/m/Y', strtotime($grade['date'])); ?>
                                </td>
                                <td>
                                    <?php if ($grade['teacher_name']): ?>
                                        <i class="fas fa-chalkboard-teacher me-2 text-success"></i>
                                        <?php echo htmlspecialchars($grade['teacher_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
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
    <?php endif; ?>
</div>

<script>
function selectFiliere(filiereId) {
    window.location.href = 'manage_grades.php?filiere_id=' + filiereId;
}
</script>

<style>
.filiere-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.filiere-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: var(--bs-primary);
}
</style>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/scripts.js"></script>
</body>
</html>
    