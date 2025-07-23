<?php
session_start();

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_cni']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: dashboard_admin.php');
            break;
        case 'teacher':
            header('Location: dashboard_teacher.php');
            break;
        case 'student':
            header('Location: dashboard_student.php');
            break;
        case 'director':
            header('Location: dashboard_director.php');
            break;
    }
    exit();
}

$error_message = '';
$cni_value = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cni = trim($_POST['cni'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $cni_value = htmlspecialchars($cni);
    
    if (empty($cni) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        // Database connection
        $host = 'localhost';
        $dbname = 'groupe_iki';
        $username = 'root';
        $db_password = '';
        
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check in admins table
            $stmt = $pdo->prepare("SELECT cni, password FROM admins WHERE cni = ?");
            $stmt->execute([$cni]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['user_cni'] = $cni;
                $_SESSION['role'] = 'admin';
                header('Location: dashboard_admin.php');
                exit();
            }
            
            // Check in teachers table
            $stmt = $pdo->prepare("SELECT cni, password, account_status FROM teachers WHERE cni = ?");
            $stmt->execute([$cni]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($teacher && password_verify($password, $teacher['password'])) {
                // Check if account is active
                if ($teacher['account_status'] === 'suspended') {
                    $error_message = 'Your account has been suspended. Please contact the administrator.';
                } else {
                    $_SESSION['user_cni'] = $cni;
                    $_SESSION['role'] = 'teacher';
                    header('Location: dashboard_teacher.php');
                    exit();
                }
            }
            
            // Check in students table
            $stmt = $pdo->prepare("SELECT cni, password FROM students WHERE cni = ?");
            $stmt->execute([$cni]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student && password_verify($password, $student['password'])) {
                $_SESSION['user_cni'] = $cni;
                $_SESSION['role'] = 'student';
                header('Location: dashboard_student.php');
                exit();
            }
            
            // Check in directors table
            $stmt = $pdo->prepare("SELECT cni, password FROM directors WHERE cni = ?");
            $stmt->execute([$cni]);
            $director = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($director && password_verify($password, $director['password'])) {
                $_SESSION['user_cni'] = $cni;
                $_SESSION['role'] = 'director';
                header('Location: dashboard_director.php');
                exit();
            }
            
            $error_message = 'Invalid CNI or password.';
            
        } catch (PDOException $e) {
            $error_message = 'Database connection failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Groupe IKI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="container-fluid h-100">
            <div class="row h-100 justify-content-center align-items-center">
                <div class="col-md-6 col-lg-4">
                    <div class="login-card">
                        <div class="card shadow-lg border-0">
                            <div class="card-body p-5">
                                <!-- Logo/Title -->
                                <div class="text-center mb-4">
                                    <div class="logo-container">
                                        <!-- <i class="fas fa-graduation-cap fa-3x text-primary mb-3"></i> -->
                                         <img src="assets/logo-circle.jpg" alt="" width="200px">
                                        <!-- <h2 class="fw-bold text-primary">Groupe IKI</h2> -->
                                        <!-- <p class="text-muted">Educational Institute Management</p> -->
                                    </div>
                                </div>

                                <!-- Error Alert -->
                                <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert" id="error-alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo htmlspecialchars($error_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>

                                <!-- Login Form -->
                                <form method="POST" action="login.php" id="loginForm" novalidate>
                                    <div class="mb-4">
                                        <label for="cni" class="form-label">
                                            <i class="fas fa-id-card text-primary"></i>CNI
                                        </label>
                                        <div class="position-relative">
                                            <input type="text" 
                                                   class="form-control form-control-lg" 
                                                   id="cni" 
                                                   name="cni" 
                                                   placeholder="Entrez votre CNI"
                                                   value="<?php echo $cni_value; ?>"
                                                   required>
                                            <div class="invalid-feedback">
                                                Veuillez saisir votre CNI.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-5">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock text-primary"></i>Mot de passe
                                        </label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control form-control-lg" 
                                                   id="password" 
                                                   name="password" 
                                                   placeholder="Entrez votre mot de passe"
                                                   required>
                                            <button class="btn btn-outline-secondary" 
                                                    type="button" 
                                                    id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">
                                            Veuillez saisir votre mot de passe.
                                        </div>
                                    </div>

                                    <div class="d-grid mb-4">
                                        <button type="submit" 
                                                class="btn btn-primary btn-lg" 
                                                id="loginBtn">
                                            <span class="btn-text">
                                                <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                            </span>
                                            <span class="btn-loading d-none">
                                                <span class="spinner-border spinner-border-sm me-2"></span>
                                                Connexion...
                                            </span>
                                        </button>
                                    </div>
                                </form>

                                <div class="text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Connexion sécurisée - Groupe IKI
                                    </small>
                                </div>

                                <!-- Sample Credentials -->
                                <!-- <div class="mt-4">
                                    <small class="text-muted">
                                        <strong>Sample Credentials:</strong><br>
                                        Admin: AA123456 / password<br>
                                        Teacher: BB123456 / password<br>
                                        Student: EE123456 / password
                                    </small> -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/scripts.js"></script>
</body>
</html>
