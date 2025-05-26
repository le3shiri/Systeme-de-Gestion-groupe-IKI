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
    
    $messages = [];
    
    if ($user_role === 'student') {
        // Get student's filiere and messages
        $stmt = $pdo->prepare("SELECT filiere_id FROM students WHERE cni = ?");
        $stmt->execute([$user_cni]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        $student_filiere_id = $student['filiere_id'] ?? null;
        
        // Get messages for this student
        $stmt = $pdo->prepare("
            SELECT m.content, m.date, m.type,
                   CASE 
                       WHEN m.sender_cni IN (SELECT cni FROM admins) THEN 'Admin'
                       WHEN m.sender_cni IN (SELECT cni FROM teachers) THEN 'Teacher'
                       ELSE 'System'
                   END as sender_type,
                   COALESCE(
                       (SELECT CONCAT(prenom, ' ', nom) FROM admins WHERE cni = m.sender_cni),
                       (SELECT CONCAT(prenom, ' ', nom) FROM teachers WHERE cni = m.sender_cni),
                       'System'
                   ) as sender_name,
                   modules.name as module_name,
                   f.name as filiere_name
            FROM messages m
            LEFT JOIN modules ON m.module_id = modules.id
            LEFT JOIN filieres f ON m.target_classe_id = f.id
            WHERE (m.target_cni = ? OR m.target_cni IS NULL)
              AND (m.target_classe_id IS NULL OR m.target_classe_id = ?)
            ORDER BY m.date DESC
        ");
        $stmt->execute([$user_cni, $student_filiere_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Get all messages for teachers and admins
        $stmt = $pdo->query("
            SELECT m.content, m.date, m.type, m.target_cni,
                   CASE 
                       WHEN m.sender_cni IN (SELECT cni FROM admins) THEN 'Admin'
                       WHEN m.sender_cni IN (SELECT cni FROM teachers) THEN 'Teacher'
                       ELSE 'System'
                   END as sender_type,
                   COALESCE(
                       (SELECT CONCAT(prenom, ' ', nom) FROM admins WHERE cni = m.sender_cni),
                       (SELECT CONCAT(prenom, ' ', nom) FROM teachers WHERE cni = m.sender_cni),
                       'System'
                   ) as sender_name,
                   COALESCE(
                       (SELECT CONCAT(prenom, ' ', nom) FROM students WHERE cni = m.target_cni),
                       'All Students'
                   ) as target_name,
                   modules.name as module_name,
                   f.name as filiere_name
            FROM messages m
            LEFT JOIN modules ON m.module_id = modules.id
            LEFT JOIN filieres f ON m.target_classe_id = f.id
            ORDER BY m.date DESC
            LIMIT 100
        ");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>View Messages - Groupe IKI</title>
    
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
                                Record Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="send_message.php">
                                <i class="fas fa-paper-plane me-2"></i>
                                Send Messages
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($user_role === 'student'): ?>
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
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="view_messages.php">
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
                        <i class="fas fa-inbox me-2"></i>
                        Messages & Announcements
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

                <!-- Messages List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-envelope me-2"></i>
                            Your Messages
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No messages found</h5>
                            <p class="text-muted">You don't have any messages yet.</p>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($messages as $message): ?>
                            <div class="col-12 mb-3">
                                <div class="card message-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <?php if ($message['type'] === 'announcement'): ?>
                                                    <span class="badge bg-warning me-2">
                                                        <i class="fas fa-bullhorn me-1"></i>Announcement
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-info me-2">
                                                        <i class="fas fa-envelope me-1"></i>Message
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <strong class="text-primary">
                                                    <?php echo htmlspecialchars($message['sender_name']); ?>
                                                </strong>
                                                <small class="text-muted ms-2">
                                                    (<?php echo htmlspecialchars($message['sender_type']); ?>)
                                                </small>
                                            </div>
                                            
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($message['date'])); ?>
                                            </small>
                                        </div>
                                        
                                        <?php if ($user_role !== 'student' && isset($message['target_name'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-arrow-right me-1"></i>
                                                To: <?php echo htmlspecialchars($message['target_name']); ?>
                                                <?php if ($message['filiere_name']): ?>
                                                    (<?php echo htmlspecialchars($message['filiere_name']); ?>)
                                                <?php endif; ?>
                                                <?php if ($message['module_name']): ?>
                                                    - Module: <?php echo htmlspecialchars($message['module_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                        </div>
                                        
                                        <?php if ($message['module_name'] && $user_role === 'student'): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-book me-1"></i>
                                                Module: <?php echo htmlspecialchars($message['module_name']); ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
    
    <style>
        .message-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .message-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .message-content {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 3px solid #dee2e6;
        }
    </style>
</body>
</html>
