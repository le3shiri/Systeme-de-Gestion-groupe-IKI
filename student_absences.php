<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];

// Database connection
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get student's absences
    $stmt = $pdo->prepare("
        SELECT a.date, a.status, m.name as module_name, f.name as filiere_name,
               CONCAT(t.prenom, ' ', t.nom) as teacher_name,
               CONCAT(ad.prenom, ' ', ad.nom) as admin_name
        FROM absences a
        JOIN students s ON a.student_id = s.id
        JOIN modules m ON a.module_id = m.id
        JOIN filieres f ON m.filiere_id = f.id
        LEFT JOIN teachers t ON a.recorded_by_teacher_id = t.id
        LEFT JOIN admins ad ON a.recorded_by_admin_id = ad.id
        WHERE s.cni = ?
        ORDER BY a.date DESC
    ");
    $stmt->execute([$user_cni]);
    $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database connection failed.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Absences - Groupe IKI</title>
    
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
            <a class="navbar-brand d-flex align-items-center" href="dashboard_student.php">
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
                            <i class="fas fa-user-graduate me-2"></i>
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
                            <a class="nav-link" href="dashboard_student.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                View Grades
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="student_absences.php">
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
                        <i class="fas fa-calendar-check me-2"></i>
                        My Attendance Record
                    </h1>
                    <button class="btn btn-outline-info d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <!-- Attendance Statistics -->
                <?php if (!empty($absences)): ?>
                <?php
                $totalRecords = count($absences);
                $presentCount = count(array_filter($absences, function($a) { return $a['status'] === 'present'; }));
                $absentCount = $totalRecords - $presentCount;
                $attendanceRate = $totalRecords > 0 ? ($presentCount / $totalRecords) * 100 : 0;
                ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar fa-2x mb-2"></i>
                                <h4><?php echo $totalRecords; ?></h4>
                                <small>Total Records</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-check fa-2x mb-2"></i>
                                <h4><?php echo $presentCount; ?></h4>
                                <small>Present</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-times fa-2x mb-2"></i>
                                <h4><?php echo $absentCount; ?></h4>
                                <small>Absent</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-percentage fa-2x mb-2"></i>
                                <h4><?php echo number_format($attendanceRate, 1); ?>%</h4>
                                <small>Attendance Rate</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Attendance Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            Attendance Records
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($absences)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No attendance records found</h5>
                            <p class="text-muted">Your attendance records will appear here once they are recorded.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-info">
                                    <tr>
                                        <th>Date</th>
                                        <th>Module</th>
                                        <th>Filière</th>
                                        <th>Status</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($absences as $absence): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-calendar me-2 text-muted"></i>
                                            <?php echo date('d/m/Y', strtotime($absence['date'])); ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-book me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($absence['module_name']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($absence['filiere_name']); ?>
                                            </span>
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
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/scripts.js"></script>
</body>
</html>
