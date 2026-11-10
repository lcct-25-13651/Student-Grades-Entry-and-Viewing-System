<?php
include 'includes/conn.php';
include 'includes/functions.php';
include 'includes/layout.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('index.php');
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

function validateGradeInput($pdo, $teacher_id, $in) {
    $errors = [];
    if (!ctype_digit($in['student_id']) || !ctype_digit($in['subject_id']) || !ctype_digit($in['classroom_id'])) {
        return ["Please select a student, subject and classroom."];
    }
    if ($in['grade'] === '' || !is_numeric($in['grade']) || $in['grade'] < 0 || $in['grade'] > 100) {
        $errors[] = "Grade must be a number from 0 to 100.";
    }
    if (!in_array($in['grade_type'], ['quiz', 'assignment', 'exam', 'project'], true)) {
        $errors[] = "Please choose a valid grade type.";
    }
    if (strlen($in['remarks']) > 500) {
        $errors[] = "Remarks must not exceed 500 characters.";
    }
    if ($in['term_id'] !== '' && !ctype_digit($in['term_id'])) {
        $errors[] = "Invalid term selected.";
    }
    if ($errors) return $errors;

    $chk = $pdo->prepare("SELECT 1 FROM classrooms WHERE id = ? AND teacher_id = ?");
    $chk->execute([$in['classroom_id'], $teacher_id]);
    if (!$chk->fetch()) $errors[] = "You can only grade students in your own classrooms.";

    $chk = $pdo->prepare("SELECT 1 FROM classroom_students WHERE classroom_id = ? AND student_id = ?");
    $chk->execute([$in['classroom_id'], $in['student_id']]);
    if (!$chk->fetch()) $errors[] = "That student is not enrolled in the selected classroom.";

    $chk = $pdo->prepare("SELECT 1 FROM subjects WHERE id = ?");
    $chk->execute([$in['subject_id']]);
    if (!$chk->fetch()) $errors[] = "Selected subject does not exist.";

    if ($in['term_id'] !== '') {
        $chk = $pdo->prepare("SELECT 1 FROM terms WHERE id = ?");
        $chk->execute([$in['term_id']]);
        if (!$chk->fetch()) $errors[] = "Selected term does not exist.";
    }
    return $errors;
}

