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
    
    // Get teacher/admin ID
    $recorder_id = null;
    if ($user_role === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE cni = ?");
        $stmt->execute([$user_cni]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        $recorder_id = $teacher['id'] ?? null;
    } elseif ($user_role === 'admin') {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE cni = ?");
        $stmt->execute([$user_cni]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $recorder_id = $admin['id'] ?? null;
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
    
    // Fetch recent absences
    $stmt = $pdo->query("
        SELECT a.id, a.date, a.status,
               CONCAT(s.prenom, ' ', s.nom) as student_name, s.cni as student_cni,
               m.name as module_name, f.name as filiere_name,
               CONCAT(t.prenom, ' ', t.nom) as teacher_name,
               CONCAT(ad.prenom, ' ', ad.nom) as admin_name
        FROM absences a
        JOIN students s ON a.student_id = s.id
        JOIN modules m ON a.module_id = m.id
        JOIN filieres f ON m.filiere_id = f.id
        LEFT JOIN teachers t ON a.recorded_by_teacher_id = t.id
        LEFT JOIN admins ad ON a.recorded_by_admin_id = ad.id
        ORDER BY a.date DESC
        LIMIT 50
    ");
    $recent_absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database connection failed.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $module_id = trim($_POST['module_id'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $date = trim($_POST['date'] ?? '');
    
    if (empty($student_id) || empty($module_id) || empty($status) || empty($date)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            if ($user_role === 'teacher') {
                $stmt = $pdo->prepare("
                    INSERT INTO absences (student_id, module_id, date, status, recorded_by_teacher_id) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$student_id, $module_id, $date, $status, $recorder_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO absences (student_id, module_id, date, status, recorded_by_admin_id) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$student_id, $module_id, $date, $status, $recorder_id]);
            }
            
            $success_message = 'Attendance recorded successfully!';
            
            // Refresh recent absences
            $stmt = $pdo->query("
                SELECT a.id, a.date, a.status,
                       CONCAT(s.prenom, ' ', s.nom) as student_name, s.cni as student_cni,
                       m.name as module_name, f.name as filiere_name,
                       CONCAT(t.prenom, ' ', t.nom) as teacher_name,
                       CONCAT(ad.prenom, ' ', ad.nom) as admin_name
                FROM absences a
                JOIN students s ON a.student_id = s.id
                JOIN modules m ON a.module_id = m.id
                JOIN filieres f ON m.filiere_id = f.id
                LEFT JOIN teachers t ON a.recorded_by_teacher_id = t.id
                LEFT JOIN admins ad ON a.recorded_by_admin_id = ad.id
                ORDER BY a.date DESC
                LIMIT 50
            ");
            $recent_absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error_message = 'Error recording attendance. Please try again.';
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
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top border-bottom shadow-sm <?php echo $navbar_color; ?> ">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $dashboard_link; ?>">
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
                    <i class="fas fa-calendar-check me-2"></i>
                    Select Filière to Record Attendance
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
                                    <i class="fas fa-users fa-3x text-success"></i>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($filiere['name']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($filiere['description']); ?></p>
                                <div class="mt-3">
                                    <span class="badge bg-info fs-6">
                                        <i class="fas fa-user-graduate me-1"></i>
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
                    <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Filières Found</h5>
                    <p class="text-muted">Please add filières first to record attendance.</p>
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
    $stmt = $pdo->prepare("
    SELECT s.id, s.nom, s.prenom, s.cni
    FROM students s 
    WHERE s.filiere_id = ?
    ORDER BY s.nom, s.prenom
");
    $stmt->execute([$filiere_id]);
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
    
    // Get recent attendance for this filière
    $stmt = $pdo->prepare("
        SELECT a.id, a.date, a.status,
               CONCAT(s.prenom, ' ', s.nom) as student_name, s.cni as student_cni,
               m.name as module_name,
               CONCAT(t.prenom, ' ', t.nom) as teacher_name,
               CONCAT(ad.prenom, ' ', ad.nom) as admin_name
        FROM absences a
        JOIN students s ON a.student_id = s.id
        JOIN modules m ON a.module_id = m.id
        LEFT JOIN teachers t ON a.recorded_by_teacher_id = t.id
        LEFT JOIN admins ad ON a.recorded_by_admin_id = ad.id
        WHERE s.filiere_id = ?
        ORDER BY a.date DESC
        LIMIT 30
    ");
    $stmt->execute([$filiere_id]);
    $filiere_absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <!-- Breadcrumb -->
    <div class="col-12 mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="record_absence.php" class="text-decoration-none">
                        <i class="fas fa-calendar-check me-1"></i>Record Attendance
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <i class="fas fa-users me-1"></i>
                    <?php echo htmlspecialchars($selected_filiere['name']); ?>
                </li>
            </ol>
        </nav>
    </div>
    
    <!-- Record Attendance Form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Record Attendance - <?php echo htmlspecialchars($selected_filiere['name']); ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="record_absence.php?filiere_id=<?php echo $filiere_id; ?>" id="attendanceForm" novalidate>
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
                        <label for="status" class="form-label">
                            <i class="fas fa-check-circle me-2"></i>Status *
                        </label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                        </select>
                        <div class="invalid-feedback">
                            Please select attendance status.
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
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            <span class="btn-text">
                                <i class="fas fa-save me-2"></i>Record Attendance
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

    <!-- Recent Attendance for this Filière -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>
                    Recent Attendance - <?php echo htmlspecialchars($selected_filiere['name']); ?>
                </h5>
                <span class="badge bg-success">
                    <?php echo count($filiere_absences); ?> Records
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($filiere_absences)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No attendance records yet</h5>
                    <p class="text-muted">Start by recording attendance for students in this filière.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-success">
                            <tr>
                                <th>Student</th>
                                <th>Module</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filiere_absences as $absence): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($absence['student_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($absence['student_cni']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <i class="fas fa-book me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($absence['module_name']); ?>
                                </td>
                                <td>
                                    <?php if ($absence['status'] === 'present'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Present
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times me-1"></i>Absent
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fas fa-calendar me-2 text-muted"></i>
                                    <?php echo date('d/m/Y', strtotime($absence['date'])); ?>
                                </td>
                                <td>
                                    <?php if ($absence['teacher_name']): ?>
                                        <i class="fas fa-chalkboard-teacher me-2 text-success"></i>
                                        <?php echo htmlspecialchars($absence['teacher_name']); ?>
                                    <?php elseif ($absence['admin_name']): ?>
                                        <i class="fas fa-user-shield me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($absence['admin_name']); ?>
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
    window.location.href = 'record_absence.php?filiere_id=' + filiereId;
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
    border-color: var(--bs-success);
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
