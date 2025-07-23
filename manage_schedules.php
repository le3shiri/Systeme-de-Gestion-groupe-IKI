<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$user_cni = $_SESSION['user_cni'];
$user_role = $_SESSION['role']; // Added to fix undefined variable error
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
    
    // Get all filieres for dropdown
    $stmt = $pdo->query("SELECT id, name FROM filieres ORDER BY name");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_schedule'])) {
        $filiere_id = $_POST['filiere_id'];
        
        // Validate filiere_id
        if (empty($filiere_id)) {
            $error_message = "Veuillez sélectionner une filière.";
        } else {
            // Check if file was uploaded without errors
            if (isset($_FILES['schedule_file']) && $_FILES['schedule_file']['error'] == 0) {
                $allowed_types = ['application/pdf'];
                $file_type = $_FILES['schedule_file']['type'];
                
                if (in_array($file_type, $allowed_types)) {
                    $upload_dir = 'uploads/schedules/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $filename = 'schedule_' . $filiere_id . '_' . time() . '.pdf';
                    $file_path = $upload_dir . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['schedule_file']['tmp_name'], $file_path)) {
                        // Deactivate any existing schedules for this filiere
                        $stmt = $pdo->prepare("UPDATE schedules SET active = 0 WHERE filiere_id = ?");
                        $stmt->execute([$filiere_id]);
                        
                        // Insert new schedule
                        $stmt = $pdo->prepare("INSERT INTO schedules (filiere_id, filename, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$filiere_id, $filename, $file_path, $user_cni]);
                        
                        $success_message = "L'emploi du temps a été téléchargé avec succès.";
                    } else {
                        $error_message = "Erreur lors du téléchargement du fichier.";
                    }
                } else {
                    $error_message = "Seuls les fichiers PDF sont autorisés.";
                }
            } else {
                $error_message = "Veuillez sélectionner un fichier à télécharger.";
            }
        }
    }
    
    // Handle schedule deletion
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_schedule'])) {
        $schedule_id = $_POST['schedule_id'];
        
        // Get file path before deleting record
        $stmt = $pdo->prepare("SELECT file_path FROM schedules WHERE id = ?");
        $stmt->execute([$schedule_id]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($schedule) {
            // Delete file if it exists
            if (file_exists($schedule['file_path'])) {
                unlink($schedule['file_path']);
            }
            
            // Delete record from database
            $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
            $stmt->execute([$schedule_id]);
            
            $success_message = "L'emploi du temps a été supprimé avec succès.";
        }
    }
    
    // Get all schedules with filiere names
    $stmt = $pdo->query("
        SELECT s.*, f.name as filiere_name 
        FROM schedules s 
        JOIN filieres f ON s.filiere_id = f.id 
        ORDER BY s.uploaded_at DESC
    ");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Emplois du Temps - Groupe IKI</title>
    
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard_admin.php">
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
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard_admin.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de Bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Gestion des Utilisateurs
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
                                Gestion des Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="record_absence.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                Gestion des Absences
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_schedules.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Emplois du Temps
                            </a>
                        </li>
                        <?php if ($user_role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="send_message.php">
                                <i class="fas fa-paper-plane me-2"></i>
                                Send Messages
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
                        <i class="fas fa-calendar-alt me-2"></i>
                        Gestion des Emplois du Temps
                    </h1>
                </div>

                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-upload me-2"></i>
                            Télécharger un Nouvel Emploi du Temps
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="filiere_id" class="form-label">Filière</label>
                                    <select class="form-select" id="filiere_id" name="filiere_id" required>
                                        <option value="">Sélectionner une filière</option>
                                        <?php foreach ($filieres as $filiere): ?>
                                        <option value="<?php echo $filiere['id']; ?>">
                                            <?php echo htmlspecialchars($filiere['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="schedule_file" class="form-label">Fichier PDF (Emploi du Temps)</label>
                                    <input class="form-control" type="file" id="schedule_file" name="schedule_file" accept="application/pdf" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <button type="submit" name="upload_schedule" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Télécharger
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Schedules List -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Emplois du Temps Existants
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($schedules)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucun emploi du temps n'a été téléchargé.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Filière</th>
                                        <th>Fichier</th>
                                        <th>Date de téléchargement</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedules as $schedule): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($schedule['filiere_name']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['filename']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($schedule['uploaded_at'])); ?></td>
                                        <td>
                                            <?php if ($schedule['active']): ?>
                                            <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($schedule['file_path']); ?>" class="btn btn-sm btn-info" target="_blank">
                                                <i class="fas fa-eye me-1"></i>Voir
                                            </a>
                                            <form action="" method="post" class="d-inline">
                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                <button type="submit" name="delete_schedule" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet emploi du temps?');">
                                                    <i class="fas fa-trash me-1"></i>Supprimer
                                                </button>
                                            </form>
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
</body>
</html>
