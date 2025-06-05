<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];

// Database connection to get student details
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

$student_name = '';
$student_filiere = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get student details using correct column names
    $stmt = $pdo->prepare("
        SELECT s.nom, s.prenom, s.niveau, f.name as filiere_name 
        FROM students s 
        LEFT JOIN filieres f ON s.filiere_id = f.id 
        WHERE s.cni = ?
    ");
    $stmt->execute([$user_cni]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        $student_name = $student['prenom'] . ' ' . $student['nom'];
        $student_filiere = $student['filiere_name'] ?? 'No filière assigned';
        $student_niveau = $student['niveau'] ?? '';
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
    <title>Student Dashboard - Groupe IKI</title>
    
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
                            <a class="nav-link active" href="dashboard_student.php">
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
                        <i class="fas fa-user-graduate me-2"></i>
                        Student Dashboard
                    </h1>
                    <button class="btn btn-outline-info d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <!-- Welcome Message -->
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-hand-wave me-2"></i>
                    <strong>Welcome, <?php echo $student_name ? htmlspecialchars($student_name) : 'Student'; ?>!</strong> 
                    <br>
                    <small>
                        CNI: <?php echo htmlspecialchars($user_cni); ?>
                        <?php if ($student_filiere): ?>
                            | Filière: <?php echo htmlspecialchars($student_filiere); ?>
                        <?php endif; ?>
                        <?php if ($student_niveau): ?>
                            | Level: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $student_niveau))); ?>
                        <?php endif; ?>
                    </small>
                </div>

                <!-- Dashboard Cards -->
                <div class="row g-4">
                    <!-- View Grades Card -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card dashboard-card h-100">
                            <div class="card-body">
                                <div class="card-icon">
                                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 p-4 mb-3">
                                        <i class="fas fa-chart-line fa-2x text-warning"></i>
                                    </div>
                                </div>
                                <h5 class="card-title">Mes Notes</h5>
                                <p class="card-text">Consultez vos résultats académiques et votre progression.</p>
                                <a href="view_grades.php" class="btn btn-warning">
                                    <i class="fas fa-eye me-2"></i>Consulter
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- View Attendance Card -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card dashboard-card h-100">
                            <div class="card-body">
                                <div class="card-icon">
                                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 p-4 mb-3">
                                        <i class="fas fa-calendar-check fa-2x text-success"></i>
                                    </div>
                                </div>
                                <h5 class="card-title">Mes Absences</h5>
                                <p class="card-text">Vérifiez votre historique de présence et d'absences.</p>
                                <a href="student_absences.php" class="btn btn-success">
                                    <i class="fas fa-eye me-2"></i>Consulter
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- View Messages Card -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card dashboard-card h-100">
                            <div class="card-body">
                                <div class="card-icon">
                                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 p-4 mb-3">
                                        <i class="fas fa-inbox fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <h5 class="card-title">Mes Messages</h5>
                                <p class="card-text">Consultez les annonces et messages de vos enseignants.</p>
                                <a href="view_messages.php" class="btn btn-primary">
                                    <i class="fas fa-eye me-2"></i>Consulter
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
