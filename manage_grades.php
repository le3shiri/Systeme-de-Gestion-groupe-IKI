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
    
    // Handle grade submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_grades'])) {
        $filiere_id = intval($_POST['filiere_id']);
        $module_id = intval($_POST['module_id']);
        $student_grades = $_POST['grades'] ?? [];
        
        try {
            $pdo->beginTransaction();
            
            // Get teacher ID for recording
            if ($user_role === 'teacher') {
                $stmt = $pdo->prepare("SELECT id FROM teachers WHERE cni = ?");
                $stmt->execute([$user_cni]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                $teacher_id = $teacher['id'] ?? null;
                
                if (!$teacher_id) {
                    throw new Exception("Teacher record not found.");
                }
                
                // Verify teacher has access to this module/filiere
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM teacher_module_assignments 
                    WHERE teacher_cni = ? AND module_id = ? AND filiere_id = ? AND is_active = TRUE
                ");
                $stmt->execute([$user_cni, $module_id, $filiere_id]);
                if ($stmt->fetchColumn() == 0) {
                    throw new Exception("You don't have permission to record grades for this module in this filière.");
                }
            } else {
                // Get admin ID for recording
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE cni = ?");
                $stmt->execute([$user_cni]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                $admin_id = $admin['id'] ?? null;
                
                if (!$admin_id) {
                    throw new Exception("Admin record not found.");
                }
            }
            
            // Insert or update grades
            $insert_stmt = $pdo->prepare("
                INSERT INTO grades (student_id, module_id, grade_type, grade, date) 
                VALUES (?, ?, ?, ?, CURRENT_DATE())
                ON DUPLICATE KEY UPDATE 
                grade = VALUES(grade), 
                date = CURRENT_DATE()
            ");
            
            $grades_count = 0;
            foreach ($student_grades as $student_id => $grade_types) {
                foreach ($grade_types as $grade_type => $grade) {
                    if (trim($grade) !== '') {
                        $grade = floatval($grade);
                        if ($grade < 0) $grade = 0;
                        if ($grade > 20) $grade = 20;
                        
                        $insert_stmt->execute([$student_id, $module_id, $grade_type, $grade]);
                        $grades_count++;
                    }
                }
            }
            
            $pdo->commit();
            
            // Store success message in session
            $_SESSION['success_message'] = "Successfully recorded $grades_count grades for the selected module.";
            
            // Redirect to prevent form resubmission
            header("Location: manage_grades.php?filiere_id=$filiere_id&module_id=$module_id");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error_message = 'Error recording grades: ' . $e->getMessage();
        }
    }
    
    // Check for session messages
    if (isset($_SESSION['success_message'])) {
        $success_message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }
    
    // Ensure PFE and Stage modules exist for all filières
    try {
        // Get all filières
        $all_filieres_stmt = $pdo->query("SELECT id, name FROM filieres");
        $all_filieres = $all_filieres_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_filieres as $filiere) {
            // Check if PFE module exists for this filière
            $check_pfe = $pdo->prepare("
                SELECT COUNT(*) FROM modules 
                WHERE filiere_id = ? AND type = 'pfe'
            ");
            $check_pfe->execute([$filiere['id']]);
            
            if ($check_pfe->fetchColumn() == 0) {
                // Create PFE module
                $create_pfe = $pdo->prepare("
                    INSERT INTO modules (name, filiere_id, type) 
                    VALUES (?, ?, 'pfe')
                ");
                $create_pfe->execute(['Projet de Fin d\'Études (PFE)', $filiere['id']]);
            }
            
            // Check if Stage module exists for this filière
            $check_stage = $pdo->prepare("
                SELECT COUNT(*) FROM modules 
                WHERE filiere_id = ? AND type = 'stage'
            ");
            $check_stage->execute([$filiere['id']]);
            
            if ($check_stage->fetchColumn() == 0) {
                // Create Stage module
                $create_stage = $pdo->prepare("
                    INSERT INTO modules (name, filiere_id, type) 
                    VALUES (?, ?, 'stage')
                ");
                $create_stage->execute(['Stage Professionnel', $filiere['id']]);
            }
        }
    } catch (PDOException $e) {
        // Silently handle errors - don't disrupt the user experience
        // Could log this error in a production environment
    }
    
    // Get available filieres based on user role
    if ($user_role === 'teacher') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT f.id, f.name, f.description
            FROM teacher_module_assignments tma
            JOIN filieres f ON tma.filiere_id = f.id
            WHERE tma.teacher_cni = ? AND tma.is_active = TRUE
            ORDER BY f.name
        ");
        $stmt->execute([$user_cni]);
    } else {
        $stmt = $pdo->query("
            SELECT id, name, description
            FROM filieres
            ORDER BY name
        ");
    }
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get modules for selected filiere
    $filiere_modules = [];
    if (isset($_GET['filiere_id'])) {
        $filiere_id = intval($_GET['filiere_id']);
        
        if ($user_role === 'teacher') {
            $stmt = $pdo->prepare("
                SELECT m.id, m.name, m.type
                FROM teacher_module_assignments tma
                JOIN modules m ON tma.module_id = m.id
                WHERE tma.teacher_cni = ? AND tma.filiere_id = ? AND tma.is_active = TRUE
                ORDER BY m.name
            ");
            $stmt->execute([$user_cni, $filiere_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, name, type
                FROM modules
                WHERE filiere_id = ?
                ORDER BY name
            ");
            $stmt->execute([$filiere_id]);
        }
        $filiere_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get students for selected filiere
    $filiere_students = [];
    if (isset($_GET['filiere_id'])) {
        $filiere_id = intval($_GET['filiere_id']);
        $stmt = $pdo->prepare("
            SELECT id, cni, nom, prenom
            FROM students
            WHERE filiere_id = ?
            ORDER BY nom, prenom
        ");
        $stmt->execute([$filiere_id]);
        $filiere_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get existing grades for selected module and filiere
    $existing_grades = [];
    if (isset($_GET['filiere_id']) && isset($_GET['module_id'])) {
        $filiere_id = intval($_GET['filiere_id']);
        $module_id = intval($_GET['module_id']);
        
        $stmt = $pdo->prepare("
            SELECT g.student_id, g.grade
            FROM grades g
            JOIN students s ON g.student_id = s.id
            WHERE g.module_id = ? AND s.filiere_id = ?
        ");
        $stmt->execute([$module_id, $filiere_id]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing_grades[$row['student_id']] = $row['grade'];
        }
    }
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
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
    <style>
        .grade-input {
            width: 60px;
            text-align: center;
        }
        .module-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .module-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15) !important;
        }
        .filiere-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .filiere-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top border-bottom shadow-sm <?php echo $navbar_color; ?>">
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
                                Record Attendance
                            </a>
                        </li>
                        <?php if ($user_role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_schedules.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Emplois du Temps
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($user_role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="send_message.php">
                                <i class="fas fa-paper-plane me-2"></i>
                                Send Messages
                            </a>
                        </li>
                        <?php endif; ?>
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
                <?php endif; 
                
                // Get module type for selected module
                $module_type = '';
                if (isset($_GET['module_id'])) {
                    $module_id = intval($_GET['module_id']);
                    $stmt = $pdo->prepare("
                        SELECT type 
                        FROM modules 
                        WHERE id = ?
                    ");
                    $stmt->execute([$module_id]);
                    $module_type = $stmt->fetchColumn() ?: 'standard';
                }
                
                // Get existing grades for selected module and filiere with grade types
                $existing_grades = [];
                if (isset($_GET['filiere_id']) && isset($_GET['module_id'])) {
                    $filiere_id = intval($_GET['filiere_id']);
                    $module_id = intval($_GET['module_id']);
                    
                    $stmt = $pdo->prepare("
                        SELECT g.student_id, g.grade_type, g.grade
                        FROM grades g
                        JOIN students s ON g.student_id = s.id
                        WHERE g.module_id = ? AND s.filiere_id = ?
                    ");
                    $stmt->execute([$module_id, $filiere_id]);
                    
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $existing_grades[$row['student_id']][$row['grade_type']] = $row['grade'];
                    }
                }
                ?>
                <?php if (empty($filieres)): ?>
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>No filières found!</strong> 
                        <?php if ($user_role === 'teacher'): ?>
                        You are not assigned to any filières yet. Please contact the administrator.
                        <?php else: ?>
                        Please create filières and modules first.
                        <?php endif; ?>
                    </div>
                </div>
                <?php elseif (!isset($_GET['filiere_id'])): ?>
                <!-- Show Filières Selection -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Select a Filière to Manage Grades
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($filieres as $filiere): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 filiere-card" onclick="window.location.href='manage_grades.php?filiere_id=<?php echo $filiere['id']; ?>'">
                                        <div class="card-body text-center">
                                            <div class="mb-3">
                                                <i class="fas fa-user-graduate fa-3x text-<?php echo $user_role === 'admin' ? 'primary' : 'success'; ?>"></i>
                                            </div>
                                            <h5 class="card-title"><?php echo htmlspecialchars($filiere['name']); ?></h5>
                                            <p class="card-text text-muted">
                                                <?php echo htmlspecialchars($filiere['description']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php elseif (isset($_GET['filiere_id']) && !isset($_GET['module_id'])): ?>
                <!-- Show Modules for Selected Filière -->
                <?php 
                $filiere_id = intval($_GET['filiere_id']);
                $filiere_name = '';
                foreach ($filieres as $filiere) {
                    if ($filiere['id'] == $filiere_id) {
                        $filiere_name = $filiere['name'];
                        break;
                    }
                }
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
                                <?php echo htmlspecialchars($filiere_name); ?>
                            </li>
                        </ol>
                    </nav>
                </div>
                
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-book me-2"></i>
                                Select a Module for <?php echo htmlspecialchars($filiere_name); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($filiere_modules)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>No modules found!</strong> 
                                <?php if ($user_role === 'teacher'): ?>
                                You are not assigned to any modules in this filière. Please contact the administrator.
                                <?php else: ?>
                                Please create modules for this filière first.
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="row">
                                <?php foreach ($filiere_modules as $module): 
                                    // Determine module icon based on type or name
                                    $module_icon = 'book';
                                    $module_type = $module['type'] ?? '';
                                    
                                    if (stripos($module['name'], 'math') !== false) {
                                        $module_icon = 'calculator';
                                    } elseif (stripos($module['name'], 'program') !== false || stripos($module['name'], 'coding') !== false) {
                                        $module_icon = 'code';
                                    } elseif (stripos($module['name'], 'language') !== false || stripos($module['name'], 'english') !== false) {
                                        $module_icon = 'language';
                                    } elseif ($module_type === 'stage') {
                                        $module_icon = 'briefcase';
                                    } elseif ($module_type === 'pfe') {
                                        $module_icon = 'project-diagram';
                                    }
                                    
                                    // Generate a random pastel background color
                                    $colors = ['#f0f8ff', '#f5f5dc', '#e6e6fa', '#f0fff0', '#fff0f5', '#f0ffff', '#fffacd'];
                                    $bg_color = $colors[array_rand($colors)];
                                ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 module-card shadow-sm" 
                                         onclick="window.location.href='manage_grades.php?filiere_id=<?php echo $filiere_id; ?>&module_id=<?php echo $module['id']; ?>'"
                                         style="border-radius: 15px; overflow: hidden; border: none;">
                                        <div class="card-header text-white bg-<?php echo $user_role === 'admin' ? 'primary' : 'success'; ?>" 
                                             style="border-bottom: none; padding: 0.5rem 1rem;">
                                            <small class="text-white-50">Module</small>
                                        </div>
                                        <div class="card-body text-center" style="background-color: <?php echo $bg_color; ?>;">
                                            <div class="mb-3 mt-2">
                                                <span class="rounded-circle bg-white p-3 d-inline-block shadow-sm">
                                                    <i class="fas fa-<?php echo $module_icon; ?> fa-2x text-<?php echo $user_role === 'admin' ? 'primary' : 'success'; ?>"></i>
                                                </span>
                                            </div>
                                            <h5 class="card-title"><?php echo htmlspecialchars($module['name']); ?></h5>
                                            <p class="card-text small text-muted">
                                                Click to manage grades
                                            </p>
                                        </div>
                                        <div class="card-footer bg-white text-center" style="border-top: none;">
                                            <button class="btn btn-sm btn-outline-<?php echo $user_role === 'admin' ? 'primary' : 'success'; ?> rounded-pill">
                                                <i class="fas fa-edit me-1"></i> Enter Grades
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php elseif (isset($_GET['filiere_id']) && isset($_GET['module_id'])): ?>
                <!-- Show Grade Entry Form for Selected Module and Filière -->
                <?php 
                $filiere_id = intval($_GET['filiere_id']);
                $module_id = intval($_GET['module_id']);
                
                $filiere_name = '';
                foreach ($filieres as $filiere) {
                    if ($filiere['id'] == $filiere_id) {
                        $filiere_name = $filiere['name'];
                        break;
                    }
                }
                
                $module_name = '';
                $module_type = 'standard'; // Default type
                foreach ($filiere_modules as $module) {
                    if ($module['id'] == $module_id) {
                        $module_name = $module['name'];
                        $module_type = $module['type'] ?? 'standard';
                        break;
                    }
                }
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
                            <li class="breadcrumb-item">
                                <a href="manage_grades.php?filiere_id=<?php echo $filiere_id; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($filiere_name); ?>
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                <?php echo htmlspecialchars($module_name); ?>
                            </li>
                        </ol>
                    </nav>
                </div>
                
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-edit me-2"></i>
                                Grade Entry: <?php echo htmlspecialchars($module_name); ?> - <?php echo htmlspecialchars($filiere_name); ?>
                            </h5>
                            <span class="badge bg-info fs-6">
                                <?php echo count($filiere_students); ?> Students
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($filiere_students)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>No students found!</strong> There are no students enrolled in this filière.
                            </div>
                            <?php else: ?>
                            <form method="POST" action="">
                                <input type="hidden" name="filiere_id" value="<?php echo $filiere_id; ?>">
                                <input type="hidden" name="module_id" value="<?php echo $module_id; ?>">
                                
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th rowspan="2" class="align-middle">#</th>
                                                <th rowspan="2" class="align-middle">Student Name</th>
                                                <th rowspan="2" class="align-middle">CNI</th>
                                                
                                                <?php if ($module_type === 'stage' || $module_type === 'pfe'): ?>
                                                <th class="text-center">Final Grade</th>
                                                <?php elseif ($module_type === 'standard' || empty($module_type)): ?>
                                                <th colspan="3" class="text-center">Contrôle Continu</th>
                                                <th colspan="2" class="text-center">Examen Final</th>
                                                <?php endif; ?>
                                            </tr>
                                            
                                            <?php if ($module_type === 'standard' || empty($module_type)): ?>
                                            <tr>
                                                <th class="text-center">CC1</th>
                                                <th class="text-center">CC2</th>
                                                <th class="text-center">CC3</th>
                                                <th class="text-center">Théorique</th>
                                                <th class="text-center">Pratique</th>
                                            </tr>
                                            <?php endif; ?>
                                        </thead>
                                        <tbody>
                                            <?php $counter = 1; ?>
                                            <?php foreach ($filiere_students as $student): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></td>
                                                <td><code><?php echo htmlspecialchars($student['cni']); ?></code></td>
                                                
                                                <?php if ($module_type === 'stage' || $module_type === 'pfe'): ?>
                                                <!-- For internship or final project modules -->
                                                <td class="text-center">
                                                    <input type="text" inputmode="decimal" pattern="^\d{0,2}(\.\d{0,2})?$" 
                                                           name="grades[<?php echo $student['id']; ?>][<?php echo $module_type; ?>]" 
                                                           class="form-control grade-input mx-auto" 
                                                           min="0" 
                                                           max="20" 
                                                           step="0.25"
                                                           value="<?php echo isset($existing_grades[$student['id']][$module_type]) ? htmlspecialchars($existing_grades[$student['id']][$module_type]) : ''; ?>">
                                                </td>
                                                <?php elseif ($module_type === 'standard' || empty($module_type)): ?>
                                                <!-- For standard modules with continuous assessment and final exam -->
                                                <td class="text-center">
                                                    <input type="text" inputmode="decimal" pattern="^\d{0,2}(\.\d{0,2})?$" 
                                                           name="grades[<?php echo $student['id']; ?>][cc1]" 
                                                           class="form-control grade-input mx-auto" 
                                                           min="0" 
                                                           max="20" 
                                                           step="0.25"
                                                           value="<?php echo isset($existing_grades[$student['id']]['cc1']) ? htmlspecialchars($existing_grades[$student['id']]['cc1']) : ''; ?>">
                                                </td>
                                                <td class="text-center">
                                                    <input type="text" inputmode="decimal" pattern="^\d{0,2}(\.\d{0,2})?$" 
                                                           name="grades[<?php echo $student['id']; ?>][cc2]" 
                                                           class="form-control grade-input mx-auto" 
                                                           min="0" 
                                                           max="20" 
                                                           step="0.25"
                                                           value="<?php echo isset($existing_grades[$student['id']]['cc2']) ? htmlspecialchars($existing_grades[$student['id']]['cc2']) : ''; ?>">
                                                </td>
                                                <td class="text-center">
                                                    <input type="text" inputmode="decimal" pattern="^\d{0,2}(\.\d{0,2})?$" 
                                                           name="grades[<?php echo $student['id']; ?>][cc3]" 
                                                           class="form-control grade-input mx-auto" 
                                                           min="0" 
                                                           max="20" 
                                                           step="0.25"
                                                           value="<?php echo isset($existing_grades[$student['id']]['cc3']) ? htmlspecialchars($existing_grades[$student['id']]['cc3']) : ''; ?>">
                                                </td>
                                                <td class="text-center">
                                                    <input type="text" inputmode="decimal" pattern="^\d{0,2}(\.\d{0,2})?$" 
                                                           name="grades[<?php echo $student['id']; ?>][theorique]" 
                                                           class="form-control grade-input mx-auto" 
                                                           min="0" 
                                                           max="20" 
                                                           step="0.25"
                                                           value="<?php echo isset($existing_grades[$student['id']]['theorique']) ? htmlspecialchars($existing_grades[$student['id']]['theorique']) : ''; ?>">
                                                </td>
                                                <td class="text-center">
                                                    <input type="text" inputmode="decimal" pattern="^\d{0,2}(\.\d{0,2})?$" 
                                                           name="grades[<?php echo $student['id']; ?>][pratique]" 
                                                           class="form-control grade-input mx-auto" 
                                                           min="0" 
                                                           max="20" 
                                                           step="0.25"
                                                           value="<?php echo isset($existing_grades[$student['id']]['pratique']) ? htmlspecialchars($existing_grades[$student['id']]['pratique']) : ''; ?>">
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between mt-3">
                                    <a href="manage_grades.php?filiere_id=<?php echo $filiere_id; ?>" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Modules
                                    </a>
                                    <button type="submit" name="submit_grades" class="btn btn-<?php echo $user_role === 'admin' ? 'primary' : 'success'; ?> btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Grades
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
    <script>
        // Allow only numbers and decimal point in grade inputs
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.grade-input').forEach(inp => {
                inp.addEventListener('input', () => {
                    inp.value = inp.value.replace(/[^0-9.]/g, '');
                });
            });
        });
    </script>
</body>
</html>
