<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_cni'])) {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];
$user_role = $_SESSION['role'];

// Database connection
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $grades = [];
    
    if ($user_role === 'student') {
        // Get student's grades
        $stmt = $pdo->prepare("
            SELECT g.grade, g.date, m.name as module_name, f.name as filiere_name,
                   CONCAT(t.prenom, ' ', t.nom) as teacher_name
            FROM grades g
            JOIN students s ON g.student_id = s.id
            JOIN modules m ON g.module_id = m.id
            JOIN filieres f ON m.filiere_id = f.id
            LEFT JOIN teachers t ON g.recorded_by_teacher_id = t.id
            WHERE s.cni = ?
            ORDER BY g.date DESC
        ");
        $stmt->execute([$user_cni]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role === 'teacher' || $user_role === 'admin') {
        // Get all grades (for teachers and admins)
        $stmt = $pdo->prepare("
            SELECT g.grade, g.date, m.name as module_name, f.name as filiere_name,
                   CONCAT(s.prenom, ' ', s.nom) as student_name, s.cni as student_cni,
                   CONCAT(t.prenom, ' ', t.nom) as teacher_name
            FROM grades g
            JOIN students s ON g.student_id = s.id
            JOIN modules m ON g.module_id = m.id
            JOIN filieres f ON m.filiere_id = f.id
            LEFT JOIN teachers t ON g.recorded_by_teacher_id = t.id
            ORDER BY g.date DESC
            LIMIT 100
        ");
        $stmt->execute();
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error_message = 'Database connection failed.';
}

// Determine dashboard link and navbar color based on role
$dashboard_link = 'dashboard_student.php';
$navbar_color = 'bg-info';
$user_icon = 'fa-user-graduate';

if ($user_role === 'admin') {
    $dashboard_link = 'dashboard_admin.php';
    $navbar_color = 'bg-primary';
    $user_icon = 'fa-user-shield';
} elseif ($user_role === 'teacher') {
    $dashboard_link = 'dashboard_teacher.php';
    $navbar_color = 'bg-success';
    $user_icon = 'fa-chalkboard-teacher';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Grades - Groupe IKI</title>
    
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
    <nav class="navbar navbar-expand-lg  <?php echo $navbar_color; ?> navbar-light bg-white fixed-top border-bottom shadow-sm">
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
                        
                        <?php if ($user_role === 'admin' || $user_role === 'teacher'): ?>
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
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="view_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                View Grades
                            </a>
                        </li>
                        
                        <?php if ($user_role === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="student_absences.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                View Absences
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
                        <?php echo $user_role === 'student' ? 'My Grades' : 'All Grades'; ?>
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

                <!-- Grades Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            Grades Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($grades)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No grades found</h5>
                            <p class="text-muted">
                                <?php echo $user_role === 'student' ? 'You don\'t have any grades yet.' : 'No grades have been recorded yet.'; ?>
                            </p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-primary">
                                    <tr>
                                        <?php if ($user_role !== 'student'): ?>
                                        <th>Student</th>
                                        <th>CNI</th>
                                        <?php endif; ?>
                                        <th>Module</th>
                                        <th>Filière</th>
                                        <th>Grade</th>
                                        <th>Date</th>
                                        <!-- <th>Recorded By</th> -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grades as $grade): ?>
                                    <tr>
                                        <?php if ($user_role !== 'student'): ?>
                                        <td><?php echo htmlspecialchars($grade['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($grade['student_cni']); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <i class="fas fa-book me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($grade['module_name']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($grade['filiere_name']); ?>
                                            </span>
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
                                        <!-- <td>
                                            <?php if ($grade['teacher_name']): ?>
                                                <i class="fas fa-chalkboard-teacher me-2 text-success"></i>
                                                <?php echo htmlspecialchars($grade['teacher_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td> -->
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($user_role === 'student' && !empty($grades)): ?>
                        <!-- Grade Statistics for Students -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6 class="mb-3">Grade Statistics</h6>
                            </div>
                            <?php
                            $totalGrades = count($grades);
                            $sumGrades = array_sum(array_column($grades, 'grade'));
                            $averageGrade = $totalGrades > 0 ? $sumGrades / $totalGrades : 0;
                            $passedGrades = count(array_filter($grades, function($g) { return floatval($g['grade']) >= 10; }));
                            $passRate = $totalGrades > 0 ? ($passedGrades / $totalGrades) * 100 : 0;
                            ?>
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5><?php echo $totalGrades; ?></h5>
                                        <small>Total Grades</small>
                                    </div>
                                </div>
                            </div>
                            <!-- <div class="col-md-3">
                                <div class="card bg-info text-white">
                                     <div class="card-body text-center">
                                         <h5> -->
                                        <!-- </h5> -->
                                        <!-- <small>Average Grade</small> -->
                                    <!-- </div>  -->
                                <!-- </div> -->
                            <!-- </div>  -->
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5><?php echo $passedGrades; ?></h5>
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
                        <?php endif; ?>
                        <?php endif; ?>
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
