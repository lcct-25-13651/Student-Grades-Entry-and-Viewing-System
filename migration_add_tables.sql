USE `student_grade_management`;

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
