<?php
include 'includes/conn.php';
include 'includes/functions.php';
include 'includes/layout.php';
requireRole('admin');

$message = '';
$message_type = '';

// ---------- CREATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grade_level'])) {
    $name = postVal('name');
    $description = postVal('description');

    if ($name === '' || strlen($name) > 50) {
        $message = "Grade level name is required (max 50 characters).";
        $message_type = "danger";
    } else {
        $dup = $pdo->prepare("SELECT id FROM grade_levels WHERE name = ?");
        $dup->execute([$name]);
        if ($dup->fetch()) {
            $message = "That grade level already exists!";
            $message_type = "danger";
        } else {
            $stmt = $pdo->prepare("INSERT INTO grade_levels (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $message = "Grade level added successfully!";
            $message_type = "success";
        }
    }
}

// ---------- UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grade_level'])) {
    $id = postVal('id');
    $name = postVal('name');
    $description = postVal('description');

    if (!ctype_digit($id) || $name === '' || strlen($name) > 50) {
        $message = "Grade level name is required (max 50 characters).";
        $message_type = "danger";
    } else {
        $dup = $pdo->prepare("SELECT id FROM grade_levels WHERE name = ? AND id != ?");
        $dup->execute([$name, $id]);
        if ($dup->fetch()) {
            $message = "Another grade level already uses that name!";
            $message_type = "danger";
        } else {
            $stmt = $pdo->prepare("UPDATE grade_levels SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            $message = "Grade level updated successfully!";
            $message_type = "success";
        }
    }
}

// ---------- DELETE (POST + confirm prompt in the form) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_grade_level'])) {
    $id = postVal('id');
    $result = ctype_digit($id) ? deleteGradeLevel($pdo, $id) : "Invalid grade level.";
    if ($result === true) {
        $message = "Grade level deleted successfully!";
        $message_type = "success";
    } else {
        $message = $result;
        $message_type = "danger";
    }
}

// ---------- READ (search) ----------
$edit = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM grade_levels WHERE id = ?");
    $st->execute([$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$v = $edit ?: ['id' => '', 'name' => '', 'description' => ''];

$q = getVal('q');
$like = '%' . $q . '%';
$list = $pdo->prepare("
    SELECT gl.*, (SELECT COUNT(*) FROM classrooms c WHERE c.grade_level_id = gl.id) AS classroom_count
    FROM grade_levels gl
    WHERE gl.name LIKE ? OR gl.description LIKE ?
    ORDER BY gl.name
");
$list->execute([$like, $like]);
$grade_levels = $list->fetchAll();

layoutStartSidebar('Grade Levels', 'admin', 'grade_levels');
flashMessage($message, $message_type);
?>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-<?php echo $edit ? 'pen' : 'plus-circle'; ?>"></i> <?php echo $edit ? 'Edit Grade Level' : 'Add Grade Level'; ?></h5></div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo e($v['id']); ?>"><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" maxlength="50" value="<?php echo e($v['name']); ?>" placeholder="e.g. Grade 4" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo e($v['description']); ?></textarea>
                    </div>
                    <?php if ($edit): ?>
                        <button type="submit" name="update_grade_level" class="btn btn-primary">Update</button>
                        <a href="grade_levels.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_grade_level" class="btn btn-primary">Add Grade Level</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-layer-group"></i> Grade Levels</h5></div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-8"><input type="text" class="form-control" name="q" placeholder="Search grade levels..." value="<?php echo e($q); ?>"></div>
                    <div class="col-4">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        <a href="grade_levels.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Classrooms</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($grade_levels as $gl): ?>
                            <tr>
                                <td><?php echo e($gl['id']); ?></td>
                                <td><?php echo e($gl['name']); ?></td>
                                <td><?php echo e($gl['description']); ?></td>
                                <td><span class="badge bg-info"><?php echo e($gl['classroom_count']); ?></span></td>
                                <td class="text-nowrap">
                                    <a href="?edit=<?php echo e($gl['id']); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this grade level?')">
                                        <input type="hidden" name="id" value="<?php echo e($gl['id']); ?>">
                                        <button type="submit" name="delete_grade_level" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$grade_levels): ?>
                            <tr><td colspan="5" class="text-center text-muted">No grade levels found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php layoutEnd(); ?>
