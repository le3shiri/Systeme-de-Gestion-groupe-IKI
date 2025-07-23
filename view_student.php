<?php
session_start();

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_cni']) || ($_SESSION['role'] !== 'director' && $_SESSION['role'] !== 'teacher')) {
    header('Location: login.php');
    exit();
}

// Database connection parameters
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

// Get user CNI from session
$user_cni = $_SESSION['user_cni'];
$user_role = $_SESSION['role'];

// Initialize variables
$student = null;
$grades = [];
$absences = [];
$error_message = '';
$success_message = '';

// Check if student CNI is provided
if (!isset($_GET['cni']) || empty($_GET['cni'])) {
    $error_message = "Identifiant d'étudiant non spécifié";
} else {
    $student_cni = $_GET['cni'];
    
    try {
        // Connect to the database
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get user details based on role
        if ($user_role === 'director') {
            $stmt = $pdo->prepare("SELECT * FROM directors WHERE cni = ?");
            $stmt->execute([$user_cni]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else if ($user_role === 'teacher') {
            $stmt = $pdo->prepare("SELECT * FROM teachers WHERE cni = ?");
            $stmt->execute([$user_cni]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // If user not found, create a default array to avoid undefined variable errors
        if (!$user) {
            $user = [
                'nom' => ucfirst($user_role),
                'prenom' => '',
                'cni' => $user_cni
            ];
        }
        
        // Get student details
        $stmt = $pdo->prepare("
            SELECT s.*, f.name as filiere_name
            FROM students s
            LEFT JOIN filieres f ON s.filiere_id = f.id
            WHERE s.cni = ?
        ");
        $stmt->execute([$student_cni]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            $error_message = "Étudiant non trouvé";
        } else {
            // Get student grades
            $stmt = $pdo->prepare("
                SELECT g.*, m.name as module_name
                FROM grades g
                JOIN modules m ON g.module_id = m.id
                WHERE g.student_id = ?
                ORDER BY g.date DESC, g.grade_type ASC
            ");
            $stmt->execute([$student['id']]);
            $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate average grade
            $total_grade = 0;
            $grade_count = count($grades);
            foreach ($grades as $grade) {
                $total_grade += $grade['grade'];
            }
            $average_grade = $grade_count > 0 ? $total_grade / $grade_count : 0;
            
            // Get student absences
            $stmt = $pdo->prepare("
                SELECT a.*, m.name as module_name
                FROM absences a
                LEFT JOIN modules m ON a.module_id = m.id
                WHERE a.student_id = ?
                ORDER BY a.created_at DESC
            ");
            $stmt->execute([$student['id']]);
            $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count absences by status
            $absent_count = 0;
            $late_count = 0;
            foreach ($absences as $absence) {
                if ($absence['status'] === 'absent') {
                    $absent_count++;
                } else if ($absence['status'] === 'late') {
                    $late_count++;
                }
            }
            $total_absences = $absent_count + $late_count;
        }
        
    } catch (PDOException $e) {
        $error_message = "Erreur de base de données: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Étudiant - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        .profile-header {
            background-color: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <?php if ($user_role === 'director'): ?>
            <a class="navbar-brand d-flex align-items-center" href="dashboard_director.php">
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
                            <i class="fas fa-user-tie me-2"></i>
                            Directeur (<?php echo htmlspecialchars($user_cni); ?>)
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <?php else: ?>
            <a class="navbar-brand d-flex align-items-center" href="dashboard_teacher.php">
                <img src="assets/logo-circle.jpg" alt="Groupe IKI Logo" height="40" class="me-2">
                <span>Groupe IKI | Enseignant</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <?php if ($user_role === 'director'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard_director.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de Bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="director_students.php">
                                <i class="fas fa-user-graduate me-2"></i>
                                Étudiants
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="director_teachers.php">
                                <i class="fas fa-chalkboard-teacher me-2"></i>
                                Enseignants
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="director_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                Statistiques des Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="director_absences.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                Statistiques des Absences
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_schedules_director.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Emplois du Temps
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="director_reports.php">
                                <i class="fas fa-file-alt me-2"></i>
                                Rapports
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard_teacher.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de Bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                Gestion des Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="record_absence.php">
                                <i class="fas fa-calendar-times me-2"></i>
                                Gestion des Absences
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_schedule.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Emploi du Temps
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-graduate me-2"></i>
                        Profil Étudiant
                    </h1>
                    <div>
                        <a href="<?php echo $user_role === 'director' ? 'director_students.php' : 'manage_grades.php'; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Retour
                        </a>
                    </div>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($student): ?>
                <!-- Student Profile Header -->
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <img src="assets/default-avatar.jpg" alt="Photo de profil" class="profile-image">
                        </div>
                        <div class="col-md-10">
                            <h3><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></h3>
                            <p class="text-muted mb-2">
                                <i class="fas fa-id-card me-2"></i>
                                CNI: <?php echo htmlspecialchars($student['cni']); ?>
                            </p>
                            <p class="text-muted mb-2">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Filière: <?php echo htmlspecialchars($student['filiere_name'] ?? 'Non assigné'); ?>
                            </p>
                            <p class="text-muted mb-2">
                                <i class="fas fa-user-graduate me-2"></i>
                                Niveau: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $student['niveau'])) ?? 'Non assigné'); ?>
                            </p>
                            <p class="text-muted mb-0">
                                <i class="fas fa-envelope me-2"></i>
                                Email: <?php echo htmlspecialchars($student['email'] ?? 'Non renseigné'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Student Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body d-flex align-items-center">
                                <div class="stats-icon bg-primary text-white">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div>
                                    <h6 class="card-subtitle mb-1 text-muted">Moyenne Générale</h6>
                                    <?php 
                                    $badge_class = 'bg-danger';
                                    if ($average_grade >= 10 && $average_grade < 12) {
                                        $badge_class = 'bg-warning';
                                    } elseif ($average_grade >= 12 && $average_grade < 14) {
                                        $badge_class = 'bg-info';
                                    } elseif ($average_grade >= 14) {
                                        $badge_class = 'bg-success';
                                    }
                                    ?>
                                    <h4 class="card-title mb-0">
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo number_format($average_grade, 2); ?>/20
                                        </span>
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body d-flex align-items-center">
                                <div class="stats-icon bg-danger text-white">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <div>
                                    <h6 class="card-subtitle mb-1 text-muted">Total Absences</h6>
                                    <h4 class="card-title mb-0">
                                        <?php echo $total_absences; ?> 
                                        <small class="text-muted">(<?php echo $absent_count; ?> absences, <?php echo $late_count; ?> retards)</small>
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card stats-card h-100 border-0">
                            <div class="card-body d-flex align-items-center">
                                <div class="stats-icon bg-success text-white">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div>
                                    <h6 class="card-subtitle mb-1 text-muted">Modules Évalués</h6>
                                    <h4 class="card-title mb-0">
                                        <?php 
                                        $evaluated_modules = [];
                                        foreach ($grades as $grade) {
                                            if (!in_array($grade['module_id'], $evaluated_modules)) {
                                                $evaluated_modules[] = $grade['module_id'];
                                            }
                                        }
                                        echo count($evaluated_modules); 
                                        ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs for Grades and Absences -->
                <ul class="nav nav-tabs mb-4" id="studentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="grades-tab" data-bs-toggle="tab" data-bs-target="#grades" type="button" role="tab" aria-controls="grades" aria-selected="true">
                            <i class="fas fa-chart-bar me-2"></i>
                            Notes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="absences-tab" data-bs-toggle="tab" data-bs-target="#absences" type="button" role="tab" aria-controls="absences" aria-selected="false">
                            <i class="fas fa-calendar-times me-2"></i>
                            Absences
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="studentTabsContent">
                    <!-- Grades Tab -->
                    <div class="tab-pane fade show active" id="grades" role="tabpanel" aria-labelledby="grades-tab">
                        <div class="card mb-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Notes de l'étudiant</h5>
                                <button class="btn btn-primary btn-sm" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i> Imprimer relevé
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (count($grades) > 0): ?>
                                    <?php
                                    // Helper functions for grade calculations
                                    function calculateModuleAverage($module_grades, $module_type) {
                                        if ($module_type === 'pfe') {
                                            return isset($module_grades['pfe']) ? $module_grades['pfe']['grade'] : null;
                                        }
                                        
                                        if ($module_type === 'stage') {
                                            return isset($module_grades['stage']) ? $module_grades['stage']['grade'] : null;
                                        }
                                        
                                        // For standard modules: 30% CC + 70% Exam (théorique + pratique)
                                        $cc_average = 0;
                                        $cc_count = 0;
                                        
                                        // Calculate CC average
                                        foreach (['cc1', 'cc2', 'cc3'] as $cc) {
                                            if (isset($module_grades[$cc])) {
                                                $cc_average += $module_grades[$cc]['grade'];
                                                $cc_count++;
                                            }
                                        }
                                        
                                        if ($cc_count > 0) {
                                            $cc_average = $cc_average / $cc_count;
                                        }
                                        
                                        // Calculate exam average (théorique + pratique)
                                        $exam_average = 0;
                                        $exam_count = 0;
                                        
                                        if (isset($module_grades['theorique'])) {
                                            $exam_average += $module_grades['theorique']['grade'];
                                            $exam_count++;
                                        }
                                        
                                        if (isset($module_grades['pratique'])) {
                                            $exam_average += $module_grades['pratique']['grade'];
                                            $exam_count++;
                                        }
                                        
                                        if ($exam_count > 0) {
                                            $exam_average = $exam_average / $exam_count;
                                        }
                                        
                                        // Calculate final average
                                        if ($cc_count > 0 && $exam_count > 0) {
                                            return ($cc_average * 0.3) + ($exam_average * 0.7);
                                        } elseif ($exam_count > 0) {
                                            return $exam_average;
                                        } elseif ($cc_count > 0) {
                                            return $cc_average;
                                        }
                                        
                                        return null;
                                    }

                                    function getGradeStatus($grade) {
                                        if ($grade === null) return ['status' => 'Non évalué', 'class' => 'text-muted'];
                                        if ($grade >= 16) return ['status' => 'Très Bien', 'class' => 'text-success fw-bold'];
                                        if ($grade >= 14) return ['status' => 'Bien', 'class' => 'text-info fw-bold'];
                                        if ($grade >= 12) return ['status' => 'Assez Bien', 'class' => 'text-primary'];
                                        if ($grade >= 10) return ['status' => 'Passable', 'class' => 'text-warning'];
                                        return ['status' => 'Insuffisant', 'class' => 'text-danger fw-bold'];
                                    }

                                    function getModuleTypeBadge($module_type) {
                                        switch ($module_type) {
                                            case 'pfe':
                                                return '<span class="badge bg-primary me-2">PFE</span>';
                                            case 'stage':
                                                return '<span class="badge bg-success me-2">STAGE</span>';
                                            default:
                                                return '<span class="badge bg-secondary me-2">MODULE</span>';
                                        }
                                    }

                                    function calculateCcAverage($module_grades) {
                                        $sum = 0; $count = 0;
                                        foreach (['cc1','cc2','cc3'] as $cc) {
                                            if (isset($module_grades[$cc])) { $sum += $module_grades[$cc]['grade']; $count++; }
                                        }
                                        return $count ? $sum / $count : null;
                                    }

                                    function calculateExamAverage($module_grades) {
                                        $sum = 0; $count = 0;
                                        foreach (['theorique','pratique'] as $ex) {
                                            if (isset($module_grades[$ex])) { $sum += $module_grades[$ex]['grade']; $count++; }
                                        }
                                        return $count ? $sum / $count : null;
                                    }

                                    // Organize grades by module
                                    $organized_grades = [];
                                    $modules_info = [];
                                    
                                    // Get all modules for the student's filiere
                                    $stmt = $pdo->prepare("SELECT id, name, type FROM modules WHERE filiere_id = ? ORDER BY type, name");
                                    $stmt->execute([$student['filiere_id']]);
                                    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($modules as $module) {
                                        $modules_info[$module['id']] = [
                                            'name' => $module['name'],
                                            'type' => $module['type'],
                                            'coefficient' => ($module['type'] === 'pfe' || $module['type'] === 'stage') ? 2 : 1
                                        ];
                                    }
                                    
                                    foreach ($grades as $grade) {
                                        $module_id = $grade['module_id'];
                                        $grade_type = $grade['grade_type'];
                                        
                                        if (!isset($organized_grades[$module_id])) {
                                            $organized_grades[$module_id] = [];
                                        }
                                        
                                        $organized_grades[$module_id][$grade_type] = [
                                            'grade' => $grade['grade'],
                                            'date' => $grade['date'],
                                            'module_name' => $grade['module_name'],
                                            'module_type' => $modules_info[$module_id]['type'] ?? 'standard',
                                            'coefficient' => $modules_info[$module_id]['coefficient'] ?? 1
                                        ];
                                    }
                                    
                                    $overall_total = 0;
                                    $overall_coefficient = 0;
                                    $cc_total = 0;
                                    $cc_count = 0;
                                    $exam_total = 0;
                                    $exam_count = 0;
                                    ?>

                                    <!-- Transcript Header -->
                                    <div class="transcript-header text-center mb-4 p-3 border">
                                        <div class="row align-items-center">
                                            <div class="col-md-3 text-md-start">
                                                <div class="small text-muted">Date d'édition</div>
                                                <div><?php echo date('d/m/Y'); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <h4 class="mb-1">GROUPE IKI</h4>
                                                <div class="mb-2">Institut de Formation Professionnelle</div>
                                                <h5 class="text-uppercase fw-bold">Relevé de Notes</h5>
                                            </div>
                                            <div class="col-md-3 text-md-end">
                                                <div class="small text-muted">Année Académique</div>
                                                <div><?php echo date('Y') . '-' . (date('Y') + 1); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Student Information -->
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h5 class="text-primary mb-3">INFORMATIONS ÉTUDIANT</h5>
                                            <table class="table table-sm">
                                                <tr>
                                                    <th style="width: 40%">Nom & Prénom</th>
                                                    <td><?php echo htmlspecialchars($student['nom'] . ' ' . $student['prenom']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>CNI</th>
                                                    <td><?php echo htmlspecialchars($student['cni']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Date de Naissance</th>
                                                    <td><?php echo $student['date_naissance'] ? date('d/m/Y', strtotime($student['date_naissance'])) : 'Non spécifiée'; ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="text-primary mb-3">INFORMATIONS FORMATION</h5>
                                            <table class="table table-sm">
                                                <tr>
                                                    <th style="width: 40%">Filière</th>
                                                    <td><?php echo htmlspecialchars($student['filiere_name']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Niveau</th>
                                                    <td>
                                                        <?php 
                                                        switch($student['niveau']) {
                                                            case 'technicien':
                                                                echo 'Technicien';
                                                                break;
                                                            case 'technicien_specialise':
                                                                echo 'Technicien Spécialisé';
                                                                break;
                                                            case 'qualifiant':
                                                                echo 'Qualifiant';
                                                                break;
                                                            default:
                                                                echo $student['niveau'];
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Date d'inscription</th>
                                                    <td><?php echo date('d/m/Y', strtotime($student['date_inscription'])); ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Grades Table -->
                                    <div class="table-responsive mb-4">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th rowspan="2" style="width: 25%;">Module / Type</th>
                                                    <th rowspan="2" style="width: 5%;">Coef.</th>
                                                    <th colspan="3" class="text-center">Contrôle Continu</th>
                                                    <th colspan="2" class="text-center">Examen</th>
                                                    <th rowspan="2" style="width: 10%;">Moyenne</th>
                                                    <th rowspan="2" style="width: 15%;">Mention</th>
                                                </tr>
                                                <tr>
                                                    <th>CC1</th>
                                                    <th>CC2</th>
                                                    <th>CC3</th>
                                                    <th>Théorique</th>
                                                    <th>Pratique</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($organized_grades as $module_id => $module_grades): 
                                                    $module_name = $module_grades[array_key_first($module_grades)]['module_name'] ?? 'Module Inconnu';
                                                    $module_type = $module_grades[array_key_first($module_grades)]['module_type'] ?? 'standard';
                                                    $coefficient = $module_grades[array_key_first($module_grades)]['coefficient'] ?? 1;
                                                    
                                                    $module_average = calculateModuleAverage($module_grades, $module_type);
                                                    $grade_status = getGradeStatus($module_average);
                                                    $row_class = $module_average !== null && $module_average < 10 ? 'table-danger' : '';
                                                    
                                                    if ($module_average !== null) {
                                                        $overall_total += $module_average * $coefficient;
                                                        $overall_coefficient += $coefficient;
                                                    }
                                                    
                                                    $cc_avg = calculateCcAverage($module_grades);
                                                    if ($cc_avg !== null) {
                                                        $cc_total += $cc_avg;
                                                        $cc_count++;
                                                    }
                                                    
                                                    $exam_avg = calculateExamAverage($module_grades);
                                                    if ($exam_avg !== null) {
                                                        $exam_total += $exam_avg;
                                                        $exam_count++;
                                                    }
                                                ?>
                                                <tr class="<?php echo $row_class; ?>">
                                                    <td>
                                                        <?php echo getModuleTypeBadge($module_type); ?>
                                                        <?php echo htmlspecialchars($module_name); ?>
                                                    </td>
                                                    <td class="text-center"><?php echo $coefficient; ?></td>
                                                    
                                                    <!-- CC grades -->
                                                    <?php foreach (['cc1', 'cc2', 'cc3'] as $cc): ?>
                                                        <td class="text-center">
                                                            <?php if (isset($module_grades[$cc])): ?>
                                                                <?php echo number_format($module_grades[$cc]['grade'], 2); ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    
                                                    <!-- Exam grades -->
                                                    <?php foreach (['theorique', 'pratique'] as $exam): ?>
                                                        <td class="text-center">
                                                            <?php if (isset($module_grades[$exam])): ?>
                                                                <?php echo number_format($module_grades[$exam]['grade'], 2); ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    
                                                    <!-- Average -->
                                                    <td class="text-center fw-bold">
                                                        <?php if ($module_average !== null): ?>
                                                            <?php echo number_format($module_average, 2); ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    
                                                    <!-- Status -->
                                                    <td class="<?php echo $grade_status['class']; ?>">
                                                        <?php echo $grade_status['status']; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Summary and Averages -->
                                    <?php 
                                    $overall_average = $overall_coefficient > 0 ? $overall_total / $overall_coefficient : 0;
                                    $overall_status = getGradeStatus($overall_average);
                                    $cc_average = $cc_count > 0 ? $cc_total / $cc_count : 0;
                                    $exam_average = $exam_count > 0 ? $exam_total / $exam_count : 0;
                                    ?>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header bg-light">
                                                    <h5 class="mb-0">Moyennes</h5>
                                                </div>
                                                <div class="card-body">
                                                    <table class="table table-sm">
                                                        <tr>
                                                            <th>Moyenne Contrôle Continu</th>
                                                            <td class="text-end"><?php echo number_format($cc_average, 2); ?>/20</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Moyenne Examens</th>
                                                            <td class="text-end"><?php echo number_format($exam_average, 2); ?>/20</td>
                                                        </tr>
                                                        <tr class="table-active fw-bold">
                                                            <th>Moyenne Générale</th>
                                                            <td class="text-end"><?php echo number_format($overall_average, 2); ?>/20</td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header bg-light">
                                                    <h5 class="mb-0">Résultat</h5>
                                                </div>
                                                <div class="card-body d-flex flex-column justify-content-center">
                                                    <div class="text-center">
                                                        <h4 class="mb-3 <?php echo $overall_status['class']; ?>">
                                                            <?php echo $overall_status['status']; ?>
                                                        </h4>
                                                        <div class="display-4 fw-bold <?php echo $overall_average >= 10 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo number_format($overall_average, 2); ?>/20
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Signature Section -->
                                    <div class="row mt-5 d-print-block">
                                        <div class="col-md-4 text-center">
                                            <p class="mb-5">Signature de l'étudiant</p>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <p class="mb-5">Cachet de l'établissement</p>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <p class="mb-5">Signature du directeur</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Aucune note n'a encore été enregistrée pour cet étudiant.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Original Simple Grades Table -->
                        <div class="card d-print-none">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Liste des notes</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Module</th>
                                                <th>Type</th>
                                                <th>Note</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($grades) > 0): ?>
                                                <?php foreach ($grades as $grade): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y', strtotime($grade['date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($grade['module_name']); ?></td>
                                                        <td>
                                                            <?php 
                                                            switch($grade['grade_type']) {
                                                                case 'cc1':
                                                                    echo '<span class="badge bg-secondary">CC 1</span>';
                                                                    break;
                                                                case 'cc2':
                                                                    echo '<span class="badge bg-secondary">CC 2</span>';
                                                                    break;
                                                                case 'cc3':
                                                                    echo '<span class="badge bg-secondary">CC 3</span>';
                                                                    break;
                                                                case 'theorique':
                                                                    echo '<span class="badge bg-info">Théorique</span>';
                                                                    break;
                                                                case 'pratique':
                                                                    echo '<span class="badge bg-warning">Pratique</span>';
                                                                    break;
                                                                case 'pfe':
                                                                    echo '<span class="badge bg-primary">PFE</span>';
                                                                    break;
                                                                case 'stage':
                                                                    echo '<span class="badge bg-success">Stage</span>';
                                                                    break;
                                                                default:
                                                                    echo '<span class="badge bg-secondary">Autre</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $grade['grade'] >= 10 ? 'bg-success' : 'bg-danger'; ?>">
                                                                <?php echo number_format($grade['grade'], 2); ?>/20
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Aucune note enregistrée</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Absences Tab -->
                    <div class="tab-pane fade" id="absences" role="tabpanel" aria-labelledby="absences-tab">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Absences de l'étudiant</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Module</th>
                                                <th>Statut</th>
                                                <th>Enregistré par</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($absences) > 0): ?>
                                                <?php foreach ($absences as $absence): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y', strtotime($absence['date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($absence['module_name'] ?? 'Non spécifié'); ?></td>
                                                        <td>
                                                            <?php if ($absence['status'] === 'absent'): ?>
                                                                <span class="badge bg-danger">Absent</span>
                                                            <?php elseif ($absence['status'] === 'late'): ?>
                                                                <span class="badge bg-warning">Retard</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">Présent</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if (!empty($absence['recorded_by_teacher_id'])): 
                                                                echo 'Enseignant'; 
                                                            elseif (!empty($absence['recorded_by_admin_id'])): 
                                                                echo 'Administration'; 
                                                            else: 
                                                                echo '-'; 
                                                            endif; 
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Aucune absence enregistrée</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
