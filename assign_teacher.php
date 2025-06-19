<?php
session_start();
// Only admins
if (!isset($_SESSION['user_cni']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$dbname = 'groupe_iki';
$username = 'root';
$db_password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$user_cni = $_SESSION['user_cni'];
$success_message = '';
$error_message = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher_module'])) {
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $module_id = (int)($_POST['module_id'] ?? 0);
    $filiere_id = (int)($_POST['filiere_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($teacher_id && $module_id && $filiere_id) {
        try {
            // Check duplicate
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_module_assignments WHERE teacher_id=? AND module_id=? AND filiere_id=? AND is_active=1");
            $stmt->execute([$teacher_id, $module_id, $filiere_id]);
            if ($stmt->fetchColumn() == 0) {
                // Get teacher CNI
                $cni = '';
                $st = $pdo->prepare("SELECT cni FROM teachers WHERE id=?");
                $st->execute([$teacher_id]);
                $cni = $st->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO teacher_module_assignments (teacher_id, teacher_cni, module_id, filiere_id, assigned_date, assigned_by_admin_cni, is_active, notes) VALUES (?,?,?,?,CURDATE(),?,1,?)");
                $stmt->execute([$teacher_id, $cni, $module_id, $filiere_id, $user_cni, $notes]);
                $success_message = 'Assignment created successfully!';
            } else {
                $error_message = 'This assignment already exists.';
            }
        } catch (PDOException $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Please select all fields.';
    }
}

// Handle deactivate
if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];
    try {
        $stmt = $pdo->prepare("UPDATE teacher_module_assignments SET is_active=0 WHERE id=?");
        $stmt->execute([$id]);
        $success_message = 'Assignment deactivated.';
    } catch (PDOException $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Fetch data for selects
$teachers = $pdo->query("SELECT id, CONCAT(prenom,' ',nom,' (',cni,')') AS label FROM teachers ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);
$filieres = $pdo->query("SELECT id, name FROM filieres ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$modules = $pdo->query("SELECT m.id, m.name, m.filiere_id FROM modules m ORDER BY m.name")->fetchAll(PDO::FETCH_ASSOC);
$modulesByFiliere = [];
foreach ($modules as $m) {
    $modulesByFiliere[$m['filiere_id']][] = $m;
}

// Fetch active assignments
$assignments = $pdo->query("SELECT tma.id, CONCAT(t.prenom,' ',t.nom) AS teacher_name, f.name AS filiere_name, m.name AS module_name, tma.assigned_date FROM teacher_module_assignments tma JOIN teachers t ON t.id=tma.teacher_id JOIN filieres f ON f.id=tma.filiere_id JOIN modules m ON m.id=tma.module_id WHERE tma.is_active=1 ORDER BY t.nom, m.name")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Teacher to Module</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<a href="manage_users.php?section=teachers" class="btn btn-secondary mb-3">← Back to Manage Users</a>
<h2 class="mb-4">Assign Teacher to Module</h2>
<?php if($success_message):?><div class="alert alert-success"><?=$success_message?></div><?php endif;?>
<?php if($error_message):?><div class="alert alert-danger"><?=$error_message?></div><?php endif;?>
<form method="POST" class="card p-4 mb-5">
<input type="hidden" name="assign_teacher_module" value="1">
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Teacher</label>
        <select name="teacher_id" class="form-select" required>
            <option value="">Select teacher</option>
            <?php foreach($teachers as $t):?>
            <option value="<?=$t['id']?>"><?=htmlspecialchars($t['label'])?></option>
            <?php endforeach;?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Filière</label>
        <select name="filiere_id" id="filiere_select" class="form-select" required>
            <option value="">Select filière</option>
            <?php foreach($filieres as $f):?>
            <option value="<?=$f['id']?>"><?=htmlspecialchars($f['name'])?></option>
            <?php endforeach;?>
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
<button class="btn btn-primary mt-3">Assign</button>
</form>

<h3>Current Active Assignments</h3>
<table class="table table-bordered">
<thead><tr><th>Teacher</th><th>Filière</th><th>Module</th><th>Date</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($assignments as $a):?>
<tr>
<td><?=htmlspecialchars($a['teacher_name'])?></td>
<td><?=htmlspecialchars($a['filiere_name'])?></td>
<td><?=htmlspecialchars($a['module_name'])?></td>
<td><?=htmlspecialchars($a['assigned_date'])?></td>
<td><a href="assign_teacher.php?deactivate=<?=$a['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Deactivate this assignment?');">Deactivate</a></td>
</tr>
<?php endforeach;?>
</tbody>
</table>

<script>
const modulesByFiliere = <?php echo json_encode($modulesByFiliere);?>;
document.getElementById('filiere_select').addEventListener('change', function(){
 const fid = this.value;
 const moduleSelect = document.getElementById('module_select');
 moduleSelect.innerHTML = '<option value="">Select module</option>';
 if(modulesByFiliere[fid]){
  modulesByFiliere[fid].forEach(m=>{
   const opt = document.createElement('option');
   opt.value = m.id;
   opt.textContent = m.name;
   moduleSelect.appendChild(opt);
  });
 }
});
</script>
</body>
</html>
