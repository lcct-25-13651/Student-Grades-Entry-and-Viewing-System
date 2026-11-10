<?php
include 'includes/conn.php';
include 'includes/functions.php';
include 'includes/layout.php';
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$statuses = ['present', 'absent', 'late', 'excused'];

$cs = $pdo->prepare("SELECT id, name FROM classrooms WHERE teacher_id = ? ORDER BY name");
$cs->execute([$teacher_id]);
$classrooms = $cs->fetchAll();

$ss = $pdo->prepare("
    SELECT cs.classroom_id, u.id, u.first_name, u.last_name
    FROM classroom_students cs
    JOIN users u ON u.id = cs.student_id
    JOIN classrooms c ON c.id = cs.classroom_id
    WHERE c.teacher_id = ?
    ORDER BY u.first_name, u.last_name
");
$ss->execute([$teacher_id]);
$enrolled = $ss->fetchAll();

function validateAttendance($pdo, $teacher_id, $in, $statuses) {
    if (!ctype_digit($in['classroom_id']) || !ctype_digit($in['student_id'])) {
        return "Please select a classroom and a student.";
    }
    if (!isValidDate($in['attendance_date'])) {
        return "Please enter a valid date.";
    }
    if ($in['attendance_date'] > date('Y-m-d')) {
        return "Attendance date cannot be in the future.";
    }
    if (!in_array($in['status'], $statuses, true)) {
        return "Please choose a valid status.";
    }
    if (strlen($in['remarks']) > 255) {
        return "Remarks must not exceed 255 characters.";
    }
    $chk = $pdo->prepare("SELECT 1 FROM classrooms WHERE id = ? AND teacher_id = ?");
    $chk->execute([$in['classroom_id'], $teacher_id]);
    if (!$chk->fetch()) return "You can only record attendance for your own classrooms.";

    $chk = $pdo->prepare("SELECT 1 FROM classroom_students WHERE classroom_id = ? AND student_id = ?");
    $chk->execute([$in['classroom_id'], $in['student_id']]);
    if (!$chk->fetch()) return "That student is not enrolled in the selected classroom.";
    return null;
}

function attendanceInput() {
    return [
        'classroom_id'    => postVal('classroom_id'),
        'student_id'      => postVal('student_id'),
        'attendance_date' => postVal('attendance_date'),
        'status'          => postVal('status'),
        'remarks'         => postVal('remarks'),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attendance'])) {
    $in = attendanceInput();
    $error = validateAttendance($pdo, $teacher_id, $in, $statuses);
    if ($error) {
        $message = $error;
        $message_type = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance (student_id, classroom_id, teacher_id, attendance_date, status, remarks) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$in['student_id'], $in['classroom_id'], $teacher_id, $in['attendance_date'], $in['status'], $in['remarks']]);
            $message = "Attendance recorded successfully!";
            $message_type = "success";
        } catch (PDOException $ex) {
            if ($ex->getCode() == 23000) {
                $message = "Attendance for that student, classroom and date already exists. Edit it instead.";
            } else {
                $message = "Could not save attendance. Please try again.";
            }
            $message_type = "danger";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $id = postVal('id');
    $in = attendanceInput();
    $error = ctype_digit($id) ? validateAttendance($pdo, $teacher_id, $in, $statuses) : "Invalid attendance record.";
    if ($error) {
        $message = $error;
        $message_type = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE attendance SET student_id = ?, classroom_id = ?, attendance_date = ?, status = ?, remarks = ? WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$in['student_id'], $in['classroom_id'], $in['attendance_date'], $in['status'], $in['remarks'], $id, $teacher_id]);
            $message = "Attendance updated successfully!";
            $message_type = "success";
        } catch (PDOException $ex) {
            if ($ex->getCode() == 23000) {
                $message = "Another record already exists for that student, classroom and date.";
            } else {
                $message = "Could not update attendance. Please try again.";
            }
            $message_type = "danger";
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_attendance' && isset($_GET['id'])) {
    $id = getVal('id');
    if (ctype_digit($id)) {
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$id, $teacher_id]);
        if ($stmt->rowCount() > 0) {
            $message = "Attendance record deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Record not found or you don't have permission to delete it!";
            $message_type = "danger";
        }
    } else {
        $message = "Invalid attendance record.";
        $message_type = "danger";
    }
}

$edit = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM attendance WHERE id = ? AND teacher_id = ?");
    $st->execute([$_GET['edit'], $teacher_id]);
    $edit = $st->fetch() ?: null;
}
$v = $edit ?: ['id' => '', 'classroom_id' => '', 'student_id' => '', 'attendance_date' => date('Y-m-d'), 'status' => 'present', 'remarks' => ''];

$f_q         = getVal('q');
$f_classroom = getVal('f_classroom');
$f_status    = getVal('f_status');
$f_from      = getVal('f_from');
$f_to        = getVal('f_to');

