
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `classrooms` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `classrooms` (`id`, `name`, `description`, `teacher_id`, `created_at`) VALUES
(1, 'Grade 1  - Section 2', 'Grade 1 students in section 2 class', 2, '2025-09-30 04:00:46');

CREATE TABLE `classroom_students` (
  `id` int(11) NOT NULL,
  `classroom_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `classroom_students` (`id`, `classroom_id`, `student_id`, `enrolled_at`) VALUES
(1, 1, 3, '2025-09-30 04:02:27');

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `classroom_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `grade` decimal(5,2) NOT NULL,
  `grade_type` enum('quiz','assignment','exam','project') NOT NULL,
  `remarks` text DEFAULT NULL,
  `graded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `grades` (`id`, `student_id`, `subject_id`, `classroom_id`, `teacher_id`, `grade`, `grade_type`, `remarks`, `graded_at`) VALUES
(1, 3, 1, 1, 2, 90.00, 'quiz', '', '2025-09-30 04:02:49');

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `subjects` (`id`, `name`, `code`, `description`) VALUES
(1, 'English', 'ENG_01', 'English Subject');

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `first_name`, `last_name`, `created_at`) VALUES
(1, 'admin', '$2y$10$jLlEsVNynxNb/dJjQEeYAOTOdaD9zDSuLz3TjiWPvhZCMR7RLv/L6', 'admin@school.edu', 'admin', 'System', 'Administrator', '2025-09-30 03:54:39'),
(2, 'teacher', '$2y$10$3EAQ8Q2ljVW3gDoMNbH.ZuS4gEJWdWPydKg68l1KB/4WCHyfaCWQC', 'teacher@school.edu', 'teacher', 'English ', 'Teacher', '2025-09-30 03:59:27'),
(3, 'student', '$2y$10$V9WQneCPAUcPLLDnDqe23eCqtsuiMV9N7UXVriGzeWs.jWCM1KUYu', 'student@school.edu', 'student', 'Grade 1', 'Student', '2025-09-30 04:01:42');

ALTER TABLE `classrooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

ALTER TABLE `classroom_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classroom_id` (`classroom_id`),
  ADD KEY `student_id` (`student_id`);

ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `classroom_id` (`classroom_id`),
  ADD KEY `teacher_id` (`teacher_id`);

ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `classrooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `classroom_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `classrooms`
  ADD CONSTRAINT `classrooms_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`);

ALTER TABLE `classroom_students`
  ADD CONSTRAINT `classroom_students_ibfk_1` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`),
  ADD CONSTRAINT `classroom_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`),
  ADD CONSTRAINT `grades_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`);

CREATE TABLE `grade_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `classroom_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance` (`student_id`,`classroom_id`,`attendance_date`),
  KEY `classroom_id` (`classroom_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `attendance_fk_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  CONSTRAINT `attendance_fk_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`),
  CONSTRAINT `attendance_fk_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `classrooms`
  ADD COLUMN `grade_level_id` int(11) DEFAULT NULL AFTER `teacher_id`,
  ADD KEY `grade_level_id` (`grade_level_id`),
  ADD CONSTRAINT `classrooms_fk_grade_level` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`);

ALTER TABLE `grades`
  ADD COLUMN `term_id` int(11) DEFAULT NULL AFTER `classroom_id`,
  ADD KEY `term_id` (`term_id`),
  ADD CONSTRAINT `grades_fk_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`);

INSERT INTO `grade_levels` (`id`, `name`, `description`) VALUES
(1, 'Grade 1', 'First grade'),
(2, 'Grade 2', 'Second grade'),
(3, 'Grade 3', 'Third grade');

INSERT INTO `terms` (`id`, `name`, `start_date`, `end_date`, `is_active`) VALUES
(1, '1st Quarter', '2025-08-01', '2025-10-15', 1),
(2, '2nd Quarter', '2025-10-16', '2025-12-20', 0);

UPDATE `classrooms` SET `grade_level_id` = 1 WHERE `id` = 1;
UPDATE `grades` SET `term_id` = 1 WHERE `id` = 1;

INSERT INTO `attendance` (`student_id`, `classroom_id`, `teacher_id`, `attendance_date`, `status`, `remarks`) VALUES
(3, 1, 2, '2025-09-30', 'present', '');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
