<?php
include 'includes/conn.php';
include 'includes/functions.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('index.php');
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle classroom creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_classroom'])) {
    $name = postVal('name');
    $description = postVal('description');
    $grade_level_id = postVal('grade_level_id');

    if ($name === '' || strlen($name) > 100) {
        $message = "Classroom name is required (max 100 characters).";
        $message_type = "danger";
    } elseif ($grade_level_id !== '' && !ctype_digit($grade_level_id)) {
        $message = "Invalid grade level selected.";
        $message_type = "danger";
    } else {
        $stmt = $pdo->prepare("INSERT INTO classrooms (name, description, teacher_id, grade_level_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $teacher_id, ($grade_level_id === '' ? null : $grade_level_id)]);
        $message = "Classroom created successfully!";
        $message_type = "success";
    }
}

// Handle classroom update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_classroom'])) {
    $classroom_id = postVal('classroom_id');
    $name = postVal('name');
    $description = postVal('description');
    $grade_level_id = postVal('grade_level_id');

    if (!ctype_digit($classroom_id) || $name === '' || strlen($name) > 100) {
        $message = "Classroom name is required (max 100 characters).";
        $message_type = "danger";
    } elseif ($grade_level_id !== '' && !ctype_digit($grade_level_id)) {
        $message = "Invalid grade level selected.";
        $message_type = "danger";
    } else {
        $stmt = $pdo->prepare("UPDATE classrooms SET name = ?, description = ?, grade_level_id = ? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$name, $description, ($grade_level_id === '' ? null : $grade_level_id), $classroom_id, $teacher_id]);
        $message = "Classroom updated successfully!";
        $message_type = "success";
    }
}

// Handle student addition to classroom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $classroom_id = postVal('classroom_id');
    $student_id = postVal('student_id');

    // Classroom must belong to this teacher and the user must be a student
    $own = $pdo->prepare("SELECT 1 FROM classrooms WHERE id = ? AND teacher_id = ?");
    $own->execute([$classroom_id, $teacher_id]);
    $is_student = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'student'");
    $is_student->execute([$student_id]);

    // Check if student is already in classroom
    $check_stmt = $pdo->prepare("SELECT * FROM classroom_students WHERE classroom_id = ? AND student_id = ?");
    $check_stmt->execute([$classroom_id, $student_id]);

    if (!$own->fetch() || !$is_student->fetch()) {
        $message = "Please choose one of your classrooms and a valid student.";
        $message_type = "danger";
    } elseif ($check_stmt->rowCount() == 0) {
        $stmt = $pdo->prepare("INSERT INTO classroom_students (classroom_id, student_id) VALUES (?, ?)");
        $stmt->execute([$classroom_id, $student_id]);
        $message = "Student added to classroom successfully!";
        $message_type = "success";
    } else {
        $message = "Student is already in this classroom!";
        $message_type = "danger";
    }
}

// Handle student removal from classroom
if (isset($_GET['action']) && $_GET['action'] === 'remove_student' && isset($_GET['classroom_id']) && isset($_GET['student_id'])) {
    $classroom_id = $_GET['classroom_id'];
    $student_id = $_GET['student_id'];
    
    $result = removeStudentFromClassroom($pdo, $classroom_id, $student_id);
    if ($result === true) {
        $message = "Student removed from classroom successfully!";
        $message_type = "success";
    } else {
        $message = $result;
        $message_type = "danger";
    }
}

// Handle classroom deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_classroom' && isset($_GET['id'])) {
    $classroom_id = $_GET['id'];
    
    // Verify the classroom belongs to the teacher
    $check_stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ? AND teacher_id = ?");
    $check_stmt->execute([$classroom_id, $teacher_id]);
    
    if ($check_stmt->rowCount() > 0) {
        $result = deleteClassroom($pdo, $classroom_id);
        if ($result === true) {
            $message = "Classroom deleted successfully!";
            $message_type = "success";
        } else {
            $message = $result;
            $message_type = "danger";
        }
    } else {
        $message = "Classroom not found or you don't have permission to delete it!";
        $message_type = "danger";
    }
}

// Get teacher's classrooms
$classrooms = $pdo->prepare("SELECT c.*, gl.name AS grade_level_name FROM classrooms c LEFT JOIN grade_levels gl ON c.grade_level_id = gl.id WHERE c.teacher_id = ? ORDER BY c.name");
$classrooms->execute([$teacher_id]);
$teacher_classrooms = $classrooms->fetchAll();

// Grade levels for the dropdown + classroom being edited (if any)
$grade_levels = $pdo->query("SELECT * FROM grade_levels ORDER BY name")->fetchAll();
$edit_classroom = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    foreach ($teacher_classrooms as $tc) {
        if ($tc['id'] == $_GET['edit']) { $edit_classroom = $tc; break; }
    }
}
$cv = $edit_classroom ?: ['id' => '', 'name' => '', 'description' => '', 'grade_level_id' => ''];

