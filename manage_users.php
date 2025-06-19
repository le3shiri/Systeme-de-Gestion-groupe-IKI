<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];
$success_message = '';
$error_message = '';

// Get the current section and action from URL parameters
$current_section = isset($_GET['section']) ? $_GET['section'] : '';
$current_action = isset($_GET['action']) ? $_GET['action'] : '';

// Proceed to in-page assignment section (no redirect)
// (do not remove this block; logic continues later)

// Handle connection test
if (isset($_GET['test_connection'])) {
    header('Location: manage_users.php');
    exit();
}

// Database connection with better error handling
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

try {
    // First, try to connect without specifying database to check if MySQL is running
    $pdo_test = new PDO("mysql:host=$host;charset=utf8", $username, $db_password);
    $pdo_test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists
    $stmt = $pdo_test->query("SHOW DATABASES LIKE '$dbname'");
    if ($stmt->rowCount() == 0) {
        // Database doesn't exist, create it
        $pdo_test->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8 COLLATE utf8_general_ci");
        $success_message = "Database '$dbname' created successfully. Please run the setup scripts to create tables.";
    }
    
    // Now connect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if required tables exist
    $required_tables = ['students', 'teachers', 'filieres', 'classes', 'modules'];
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (!empty($missing_tables)) {
        $error_message = "Missing database tables: " . implode(', ', $missing_tables) . ". Please run the database setup script.";
        $show_setup_button = true;
    } else {
        // Fetch data only if tables exist
        $stmt = $pdo->query("SELECT id, name FROM filieres ORDER BY name");
        $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT id, name, filiere_id FROM classes ORDER BY name");
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT id, name, filiere_id FROM modules ORDER BY name");
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error_message = 'Database connection failed: ' . $e->getMessage();
    $show_connection_help = true;
    
    // Initialize empty arrays to prevent errors
    $filieres = [];
    $classes = [];
    $modules = [];
}

// Handle form submission for adding new users
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $role = trim($_POST['role'] ?? '');
    $cni = trim($_POST['cni'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Validate required fields
    if (empty($role) || empty($cni) || empty($nom) || empty($prenom) || empty($password)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($role === 'student') {
                // Student-specific fields
                $date_naissance = !empty($_POST['date_naissance']) ? $_POST['date_naissance'] : null;
                $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
                $adresse = trim($_POST['adresse'] ?? '');
                $date_inscription = !empty($_POST['date_inscription']) ? $_POST['date_inscription'] : null;
                $niveau = trim($_POST['niveau'] ?? '');
                $telephone = trim($_POST['num_telephone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $filiere_id = !empty($_POST['filiere_id']) ? (int)$_POST['filiere_id'] : null;
                
                $stmt = $pdo->prepare("INSERT INTO students (cni, nom, prenom, password, date_naissance, lieu_naissance, adresse, date_inscription, niveau, num_telephone, email, filiere_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cni, $nom, $prenom, $hashed_password, $date_naissance, $lieu_naissance, $adresse, $date_inscription, $niveau, $telephone, $email, $filiere_id]);
                
                $success_message = 'Student added successfully!';
                
            } elseif ($role === 'teacher') {
                // Teacher-specific fields
                $adresse = trim($_POST['adresse'] ?? '');
                $type_contrat = trim($_POST['type_contrat'] ?? '');
                $date_embauche = !empty($_POST['date_embauche']) ? $_POST['date_embauche'] : null;
                $dernier_diplome = trim($_POST['dernier_diplome'] ?? '');
                $telephone = trim($_POST['num_telephone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                
                $stmt = $pdo->prepare("INSERT INTO teachers (cni, nom, prenom, password, adresse, type_contrat, date_embauche, dernier_diplome, num_telephone, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cni, $nom, $prenom, $hashed_password, $adresse, $type_contrat, $date_embauche, $dernier_diplome, $telephone, $email]);
                
                $success_message = 'Teacher added successfully!';
            }
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error_message = 'CNI already exists. Please use a different CNI.';
            } else {
                $error_message = 'Error adding user: ' . $e->getMessage();
            }
        }
    }
}

// Handle account status change
if (isset($_GET['status_action']) && isset($_GET['cni']) && $_GET['status_action'] && $_GET['cni']) {
    $status_action = $_GET['status_action'];
    $teacher_cni = $_GET['cni'];
    
    if ($status_action === 'suspend' || $status_action === 'activate') {
        try {
            $new_status = ($status_action === 'suspend') ? 'suspended' : 'active';
            
            $stmt = $pdo->prepare("UPDATE teachers SET account_status = ? WHERE cni = ?");
            $stmt->execute([$new_status, $teacher_cni]);
            
            if ($stmt->rowCount() > 0) {
                $action_text = ($status_action === 'suspend') ? 'suspended' : 'activated';
                $success_message = "Teacher account has been {$action_text} successfully!";
            } else {
                $error_message = 'Teacher not found or status unchanged.';
            }
            
        } catch (PDOException $e) {
            $error_message = 'Error updating teacher status: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Invalid status action.';
    }
}

// Handle assignment of teacher to module
if ($current_section === 'teachers' && $current_action === 'assign') {
    // Handle deactivate request
    if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate'])) {
        $deact_id = (int)$_GET['deactivate'];
        try {
            $stmt = $pdo->prepare("UPDATE teacher_module_assignments SET is_active = 0 WHERE id = ?");
            $stmt->execute([$deact_id]);
            $success_message = 'Assignment deactivated successfully!';
        } catch (PDOException $e) {
            $error_message = 'Error deactivating assignment: ' . $e->getMessage();
        }
    }

    // On form submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher_module'])) {
        $teacher_cni = trim($_POST['teacher_cni'] ?? '');
        $module_id = (int)($_POST['module_id'] ?? 0);
        $filiere_id = (int)($_POST['filiere_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        if ($teacher_cni && $module_id && $filiere_id) {
            try {
                // Get teacher ID
                $stmt = $pdo->prepare("SELECT id FROM teachers WHERE cni = ?");
                $stmt->execute([$teacher_cni]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($teacher) {
                    $teacher_id = $teacher['id'];
                    // Prevent duplicate active assignment
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_module_assignments WHERE teacher_id = ? AND module_id = ? AND filiere_id = ? AND is_active = 1");
                    $stmt->execute([$teacher_id, $module_id, $filiere_id]);
                    if ($stmt->fetchColumn() == 0) {
                        $stmt = $pdo->prepare("INSERT INTO teacher_module_assignments (teacher_id, teacher_cni, module_id, filiere_id, assigned_date, assigned_by_admin_cni, is_active, notes) VALUES (?,?,?,?,CURDATE(),?,1,?)");
                        $stmt->execute([$teacher_id, $teacher_cni, $module_id, $filiere_id, $user_cni, $notes]);
                        $success_message = 'Teacher assigned to module successfully!';
                    } else {
                        $error_message = 'This teacher is already assigned to the selected module.';
                    }
                } else {
                    $error_message = 'Teacher not found.';
                }
            } catch (PDOException $e) {
                $error_message = 'Error assigning module: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Please select filière and module.';
        }
    }
}

// Handle update operation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $update_cni = trim($_POST['update_cni'] ?? '');
    $update_role = trim($_POST['update_role'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    
    if (empty($update_cni) || empty($update_role) || empty($nom) || empty($prenom)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            if ($update_role === 'teacher') {
                $email = trim($_POST['email'] ?? '');
                $telephone = trim($_POST['num_telephone'] ?? '');
                $adresse = trim($_POST['adresse'] ?? '');
                
                $email = !empty($email) ? $email : null;
                $telephone = !empty($telephone) ? $telephone : null;
                $adresse = !empty($adresse) ? $adresse : null;
                
                $stmt = $pdo->prepare("UPDATE teachers SET nom = ?, prenom = ?, email = ?, num_telephone = ?, adresse = ? WHERE cni = ?");
                $stmt->execute([$nom, $prenom, $email, $telephone, $adresse, $update_cni]);
                
            } elseif ($update_role === 'student') {
                $email = trim($_POST['email'] ?? '');
                $telephone = trim($_POST['num_telephone'] ?? '');
                $niveau = trim($_POST['niveau'] ?? '');
                $filiere_id = trim($_POST['filiere_id'] ?? '');
                
                $email = !empty($email) ? $email : null;
                $telephone = !empty($telephone) ? $telephone : null;
                $niveau = !empty($niveau) ? $niveau : null;
                $filiere_id = !empty($filiere_id) ? (int)$filiere_id : null;
                
                $stmt = $pdo->prepare("UPDATE students SET nom = ?, prenom = ?, email = ?, num_telephone = ?, niveau = ?, filiere_id = ? WHERE cni = ?");
                $stmt->execute([$nom, $prenom, $email, $telephone, $niveau, $filiere_id, $update_cni]);
            }
            
            $success_message = ucfirst($update_role) . ' updated successfully!';
            
        } catch (PDOException $e) {
            $error_message = 'Error updating user: ' . $e->getMessage();
        }
    }
}

// Handle delete operation
if (isset($_GET['delete_action']) && $_GET['delete_action'] === 'confirm' && isset($_GET['cni']) && isset($_GET['role'])) {
    $delete_cni = $_GET['cni'];
    $delete_role = $_GET['role'];
    
    try {
        if ($delete_role === 'teacher') {
            $stmt = $pdo->prepare("DELETE FROM teachers WHERE cni = ?");
        } elseif ($delete_role === 'student') {
            $stmt = $pdo->prepare("DELETE FROM students WHERE cni = ?");
        }
        
        if (isset($stmt)) {
            $stmt->execute([$delete_cni]);
            $success_message = ucfirst($delete_role) . ' deleted successfully!';
        }
    } catch (PDOException $e) {
        $error_message = 'Error deleting user. They may have associated records.';
    }
}

// After the existing fetch students code (around line 200), replace the students fetching section with:

// Fetch students when needed with filters
$students = [];
if ($current_section === 'students' && in_array($current_action, ['edit', 'delete'])) {
    try {
        // Build the WHERE clause based on filters
        $where_conditions = [];
        $params = [];
        
        // Filter by filière
        if (!empty($_GET['filter_filiere'])) {
            $where_conditions[] = "s.filiere_id = ?";
            $params[] = (int)$_GET['filter_filiere'];
        }
        
        // Filter by level
        if (!empty($_GET['filter_niveau'])) {
            $where_conditions[] = "s.niveau = ?";
            $params[] = $_GET['filter_niveau'];
        }
        
        // Search by name or CNI
        if (!empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $where_conditions[] = "(s.cni LIKE ? OR s.nom LIKE ? OR s.prenom LIKE ? OR s.email LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $sql = "
            SELECT s.cni, s.nom, s.prenom, s.email, s.num_telephone, s.date_naissance, 
                   s.lieu_naissance, s.adresse, s.date_inscription, s.niveau,
                   f.name as filiere_name, f.id as filiere_id
            FROM students s 
            LEFT JOIN filieres f ON s.filiere_id = f.id 
            $where_clause
            ORDER BY s.nom, s.prenom
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = 'Error fetching students.';
    }
}

// Fetch teachers when needed with filters
$teachers = [];
if ($current_section === 'teachers' && in_array($current_action, ['edit', 'delete', 'hold', 'assign'])) {
    try {
        // Build the WHERE clause based on filters
        $where_conditions = [];
        $params = [];
        
        // Filter by contract type
        if (!empty($_GET['filter_contract'])) {
            $where_conditions[] = "t.type_contrat = ?";
            $params[] = $_GET['filter_contract'];
        }
        
        // Filter by degree
        if (!empty($_GET['filter_degree'])) {
            $where_conditions[] = "t.dernier_diplome LIKE ?";
            $params[] = '%' . $_GET['filter_degree'] . '%';
        }
        
        // Search by name or CNI
        if (!empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $where_conditions[] = "(t.cni LIKE ? OR t.nom LIKE ? OR t.prenom LIKE ? OR t.email LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $sql = "
            SELECT t.cni, t.nom, t.prenom, t.email, t.num_telephone, t.adresse,
                   t.type_contrat, t.date_embauche, t.dernier_diplome
            FROM teachers t 
            $where_clause
            ORDER BY t.nom, t.prenom
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = 'Error fetching teachers.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Groupe IKI</title>
    
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
            <a class="navbar-brand d-flex align-items-center" href="dashboard_admin.php">
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
                            <i class="fas fa-user-shield me-2"></i>
                            Admin (<?php echo htmlspecialchars($user_cni); ?>)
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
                            <a class="nav-link" href="dashboard_admin.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_users.php">
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
                        <i class="fas fa-users me-2"></i>
                        Manage Users
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

                <!-- Database Setup Help -->
                <?php if (isset($show_connection_help)): ?>
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Database Connection Failed</h5>
                    <p>Please check the following:</p>
                    <ul>
                        <li><strong>XAMPP/WAMP is running:</strong> Make sure Apache and MySQL services are started</li>
                        <li><strong>Database credentials:</strong> Check if username/password are correct</li>
                        <li><strong>MySQL port:</strong> Default is 3306, make sure it's not blocked</li>
                    </ul>
                    <div class="mt-3">
                        <a href="?test_connection=1" class="btn btn-primary">
                            <i class="fas fa-sync me-2"></i>Test Connection Again
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($show_setup_button)): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-database me-2"></i>Database Setup Required</h5>
                    <p>Some required tables are missing. You need to run the database setup script.</p>
                    <div class="mt-3">
                        <a href="setup_database.php" class="btn btn-warning">
                            <i class="fas fa-cogs me-2"></i>Setup Database
                        </a>
                        <a href="?test_connection=1" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-sync me-2"></i>Check Again
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (empty($current_section)): ?>
                <!-- Main Selection Screen -->
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-5">
                                <div class="mb-4">
                                    <i class="fas fa-user-graduate fa-4x text-primary"></i>
                                </div>
                                <h3 class="card-title text-primary mb-3">Manage Students</h3>
                                <p class="card-text text-muted mb-4">
                                    Add new students, edit existing student information, or remove students from the system.
                                </p>
                                <a href="?section=students" class="btn btn-primary btn-lg">
                                    <i class="fas fa-arrow-right me-2"></i>
                                    Manage Students
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-5">
                                <div class="mb-4">
                                    <i class="fas fa-chalkboard-teacher fa-4x text-success"></i>
                                </div>
                                <h3 class="card-title text-success mb-3">Manage Teachers</h3>
                                <p class="card-text text-muted mb-4">
                                    Add new teachers, edit teacher profiles, assign modules, or remove teachers from the system.
                                </p>
                                <a href="?section=teachers" class="btn btn-success btn-lg">
                                    <i class="fas fa-arrow-right me-2"></i>
                                    Manage Teachers
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($current_section === 'students' && empty($current_action)): ?>
                <!-- Student Management Options -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-primary">
                        <i class="fas fa-user-graduate me-2"></i>
                        Student Management
                    </h2>
                    <a href="manage_users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Main Menu
                    </a>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-user-plus fa-3x text-success"></i>
                                </div>
                                <h5 class="card-title text-success mb-3">Add Student</h5>
                                <p class="card-text text-muted mb-4">
                                    Register a new student in the system with all required information.
                                </p>
                                <a href="?section=students&action=add" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Add New Student
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-user-edit fa-3x text-primary"></i>
                                </div>
                                <h5 class="card-title text-primary mb-3">Edit Student</h5>
                                <p class="card-text text-muted mb-4">
                                    Update existing student information and details.
                                </p>
                                <a href="?section=students&action=edit" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Students
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-user-times fa-3x text-danger"></i>
                                </div>
                                <h5 class="card-title text-danger mb-3">Delete Student</h5>
                                <p class="card-text text-muted mb-4">
                                    Remove students from the system permanently.
                                </p>
                                <a href="?section=students&action=delete" class="btn btn-danger">
                                    <i class="fas fa-trash me-2"></i>Delete Students
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($current_section === 'teachers' && empty($current_action)): ?>
                <!-- Teacher Management Options -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-success">
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        Teacher Management
                    </h2>
                    <a href="manage_users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Main Menu
                    </a>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-user-plus fa-3x text-success"></i>
                                </div>
                                <h5 class="card-title text-success mb-3">Add Teacher</h5>
                                <p class="card-text text-muted mb-4">
                                    Register a new teacher in the system with professional details.
                                </p>
                                <a href="?section=teachers&action=add" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Add New Teacher
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-user-edit fa-3x text-primary"></i>
                                </div>
                                <h5 class="card-title text-primary mb-3">Edit Teacher</h5>
                                <p class="card-text text-muted mb-4">
                                    Update teacher information and professional details.
                                </p>
                                <a href="?section=teachers&action=edit" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Teachers
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-user-times fa-3x text-danger"></i>
                                </div>
                                <h5 class="card-title text-danger mb-3">Delete Teacher</h5>
                                <p class="card-text text-muted mb-4">
                                    Remove teachers from the system permanently.
                                </p>
                                <a href="?section=teachers&action=delete" class="btn btn-danger">
                                    <i class="fas fa-trash me-2"></i>Delete Teachers
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- Assign Modules Card -->
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-user-tie fa-3x text-warning"></i>
                                </div>
                                <h5 class="card-title text-warning mb-3">Assign Modules</h5>
                                <p class="card-text text-muted mb-4">
                                    Assign teachers to specific modules in filières.
                                </p>
                                <a href="?section=teachers&action=assign" class="btn btn-warning">
                                    <i class="fas fa-user-tie me-2"></i>Manage Assignments
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-user-lock fa-3x text-warning"></i>
                                </div>
                                <h5 class="card-title text-warning mb-3">Hold Accounts</h5>
                                <p class="card-text text-muted mb-4">
                                    Temporarily suspend or reactivate teacher accounts.
                                </p>
                                <a href="?section=teachers&action=hold" class="btn btn-warning">
                                    <i class="fas fa-user-lock me-2"></i>Manage Account Status
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($current_section === 'students' && $current_action === 'add'): ?>
                <!-- Add Student Form -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-success">
                        <i class="fas fa-user-plus me-2"></i>
                        Add New Student
                    </h2>
                    <a href="?section=students" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Student Management
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="manage_users.php?section=students&action=add" novalidate>
                            <input type="hidden" name="role" value="student">
                            <input type="hidden" name="add_user" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_cni" class="form-label">
                                            <i class="fas fa-id-card me-2"></i>CNI *
                                        </label>
                                        <input type="text" class="form-control" id="student_cni" name="cni" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Password *
                                        </label>
                                        <input type="password" class="form-control" id="student_password" name="password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_nom" class="form-label">
                                            <i class="fas fa-user me-2"></i>Last Name *
                                        </label>
                                        <input type="text" class="form-control" id="student_nom" name="nom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_prenom" class="form-label">
                                            <i class="fas fa-user me-2"></i>First Name *
                                        </label>
                                        <input type="text" class="form-control" id="student_prenom" name="prenom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i>Email
                                        </label>
                                        <input type="email" class="form-control" id="student_email" name="email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_telephone" class="form-label">
                                            <i class="fas fa-phone me-2"></i>Phone Number
                                        </label>
                                        <input type="tel" class="form-control" id="student_telephone" name="num_telephone">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_date_naissance" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="student_date_naissance" name="date_naissance">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_lieu_naissance" class="form-label">Place of Birth</label>
                                        <input type="text" class="form-control" id="student_lieu_naissance" name="lieu_naissance">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_date_inscription" class="form-label">Registration Date</label>
                                        <input type="date" class="form-control" id="student_date_inscription" name="date_inscription">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_niveau" class="form-label">Level</label>
                                        <select class="form-select" id="student_niveau" name="niveau">
                                            <option value="">Select Level</option>
                                            <option value="technicien">Technicien</option>
                                            <option value="technicien_specialise">Technicien Spécialisé</option>
                                            <option value="qualifiant">Qualifiant</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_filiere_id" class="form-label">Filière</label>
                                        <select class="form-select" id="student_filiere_id" name="filiere_id">
                                            <option value="">Select Filière</option>
                                            <?php foreach ($filieres as $filiere): ?>
                                            <option value="<?php echo $filiere['id']; ?>">
                                                <?php echo htmlspecialchars($filiere['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="student_adresse" class="form-label">Address</label>
                                        <textarea class="form-control" id="student_adresse" name="adresse" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="?section=students" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-user-plus me-2"></i>Add Student
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($current_section === 'teachers' && $current_action === 'add'): ?>
                <!-- Add Teacher Form -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-success">
                        <i class="fas fa-user-plus me-2"></i>
                        Add New Teacher
                    </h2>
                    <a href="?section=teachers" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Teacher Management
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="manage_users.php?section=teachers&action=add" novalidate>
                            <input type="hidden" name="role" value="teacher">
                            <input type="hidden" name="add_user" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacher_cni" class="form-label">
                                            <i class="fas fa-id-card me-2"></i>CNI *
                                        </label>
                                        <input type="text" class="form-control" id="teacher_cni" name="cni" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacher_password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Password *
                                        </label>
                                        <input type="password" class="form-control" id="teacher_password" name="password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacher_nom" class="form-label">
                                            <i class="fas fa-user me-2"></i>Last Name *
                                        </label>
                                        <input type="text" class="form-control" id="teacher_nom" name="nom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacher_prenom" class="form-label">
                                            <i class="fas fa-user me-2"></i>First Name *
                                        </label>
                                        <input type="text" class="form-control" id="teacher_prenom" name="prenom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacher_email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i>Email
                                        </label>
                                        <input type="email" class="form-control" id="teacher_email" name="email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacher_telephone" class="form-label">
                                            <i class="fas fa-phone me-2"></i>Phone Number
                                        </label>
                                        <input type="tel" class="form-control" id="teacher_telephone" name="num_telephone">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacher_type_contrat" class="form-label">Contract Type</label>
                                        <input type="text" class="form-control" id="teacher_type_contrat" name="type_contrat">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacher_date_embauche" class="form-label">Hire Date</label>
                                        <input type="date" class="form-control" id="teacher_date_embauche" name="date_embauche">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacher_dernier_diplome" class="form-label">Latest Degree</label>
                                        <input type="text" class="form-control" id="teacher_dernier_diplome" name="dernier_diplome">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="teacher_adresse" class="form-label">Address</label>
                                        <textarea class="form-control" id="teacher_adresse" name="adresse" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="?section=teachers" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-user-plus me-2"></i>Add Teacher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($current_section === 'students' && $current_action === 'edit'): ?>
                <!-- Edit Students List -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-primary">
                        <i class="fas fa-user-edit me-2"></i>
                        Edit Students
                    </h2>
                    <a href="?section=students" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Student Management
                    </a>
                </div>

                <!-- Student Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filter Students
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="section" value="students">
                            <input type="hidden" name="action" value="edit">
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="search" 
                                       name="search" 
                                       placeholder="Name, CNI, or Email"
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_filiere" class="form-label">Filière</label>
                                <select class="form-select" id="filter_filiere" name="filter_filiere">
                                    <option value="">All Filières</option>
                                    <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?php echo $filiere['id']; ?>" 
                                            <?php echo (isset($_GET['filter_filiere']) && $_GET['filter_filiere'] == $filiere['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($filiere['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_niveau" class="form-label">Level</label>
                                <select class="form-select" id="filter_niveau" name="filter_niveau">
                                    <option value="">All Levels</option>
                                    <option value="technicien" <?php echo (isset($_GET['filter_niveau']) && $_GET['filter_niveau'] == 'technicien') ? 'selected' : ''; ?>>
                                        Technicien
                                    </option>
                                    <option value="technicien_specialise" <?php echo (isset($_GET['filter_niveau']) && $_GET['filter_niveau'] == 'technicien_specialise') ? 'selected' : ''; ?>>
                                        Technicien Spécialisé
                                    </option>
                                    <option value="qualifiant" <?php echo (isset($_GET['filter_niveau']) && $_GET['filter_niveau'] == 'qualifiant') ? 'selected' : ''; ?>>
                                        Qualifiant
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                    <a href="?section=students&action=edit" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Filter Results Summary -->
                        <?php if (!empty($_GET['search']) || !empty($_GET['filter_filiere']) || !empty($_GET['filter_niveau'])): ?>
                        <div class="mt-3">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Showing <?php echo count($students); ?> student(s)</strong>
                                <?php if (!empty($_GET['search'])): ?>
                                    matching "<?php echo htmlspecialchars($_GET['search']); ?>"
                                <?php endif; ?>
                                <?php if (!empty($_GET['filter_filiere'])): ?>
                                    <?php 
                                    $selected_filiere = array_filter($filieres, function($f) { return $f['id'] == $_GET['filter_filiere']; });
                                    $selected_filiere = reset($selected_filiere);
                                    ?>
                                    in <?php echo htmlspecialchars($selected_filiere['name']); ?>
                                <?php endif; ?>
                                <?php if (!empty($_GET['filter_niveau'])): ?>
                                    at <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_GET['filter_niveau']))); ?> level
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No students found</h5>
                            <p class="text-muted">There are no students to edit.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-primary">
                                    <tr>
                                        <th>CNI</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Filière</th>
                                        <th>Level</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['cni']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></td>
                                        <td>
                                            <?php if ($student['email']): ?>
                                                <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($student['num_telephone']): ?>
                                                <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($student['num_telephone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['filiere_name']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($student['filiere_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['niveau']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $student['niveau']))); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary" 
                                                    onclick="editUser('<?php echo htmlspecialchars($student['cni']); ?>', 'student', '<?php echo htmlspecialchars($student['prenom']); ?>', '<?php echo htmlspecialchars($student['nom']); ?>', '<?php echo htmlspecialchars($student['email']); ?>', '<?php echo htmlspecialchars($student['num_telephone']); ?>', '<?php echo htmlspecialchars($student['niveau']); ?>', '<?php echo $student['filiere_id']; ?>')">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($current_section === 'teachers' && $current_action === 'edit'): ?>
                <!-- Edit Teachers List -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-primary">
                        <i class="fas fa-user-edit me-2"></i>
                        Edit Teachers
                    </h2>
                    <a href="?section=teachers" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Teacher Management
                    </a>
                </div>

                <!-- Teacher Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filter Teachers
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="section" value="teachers">
                            <input type="hidden" name="action" value="edit">
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="search" 
                                       name="search" 
                                       placeholder="Name, CNI, or Email"
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_contract" class="form-label">Contract Type</label>
                                <select class="form-select" id="filter_contract" name="filter_contract">
                                    <option value="">All Contracts</option>
                                    <option value="CDI" <?php echo (isset($_GET['filter_contract']) && $_GET['filter_contract'] == 'CDI') ? 'selected' : ''; ?>>
                                        CDI (Permanent)
                                    </option>
                                    <option value="CDD" <?php echo (isset($_GET['filter_contract']) && $_GET['filter_contract'] == 'CDD') ? 'selected' : ''; ?>>
                                        CDD (Fixed-term)
                                    </option>
                                    <option value="Freelance" <?php echo (isset($_GET['filter_contract']) && $_GET['filter_contract'] == 'Freelance') ? 'selected' : ''; ?>>
                                        Freelance
                                    </option>
                                    </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_degree" class="form-label">Degree</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="filter_degree" 
                                       name="filter_degree" 
                                       placeholder="e.g., Master, PhD, License"
                                       value="<?php echo htmlspecialchars($_GET['filter_degree'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                    <a href="?section=teachers&action=edit" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Filter Results Summary -->
                        <?php if (!empty($_GET['search']) || !empty($_GET['filter_contract']) || !empty($_GET['filter_degree'])): ?>
                        <div class="mt-3">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Showing <?php echo count($teachers); ?> teacher(s)</strong>
                                <?php if (!empty($_GET['search'])): ?>
                                    matching "<?php echo htmlspecialchars($_GET['search']); ?>"
                                <?php endif; ?>
                                <?php if (!empty($_GET['filter_contract'])): ?>
                                    with <?php echo htmlspecialchars($_GET['filter_contract']); ?> contract
                                <?php endif; ?>
                                <?php if (!empty($_GET['filter_degree'])): ?>
                                    with degree containing "<?php echo htmlspecialchars($_GET['filter_degree']); ?>"
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($teachers)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No teachers found</h5>
                            <p class="text-muted">There are no teachers to edit.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-success">
                                    <tr>
                                        <th>CNI</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Contract</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($teacher['cni']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($teacher['prenom'] . ' ' . $teacher['nom']); ?></td>
                                        <td>
                                            <?php if ($teacher['email']): ?>
                                                <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($teacher['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($teacher['num_telephone']): ?>
                                                <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($teacher['num_telephone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($teacher['type_contrat']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($teacher['type_contrat']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary" 
                                                    onclick="editUser('<?php echo htmlspecialchars($teacher['cni']); ?>', 'teacher', '<?php echo htmlspecialchars($teacher['prenom']); ?>', '<?php echo htmlspecialchars($teacher['nom']); ?>', '<?php echo htmlspecialchars($teacher['email']); ?>', '<?php echo htmlspecialchars($teacher['num_telephone']); ?>', '', '', '<?php echo htmlspecialchars($teacher['adresse']); ?>')">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($current_section === 'students' && $current_action === 'delete'): ?>
                <!-- Delete Students List -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-danger">
                        <i class="fas fa-user-times me-2"></i>
                        Delete Students
                    </h2>
                    <a href="?section=students" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Student Management
                    </a>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> Deleting a student will permanently remove all their data from the system. This action cannot be undone.
                </div>

                <!-- Student Filters for Delete -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filter Students
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="section" value="students">
                            <input type="hidden" name="action" value="delete">
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="search" 
                                       name="search" 
                                       placeholder="Name, CNI, or Email"
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_filiere" class="form-label">Filière</label>
                                <select class="form-select" id="filter_filiere" name="filter_filiere">
                                    <option value="">All Filières</option>
                                    <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?php echo $filiere['id']; ?>" 
                                            <?php echo (isset($_GET['filter_filiere']) && $_GET['filter_filiere'] == $filiere['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($filiere['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_niveau" class="form-label">Level</label>
                                <select class="form-select" id="filter_niveau" name="filter_niveau">
                                    <option value="">All Levels</option>
                                    <option value="technicien" <?php echo (isset($_GET['filter_niveau']) && $_GET['filter_niveau'] == 'technicien') ? 'selected' : ''; ?>>
                                        Technicien
                                    </option>
                                    <option value="technicien_specialise" <?php echo (isset($_GET['filter_niveau']) && $_GET['filter_niveau'] == 'technicien_specialise') ? 'selected' : ''; ?>>
                                        Technicien Spécialisé
                                    </option>
                                    <option value="qualifiant" <?php echo (isset($_GET['filter_niveau']) && $_GET['filter_niveau'] == 'qualifiant') ? 'selected' : ''; ?>>
                                        Qualifiant
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                    <a href="?section=students&action=delete" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Filter Results Summary -->
                        <?php if (!empty($_GET['search']) || !empty($_GET['filter_filiere']) || !empty($_GET['filter_niveau'])): ?>
                        <div class="mt-3">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Showing <?php echo count($students); ?> student(s)</strong>
                                <?php if (!empty($_GET['search'])): ?>
                                    matching "<?php echo htmlspecialchars($_GET['search']); ?>"
                                <?php endif; ?>
                                <?php if (!empty($_GET['filter_filiere'])): ?>
                                    <?php 
                                    $selected_filiere = array_filter($filieres, function($f) { return $f['id'] == $_GET['filter_filiere']; });
                                    $selected_filiere = reset($selected_filiere);
                                    ?>
                                    in <?php echo htmlspecialchars($selected_filiere['name']); ?>
                                <?php endif; ?>
                                <?php if (!empty($_GET['filter_niveau'])): ?>
                                    at <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_GET['filter_niveau']))); ?> level
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <!-- Student Filters -->
                        <form method="GET" action="manage_users.php" class="mb-3">
                            <input type="hidden" name="section" value="students">
                            <input type="hidden" name="action" value="delete">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-3">
                                    <label for="filter_filiere" class="form-label">Filière:</label>
                                    <select class="form-select" id="filter_filiere" name="filter_filiere">
                                        <option value="">All Filières</option>
                                        <?php foreach ($filieres as $filiere): ?>
                                        <option value="<?php echo $filiere['id']; ?>" <?php if (isset($_GET['filter_filiere']) && $_GET['filter_filiere'] == $filiere['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($filiere['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="filter_niveau" class="form-label">Level:</label>
                                    <select class="form-select" id="filter_niveau" name="filter_niveau">
                                        <option value="">All Levels</option>
                                        <option value="technicien" <?php if (isset($_GET['filter_niveau']) && $_GET['filter_niveau'] == 'technicien') echo 'selected'; ?>>Technicien</option>
                                        <option value="technicien_specialise" <?php if (isset($_GET['filter_niveau']) && $_GET['filter_niveau'] == 'technicien_specialise') echo 'selected'; ?>>Technicien Spécialisé</option>
                                        <option value="qualifiant" <?php if (isset($_GET['filter_niveau']) && $_GET['filter_niveau'] == 'qualifiant') echo 'selected'; ?>>Qualifiant</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search:</label>
                                    <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search by CNI, Name, or Email">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Filter
                                    </button>
                                    <a href="?section=students&action=delete" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </form>

                        <?php if (empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No students found</h5>
                            <p class="text-muted">There are no students to delete.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-danger">
                                    <tr>
                                        <th>CNI</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Filière</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['cni']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></td>
                                        <td>
                                            <?php if ($student['email']): ?>
                                                <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($student['num_telephone']): ?>
                                                <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($student['num_telephone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['filiere_name']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($student['filiere_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete('<?php echo htmlspecialchars($student['cni']); ?>', 'student', '<?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?>')">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($current_section === 'teachers' && $current_action === 'assign'): ?>
<!-- Assign Teacher Section -->
<?php
    // Fetch active assignments
    $active_assignments = [];
    try {
        $assign_stmt = $pdo->query("SELECT tma.id, CONCAT(t.prenom,' ',t.nom) AS teacher_name, t.cni as teacher_cni, f.name AS filiere_name, m.name AS module_name, tma.assigned_date
                                   FROM teacher_module_assignments tma
                                   JOIN teachers t ON t.id = tma.teacher_id
                                   JOIN filieres f ON f.id = tma.filiere_id
                                   JOIN modules m ON m.id = tma.module_id
                                   WHERE tma.is_active = 1
                                   ORDER BY t.nom, m.name");
        $active_assignments = $assign_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // ignore
    }
    // Build modules grouped by filiere for JS
    $modulesByFiliere = [];
    foreach ($modules as $m) {
        $modulesByFiliere[$m['filiere_id']][] = $m;
    }
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary"><i class="fas fa-link me-2"></i>Assign Teacher to Module</h2>
</div>
<form method="POST" class="card p-4 mb-5">
    <input type="hidden" name="assign_teacher_module" value="1">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Teacher</label>
            <select name="teacher_cni" class="form-select" required>
                <option value="">Select teacher</option>
                <?php foreach ($teachers as $t): ?>
                <option value="<?php echo htmlspecialchars($t['cni']); ?>">
                    <?php echo htmlspecialchars($t['prenom'].' '.$t['nom'].' ('.$t['cni'].')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Filière</label>
            <select name="filiere_id" id="filiere_select" class="form-select" required>
                <option value="">Select filière</option>
                <?php foreach ($filieres as $f): ?>
                <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Module</label>
            <select name="module_id" id="module_select" class="form-select" required>
                <option value="">Select module</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Notes (optional)</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
    </div>
    <button class="btn btn-primary mt-3"><i class="fas fa-plus me-2"></i>Assign</button>
</form>

<h3>Current Active Assignments</h3>
<table class="table table-bordered">
    <thead>
        <tr><th>Teacher</th><th>Filière</th><th>Module</th><th>Date</th><th>Action</th></tr>
    </thead>
    <tbody>
        <?php foreach ($active_assignments as $a): ?>
        <tr>
            <td><?php echo htmlspecialchars($a['teacher_name']).' ('.htmlspecialchars($a['teacher_cni']).')'; ?></td>
            <td><?php echo htmlspecialchars($a['filiere_name']); ?></td>
            <td><?php echo htmlspecialchars($a['module_name']); ?></td>
            <td><?php echo htmlspecialchars($a['assigned_date']); ?></td>
            <td>
                <a href="?section=teachers&action=assign&deactivate=<?php echo $a['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deactivate this assignment?');">Deactivate</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
const modulesByFiliere = <?php echo json_encode($modulesByFiliere); ?>;
const filiereSelect = document.getElementById('filiere_select');
if (filiereSelect) {
    filiereSelect.addEventListener('change', function(){
        const fid = this.value;
        const moduleSelect = document.getElementById('module_select');
        moduleSelect.innerHTML = '<option value="">Select module</option>';
        if (modulesByFiliere[fid]) {
            modulesByFiliere[fid].forEach(function(m){
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name;
                moduleSelect.appendChild(opt);
            });
        }
    });
}
</script>

<?php elseif ($current_section === 'teachers' && $current_action === 'delete'): ?>
                <!-- Delete Teachers List -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-danger">
                        <i class="fas fa-user-times me-2"></i>
                        Delete Teachers
                    </h2>
                    <a href="?section=teachers" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Teacher Management
                    </a>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> Deleting a teacher will permanently remove all their data from the system. This action cannot be undone.
                </div>

                <!-- Teacher Filters for Delete -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filter Teachers
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="section" value="teachers">
                            <input type="hidden" name="action" value="delete">
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="search" 
                                       name="search" 
                                       placeholder="Name, CNI, or Email"
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_contract" class="form-label">Contract Type</label>
                                <select class="form-select" id="filter_contract" name="filter_contract">
                                    <option value="">All Contracts</option>
                                    <option value="CDI" <?php echo (isset($_GET['filter_contract']) && $_GET['filter_contract'] == 'CDI') ? 'selected' : ''; ?>>
                                        CDI (Permanent)
                                    </option>
                                    <option value="CDD" <?php echo (isset($_GET['filter_contract']) && $_GET['filter_contract'] == 'CDD') ? 'selected' : ''; ?>>
                                        CDD (Fixed-term)
                                    </option>
                                    <option value="Freelance" <?php echo (isset($_GET['filter_contract']) && $_GET['filter_contract'] == 'Freelance') ? 'selected' : ''; ?>>
                                        Freelance
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_degree" class="form-label">Degree</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="filter_degree" 
                                       name="filter_degree" 
                                       placeholder="e.g., Master, PhD, License"
                                       value="<?php echo htmlspecialchars($_GET['filter_degree'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                    <a href="?section=teachers&action=delete" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Filter Results Summary -->
                        <?php if (!empty($_GET['search']) || !empty($_GET['filter_contract']) || !empty($_GET['filter_degree'])): ?>
                        <div class="mt-3">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Showing <?php echo count($teachers); ?> teacher(s)</strong>
                                <?php if (!empty($_GET['search'])): ?>
                                    matching "<?php echo htmlspecialchars($_GET['search']); ?>"
                                <?php endif; ?>
                                <?php if (!empty($_GET['filter_contract'])): ?>
                                    with <?php echo htmlspecialchars($_GET['filter_contract']); ?> contract
                                <?php endif; ?>
                                <?php if (!empty($_GET['filter_degree'])): ?>
                                    with degree containing "<?php echo htmlspecialchars($_GET['filter_degree']); ?>"
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <!-- Teacher Filters -->
                        <form method="GET" action="manage_users.php" class="mb-3">
                            <input type="hidden" name="section" value="teachers">
                            <input type="hidden" name="action" value="delete">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-3">
                                    <label for="filter_contract" class="form-label">Contract Type:</label>
                                    <input type="text" class="form-control" id="filter_contract" name="filter_contract" value="<?php echo isset($_GET['filter_contract']) ? htmlspecialchars($_GET['filter_contract']) : ''; ?>" placeholder="Enter contract type">
                                </div>
                                <div class="col-md-3">
                                    <label for="filter_degree" class="form-label">Degree:</label>
                                    <input type="text" class="form-control" id="filter_degree" name="filter_degree" value="<?php echo isset($_GET['filter_degree']) ? htmlspecialchars($_GET['filter_degree']) : ''; ?>" placeholder="Enter degree">
                                </div>
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search:</label>
                                    <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search by CNI, Name, or Email">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Filter
                                    </button>
                                    <a href="?section=teachers&action=delete" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </form>

                        <?php if (empty($teachers)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No teachers found</h5>
                            <p class="text-muted">There are no teachers to delete.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-danger">
                                    <tr>
                                        <th>CNI</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Contract</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($teacher['cni']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($teacher['prenom'] . ' ' . $teacher['nom']); ?></td>
                                        <td>
                                            <?php if ($teacher['email']): ?>
                                                <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($teacher['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($teacher['num_telephone']): ?>
                                                <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($teacher['num_telephone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($teacher['type_contrat']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($teacher['type_contrat']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete('<?php echo htmlspecialchars($teacher['cni']); ?>', 'teacher', '<?php echo htmlspecialchars($teacher['prenom'] . ' ' . $teacher['nom']); ?>')">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($current_section === 'teachers' && $current_action === 'hold'): ?>
                <!-- Hold Teacher Accounts -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-warning">
                        <i class="fas fa-user-lock me-2"></i>
                        Hold Teacher Accounts
                    </h2>
                    <a href="?section=teachers" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Teacher Management
                    </a>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Account Status Management:</strong> You can temporarily suspend teacher accounts to prevent login access without deleting their data. Suspended teachers cannot log in until their account is reactivated.
                </div>

                <!-- Teacher Status Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filter Teachers by Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="section" value="teachers">
                            <input type="hidden" name="action" value="hold">
                            
                            <div class="col-md-3">
                                <label for="status_filter" class="form-label">Account Status</label>
                                <select class="form-select" id="status_filter" name="status_filter">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'active') ? 'selected' : ''; ?>>
                                        Active
                                    </option>
                                    <option value="suspended" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'suspended') ? 'selected' : ''; ?>>
                                        Suspended
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="search" 
                                       name="search" 
                                       placeholder="Name, CNI, or Email"
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_contract" class="form-label">Contract Type</label>
                                <select class="form-select" id="filter_contract" name="filter_contract">
                                    <option value="">All Contracts</option>
                                    <option value="CDI" <?php echo (isset($_GET['filter_contract']) && $_GET['filter_contract'] == 'CDI') ? 'selected' : ''; ?>>
                                        CDI (Permanent)
                                    </option>
                                    <option value="CDD" <?php echo (isset($_GET['filter_contract']) && $_GET['filter_contract'] == 'CDD') ? 'selected' : ''; ?>>
                                        CDD (Fixed-term)
                                    </option>
                                    <option value="Freelance" <?php echo (isset($_GET['filter_contract']) && $_GET['filter_contract'] == 'Freelance') ? 'selected' : ''; ?>>
                                        Freelance
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                    <a href="?section=teachers&action=hold" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php
// Fetch teachers with status filtering for hold action
$hold_teachers = [];
try {
    // Build the WHERE clause based on filters
    $where_conditions = [];
    $params = [];
    
    // Filter by status
    if (!empty($_GET['status_filter'])) {
        $where_conditions[] = "t.account_status = ?";
        $params[] = $_GET['status_filter'];
    }
    
    // Filter by contract type
    if (!empty($_GET['filter_contract'])) {
        $where_conditions[] = "t.type_contrat = ?";
        $params[] = $_GET['filter_contract'];
    }
    
    // Search by name or CNI
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where_conditions[] = "(t.cni LIKE ? OR t.nom LIKE ? OR t.prenom LIKE ? OR t.email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $sql = "
        SELECT t.cni, t.nom, t.prenom, t.email, t.num_telephone, t.adresse,
               t.type_contrat, t.date_embauche, t.dernier_diplome, t.account_status,
               t.created_at
        FROM teachers t 
        $where_clause
        ORDER BY t.account_status DESC, t.nom, t.prenom
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $hold_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Error fetching teachers: ' . $e->getMessage();
}
?>

<div class="card">
    <div class="card-body">
        <?php if (empty($hold_teachers)): ?>
        <div class="text-center py-5">
            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No teachers found</h5>
            <p class="text-muted">No teachers match your current filters.</p>
        </div>
        <?php else: ?>
        
        <!-- Status Summary -->
        <?php
        $active_count = count(array_filter($hold_teachers, function($t) { return $t['account_status'] === 'active'; }));
        $suspended_count = count(array_filter($hold_teachers, function($t) { return $t['account_status'] === 'suspended'; }));
        ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $active_count; ?></h3>
                        <p class="mb-0">Active Teachers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $suspended_count; ?></h3>
                        <p class="mb-0">Suspended Teachers</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-warning">
                    <tr>
                        <th>CNI</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Contract</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hold_teachers as $teacher): ?>
                    <tr class="<?php echo $teacher['account_status'] === 'suspended' ? 'table-warning' : ''; ?>">
                        <td><strong><?php echo htmlspecialchars($teacher['cni']); ?></strong></td>
                        <td>
                            <div>
                                <?php echo htmlspecialchars($teacher['prenom'] . ' ' . $teacher['nom']); ?>
                                <?php if ($teacher['account_status'] === 'suspended'): ?>
                                    <i class="fas fa-lock text-warning ms-2" title="Account Suspended"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($teacher['email']): ?>
                                <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($teacher['email']); ?></div>
                            <?php endif; ?>
                            <?php if ($teacher['num_telephone']): ?>
                                <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($teacher['num_telephone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($teacher['type_contrat']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($teacher['type_contrat']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($teacher['account_status'] === 'active'): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i>Active
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-lock me-1"></i>Suspended
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($teacher['account_status'] === 'active'): ?>
                                <button type="button" 
                                        class="btn btn-sm btn-warning" 
                                        onclick="confirmStatusChange('<?php echo htmlspecialchars($teacher['cni']); ?>', 'suspend', '<?php echo htmlspecialchars($teacher['prenom'] . ' ' . $teacher['nom']); ?>')">
                                    <i class="fas fa-lock me-1"></i>Suspend
                                </button>
                            <?php else: ?>
                                <button type="button" 
                                        class="btn btn-sm btn-success" 
                                        onclick="confirmStatusChange('<?php echo htmlspecialchars($teacher['cni']); ?>', 'activate', '<?php echo htmlspecialchars($teacher['prenom'] . ' ' . $teacher['nom']); ?>')">
                                    <i class="fas fa-unlock me-1"></i>Activate
                                </button>
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

                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Edit User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="update_user" value="1">
                        <input type="hidden" name="update_cni" id="edit_cni">
                        <input type="hidden" name="update_role" id="edit_role">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nom" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="edit_nom" name="nom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_prenom" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="edit_prenom" name="prenom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="edit_email" name="email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_telephone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="edit_telephone" name="num_telephone">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Teacher specific fields -->
                        <div id="edit_teacher_fields" style="display: none;">
                            <div class="mb-3">
                                <label for="edit_adresse" class="form-label">Address</label>
                                <textarea class="form-control" id="edit_adresse" name="adresse" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <!-- Student specific fields -->
                        <div id="edit_student_fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_niveau" class="form-label">Level</label>
                                        <select class="form-select" id="edit_niveau" name="niveau">
                                            <option value="">Select Level</option>
                                            <option value="technicien">Technicien</option>
                                            <option value="technicien_specialise">Technicien Spécialisé</option>
                                            <option value="qualifiant">Qualifiant</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_filiere_id" class="form-label">Filière</label>
                                        <select class="form-select" id="edit_filiere_id" name="filiere_id">
                                            <option value="">Select Filière</option>
                                            <?php foreach ($filieres as $filiere): ?>
                                            <option value="<?php echo $filiere['id']; ?>">
                                                <?php echo htmlspecialchars($filiere['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this user?</p>
                    <div class="alert alert-danger">
                        <strong id="delete_user_info"></strong>
                    </div>
                    <p class="text-muted">This action cannot be undone and will permanently remove all associated data.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="delete_confirm_link" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete User
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Change Confirmation Modal -->
    <div class="modal fade" id="statusChangeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusChangeTitle">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Status Change
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="statusChangeMessage"></p>
                    <div class="alert" id="statusChangeAlert">
                        <strong id="teacher_status_info"></strong>
                    </div>
                    <p class="text-muted" id="statusChangeNote"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="GET" action="" style="display: inline;" id="statusChangeForm">
                        <input type="hidden" name="section" value="teachers">
                        <input type="hidden" name="action" value="hold">
                        <input type="hidden" name="status_action" id="status_action_input">
                        <input type="hidden" name="cni" id="status_cni_input">
                        <button type="submit" class="btn" id="status_change_btn">
                            <i class="fas fa-check me-2"></i>
                            <span id="status_action_text">Confirm</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/scripts.js"></script>
    <script>
        function editUser(cni, role, prenom, nom, email, telephone, niveau, filiere_id, adresse) {
            // Set form action based on current section
            const currentSection = '<?php echo $current_section; ?>';
            document.getElementById('editUserForm').action = `?section=${currentSection}&action=edit`;
            
            // Set hidden fields
            document.getElementById('edit_cni').value = cni;
            document.getElementById('edit_role').value = role;
            
            // Set basic fields
            document.getElementById('edit_prenom').value = prenom || '';
            document.getElementById('edit_nom').value = nom || '';
            document.getElementById('edit_email').value = email || '';
            document.getElementById('edit_telephone').value = telephone || '';
            
            // Hide all role-specific fields
            document.getElementById('edit_teacher_fields').style.display = 'none';
            document.getElementById('edit_student_fields').style.display = 'none';
            
            // Show relevant fields based on role
            if (role === 'teacher') {
                document.getElementById('edit_teacher_fields').style.display = 'block';
                document.getElementById('edit_adresse').value = adresse || '';
            } else if (role === 'student') {
                document.getElementById('edit_student_fields').style.display = 'block';
                document.getElementById('edit_niveau').value = niveau || '';
                document.getElementById('edit_filiere_id').value = filiere_id || '';
            }
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        function confirmDelete(cni, role, name) {
            document.getElementById('delete_user_info').textContent = `${name} (${cni}) - ${role.toUpperCase()}`;
            const deleteLink = document.getElementById('delete_confirm_link');
            const url = `?section=${role}s&action=delete&delete_action=confirm&cni=${encodeURIComponent(cni)}&role=${encodeURIComponent(role)}`;
            deleteLink.href = url;
            // Force navigation manually to bypass global anchor smooth-scroll listener
            deleteLink.onclick = function (e) {
                e.preventDefault();
                window.location.href = url;
            };
            
            new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
        }

        // Add hover effects for cards
        document.addEventListener('DOMContentLoaded', function() {
            const style = document.createElement('style');
            style.textContent = `
                .hover-card {
                    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
                }
                .hover-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
                }
            `;
            document.head.appendChild(style);
        });

function confirmStatusChange(cni, action, name) {
    const modal = document.getElementById('statusChangeModal');
    const title = document.getElementById('statusChangeTitle');
    const message = document.getElementById('statusChangeMessage');
    const alert = document.getElementById('statusChangeAlert');
    const info = document.getElementById('teacher_status_info');
    const note = document.getElementById('statusChangeNote');
    const btn = document.getElementById('status_change_btn');
    const actionText = document.getElementById('status_action_text');
    const actionInput = document.getElementById('status_action_input');
    const cniInput = document.getElementById('status_cni_input');
    
    // Set form values
    actionInput.value = action;
    cniInput.value = cni;
    
    if (action === 'suspend') {
        title.innerHTML = '<i class="fas fa-lock me-2"></i>Suspend Teacher Account';
        message.textContent = 'Are you sure you want to suspend this teacher account?';
        alert.className = 'alert alert-warning';
        info.textContent = `${name} (${cni})`;
        note.textContent = 'The teacher will not be able to log in until the account is reactivated.';
        btn.className = 'btn btn-warning';
        actionText.textContent = 'Suspend Account';
    } else {
        title.innerHTML = '<i class="fas fa-unlock me-2"></i>Activate Teacher Account';
        message.textContent = 'Are you sure you want to activate this teacher account?';
        alert.className = 'alert alert-success';
        info.textContent = `${name} (${cni})`;
        note.textContent = 'The teacher will be able to log in normally after activation.';
        btn.className = 'btn btn-success';
        actionText.textContent = 'Activate Account';
    }
    
    new bootstrap.Modal(modal).show();
}
    </script>
</body>
</html>
