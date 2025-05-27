<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_cni'])) {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];
$user_role = $_SESSION['role'];

// Redirect non-students to dashboard
if ($user_role !== 'student') {
    header('Location: ' . ($user_role === 'admin' ? 'dashboard_admin.php' : 'dashboard_teacher.php'));
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $grades = [];
    $modules = [];
    $student_info = null;
    
    // Get student information
    $stmt = $pdo->prepare("
        SELECT s.id, s.prenom, s.nom, s.cni, f.id as filiere_id, f.name as filiere_name
        FROM students s
        JOIN filieres f ON s.filiere_id = f.id
        WHERE s.cni = ?
    ");
    $stmt->execute([$user_cni]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student_info) {
        // Get all modules for the student's filiere
        $stmt = $pdo->prepare("
            SELECT id, name, type
            FROM modules
            WHERE filiere_id = ?
            ORDER BY name
        ");
        $stmt->execute([$student_info['filiere_id']]);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all grades for the student
        $stmt = $pdo->prepare("
            SELECT g.module_id, g.grade_type, g.grade, g.date,
                   m.name as module_name, m.type as module_type,
                   f.name as filiere_name
            FROM grades g
            JOIN modules m ON g.module_id = m.id
            JOIN filieres f ON m.filiere_id = f.id
            WHERE g.student_id = ?
            ORDER BY m.name, g.grade_type
        ");
        $stmt->execute([$student_info['id']]);
        
        // Organize grades by module and grade type
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $grades[$row['module_id']][$row['grade_type']] = [
                'grade' => $row['grade'],
                'date' => $row['date'],
                'module_name' => $row['module_name'],
                'module_type' => $row['module_type'],
                'filiere_name' => $row['filiere_name']
            ];
        }
    }
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Helper function to determine badge color based on grade
function getBadgeColor($grade) {
    if ($grade >= 16) return 'bg-success';
    if ($grade >= 14) return 'bg-info';
    if ($grade >= 12) return 'bg-primary';
    if ($grade >= 10) return 'bg-warning';
    return 'bg-danger';
}

// Dashboard link and navbar color for student
$dashboard_link = 'dashboard_student.php';
$navbar_color = 'bg-info';
$user_icon = 'fa-user-graduate';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    <style>
        .grade-badge {
            min-width: 60px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg  <?php echo $navbar_color; ?> navbar-light bg-white fixed-top border-bottom shadow-sm">
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
                            Student (<?php echo htmlspecialchars($user_cni); ?>)
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
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="view_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                View Grades
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="student_absences.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                View Absences
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
                        My Grades
                    </h1>
                    <button class="btn btn-outline-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <!-- Student Information -->
                <?php if ($student_info): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-user-graduate me-2"></i>
                            Student Information
                        </h5>
                        <span class="badge bg-info">
                            <?php echo htmlspecialchars($student_info['filiere_name']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($student_info['prenom'] . ' ' . $student_info['nom']); ?></p>
                                <p><strong>CNI:</strong> <?php echo htmlspecialchars($student_info['cni']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Filière:</strong> <?php echo htmlspecialchars($student_info['filiere_name']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Grades Table -->
                <?php if (!empty($grades)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Your Grades
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Module</th>
                                        <th>Type</th>
                                        <th>CC1</th>
                                        <th>CC2</th>
                                        <th>CC3</th>
                                        <th>Théorique</th>
                                        <th>Pratique</th>
                                        <th>Final</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grades as $module_id => $module_grades): 
                                        $module_name = $module_grades[array_key_first($module_grades)]['module_name'] ?? 'Unknown';
                                        $module_type = $module_grades[array_key_first($module_grades)]['module_type'] ?? 'standard';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($module_name); ?></td>
                                        <td>
                                            <?php if ($module_type === 'pfe'): ?>
                                            <span class="badge bg-primary">PFE</span>
                                            <?php elseif ($module_type === 'stage'): ?>
                                            <span class="badge bg-success">Stage</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Standard</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($module_grades['cc1'])): ?>
                                            <span class="badge <?php echo getBadgeColor($module_grades['cc1']['grade']); ?> grade-badge">
                                                <?php echo number_format($module_grades['cc1']['grade'], 2); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($module_grades['cc2'])): ?>
                                            <span class="badge <?php echo getBadgeColor($module_grades['cc2']['grade']); ?> grade-badge">
                                                <?php echo number_format($module_grades['cc2']['grade'], 2); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($module_grades['cc3'])): ?>
                                            <span class="badge <?php echo getBadgeColor($module_grades['cc3']['grade']); ?> grade-badge">
                                                <?php echo number_format($module_grades['cc3']['grade'], 2); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($module_grades['theorique'])): ?>
                                            <span class="badge <?php echo getBadgeColor($module_grades['theorique']['grade']); ?> grade-badge">
                                                <?php echo number_format($module_grades['theorique']['grade'], 2); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($module_grades['pratique'])): ?>
                                            <span class="badge <?php echo getBadgeColor($module_grades['pratique']['grade']); ?> grade-badge">
                                                <?php echo number_format($module_grades['pratique']['grade'], 2); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($module_grades['pfe']) || isset($module_grades['stage'])): ?>
                                            <span class="badge <?php echo getBadgeColor($module_grades[$module_type]['grade']); ?> grade-badge">
                                                <?php echo number_format($module_grades[$module_type]['grade'], 2); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Grade Statistics -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6 class="mb-3">Grade Statistics</h6>
                            </div>
                            <?php
                            // Calculate statistics
                            $moduleCount = count($grades);
                            $passedModules = 0;
                            
                            foreach ($grades as $module_id => $module_grades) {
                                $module_type = $module_grades[array_key_first($module_grades)]['module_type'] ?? 'standard';
                                
                                // Check if module is passed
                                $passed = false;
                                if ($module_type === 'pfe' || $module_type === 'stage') {
                                    if (isset($module_grades[$module_type]['grade']) && $module_grades[$module_type]['grade'] >= 10) {
                                        $passed = true;
                                    }
                                } else {
                                    // For standard modules, check if theoretical exam is passed
                                    if (isset($module_grades['theorique']['grade']) && $module_grades['theorique']['grade'] >= 10) {
                                        $passed = true;
                                    }
                                }
                                
                                if ($passed) {
                                    $passedModules++;
                                }
                            }
                            
                            $passRate = $moduleCount > 0 ? ($passedModules / $moduleCount) * 100 : 0;
                            ?>
                            
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5><?php echo $moduleCount; ?></h5>
                                        <small>Total Modules</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5><?php echo $passedModules; ?></h5>
                                        <small>Passed Modules</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h5><?php echo number_format($passRate, 1); ?>%</h5>
                                        <small>Pass Rate</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No grades have been recorded for you yet.
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/scripts.js"></script>
</body>
</html>