$where  = ["a.teacher_id = ?"];
$params = [$teacher_id];
if ($f_q !== '') {
    $where[] = "CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    $params[] = '%' . $f_q . '%';
}
if (ctype_digit($f_classroom)) { $where[] = "a.classroom_id = ?"; $params[] = $f_classroom; }
if (in_array($f_status, $statuses, true)) { $where[] = "a.status = ?"; $params[] = $f_status; }
if (isValidDate($f_from)) { $where[] = "a.attendance_date >= ?"; $params[] = $f_from; }
if (isValidDate($f_to))   { $where[] = "a.attendance_date <= ?"; $params[] = $f_to; }

$list = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, c.name AS classroom_name
    FROM attendance a
    JOIN users u ON u.id = a.student_id
    JOIN classrooms c ON c.id = a.classroom_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.attendance_date DESC, u.first_name
");
$list->execute($params);
$records = $list->fetchAll();

$counts = array_fill_keys($statuses, 0);
foreach ($records as $r) { $counts[$r['status']]++; }
$badge = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info'];

layoutStartSidebar('Attendance', 'teacher', 'attendance');
flashMessage($message, $message_type);
?>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-<?php echo $edit ? 'pen' : 'plus-circle'; ?>"></i> <?php echo $edit ? 'Edit Attendance' : 'Record Attendance'; ?></h5></div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo e($v['id']); ?>"><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Classroom</label>
                        <select class="form-select" name="classroom_id" id="classroom_id" required>
                            <option value="">Select classroom...</option>
                            <?php foreach ($classrooms as $c): ?>
                                <option value="<?php echo e($c['id']); ?>" <?php echo ($v['classroom_id'] == $c['id']) ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Student</label>
                        <select class="form-select" name="student_id" id="student_id" required>
                            <option value="">Select student...</option>
                            <?php foreach ($enrolled as $s): ?>
                                <option value="<?php echo e($s['id']); ?>" data-classroom="<?php echo e($s['classroom_id']); ?>" <?php echo ($v['student_id'] == $s['id'] && $v['classroom_id'] == $s['classroom_id']) ? 'selected' : ''; ?>><?php echo e($s['first_name'] . ' ' . $s['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="attendance_date" max="<?php echo e(date('Y-m-d')); ?>" value="<?php echo e($v['attendance_date']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?php echo e($st); ?>" <?php echo ($v['status'] === $st) ? 'selected' : ''; ?>><?php echo e(ucfirst($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <input type="text" class="form-control" name="remarks" maxlength="255" value="<?php echo e($v['remarks']); ?>">
                    </div>
                    <?php if ($edit): ?>
                        <button type="submit" name="update_attendance" class="btn btn-primary">Update</button>
                        <a href="attendance.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_attendance" class="btn btn-primary">Save Attendance</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-clipboard-check"></i> Attendance Records</h5></div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4"><input type="text" class="form-control" name="q" placeholder="Search student..." value="<?php echo e($f_q); ?>"></div>
                    <div class="col-md-4">
                        <select class="form-select" name="f_classroom">
                            <option value="">All classrooms</option>
                            <?php foreach ($classrooms as $c): ?>
                                <option value="<?php echo e($c['id']); ?>" <?php echo ($f_classroom == $c['id']) ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="f_status">
                            <option value="">All statuses</option>
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?php echo e($st); ?>" <?php echo ($f_status === $st) ? 'selected' : ''; ?>><?php echo e(ucfirst($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><input type="date" class="form-control" name="f_from" value="<?php echo e($f_from); ?>" title="From date"></div>
                    <div class="col-md-4"><input type="date" class="form-control" name="f_to" value="<?php echo e($f_to); ?>" title="To date"></div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Filter</button>
                        <a href="attendance.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>

                <div class="mb-3">
                    <?php foreach ($statuses as $st): ?>
                        <span class="badge bg-<?php echo e($badge[$st]); ?> me-1"><?php echo e(ucfirst($st)); ?>: <?php echo e($counts[$st]); ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead><tr><th>Date</th><th>Student</th><th>Classroom</th><th>Status</th><th>Remarks</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?php echo e(date('M j, Y', strtotime($r['attendance_date']))); ?></td>
                                <td><?php echo e($r['first_name'] . ' ' . $r['last_name']); ?></td>
                                <td><?php echo e($r['classroom_name']); ?></td>
                                <td><span class="badge bg-<?php echo e($badge[$r['status']]); ?>"><?php echo e(ucfirst($r['status'])); ?></span></td>
                                <td><?php echo e($r['remarks']); ?></td>
                                <td class="text-nowrap">
                                    <a href="?edit=<?php echo e($r['id']); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <a href="?action=delete_attendance&id=<?php echo e($r['id']); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this attendance record?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$records): ?>
                            <tr><td colspan="6" class="text-center text-muted">No attendance records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var classroom = document.getElementById('classroom_id');
    var student = document.getElementById('student_id');
    function filterStudents() {
        var cid = classroom.value;
        Array.prototype.forEach.call(student.options, function (opt) {
            if (!opt.value) return;
            var show = (opt.getAttribute('data-classroom') === cid);
            opt.hidden = !show;
            opt.disabled = !show;
        });
        var cur = student.options[student.selectedIndex];
        if (cur && cur.value && cur.disabled) student.value = '';
    }
    classroom.addEventListener('change', filterStudents);
    filterStudents();
})();
</script>
<?php layoutEnd(); ?>