function gradeInputFromPost() {
    return [
        'student_id'   => postVal('student_id'),
        'subject_id'   => postVal('subject_id'),
        'classroom_id' => postVal('classroom_id'),
        'term_id'      => postVal('term_id'),
        'grade'        => postVal('grade'),
        'grade_type'   => postVal('grade_type'),
        'remarks'      => postVal('remarks'),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grade'])) {
    $in = gradeInputFromPost();
    $errors = validateGradeInput($pdo, $teacher_id, $in);
    if ($errors) {
        $message = implode(' ', $errors);
        $message_type = "danger";
    } else {
        $stmt = $pdo->prepare("INSERT INTO grades (student_id, subject_id, classroom_id, term_id, teacher_id, grade, grade_type, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$in['student_id'], $in['subject_id'], $in['classroom_id'], ($in['term_id'] === '' ? null : $in['term_id']), $teacher_id, $in['grade'], $in['grade_type'], $in['remarks']]);
        $message = "Grade added successfully!";
        $message_type = "success";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grade'])) {
    $grade_id = postVal('grade_id');
    $in = gradeInputFromPost();
    $errors = ctype_digit($grade_id) ? validateGradeInput($pdo, $teacher_id, $in) : ["Invalid grade record."];
    if ($errors) {
        $message = implode(' ', $errors);
        $message_type = "danger";
    } else {
        $stmt = $pdo->prepare("UPDATE grades SET student_id = ?, subject_id = ?, classroom_id = ?, term_id = ?, grade = ?, grade_type = ?, remarks = ? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$in['student_id'], $in['subject_id'], $in['classroom_id'], ($in['term_id'] === '' ? null : $in['term_id']), $in['grade'], $in['grade_type'], $in['remarks'], $grade_id, $teacher_id]);
        $message = "Grade updated successfully!";
        $message_type = "success";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_grade' && isset($_GET['id'])) {
    $grade_id = $_GET['id'];
    
    $result = deleteGrade($pdo, $grade_id, $teacher_id);
    if ($result === true) {
        $message = "Grade deleted successfully!";
        $message_type = "success";
    } else {
        $message = $result;
        $message_type = "danger";
    }
}

$edit_grade = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM grades WHERE id = ? AND teacher_id = ?");
    $st->execute([$_GET['edit'], $teacher_id]);
    $edit_grade = $st->fetch() ?: null;
}

$classrooms = $pdo->prepare("SELECT * FROM classrooms WHERE teacher_id = ?");
$classrooms->execute([$teacher_id]);
$teacher_classrooms = $classrooms->fetchAll();

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$terms = $pdo->query("SELECT * FROM terms ORDER BY start_date")->fetchAll();

$students_stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name 
    FROM users u 
    JOIN classroom_students cs ON u.id = cs.student_id 
    JOIN classrooms c ON cs.classroom_id = c.id 
    WHERE c.teacher_id = ? 
    ORDER BY u.first_name
");
$students_stmt->execute([$teacher_id]);
$students = $students_stmt->fetchAll();

$f_q       = getVal('q');
$f_subject = getVal('f_subject');
$f_term    = getVal('f_term');
$f_type    = getVal('f_type');

$where  = ["g.teacher_id = ?"];
$params = [$teacher_id];
if ($f_q !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $like = '%' . $f_q . '%';
    array_push($params, $like, $like, $like);
}
if (ctype_digit($f_subject)) { $where[] = "g.subject_id = ?"; $params[] = $f_subject; }
if (ctype_digit($f_term))    { $where[] = "g.term_id = ?";    $params[] = $f_term; }
if (in_array($f_type, ['quiz', 'assignment', 'exam', 'project'], true)) { $where[] = "g.grade_type = ?"; $params[] = $f_type; }

$grades_stmt = $pdo->prepare("
    SELECT g.*, u.first_name, u.last_name, s.name as subject_name, c.name as classroom_name, t.name as term_name
    FROM grades g 
    JOIN users u ON g.student_id = u.id 
    JOIN subjects s ON g.subject_id = s.id 
    JOIN classrooms c ON g.classroom_id = c.id 
    LEFT JOIN terms t ON g.term_id = t.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY g.graded_at DESC
");
$grades_stmt->execute($params);
$grades = $grades_stmt->fetchAll();

$v = $edit_grade ?: ['id' => '', 'student_id' => '', 'subject_id' => '', 'classroom_id' => '', 'term_id' => '', 'grade' => '', 'grade_type' => '', 'remarks' => ''];

$total_grades = count($grades);
$average_grade = 0;
if ($total_grades > 0) {
    $sum = 0;
    foreach ($grades as $grade) {
        $sum += $grade['grade'];
    }
    $average_grade = $sum / $total_grades;
}
?>

<?php layoutStartSidebar('Manage Grades', 'teacher', 'grades'); ?>
<style>
    .grade-badge { font-size: 0.9em; font-weight: bold; }
</style>
<?php flashMessage($message, $message_type); ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-<?php echo $edit_grade ? 'pen' : 'plus-circle'; ?>"></i> <?php echo $edit_grade ? 'Edit Grade' : 'Add New Grade'; ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($edit_grade): ?><input type="hidden" name="grade_id" value="<?php echo e($v['id']); ?>"><?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Student</label>
                                <select class="form-select" name="student_id" required>
                                    <option value="">Select student...</option>
                                    <?php foreach($students as $student): ?>
                                    <option value="<?php echo e($student['id']); ?>" <?php echo ($v['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Subject</label>
                                        <select class="form-select" name="subject_id" required>
                                            <option value="">Select subject...</option>
                                            <?php foreach($subjects as $subject): ?>
                                            <option value="<?php echo e($subject['id']); ?>" <?php echo ($v['subject_id'] == $subject['id']) ? 'selected' : ''; ?>><?php echo e($subject['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Classroom</label>
                                        <select class="form-select" name="classroom_id" required>
                                            <option value="">Select classroom...</option>
                                            <?php foreach($teacher_classrooms as $classroom): ?>
                                            <option value="<?php echo e($classroom['id']); ?>" <?php echo ($v['classroom_id'] == $classroom['id']) ? 'selected' : ''; ?>><?php echo e($classroom['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Term</label>
                                <select class="form-select" name="term_id">
                                    <option value="">No term</option>
                                    <?php foreach($terms as $term): ?>
                                    <option value="<?php echo e($term['id']); ?>" <?php echo ($v['term_id'] == $term['id']) ? 'selected' : ''; ?>><?php echo e($term['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Grade</label>
                                        <input type="number" class="form-control" name="grade" min="0" max="100" step="0.01" value="<?php echo e($v['grade']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Grade Type</label>
                                        <select class="form-select" name="grade_type" required>
                                            <option value="">Select type...</option>
                                            <option value="quiz" <?php echo ($v['grade_type'] === 'quiz') ? 'selected' : ''; ?>>Quiz</option>
                                            <option value="assignment" <?php echo ($v['grade_type'] === 'assignment') ? 'selected' : ''; ?>>Assignment</option>
                                            <option value="exam" <?php echo ($v['grade_type'] === 'exam') ? 'selected' : ''; ?>>Exam</option>
                                            <option value="project" <?php echo ($v['grade_type'] === 'project') ? 'selected' : ''; ?>>Project</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="remarks" rows="2" maxlength="500"><?php echo e($v['remarks']); ?></textarea>
                            </div>
                            <?php if ($edit_grade): ?>
                            <button type="submit" name="update_grade" class="btn btn-primary"><i class="fas fa-save"></i> Update Grade</button>
                            <a href="grades.php" class="btn btn-secondary">Cancel</a>
                            <?php else: ?>
                            <button type="submit" name="add_grade" class="btn btn-primary"><i class="fas fa-save"></i> Add Grade</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-chart-bar"></i> Grade Statistics</h4>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border rounded p-3">
                                    <h3 class="text-primary"><?php echo e($total_grades); ?></h3>
                                    <small>Total Grades</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-3">
                                    <h3 class="text-success"><?php echo e(number_format($average_grade, 1)); ?></h3>
                                    <small>Average Grade</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-3">
                                    <h3 class="text-info"><?php echo e(count($teacher_classrooms)); ?></h3>
                                    <small>Classrooms</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h4><i class="fas fa-info-circle"></i> Grading Scale</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><td>90-100</td><td><span class="badge bg-success">A</span></td><td>Excellent</td></tr>
                            <tr><td>80-89</td><td><span class="badge bg-info">B</span></td><td>Good</td></tr>
                            <tr><td>70-79</td><td><span class="badge bg-warning">C</span></td><td>Satisfactory</td></tr>
                            <tr><td>60-69</td><td><span class="badge bg-danger">D</span></td><td>Needs Improvement</td></tr>
                            <tr><td>Below 60</td><td><span class="badge bg-dark">F</span></td><td>Fail</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-list"></i> Recent Grades</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="q" placeholder="Search student name..." value="<?php echo e($f_q); ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="f_subject">
                                    <option value="">All subjects</option>
                                    <?php foreach($subjects as $subject): ?>
                                    <option value="<?php echo e($subject['id']); ?>" <?php echo ($f_subject == $subject['id']) ? 'selected' : ''; ?>><?php echo e($subject['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="f_term">
                                    <option value="">All terms</option>
                                    <?php foreach($terms as $term): ?>
                                    <option value="<?php echo e($term['id']); ?>" <?php echo ($f_term == $term['id']) ? 'selected' : ''; ?>><?php echo e($term['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="f_type">
                                    <option value="">All types</option>
                                    <?php foreach(['quiz','assignment','exam','project'] as $t): ?>
                                    <option value="<?php echo e($t); ?>" <?php echo ($f_type === $t) ? 'selected' : ''; ?>><?php echo e(ucfirst($t)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
                                <a href="grades.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Subject</th>
                                        <th>Classroom</th>
                                        <th>Term</th>
                                        <th>Grade</th>
                                        <th>Type</th>
                                        <th>Remarks</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($grades as $grade): 
                                        $grade_point = calculateGradePoint($grade['grade']);
                                        $grade_color = getGradeColor($grade['grade']);
                                    ?>
                                    <tr>
                                        <td><?php echo e($grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                                        <td><?php echo e($grade['subject_name']); ?></td>
                                        <td><?php echo e($grade['classroom_name']); ?></td>
                                        <td><?php echo e($grade['term_name'] ?? '—'); ?></td>
                                        <td>
                                            <span class="badge grade-badge bg-<?php echo e($grade_color); ?>">
                                                <?php echo e($grade['grade']); ?> (<?php echo e($grade_point); ?>)
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo e(ucfirst($grade['grade_type'])); ?></span>
                                        </td>
                                        <td><?php echo e($grade['remarks']); ?></td>
                                        <td><?php echo e(date('M j, Y', strtotime($grade['graded_at']))); ?></td>
                                        <td>
                                            <a href="?edit=<?php echo e($grade['id']); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="?action=delete_grade&id=<?php echo e($grade['id']); ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to delete this grade?')">
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php layoutEnd(); ?>
