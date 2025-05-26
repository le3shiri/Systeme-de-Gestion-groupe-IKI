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
    
    // Fetch filieres for class selection and messaging
    $stmt = $pdo->query("SELECT id, name FROM filieres ORDER BY name");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all students with their filiere information
    $stmt = $pdo->query("
        SELECT s.cni, CONCAT(s.prenom, ' ', s.nom) as full_name, 
               COALESCE(f.name, 'No Class') as filiere_name, 
               COALESCE(s.niveau, '') as niveau,
               s.filiere_id
        FROM students s 
        LEFT JOIN filieres f ON s.filiere_id = f.id 
        ORDER BY f.name, s.nom, s.prenom
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch modules for module-specific messaging
    $stmt = $pdo->query("
        SELECT m.id, m.name, f.name as filiere_name 
        FROM modules m 
        LEFT JOIN filieres f ON m.filiere_id = f.id 
        ORDER BY f.name, m.name
    ");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database connection failed.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message_type = trim($_POST['message_type'] ?? '');
    $target_type = trim($_POST['target_type'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($message_type) || empty($target_type) || empty($content)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            $target_cni = null;
            $target_classe_id = null;
            $module_id = null;
            
            // Determine target based on type
            if ($target_type === 'individual') {
                $target_cni = trim($_POST['target_student'] ?? '');
                if (empty($target_cni)) {
                    $error_message = 'Please select a student.';
                }
            } elseif ($target_type === 'class') {
                $target_classe_id = trim($_POST['target_class'] ?? '');
                if (empty($target_classe_id)) {
                    $error_message = 'Please select a class.';
                }
            } elseif ($target_type === 'module') {
                $module_id = trim($_POST['target_module'] ?? '');
                if (empty($module_id)) {
                    $error_message = 'Please select a module.';
                }
            }
            
            if (empty($error_message)) {
                $stmt = $pdo->prepare("
                    INSERT INTO messages (sender_cni, target_cni, target_classe_id, module_id, content, date, type) 
                    VALUES (?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([$user_cni, $target_cni, $target_classe_id, $module_id, $content, $message_type]);
                
                $success_message = 'Message sent successfully!';
                
                // Clear form data
                $_POST = [];
            }
            
        } catch (PDOException $e) {
            $error_message = 'Error sending message. Please try again.';
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
    <title>Send Message - Groupe IKI</title>
    
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
                            <a class="nav-link" href="record_absence.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                Record Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="send_message.php">
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
                        <i class="fas fa-paper-plane me-2"></i>
                        Send Message
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

                <!-- Send Message Form -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-envelope me-2"></i>
                                    Compose Message
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="send_message.php" id="messageForm" novalidate>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="message_type" class="form-label">
                                                    <i class="fas fa-tag me-2"></i>Message Type *
                                                </label>
                                                <select class="form-select" id="message_type" name="message_type" required>
                                                    <option value="">Select Type</option>
                                                    <option value="message" <?php echo (($_POST['message_type'] ?? '') === 'message') ? 'selected' : ''; ?>>Personal Message</option>
                                                    <option value="announcement" <?php echo (($_POST['message_type'] ?? '') === 'announcement') ? 'selected' : ''; ?>>Announcement</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a message type.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="target_type" class="form-label">
                                                    <i class="fas fa-users me-2"></i>Send To *
                                                </label>
                                                <select class="form-select" id="target_type" name="target_type" required>
                                                    <option value="">Select Target</option>
                                                    <option value="individual" <?php echo (($_POST['target_type'] ?? '') === 'individual') ? 'selected' : ''; ?>>Individual Student</option>
                                                    <option value="class" <?php echo (($_POST['target_type'] ?? '') === 'class') ? 'selected' : ''; ?>>Entire Class</option>
                                                    <option value="module" <?php echo (($_POST['target_type'] ?? '') === 'module') ? 'selected' : ''; ?>>Module Students</option>
                                                    <option value="all" <?php echo (($_POST['target_type'] ?? '') === 'all') ? 'selected' : ''; ?>>All Students</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a target.
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Individual Student Selection -->
                                    <div id="individualTarget" class="target-selection" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="student_filiere_filter" class="form-label">
                                                        <i class="fas fa-filter me-2"></i>Filter by Class (Optional)
                                                    </label>
                                                    <select class="form-select" id="student_filiere_filter">
                                                        <option value="">All Classes</option>
                                                        <?php foreach ($filieres as $filiere): ?>
                                                        <option value="<?php echo $filiere['id']; ?>">
                                                            <?php echo htmlspecialchars($filiere['name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="form-text">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Select a class to filter students, or leave as "All Classes" to see everyone
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="target_student" class="form-label">
                                                        <i class="fas fa-user-graduate me-2"></i>Select Student *
                                                    </label>
                                                    <select class="form-select" id="target_student" name="target_student">
                                                        <option value="">Choose a student</option>
                                                        <?php foreach ($students as $student): ?>
                                                        <option value="<?php echo htmlspecialchars($student['cni']); ?>" 
                                                                data-filiere="<?php echo $student['filiere_id'] ?? ''; ?>"
                                                                <?php echo (($_POST['target_student'] ?? '') === $student['cni']) ? 'selected' : ''; ?>>
                                                            <?php 
                                                            echo htmlspecialchars($student['full_name'] . ' (' . $student['cni'] . ')');
                                                            if ($student['filiere_name'] && $student['filiere_name'] !== 'No Class') {
                                                                echo ' - ' . htmlspecialchars($student['filiere_name']);
                                                            }
                                                            if ($student['niveau']) {
                                                                $niveau_formatted = ucwords(str_replace('_', ' ', $student['niveau']));
                                                                echo ' [' . htmlspecialchars($niveau_formatted) . ']';
                                                            }
                                                            ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Please select a student.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Student count info -->
                                        <div class="mb-3">
                                            <div class="alert alert-info" id="studentCountInfo" style="display: none;">
                                                <i class="fas fa-users me-2"></i>
                                                <span id="studentCountText"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Class Selection -->
                                    <div id="classTarget" class="target-selection" style="display: none;">
                                        <div class="mb-3">
                                            <label for="target_class" class="form-label">
                                                <i class="fas fa-users me-2"></i>Select Class
                                            </label>
                                            <select class="form-select" id="target_class" name="target_class">
                                                <option value="">Choose a class</option>
                                                <?php foreach ($filieres as $filiere): ?>
                                                <option value="<?php echo $filiere['id']; ?>"
                                                        <?php echo (($_POST['target_class'] ?? '') == $filiere['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($filiere['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Module Selection -->
                                    <div id="moduleTarget" class="target-selection" style="display: none;">
                                        <div class="mb-3">
                                            <label for="target_module" class="form-label">
                                                <i class="fas fa-book me-2"></i>Select Module
                                            </label>
                                            <select class="form-select" id="target_module" name="target_module">
                                                <option value="">Choose a module</option>
                                                <?php foreach ($modules as $module): ?>
                                                <option value="<?php echo $module['id']; ?>"
                                                        <?php echo (($_POST['target_module'] ?? '') == $module['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($module['name']); ?>
                                                    <?php if ($module['filiere_name']): ?>
                                                        (<?php echo htmlspecialchars($module['filiere_name']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="content" class="form-label">
                                            <i class="fas fa-comment me-2"></i>Message Content *
                                        </label>
                                        <textarea class="form-control" 
                                                  id="content" 
                                                  name="content" 
                                                  rows="6" 
                                                  placeholder="Enter your message content here..."
                                                  required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                                        <div class="invalid-feedback">
                                            Please enter message content.
                                        </div>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Maximum 1000 characters
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="reset" class="btn btn-secondary me-md-2">
                                            <i class="fas fa-undo me-2"></i>Clear
                                        </button>
                                        <button type="submit" class="btn btn-primary" id="submitBtn">
                                            <span class="btn-text">
                                                <i class="fas fa-paper-plane me-2"></i>Send Message
                                            </span>
                                            <span class="btn-loading d-none">
                                                <span class="spinner-border spinner-border-sm me-2"></span>
                                                Sending...
                                            </span>
                                        </button>
                                    </div>
                                </form>
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
    
    <script>
        // Handle target type changes
        document.getElementById('target_type').addEventListener('change', function() {
            const targetType = this.value;
            const targetSelections = document.querySelectorAll('.target-selection');
            
            // Hide all target selections
            targetSelections.forEach(selection => {
                selection.style.display = 'none';
            });
            
            // Show relevant target selection
            if (targetType === 'individual') {
                document.getElementById('individualTarget').style.display = 'block';
                // Filter students initially
                filterStudents();
            } else if (targetType === 'class') {
                document.getElementById('classTarget').style.display = 'block';
            } else if (targetType === 'module') {
                document.getElementById('moduleTarget').style.display = 'block';
            }
        });
        
        // Handle filiere filter change for individual students
        document.getElementById('student_filiere_filter').addEventListener('change', function() {
            filterStudents();
        });
        
        // Function to filter students based on selected filiere
        function filterStudents() {
            const filiereFilter = document.getElementById('student_filiere_filter').value;
            const studentSelect = document.getElementById('target_student');
            const countInfo = document.getElementById('studentCountInfo');
            const countText = document.getElementById('studentCountText');
            
            const allOptions = studentSelect.querySelectorAll('option');
            let visibleCount = 0;
            
            // Show/hide options based on filter
            allOptions.forEach(option => {
                if (option.value === '') {
                    // Keep the default "Choose a student" option
                    option.style.display = 'block';
                    return;
                }
                
                const studentFiliere = option.getAttribute('data-filiere');
                
                if (filiereFilter === '' || filiereFilter === studentFiliere) {
                    option.style.display = 'block';
                    visibleCount++;
                } else {
                    option.style.display = 'none';
                    // If this option was selected and now hidden, deselect it
                    if (option.selected) {
                        option.selected = false;
                        studentSelect.value = '';
                    }
                }
            });
            
            // Update count display
            if (filiereFilter === '') {
                countText.textContent = `Showing all ${visibleCount} students`;
            } else {
                const filiereSelect = document.getElementById('student_filiere_filter');
                const selectedFiliereName = filiereSelect.options[filiereSelect.selectedIndex].text;
                countText.textContent = `Showing ${visibleCount} students from ${selectedFiliereName}`;
            }
            
            countInfo.style.display = 'block';
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const targetType = document.getElementById('target_type').value;
            if (targetType) {
                document.getElementById('target_type').dispatchEvent(new Event('change'));
            }
        });
        
        // Handle form reset
        document.querySelector('button[type="reset"]').addEventListener('click', function() {
            // Hide all target selections
            document.querySelectorAll('.target-selection').forEach(selection => {
                selection.style.display = 'none';
            });
            
            // Hide student count info
            document.getElementById('studentCountInfo').style.display = 'none';
            
            // Reset student filter
            document.getElementById('student_filiere_filter').value = '';
        });
    </script>
</body>
</html>
