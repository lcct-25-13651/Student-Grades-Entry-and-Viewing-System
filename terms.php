<?php
include 'includes/conn.php';
include 'includes/functions.php';
include 'includes/layout.php';
requireRole('admin');

$message = '';
$message_type = '';

function validateTerm($name, $start, $end) {
    if ($name === '' || strlen($name) > 50) return "Term name is required (max 50 characters).";
    if (!isValidDate($start) || !isValidDate($end)) return "Please enter valid start and end dates.";
    if ($end < $start) return "End date cannot be earlier than the start date.";
    return null;
}

// ---------- CREATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_term'])) {
    $name = postVal('name');
    $start = postVal('start_date');
    $end = postVal('end_date');
    $active = isset($_POST['is_active']) ? 1 : 0;

    $error = validateTerm($name, $start, $end);
    if (!$error) {
        $dup = $pdo->prepare("SELECT id FROM terms WHERE name = ?");
        $dup->execute([$name]);
        if ($dup->fetch()) $error = "That term name already exists!";
    }
    if ($error) {
        $message = $error;
        $message_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO terms (name, start_date, end_date, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $start, $end, $active]);
            if ($active) {
                $new_id = $pdo->lastInsertId();
                $off = $pdo->prepare("UPDATE terms SET is_active = 0 WHERE id != ?");
                $off->execute([$new_id]);
            }
            $pdo->commit();
            $message = "Term added successfully!";
            $message_type = "success";
        } catch (Exception $ex) {
            $pdo->rollBack();
            $message = "Could not add the term. Please try again.";
            $message_type = "danger";
        }
    }
}

// ---------- UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_term'])) {
    $id = postVal('id');
    $name = postVal('name');
    $start = postVal('start_date');
    $end = postVal('end_date');
    $active = isset($_POST['is_active']) ? 1 : 0;

    $error = ctype_digit($id) ? validateTerm($name, $start, $end) : "Invalid term.";
    if (!$error) {
        $dup = $pdo->prepare("SELECT id FROM terms WHERE name = ? AND id != ?");
        $dup->execute([$name, $id]);
        if ($dup->fetch()) $error = "Another term already uses that name!";
    }
    if ($error) {
        $message = $error;
        $message_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE terms SET name = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $start, $end, $active, $id]);
            if ($active) {
                $off = $pdo->prepare("UPDATE terms SET is_active = 0 WHERE id != ?");
                $off->execute([$id]);
            }
            $pdo->commit();
            $message = "Term updated successfully!";
            $message_type = "success";
        } catch (Exception $ex) {
            $pdo->rollBack();
            $message = "Could not update the term. Please try again.";
            $message_type = "danger";
        }
    }
}

// ---------- DELETE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_term'])) {
    $id = postVal('id');
    $result = ctype_digit($id) ? deleteTerm($pdo, $id) : "Invalid term.";
    if ($result === true) {
        $message = "Term deleted successfully!";
        $message_type = "success";
    } else {
        $message = $result;
        $message_type = "danger";
    }
}

// ---------- READ (search + status filter) ----------
$edit = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM terms WHERE id = ?");
    $st->execute([$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$v = $edit ?: ['id' => '', 'name' => '', 'start_date' => '', 'end_date' => '', 'is_active' => 0];

$q = getVal('q');
$status = getVal('status');
$where = ["t.name LIKE ?"];
$params = ['%' . $q . '%'];
if ($status === 'active')   { $where[] = "t.is_active = 1"; }
if ($status === 'inactive') { $where[] = "t.is_active = 0"; }

$list = $pdo->prepare("
    SELECT t.*, (SELECT COUNT(*) FROM grades g WHERE g.term_id = t.id) AS grade_count
    FROM terms t
    WHERE " . implode(' AND ', $where) . "
    ORDER BY t.start_date
");
$list->execute($params);
$terms = $list->fetchAll();

layoutStartSidebar('Terms', 'admin', 'terms');
flashMessage($message, $message_type);
?>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-<?php echo $edit ? 'pen' : 'plus-circle'; ?>"></i> <?php echo $edit ? 'Edit Term' : 'Add Term'; ?></h5></div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo e($v['id']); ?>"><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Term Name</label>
                        <input type="text" class="form-control" name="name" maxlength="50" value="<?php echo e($v['name']); ?>" placeholder="e.g. 3rd Quarter" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo e($v['start_date']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo e($v['end_date']); ?>" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo $v['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Set as the active term</label>
                    </div>
                    <?php if ($edit): ?>
                        <button type="submit" name="update_term" class="btn btn-primary">Update</button>
                        <a href="terms.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_term" class="btn btn-primary">Add Term</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-calendar-alt"></i> Terms</h5></div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-5"><input type="text" class="form-control" name="q" placeholder="Search terms..." value="<?php echo e($q); ?>"></div>
                    <div class="col-3">
                        <select class="form-select" name="status">
                            <option value="">All</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        <a href="terms.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead><tr><th>Name</th><th>Start</th><th>End</th><th>Status</th><th>Grades</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($terms as $t): ?>
                            <tr>
                                <td><?php echo e($t['name']); ?></td>
                                <td><?php echo e(date('M j, Y', strtotime($t['start_date']))); ?></td>
                                <td><?php echo e(date('M j, Y', strtotime($t['end_date']))); ?></td>
                                <td><span class="badge bg-<?php echo $t['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td><span class="badge bg-info"><?php echo e($t['grade_count']); ?></span></td>
                                <td class="text-nowrap">
                                    <a href="?edit=<?php echo e($t['id']); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this term?')">
                                        <input type="hidden" name="id" value="<?php echo e($t['id']); ?>">
                                        <button type="submit" name="delete_term" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$terms): ?>
                            <tr><td colspan="6" class="text-center text-muted">No terms found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php layoutEnd(); ?>