// Get all students
$students = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY first_name, last_name")->fetchAll();

// Get students for a specific classroom if requested
$classroom_students = [];
if (isset($_GET['view_students']) && isset($_GET['classroom_id'])) {
    $classroom_id = $_GET['classroom_id'];
    $classroom_students = getClassroomStudents($pdo, $classroom_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classrooms - Student Grade Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="teacher.php">
                <i class="fas fa-chalkboard-teacher"></i> Teacher Portal
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo e($_SESSION['first_name']); ?>
                </span>
                <a class="nav-link" href="teacher.php">Dashboard</a>
                <a class="nav-link" href="attendance.php">Attendance</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['view_students']) && isset($_GET['classroom_id'])): ?>
            <!-- View Students in Classroom -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>
                        <i class="fas fa-users"></i> 
                        Students in <?php echo e($teacher_classrooms[array_search($_GET['classroom_id'], array_column($teacher_classrooms, 'id'))]['name']); ?>
                    </h4>
                    <a href="classroom.php" class="btn btn-secondary">Back to Classrooms</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($classroom_students as $student): ?>
                                <tr>
                                    <td><?php echo e($student['id']); ?></td>
                                    <td><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td><?php echo e($student['username']); ?></td>
                                    <td><?php echo e($student['email']); ?></td>
                                    <td>
                                        <a href="?action=remove_student&classroom_id=<?php echo e($_GET['classroom_id']); ?>&student_id=<?php echo e($student['id']); ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to remove this student from the classroom?')">
                                            Remove
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Main Classroom Management -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-<?php echo $edit_classroom ? 'pen' : 'plus-circle'; ?>"></i> <?php echo $edit_classroom ? 'Edit Classroom' : 'Create New Classroom'; ?></h4>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($edit_classroom): ?><input type="hidden" name="classroom_id" value="<?php echo e($cv['id']); ?>"><?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label">Classroom Name</label>
                                    <input type="text" class="form-control" name="name" maxlength="100" value="<?php echo e($cv['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Grade Level</label>
                                    <select class="form-select" name="grade_level_id">
                                        <option value="">No grade level</option>
                                        <?php foreach($grade_levels as $gl): ?>
                                        <option value="<?php echo e($gl['id']); ?>" <?php echo ($cv['grade_level_id'] == $gl['id']) ? 'selected' : ''; ?>><?php echo e($gl['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3"><?php echo e($cv['description']); ?></textarea>
                                </div>
                                <?php if ($edit_classroom): ?>
                                <button type="submit" name="update_classroom" class="btn btn-primary"><i class="fas fa-save"></i> Update Classroom</button>
                                <a href="classroom.php" class="btn btn-secondary">Cancel</a>
                                <?php else: ?>
                                <button type="submit" name="create_classroom" class="btn btn-primary"><i class="fas fa-save"></i> Create Classroom</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-user-plus"></i> Add Student to Classroom</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Select Classroom</label>
                                    <select class="form-select" name="classroom_id" required>
                                        <option value="">Choose classroom...</option>
                                        <?php foreach($teacher_classrooms as $classroom): ?>
                                        <option value="<?php echo e($classroom['id']); ?>"><?php echo e($classroom['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Select Student</label>
                                    <select class="form-select" name="student_id" required>
                                        <option value="">Choose student...</option>
                                        <?php foreach($students as $student): ?>
                                        <option value="<?php echo e($student['id']); ?>">
                                            <?php echo e($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="add_student" class="btn btn-success">
                                    <i class="fas fa-user-plus"></i> Add Student
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Classrooms -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-chalkboard"></i> My Classrooms</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Grade Level</th>
                                            <th>Description</th>
                                            <th>Students</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($teacher_classrooms as $classroom): 
                                            // Count students in this classroom
                                            $student_count = $pdo->prepare("SELECT COUNT(*) FROM classroom_students WHERE classroom_id = ?");
                                            $student_count->execute([$classroom['id']]);
                                            $count = $student_count->fetchColumn();
                                        ?>
                                        <tr>
                                            <td><?php echo e($classroom['name']); ?></td>
                                            <td><?php echo e($classroom['grade_level_name'] ?? '—'); ?></td>
                                            <td><?php echo e($classroom['description']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo e($count); ?> students</span></td>
                                            <td>
                                                <a href="?view_students=true&classroom_id=<?php echo e($classroom['id']); ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    View Students
                                                </a>
                                                <a href="?edit=<?php echo e($classroom['id']); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <a href="?action=delete_classroom&id=<?php echo e($classroom['id']); ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to delete this classroom? This will also delete all grades associated with it.')">
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
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>