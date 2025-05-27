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
} catch (PDOException $e) {
    $error_message = 'Database connection failed: ' . $e->getMessage();
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_attendance'])) {
    $module_id = intval($_POST['module_id']);
    $filiere_id = intval($_POST['filiere_id']);
    
    // Admin can select any date, teacher uses current date
    if ($user_role === 'admin' && !empty($_POST['attendance_date'])) {
        $date = $_POST['attendance_date'];
    } else {
        $date = date('Y-m-d');
    }
    
    $absent_students = $_POST['absent_students'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Get all students in the filiere with their id and cni
        $stmt = $pdo->prepare("SELECT id, cni FROM students WHERE filiere_id = ?");
        $stmt->execute([$filiere_id]);
        $all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($all_students)) {
            throw new Exception("No students found in this filiere.");
        }
        
        // Create a mapping from cni to id
        $cni_to_id_map = [];
        foreach ($all_students as $student) {
            $cni_to_id_map[$student['cni']] = $student['id'];
        }
        
        // Delete existing attendance for this date/module/filiere to avoid duplicates
        $stmt = $pdo->prepare("
            DELETE FROM absences 
            WHERE module_id = ? AND date = ? 
            AND student_id IN (
                SELECT id FROM students WHERE filiere_id = ?
            )
        ");
        $stmt->execute([$module_id, $date, $filiere_id]);
        
        // Insert attendance records for absent students only
        if (!empty($absent_students)) {
            $stmt = $pdo->prepare("
                INSERT INTO absences (student_id, module_id, date, status, recorded_by_teacher_id, recorded_by_admin_id) 
                VALUES (?, ?, ?, 'absent', ?, ?)
            ");
            
            foreach ($absent_students as $student_cni) {
                // Verify student belongs to this filiere and get their ID
                if (isset($cni_to_id_map[$student_cni])) {
                    $student_id = $cni_to_id_map[$student_cni];
                    if ($user_role === 'teacher') {
                        $stmt->execute([$student_id, $module_id, $date, $user_cni, null]);
                    } else {
                        // Get admin ID from CNI
                        $admin_stmt = $pdo->prepare("SELECT id FROM admins WHERE cni = ?");
                        $admin_stmt->execute([$user_cni]);
                        $admin_id = $admin_stmt->fetchColumn();
                        
                        if ($admin_id) {
                            $stmt->execute([$student_id, $module_id, $date, null, $admin_id]);
                        } else {
                            throw new Exception("Admin ID not found for CNI: $user_cni");
                        }
                    }
                }
            }
        }
        
        $pdo->commit();
        $absent_count = count($absent_students);
        $present_count = count($all_students) - $absent_count;
        
        // Store success message in session instead of variable
        $_SESSION['success_message'] = "Attendance recorded successfully! Present: $present_count, Absent: $absent_count";
        
        // Redirect to the same page but with GET method
        $redirect_url = "record_absence.php?module_id=$module_id&filiere_id=$filiere_id";
        if ($user_role === 'admin' && !empty($_POST['attendance_date'])) {
            $redirect_url .= "&date=" . urlencode($_POST['attendance_date']);
        }
        header("Location: $redirect_url");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = 'Error recording attendance: ' . $e->getMessage();
    }
}

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying it once
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
    <title>Record Attendance - Groupe IKI</title>
    
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
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top border-bottom shadow-sm <?php echo $navbar_color; ?>">
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
                            <a class="nav-link" href="manage_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                Manage Grades
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="record_absence.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                Record Attendance
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
                        <i class="fas fa-calendar-check me-2"></i>
                        Record Attendance
                        <?php if ($user_role === 'teacher'): ?>
                        - <?php echo date('d/m/Y'); ?>
                        <?php else: ?>
                        - Admin Access
                        <?php endif; ?>
                    </h1>
                    <div class="text-muted">
                        <?php if ($user_role === 'teacher'): ?>
                        <i class="fas fa-clock me-2"></i>
                        Current Time: <?php echo date('H:i'); ?>
                        <?php else: ?>
                        <i class="fas fa-crown me-2"></i>
                        Admin Privileges
                        <?php endif; ?>
                    </div>
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

                <?php if ($user_role === 'teacher'): ?>
<!-- Teacher's Assigned Classes -->
<div class="row">
    <?php
    // Get teacher's assigned modules and filieres
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            tma.module_id, 
            tma.filiere_id,
            m.name as module_name,
            f.name as filiere_name,
            f.description as filiere_description,
            COUNT(s.cni) as student_count
        FROM teacher_module_assignments tma
        JOIN modules m ON tma.module_id = m.id
        JOIN filieres f ON tma.filiere_id = f.id
        LEFT JOIN students s ON s.filiere_id = f.id
        WHERE tma.teacher_cni = ? AND tma.is_active = TRUE
        AND m.type NOT IN ('pfe', 'stage')
        GROUP BY tma.module_id, tma.filiere_id
        ORDER BY f.name, m.name
    ");
    $stmt->execute([$user_cni]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($assignments)): ?>
    <div class="col-12">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>No assignments found!</strong> You are not assigned to any modules yet. Please contact the administrator.
        </div>
    </div>
    <?php else: ?>
    
    <?php if (!isset($_GET['module_id']) || !isset($_GET['filiere_id'])): ?>
    <!-- Show available assignments -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chalkboard me-2"></i>
                    Select Your Assigned Module to Record Attendance
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($assignments as $assignment): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 module-card" style="cursor: pointer;" 
                             onclick="selectModule(<?php echo $assignment['module_id']; ?>, <?php echo $assignment['filiere_id']; ?>)">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-book fa-3x text-success"></i>
                                </div>
                                <h6 class="card-title"><?php echo htmlspecialchars($assignment['module_name']); ?></h6>
                                <p class="card-text">
                                    <strong><?php echo htmlspecialchars($assignment['filiere_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['filiere_description']); ?></small>
                                </p>
                                <span class="badge bg-success">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo $assignment['student_count']; ?> Students
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>
<?php endif; ?>

                <?php if ($user_role === 'admin' && (!isset($_GET['module_id']) || !isset($_GET['filiere_id']))): ?>
                <!-- Show available modules and filieres -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chalkboard me-2"></i>
                                    Select Module and Filière to Record Attendance
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get all modules and filieres
                                try {
                                    $stmt = $pdo->query("
                                        SELECT 
                                            m.id as module_id, 
                                            m.name as module_name,
                                            f.id as filiere_id,
                                            f.name as filiere_name,
                                            f.description as filiere_description,
                                            COUNT(s.cni) as student_count
                                        FROM modules m
                                        JOIN filieres f ON m.filiere_id = f.id
                                        LEFT JOIN students s ON s.filiere_id = f.id
                                        WHERE m.type NOT IN ('pfe', 'stage')
                                        GROUP BY m.id, f.id
                                        ORDER BY f.name, m.name
                                    ");
                                    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (empty($modules)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>No modules found!</strong> Please contact the administrator to set up modules and filieres.
                                    </div>
                                    <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($modules as $module): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card h-100 module-card" style="cursor: pointer;" 
                                                 onclick="selectModule(<?php echo $module['module_id']; ?>, <?php echo $module['filiere_id']; ?>)">
                                                <div class="card-body text-center">
                                                    <div class="mb-3">
                                                        <i class="fas fa-book fa-3x text-primary"></i>
                                                    </div>
                                                    <h6 class="card-title"><?php echo htmlspecialchars($module['module_name']); ?></h6>
                                                    <p class="card-text">
                                                        <strong><?php echo htmlspecialchars($module['filiere_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($module['filiere_description']); ?></small>
                                                    </p>
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-users me-1"></i>
                                                        <?php echo $module['student_count']; ?> Students
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                <?php } catch (PDOException $e) { ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Error loading modules: <?php echo htmlspecialchars($e->getMessage()); ?>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>
                
                <?php if (isset($_GET['module_id']) && isset($_GET['filiere_id'])): 
                // Show attendance form for selected module/filiere
                $module_id = intval($_GET['module_id']);
                $filiere_id = intval($_GET['filiere_id']);
                
                try {

                    if ($user_role === 'teacher'){
                        // Verify teacher has access to this module/filiere
                        $stmt = $pdo->prepare("
                            SELECT m.name as module_name, f.name as filiere_name, f.description as filiere_description
                            FROM teacher_module_assignments tma
                            JOIN modules m ON tma.module_id = m.id
                            JOIN filieres f ON tma.filiere_id = f.id
                            WHERE tma.teacher_cni = ? AND tma.module_id = ? AND tma.filiere_id = ? AND tma.is_active = TRUE
                        ");
                        $stmt->execute([$user_cni, $module_id, $filiere_id]);
                        $info = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$info): ?>
                        <div class="col-12">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                You don't have permission to record attendance for this module in this filière.
                            </div>
                        </div>
                        <?php else:
                    
                        // Get students in this filiere
                        $stmt = $pdo->prepare("
                            SELECT cni, nom, prenom
                            FROM students 
                            WHERE filiere_id = ?
                            ORDER BY nom, prenom
                        ");
                        $stmt->execute([$filiere_id]);
                        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Get selected date for checking existing attendance
                        $selected_date = $_GET['date'] ?? date('Y-m-d');
                        
                        // Check if attendance already recorded for selected date
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count
                            FROM absences a
                            JOIN students s ON a.student_id = s.cni
                            WHERE a.module_id = ? AND s.filiere_id = ? AND a.date = ?
                        ");
                        $stmt->execute([$module_id, $filiere_id, $selected_date]);
                        $attendance_exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                        ?>
                        
                        <!-- Breadcrumb -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="record_absence.php" class="text-decoration-none">
                                                <i class="fas fa-calendar-check me-1"></i>Record Attendance
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">
                                            <?php echo htmlspecialchars($info['filiere_name'] . ' - ' . $info['module_name']); ?>
                                        </li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        
                        <!-- Date Selection for Admin -->
                        
                        
                        <!-- Attendance Form -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-list-check me-2"></i>
                                                Attendance - <?php echo htmlspecialchars($info['filiere_name']); ?>
                                            </h5>
                                            <small class="text-muted">
                                                Module: <?php echo htmlspecialchars($info['module_name']); ?> |
                                                Date: <?php echo date('d/m/Y', strtotime($selected_date)); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-info fs-6">
                                            <?php echo count($students); ?> Students
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($attendance_exists): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Attendance already recorded for this date!</strong> Submitting again will update the existing records.
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (empty($students)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No students found in this filiere</h5>
                                            <p class="text-muted">Please contact the administrator to add students to this filiere.</p>
                                        </div>
                                        <?php else: ?>
                                        
                                        <form method="POST" action="" id="attendanceForm">
                                            <input type="hidden" name="module_id" value="<?php echo $module_id; ?>">
                                            <input type="hidden" name="filiere_id" value="<?php echo $filiere_id; ?>">
                                            
                                            
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div>
                                                        <strong>Instructions:</strong> All students are marked as <span class="text-success">Present</span> by default. 
                                                        Check the box next to students who are <span class="text-danger">Absent</span>.
                                                    </div>
                                                    <div>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll()">
                                                            <i class="fas fa-check-square me-1"></i>
                                                            Toggle All
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th width="50">
                                                                    <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes()">
                                                                </th>
                                                                <th>Student Name</th>
                                                                <th>CNI</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($students as $student): ?>
                                                            <tr>
                                                                <td>
                                                                    <input type="checkbox" 
                                                                           name="absent_students[]" 
                                                                           value="<?php echo htmlspecialchars($student['cni']); ?>"
                                                                           class="student-checkbox"
                                                                           onchange="updateStatus(this)">
                                                                </td>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></strong>
                                                                </td>
                                                                <td>
                                                                    <code><?php echo htmlspecialchars($student['cni']); ?></code>
                                                                </td>
                                                                <td>
                                                                    <span class="status-badge badge bg-success">
                                                                        <i class="fas fa-check me-1"></i>Present
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <a href="record_absence.php" class="btn btn-secondary">
                                                    <i class="fas fa-arrow-left me-2"></i>Back to Modules
                                                </a>
                                                <button type="submit" name="submit_attendance" class="btn btn-<?php echo $user_role === 'admin' ? 'warning' : 'success'; ?> btn-lg">
                                                    <i class="fas fa-<?php echo $user_role === 'admin' ? 'crown' : 'save'; ?> me-2"></i>
                                                    Submit Attendance (<?php echo count($students); ?> students)
                                                </button>
                                            </div>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif;
                    } else {
                        // Get module and filiere info
                        $stmt = $pdo->prepare("
                            SELECT m.name as module_name, f.name as filiere_name, f.description as filiere_description
                            FROM modules m
                            JOIN filieres f ON m.filiere_id = f.id
                            WHERE m.id = ? AND f.id = ?
                        ");
                        $stmt->execute([$module_id, $filiere_id]);
                        $info = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$info): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Invalid module or filiere selection.
                        </div>
                        <?php else:
                        
                        // Get students in this filiere
                        $stmt = $pdo->prepare("
                            SELECT cni, nom, prenom
                            FROM students 
                            WHERE filiere_id = ?
                            ORDER BY nom, prenom
                        ");
                        $stmt->execute([$filiere_id]);
                        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Get selected date for checking existing attendance
                        $selected_date = $_GET['date'] ?? date('Y-m-d');
                        
                        // Check if attendance already recorded for selected date
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count
                            FROM absences a
                            JOIN students s ON a.student_id = s.cni
                            WHERE a.module_id = ? AND s.filiere_id = ? AND a.date = ?
                        ");
                        $stmt->execute([$module_id, $filiere_id, $selected_date]);
                        $attendance_exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                        ?>
                        
                        <!-- Breadcrumb -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="record_absence.php" class="text-decoration-none">
                                                <i class="fas fa-calendar-check me-1"></i>Record Attendance
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">
                                            <?php echo htmlspecialchars($info['filiere_name'] . ' - ' . $info['module_name']); ?>
                                        </li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        
                        <!-- Date Selection for Admin -->
                        <?php if ($user_role === 'admin'): ?>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <form method="GET" action="" class="row g-3 align-items-end">
                                            <input type="hidden" name="module_id" value="<?php echo $module_id; ?>">
                                            <input type="hidden" name="filiere_id" value="<?php echo $filiere_id; ?>">
                                            <div class="col-md-4">
                                                <label for="date" class="form-label">
                                                    <i class="fas fa-calendar me-2"></i>Select Date
                                                </label>
                                                <input type="date" 
                                                       class="form-control" 
                                                       id="date" 
                                                       name="date" 
                                                       value="<?php echo htmlspecialchars($selected_date); ?>"
                                                       max="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-search me-2"></i>Load Date
                                                </button>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <span class="badge bg-info fs-6">
                                                    Recording for: <?php echo date('d/m/Y', strtotime($selected_date)); ?>
                                                </span>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Attendance Form -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-list-check me-2"></i>
                                                Attendance - <?php echo htmlspecialchars($info['filiere_name']); ?>
                                            </h5>
                                            <small class="text-muted">
                                                Module: <?php echo htmlspecialchars($info['module_name']); ?> |
                                                Date: <?php echo date('d/m/Y', strtotime($selected_date)); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-info fs-6">
                                            <?php echo count($students); ?> Students
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($attendance_exists): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Attendance already recorded for this date!</strong> Submitting again will update the existing records.
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (empty($students)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No students found in this filiere</h5>
                                            <p class="text-muted">Please contact the administrator to add students to this filiere.</p>
                                        </div>
                                        <?php else: ?>
                                        
                                        <form method="POST" action="" id="attendanceForm">
                                            <input type="hidden" name="module_id" value="<?php echo $module_id; ?>">
                                            <input type="hidden" name="filiere_id" value="<?php echo $filiere_id; ?>">
                                            <?php if ($user_role === 'admin'): ?>
                                            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div>
                                                        <strong>Instructions:</strong> All students are marked as <span class="text-success">Present</span> by default. 
                                                        Check the box next to students who are <span class="text-danger">Absent</span>.
                                                    </div>
                                                    <div>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll()">
                                                            <i class="fas fa-check-square me-1"></i>
                                                            Toggle All
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th width="50">
                                                                    <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes()">
                                                                </th>
                                                                <th>Student Name</th>
                                                                <th>CNI</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($students as $student): ?>
                                                            <tr>
                                                                <td>
                                                                    <input type="checkbox" 
                                                                           name="absent_students[]" 
                                                                           value="<?php echo htmlspecialchars($student['cni']); ?>"
                                                                           class="student-checkbox"
                                                                           onchange="updateStatus(this)">
                                                                </td>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></strong>
                                                                </td>
                                                                <td>
                                                                    <code><?php echo htmlspecialchars($student['cni']); ?></code>
                                                                </td>
                                                                <td>
                                                                    <span class="status-badge badge bg-success">
                                                                        <i class="fas fa-check me-1"></i>Present
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <a href="record_absence.php" class="btn btn-secondary">
                                                    <i class="fas fa-arrow-left me-2"></i>Back to Modules
                                                </a>
                                                <button type="submit" name="submit_attendance" class="btn btn-<?php echo $user_role === 'admin' ? 'warning' : 'success'; ?> btn-lg">
                                                    <i class="fas fa-<?php echo $user_role === 'admin' ? 'crown' : 'save'; ?> me-2"></i>
                                                    Submit Attendance (<?php echo count($students); ?> students)
                                                </button>
                                            </div>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php } ?>
                <?php } catch (PDOException $e) { ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Database error: <?php echo htmlspecialchars($e->getMessage()); ?>
                </div>
                <?php } ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
    function selectModule(moduleId, filiereId) {
        window.location.href = 'record_absence.php?module_id=' + moduleId + '&filiere_id=' + filiereId;
    }
    
    function toggleAllCheckboxes() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.student-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
            updateStatus(checkbox);
        });
    }
    
    function updateStatus(checkbox) {
        const row = checkbox.closest('tr');
        const statusBadge = row.querySelector('.status-badge');
        
        if (checkbox.checked) {
            statusBadge.className = 'status-badge badge bg-danger';
            statusBadge.innerHTML = '<i class="fas fa-times me-1"></i>Absent';
        } else {
            statusBadge.className = 'status-badge badge bg-success';
            statusBadge.innerHTML = '<i class="fas fa-check me-1"></i>Present';
        }
    }
    
    function toggleAll() {
        const selectAll = document.getElementById('selectAll');
        selectAll.checked = !selectAll.checked;
        toggleAllCheckboxes();
    }
    </script>

    <style>
    .module-card {
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .module-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border-color: var(--bs-primary);
    }
    
    .table tbody tr:hover {
        background-color: rgba(0,0,0,0.02);
    }
    
    .student-checkbox {
        transform: scale(1.2);
    }
    </style>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/scripts.js"></script>
</body>
</html>
