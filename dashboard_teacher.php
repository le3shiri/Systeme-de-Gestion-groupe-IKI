<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];

// Database connection to get teacher details
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

$teacher_name = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get teacher details using correct column names
    $stmt = $pdo->prepare("SELECT nom, prenom FROM teachers WHERE cni = ?");
    $stmt->execute([$user_cni]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teacher) {
        $teacher_name = $teacher['prenom'] . ' ' . $teacher['nom'];
    }
} catch (PDOException $e) {
    // Handle error silently for dashboard
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Groupe IKI</title>
    
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
            <a class="navbar-brand d-flex align-items-center" href="dashboard_teacher.php">
                <!-- <i class="fas fa-graduation-cap me-2"></i>
                <span class="fw-bold">Groupe IKI</span> -->
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
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            Teacher (<?php echo htmlspecialchars($user_cni); ?>)
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
                            <a class="nav-link active" href="dashboard_teacher.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
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
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        Teacher Dashboard
                    </h1>
                    <button class="btn btn-outline-success d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <!-- Welcome Message -->
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-hand-wave me-2"></i>
                    <strong>Welcome, <?php echo $teacher_name ? htmlspecialchars($teacher_name) : 'Teacher'; ?>!</strong> 
                    CNI: <?php echo htmlspecialchars($user_cni); ?>
                </div>

                <!-- Dashboard Cards -->
                <div class="row g-4">
                    <!-- Manage Grades Card -->
                    <div class="col-md-6 col-lg-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body text-center">
                                <div class="card-icon">
                                    <i class="fas fa-chart-line fa-3x text-warning mb-3"></i>
                                </div>
                                <h5 class="card-title">Enter Grades</h5>
                                <p class="card-text">Record and manage student grades for your modules.</p>
                                <a href="manage_grades.php" class="btn btn-warning">
                                    <i class="fas fa-arrow-right me-2"></i>Go
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Record Attendance Card -->
                    <div class="col-md-6 col-lg-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body text-center">
                                <div class="card-icon">
                                    <i class="fas fa-calendar-check fa-3x text-info mb-3"></i>
                                </div>
                                <h5 class="card-title">Record Attendance</h5>
                                <p class="card-text">Mark student attendance and manage absences.</p>
                                <a href="record_absence.php" class="btn btn-info">
                                    <i class="fas fa-arrow-right me-2"></i>Go
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Send Messages Card -->
                    <div class="col-md-6 col-lg-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body text-center">
                                <div class="card-icon">
                                    <i class="fas fa-paper-plane fa-3x text-danger mb-3"></i>
                                </div>
                                <h5 class="card-title">Send Messages</h5>
                                <p class="card-text">Send messages and announcements to students.</p>
                                <a href="send_message.php" class="btn btn-danger">
                                    <i class="fas fa-arrow-right me-2"></i>Go
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- View Messages Card -->
                    <div class="col-md-6 col-lg-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body text-center">
                                <div class="card-icon">
                                    <i class="fas fa-inbox fa-3x text-secondary mb-3"></i>
                                </div>
                                <h5 class="card-title">View Messages</h5>
                                <p class="card-text">Check your messages and communications.</p>
                                <a href="view_messages.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right me-2"></i>Go
                                </a>
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
