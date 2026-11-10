# Student Grades Entry and Viewing System

PHP + MySQL (PDO) record management system with three roles: admin, teacher, student.

## Setup (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\student-grades-management-system`.
2. Start Apache and MySQL in XAMPP.
3. In phpMyAdmin, create a database named `student_grade_management`, then import `student_grade_management.sql` (fresh install, includes all 8 tables).
   - Already have the old database? Run `migration_add_tables.sql` instead (adds the 3 new tables and 2 foreign keys).
4. Open http://localhost/student-grades-management-system/

Demo logins (from the sample data): `admin`, `teacher`, `student` (use the passwords you set when you created them).

## Database (8 tables, all InnoDB with PK + FK)
| Table | Purpose | Foreign keys |
|---|---|---|
| users | admin / teacher / student accounts | - |
| subjects | subjects | - |
| grade_levels | Grade 1, Grade 2, ... | - |
| terms | quarters / terms | - |
| classrooms | classrooms | teacher_id -> users, grade_level_id -> grade_levels |
| classroom_students | enrollment (many-to-many) | classroom_id -> classrooms, student_id -> users |
| grades | grade records | student_id, teacher_id -> users; subject_id -> subjects; classroom_id -> classrooms; term_id -> terms |
| attendance | daily attendance | student_id, teacher_id -> users; classroom_id -> classrooms |

## CRUD map
| Record | Create | Read / Search | Update | Delete (confirm) |
|---|---|---|---|---|
| Users | admin.php | admin.php (search + role filter) | admin.php | admin.php |
| Subjects | admin.php | admin.php | admin.php | admin.php |
| Grade levels | grade_levels.php | grade_levels.php (search) | grade_levels.php | grade_levels.php |
| Terms | terms.php | terms.php (search + status) | terms.php | terms.php |
| Classrooms | classroom.php | classroom.php | classroom.php | classroom.php |
| Enrollment | classroom.php | classroom.php | - | classroom.php |
| Grades | grades.php | grades.php (name, subject, term, type) | grades.php | grades.php |
| Attendance | attendance.php | attendance.php (name, classroom, status, dates) | attendance.php | attendance.php |

## Security
- Every query that takes user input uses PDO prepared statements.
- All output is escaped with `htmlspecialchars()` through the `e()` helper in `includes/functions.php`.
- Passwords are hashed with `password_hash()`; the session id is regenerated on login.
- Server-side validation with success / error messages on every form.
