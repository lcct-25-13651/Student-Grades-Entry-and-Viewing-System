<?php
// Shared page layout for the newer pages (grade_levels, terms, attendance)

function layoutStart($title, $navClass, $links) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> - Student Grade Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark <?php echo e($navClass); ?>">
        <div class="container">
            <a class="navbar-brand" href="<?php echo e(getDashboardUrl()); ?>">
                <i class="fas fa-graduation-cap"></i> Grade Management
            </a>
            <div class="navbar-nav ms-auto flex-row">
                <span class="navbar-text me-3">Welcome, <?php echo e($_SESSION['first_name']); ?></span>
                <?php foreach ($links as $label => $href): ?>
                    <a class="nav-link me-3" href="<?php echo e($href); ?>"><?php echo e($label); ?></a>
                <?php endforeach; ?>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
    <?php
}

// Sidebar layout (same look as the admin / teacher dashboards)
function layoutStartSidebar($title, $role, $active) {
    $GLOBALS['layout_sidebar'] = true;
    if ($role === 'admin') {
        $gradient = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        $icon = 'fa-graduation-cap';
        $heading = 'Grade Management';
        $sub = 'Administrator Panel';
        $items = [
            ['dashboard',    'admin.php#dashboard',  'fa-tachometer-alt', 'Dashboard'],
            ['users',        'admin.php#users',      'fa-users',          'Manage Users'],
            ['subjects',     'admin.php#subjects',   'fa-book',           'Subjects'],
            ['classrooms',   'admin.php#classrooms', 'fa-chalkboard',     'Classrooms'],
            ['grade_levels', 'grade_levels.php',     'fa-layer-group',    'Grade Levels'],
            ['terms',        'terms.php',            'fa-calendar-alt',   'Terms'],
        ];
    } else {
        $gradient = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
        $icon = 'fa-chalkboard-teacher';
        $heading = 'Teacher Portal';
        $sub = 'Grade Management';
        $items = [
            ['dashboard',  'teacher.php#dashboard', 'fa-tachometer-alt',    'Dashboard'],
            ['students',   'teacher.php#students',  'fa-user-graduate',     'My Students'],
            ['grades',     'grades.php',            'fa-edit',              'Manage Grades'],
            ['attendance', 'attendance.php',        'fa-clipboard-check',   'Attendance'],
            ['classrooms', 'classroom.php',         'fa-chalkboard',        'My Classrooms'],
        ];
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> - Student Grade Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: <?php echo $gradient; ?>; color: white; min-height: 100vh; padding: 0; }
        .sidebar .nav-link { color: white; padding: 1rem 1.5rem; border-left: 4px solid transparent; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); border-left-color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="d-flex flex-column p-3">
                    <div class="text-center mb-4">
                        <i class="fas <?php echo e($icon); ?> fa-2x mb-2"></i>
                        <h5><?php echo e($heading); ?></h5>
                        <small><?php echo e($sub); ?></small>
                    </div>
                    <ul class="nav nav-pills flex-column">
                        <?php foreach ($items as $it): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($it[0] === $active) ? 'active' : ''; ?>" href="<?php echo e($it[1]); ?>">
                                <i class="fas <?php echo e($it[2]); ?> me-2"></i><?php echo e($it[3]); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-md-9 col-lg-10 ms-sm-auto px-4 py-4">
    <?php
}

function flashMessage($message, $type) {
    if ($message): ?>
        <div class="alert alert-<?php echo e($type); ?> alert-dismissible fade show" role="alert">
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif;
}

function layoutEnd() {
    $sidebar = !empty($GLOBALS['layout_sidebar']);
    ?>
    <?php echo $sidebar ? '</div></div></div>' : '</div>'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}
