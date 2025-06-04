<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_cni'])) {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];
$user_role = $_SESSION['role'];

// Redirect non-students to dashboard
if ($user_role !== 'student') {
    header('Location: ' . ($user_role === 'admin' ? 'dashboard_admin.php' : 'dashboard_teacher.php'));
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $grades = [];
    $modules = [];
    $student_info = null;
    
    // Get student information
    $stmt = $pdo->prepare("
        SELECT s.id, s.prenom, s.nom, s.cni, s.date_naissance, s.lieu_naissance,
               f.id as filiere_id, f.name as filiere_name
        FROM students s
        JOIN filieres f ON s.filiere_id = f.id
        WHERE s.cni = ?
    ");
    $stmt->execute([$user_cni]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student_info) {
        // Get all modules for the student's filiere
        $stmt = $pdo->prepare("
            SELECT id, name, type
            FROM modules
            WHERE filiere_id = ?
            ORDER BY 
                CASE 
                    WHEN type = 'pfe' THEN 3
                    WHEN type = 'stage' THEN 2
                    ELSE 1
                END,
                name
        ");
        $stmt->execute([$student_info['filiere_id']]);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all grades for the student
        $stmt = $pdo->prepare("
            SELECT g.module_id, g.grade_type, g.grade, g.date,
                   m.name as module_name, m.type as module_type,
                   f.name as filiere_name
            FROM grades g
            JOIN modules m ON g.module_id = m.id
            JOIN filieres f ON m.filiere_id = f.id
            WHERE g.student_id = ?
            ORDER BY 
                CASE 
                    WHEN m.type = 'pfe' THEN 3
                    WHEN m.type = 'stage' THEN 2
                    ELSE 1
                END,
                m.name, g.grade_type
        ");
        $stmt->execute([$student_info['id']]);
        
        // Organize grades by module
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $grades[$row['module_id']][$row['grade_type']] = [
                'grade' => $row['grade'],
                'date' => $row['date'],
                'module_name' => $row['module_name'],
                'module_type' => $row['module_type'],
                'coefficient' => ($row['module_type'] === 'pfe' || $row['module_type'] === 'stage') ? 2 : 1,
                'filiere_name' => $row['filiere_name']
            ];
        }
    }
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Helper function to calculate module average
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

// Helper function to get grade status
function getGradeStatus($grade) {
    if ($grade === null) return ['status' => 'Non évalué', 'class' => 'text-muted'];
    if ($grade >= 16) return ['status' => 'Très Bien', 'class' => 'text-success fw-bold'];
    if ($grade >= 14) return ['status' => 'Bien', 'class' => 'text-info fw-bold'];
    if ($grade >= 12) return ['status' => 'Assez Bien', 'class' => 'text-primary'];
    if ($grade >= 10) return ['status' => 'Passable', 'class' => 'text-warning'];
    return ['status' => 'Insuffisant', 'class' => 'text-danger fw-bold'];
}

// Helper function to get module type badge
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

// Dashboard link and navbar color for student
$dashboard_link = 'dashboard_student.php';
$navbar_color = 'bg-info';
$user_icon = 'fa-user-graduate';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relevé des Notes - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    
    <style>
        .transcript-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .transcript-body {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .grade-table {
            margin-bottom: 0;
        }
        
        .grade-table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .grade-table td {
            text-align: center;
            vertical-align: middle;
            padding: 0.75rem 0.4rem;
            font-size: 0.9rem;
        }
        
        .module-name {
            text-align: left !important;
            font-weight: 500;
        }
        
        .grade-cell {
            font-weight: 600;
            min-width: 50px;
        }
        
        .average-row {
            background-color: #f8f9fa;
            font-weight: 600;
            border-top: 2px solid #dee2e6;
        }
        
        .final-average {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .pfe-row {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
        
        .stage-row {
            background-color: #e8f5e8;
            border-left: 4px solid #4caf50;
        }
        
        .standard-row {
            background-color: #ffffff;
        }
        
        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        @media print {
            .navbar, .sidebar, .print-btn, .no-print {
                display: none !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .transcript-body {
                box-shadow: none !important;
                border: 1px solid #000;
            }
            
            .transcript-header {
                background: #333 !important;
                -webkit-print-color-adjust: exact;
            }
        }
        
        .legend {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .legend-item {
            display: inline-block;
            margin: 0.25rem 0.5rem;
            font-size: 0.9rem;
        }
        
        .special-note {
            font-style: italic;
            color: #6c757d;
            font-size: 0.8em;
        }
        
        .exam-grade {
            font-size: 0.85rem;
            line-height: 1.2;
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg <?php echo $navbar_color; ?> navbar-light bg-white fixed-top border-bottom shadow-sm no-print">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $dashboard_link; ?>">
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
                            Étudiant (<?php echo htmlspecialchars($user_cni); ?>)
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
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
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse no-print" id="sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $dashboard_link; ?>">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de Bord
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="view_grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                Relevé des Notes
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="student_absences.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                Mes Absences
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="view_messages.php">
                                <i class="fas fa-inbox me-2"></i>
                                Messages
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                    <h1 class="h2">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Relevé des Notes
                    </h1>
                    <div>
                        <button class="btn btn-outline-primary me-2" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Imprimer
                        </button>
                        <button class="btn btn-outline-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger no-print" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <!-- Transcript -->
                <?php if ($student_info): ?>
                <div class="transcript-body">
                    <!-- Header -->
                    <div class="transcript-header text-center">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <img src="assets/logo-circle.jpg" alt="Logo" style="max-width: 80px;" class="img-fluid">
                            </div>
                            <div class="col-md-6">
                                <h2 class="mb-1">GROUPE IKI</h2>
                                <h4 class="mb-0">RELEVÉ DES NOTES</h4>
                                <p class="mb-0">Année Académique <?php echo date('Y') - 1; ?>-<?php echo date('Y'); ?></p>
                            </div>
                            <div class="col-md-3">
                                <p class="mb-0"><small>Date d'édition:</small></p>
                                <p class="mb-0"><strong><?php echo date('d/m/Y'); ?></strong></p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <!-- Student Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">INFORMATIONS ÉTUDIANT</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Nom et Prénom:</strong></td>
                                        <td><?php echo htmlspecialchars(strtoupper($student_info['nom']) . ' ' . ucfirst($student_info['prenom'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>CNI:</strong></td>
                                        <td><?php echo htmlspecialchars($student_info['cni']); ?></td>
                                    </tr>
                                    <?php if (!empty($student_info['date_naissance'])): ?>
                                    <tr>
                                        <td><strong>Date de naissance:</strong></td>
                                        <td><?php echo date('d/m/Y', strtotime($student_info['date_naissance'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($student_info['lieu_naissance'])): ?>
                                    <tr>
                                        <td><strong>Lieu de naissance:</strong></td>
                                        <td><?php echo htmlspecialchars($student_info['lieu_naissance']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">FORMATION</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Filière:</strong></td>
                                        <td><?php echo htmlspecialchars($student_info['filiere_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Établissement:</strong></td>
                                        <td>Groupe IKI</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Année:</strong></td>
                                        <td><?php echo date('Y') - 1; ?>-<?php echo date('Y'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Unified Grades Table -->
                        <?php if (!empty($grades)): ?>
                            <?php 
                            $overall_total = 0;
                            $overall_coefficient = 0;
                            ?>
                            
                            <div class="table-responsive mb-4">
                                <table class="table grade-table table-bordered">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" style="width: 22%;">Module / Type</th>
                                            <th rowspan="2" style="width: 5%;">Coef.</th>
                                            <th colspan="3">Contrôles Continus</th>
                                            <th colspan="2">Examens</th>
                                            <th rowspan="2" style="width: 8%;">Moyenne</th>
                                            <th rowspan="2" style="width: 12%;">Mention</th>
                                        </tr>
                                        <tr>
                                            <th style="width: 6%;">CC1</th>
                                            <th style="width: 6%;">CC2</th>
                                            <th style="width: 6%;">CC3</th>
                                            <th style="width: 8%;">Théorique</th>
                                            <th style="width: 8%;">Pratique</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grades as $module_id => $module_grades): 
                                            $module_name = $module_grades[array_key_first($module_grades)]['module_name'] ?? 'Module Inconnu';
                                            $module_type = $module_grades[array_key_first($module_grades)]['module_type'] ?? 'standard';
                                            $coefficient = $module_grades[array_key_first($module_grades)]['coefficient'] ?? 1;
                                            
                                            $module_average = calculateModuleAverage($module_grades, $module_type);
                                            $grade_status = getGradeStatus($module_average);
                                            
                                            if ($module_average !== null) {
                                                $overall_total += $module_average * $coefficient;
                                                $overall_coefficient += $coefficient;
                                            }
                                            
                                            // Determine row class based on module type
                                            $row_class = '';
                                            switch ($module_type) {
                                                case 'pfe':
                                                    $row_class = 'pfe-row';
                                                    break;
                                                case 'stage':
                                                    $row_class = 'stage-row';
                                                    break;
                                                default:
                                                    $row_class = 'standard-row';
                                            }
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td class="module-name">
                                                <?php echo getModuleTypeBadge($module_type); ?>
                                                <?php echo htmlspecialchars($module_name); ?>
                                                <?php if ($module_type === 'pfe' || $module_type === 'stage'): ?>
                                                    <br><small class="special-note">Note finale uniquement</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="grade-cell"><?php echo $coefficient; ?></td>
                                            
                                            <!-- CC Grades (only for standard modules) -->
                                            <?php if ($module_type === 'pfe' || $module_type === 'stage'): ?>
                                                <td class="grade-cell text-muted">-</td>
                                                <td class="grade-cell text-muted">-</td>
                                                <td class="grade-cell text-muted">-</td>
                                                <td class="grade-cell text-muted">-</td>
                                                <td class="grade-cell">
                                                    <?php 
                                                    if ($module_type === 'pfe') {
                                                        echo isset($module_grades['pfe']) ? number_format($module_grades['pfe']['grade'], 2) : '-';
                                                    } else {
                                                        echo isset($module_grades['stage']) ? number_format($module_grades['stage']['grade'], 2) : '-';
                                                    }
                                                    ?>
                                                </td>
                                            <?php else: ?>
                                                <td class="grade-cell">
                                                    <?php echo isset($module_grades['cc1']) ? number_format($module_grades['cc1']['grade'], 2) : '-'; ?>
                                                </td>
                                                <td class="grade-cell">
                                                    <?php echo isset($module_grades['cc2']) ? number_format($module_grades['cc2']['grade'], 2) : '-'; ?>
                                                </td>
                                                <td class="grade-cell">
                                                    <?php echo isset($module_grades['cc3']) ? number_format($module_grades['cc3']['grade'], 2) : '-'; ?>
                                                </td>
                                                <td class="grade-cell">
                                                    <?php echo isset($module_grades['theorique']) ? number_format($module_grades['theorique']['grade'], 2) : '-'; ?>
                                                </td>
                                                <td class="grade-cell">
                                                    <?php echo isset($module_grades['pratique']) ? number_format($module_grades['pratique']['grade'], 2) : '-'; ?>
                                                </td>
                                            <?php endif; ?>
                                            
                                            <!-- Module Average -->
                                            <td class="grade-cell <?php echo $grade_status['class']; ?>">
                                                <?php echo $module_average !== null ? number_format($module_average, 2) : '-'; ?>
                                            </td>
                                            
                                            <!-- Grade Status -->
                                            <td class="<?php echo $grade_status['class']; ?>">
                                                <?php echo $grade_status['status']; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Overall Average -->
                            <?php 
                            $overall_average = $overall_coefficient > 0 ? $overall_total / $overall_coefficient : 0;
                            $overall_status = getGradeStatus($overall_average);
                            ?>
                            <div class="table-responsive">
                                <table class="table grade-table table-bordered">
                                    <tr class="final-average">
                                        <td class="module-name"><strong>MOYENNE GÉNÉRALE PONDÉRÉE</strong></td>
                                        <td class="grade-cell"><strong><?php echo $overall_coefficient; ?></strong></td>
                                        <td colspan="5" class="text-center">-</td>
                                        <td class="grade-cell">
                                            <strong><?php echo number_format($overall_average, 2); ?>/20</strong>
                                        </td>
                                        <td>
                                            <strong><?php echo $overall_status['status']; ?></strong>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Legend -->
                            <div class="legend">
                                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Informations importantes:</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Mentions:</h6>
                                        <div class="mb-2">
                                            <span class="legend-item text-success fw-bold">≥ 16: Très Bien</span>
                                            <span class="legend-item text-info fw-bold">≥ 14: Bien</span>
                                            <span class="legend-item text-primary">≥ 12: Assez Bien</span>
                                        </div>
                                        <div>
                                            <span class="legend-item text-warning">≥ 10: Passable</span>
                                            <span class="legend-item text-danger fw-bold">< 10: Insuffisant</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Types de modules:</h6>
                                        <div class="mb-2">
                                            <span class="badge bg-secondary me-2">MODULE</span> Module standard (CC + Examens)
                                        </div>
                                        <div class="mb-2">
                                            <span class="badge bg-success me-2">STAGE</span> Stage professionnel (Note finale)
                                        </div>
                                        <div class="mb-2">
                                            <span class="badge bg-primary me-2">PFE</span> Projet de fin d'études (Note finale)
                                        </div>
                                        <h6 class="mt-3">Calculs:</h6>
                                        <p class="mb-1"><small><strong>Modules Standards:</strong> (Moyenne CC × 30%) + (Moyenne Examens × 70%)</small></p>
                                        <p class="mb-1"><small><strong>Examens:</strong> Moyenne entre Théorique et Pratique</small></p>
                                        <p class="mb-1"><small><strong>Stage/PFE:</strong> Note finale directe</small></p>
                                        <p class="mb-0"><small><strong>Coefficients:</strong> Standard = 1, Stage/PFE = 2</small></p>
                                    </div>
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
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Print Button -->
    <button class="btn btn-primary btn-lg print-btn no-print" onclick="window.print()">
        <i class="fas fa-print"></i>
    </button>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/scripts.js"></script>
</body>
</html>
