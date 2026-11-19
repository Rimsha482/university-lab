-- ==========================================================
-- Smart University / Institute Lab & Exam Proctoring Portal
-- Database Schema & Initial Data
-- Compatible with MySQL 5.7+ / MariaDB 10.4+ / XAMPP
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `exam_portal_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `exam_portal_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `system_logs`;
DROP TABLE IF EXISTS `proctor_logs`;
DROP TABLE IF EXISTS `student_answers`;
DROP TABLE IF EXISTS `exam_attempts`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `exams`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `lab_pcs`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------
-- 1. Table structure for `users`
-- ----------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
  `roll_number` VARCHAR(50) NULL,
  `phone` VARCHAR(20) NULL,
  `avatar` VARCHAR(255) DEFAULT 'default_avatar.png',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Table structure for `courses`
-- ----------------------------------------------------------
CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_code` VARCHAR(30) NOT NULL UNIQUE,
  `course_name` VARCHAR(150) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `teacher_id` INT NULL,
  `description` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Table structure for `exams`
-- ----------------------------------------------------------
CREATE TABLE `exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `exam_code` VARCHAR(30) NOT NULL UNIQUE,
  `duration_minutes` INT NOT NULL DEFAULT 45,
  `total_marks` DECIMAL(6,2) NOT NULL DEFAULT 50.00,
  `passing_marks` DECIMAL(6,2) NOT NULL DEFAULT 20.00,
  `max_violations` INT NOT NULL DEFAULT 3,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `status` ENUM('draft', 'published', 'ongoing', 'completed') NOT NULL DEFAULT 'draft',
  `shuffle_questions` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Table structure for `questions`
-- ----------------------------------------------------------
CREATE TABLE `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `question_text` TEXT NOT NULL,
  `question_type` ENUM('mcq', 'coding', 'short') NOT NULL DEFAULT 'mcq',
  `option_a` TEXT NULL,
  `option_b` TEXT NULL,
  `option_c` TEXT NULL,
  `option_d` TEXT NULL,
  `correct_option` VARCHAR(20) NOT NULL,
  `marks` DECIMAL(5,2) NOT NULL DEFAULT 2.00,
  `question_image` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. Table structure for `lab_pcs`
-- ----------------------------------------------------------
CREATE TABLE `lab_pcs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pc_name` VARCHAR(50) NOT NULL UNIQUE,
  `lab_room` VARCHAR(50) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `mac_address` VARCHAR(50) NULL,
  `current_student_id` INT NULL,
  `current_exam_id` INT NULL,
  `last_heartbeat` DATETIME NULL,
  `status` ENUM('online', 'offline', 'in_exam', 'locked') NOT NULL DEFAULT 'offline',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`current_student_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`current_exam_id`) REFERENCES `exams`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. Table structure for `exam_attempts`
-- ----------------------------------------------------------
CREATE TABLE `exam_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `lab_pc_id` INT NULL,
  `start_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `end_time` DATETIME NULL,
  `submitted_at` DATETIME NULL,
  `total_score` DECIMAL(6,2) DEFAULT 0.00,
  `status` ENUM('in_progress', 'completed', 'disqualified', 'timed_out') NOT NULL DEFAULT 'in_progress',
  `violation_count` INT NOT NULL DEFAULT 0,
  `is_auto_submitted` TINYINT(1) NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) NULL,
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lab_pc_id`) REFERENCES `lab_pcs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. Table structure for `student_answers`
-- ----------------------------------------------------------
CREATE TABLE `student_answers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `attempt_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `selected_option` VARCHAR(20) NULL,
  `answer_text` TEXT NULL,
  `marks_awarded` DECIMAL(5,2) DEFAULT 0.00,
  `is_correct` TINYINT(1) DEFAULT 0,
  `answered_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_attempt_question` (`attempt_id`, `question_id`),
  FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. Table structure for `proctor_logs`
-- ----------------------------------------------------------
CREATE TABLE `proctor_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `attempt_id` INT NOT NULL,
  `exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `violation_type` VARCHAR(50) NOT NULL,
  `violation_message` VARCHAR(255) NOT NULL,
  `severity` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 9. Table structure for `system_logs`
-- ----------------------------------------------------------
CREATE TABLE `system_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 10. Table structure for `system_settings`
-- ----------------------------------------------------------
CREATE TABLE `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `description` VARCHAR(255) NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- SEED DATA
-- Password for all accounts: Password@123
-- Hash: $2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG
-- ==========================================================

-- System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('institute_name', 'University Institute of Science & Technology', 'Official institute header title'),
('portal_title', 'Smart Exam & Lab Proctoring Portal', 'Portal branding name'),
('max_tab_switches', '3', 'Default maximum tab switches before disqualification'),
('allow_student_registration', '1', '1 = Enabled, 0 = Disabled'),
('heartbeat_interval_sec', '10', 'Student workstation ping frequency in seconds'),
('session_timeout_min', '120', 'Inactivity timeout in minutes');

-- Users
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `roll_number`, `phone`, `status`) VALUES
(1, 'admin', 'admin@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'System Administrator', 'admin', NULL, '+1-555-0100', 'active'),
(2, 'teacher', 'teacher@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Prof. Alexander Vance', 'teacher', NULL, '+1-555-0101', 'active'),
(3, 'dr.sarah', 'sarah@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Dr. Sarah Jenkins', 'teacher', NULL, '+1-555-0102', 'active'),
(4, 'student', 'student@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'John Doe', 'student', 'CS-2024-001', '+1-555-0150', 'active'),
(5, 'jane.smith', 'jane@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Jane Smith', 'student', 'CS-2024-002', '+1-555-0151', 'active'),
(6, 'ahmed.k', 'ahmed@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Ahmed Khan', 'student', 'CS-2024-003', '+1-555-0152', 'active'),
(7, 'emily.w', 'emily@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Emily Watson', 'student', 'CS-2024-004', '+1-555-0153', 'active');

-- Courses
INSERT INTO `courses` (`id`, `course_code`, `course_name`, `department`, `teacher_id`, `description`) VALUES
(1, 'CS-401', 'Web Engineering & Cloud Architecture', 'Computer Science & IT', 2, 'Full-stack application development, secure web protocols, PHP/MySQL, RESTful APIs, and cloud microservices.'),
(2, 'CS-301', 'Database Management Systems', 'Computer Science & IT', 2, 'Relational models, SQL optimization, ACID transactions, normalization, and indexing techniques.'),
(3, 'CS-201', 'Data Structures & Algorithms', 'Computer Science & IT', 3, 'Linear & non-linear data structures, asymptotic complexity, sorting/searching algorithms, and dynamic programming.');

-- Exams
INSERT INTO `exams` (`id`, `course_id`, `teacher_id`, `title`, `description`, `exam_code`, `duration_minutes`, `total_marks`, `passing_marks`, `max_violations`, `start_time`, `end_time`, `status`, `shuffle_questions`) VALUES
(1, 1, 2, 'CS-401 Midterm Examination 2026', 'Strictly proctored exam. Fullscreen lock and tab monitoring are enabled. Any violation is logged instantly to the proctoring dashboard.', 'EXAM-WEB-2026', 30, 20.00, 10.00, 3, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 7 DAY), 'published', 1),
(2, 2, 2, 'CS-301 Quiz 1: SQL & Schema Normalization', 'First comprehensive quiz covering relational algebra, joins, and normal forms (1NF to BCNF).', 'QUIZ-SQL-2026', 20, 10.00, 5.00, 3, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 'completed', 0),
(3, 3, 3, 'CS-201 Final Practical Assessment', 'Covers binary search trees, graph traversals, and algorithmic complexity.', 'EXAM-DSA-2026', 60, 40.00, 20.00, 2, DATE_ADD(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY), 'published', 1);

-- Questions for Exam 1 (CS-401 Midterm)
INSERT INTO `questions` (`id`, `exam_id`, `question_text`, `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `marks`) VALUES
(1, 1, 'Which HTTP method is specifically defined as idempotent and primarily used to request representations of a specified resource?', 'mcq', 'POST', 'GET', 'PATCH', 'CONNECT', 'B', 2.00),
(2, 1, 'In PHP and MySQL web applications, which technique is the primary defense against SQL Injection vulnerabilities?', 'mcq', 'HTML entity escaping with htmlspecialchars()', 'Prepared Statements with Parameterized Queries (PDO/MySQLi)', 'MD5 hashing input data', 'Client-side JavaScript form validation', 'B', 2.00),
(3, 1, 'Which HTTP status code corresponds to "401 Unauthorized" according to RFC standards?', 'mcq', 'Resource does not exist', 'The request lacks valid authentication credentials for the target resource', 'The server understood the request but refuses to authorize it', 'The payload sent exceeded server capacity', 'B', 2.00),
(4, 1, 'What is the primary role of the JavaScript Event Loop in modern web browsers?', 'mcq', 'To compile TypeScript into native machine code', 'To execute synchronous rendering cycles in multi-threaded hardware', 'To continuously monitor the Call Stack and Task/Microtask Queues for asynchronous callback execution', 'To encrypt WebSocket handshakes', 'C', 2.00),
(5, 1, 'Which security flag prevents a cookie from being accessed by client-side JavaScript via document.cookie?', 'mcq', 'Secure', 'SameSite=Strict', 'HttpOnly', 'Domain-Locked', 'C', 2.00),
(6, 1, 'In relational database design, a relation is in Third Normal Form (3NF) if it is in 2NF and:', 'mcq', 'It has composite primary keys on all tables', 'Every non-prime attribute is non-transitively dependent on the candidate key', 'All multivalued dependencies are decomposed', 'Every column has a NOT NULL constraint', 'B', 2.00),
(7, 1, 'Which header is recommended by modern web standards to mitigate Cross-Site Scripting (XSS) and data injection attacks?', 'mcq', 'Content-Security-Policy (CSP)', 'X-Powered-By', 'Access-Control-Allow-Origin: *', 'Cache-Control: private', 'A', 2.00),
(8, 1, 'In PHP, what is the default session garbage collection storage mechanism on standard Apache installations?', 'mcq', 'In-memory Redis cache', 'Temporary server files on local disk (session.save_path)', 'Client browser local storage', 'PostgreSQL database table', 'B', 2.00),
(9, 1, 'What does the acronym REST stand for in web API architecture?', 'mcq', 'Remote Enterprise Server Technology', 'Representational State Transfer', 'Responsive Encrypted Socket Tunnel', 'Recursive Entity System Table', 'B', 2.00),
(10, 1, 'Write the output or purpose of the PHP function `password_verify($password, $hash)`:', 'mcq', 'Returns MD5 checksum of given string', 'Verifies that a password matches a bcrypt or argon2 hash securely without timing leaks', 'Decrypts an AES encrypted password', 'Resets user credentials in MySQL', 'B', 2.00);

-- Lab PCs (20 lab workstations in Lab 101 & 102)
INSERT INTO `lab_pcs` (`id`, `pc_name`, `lab_room`, `ip_address`, `mac_address`, `current_student_id`, `current_exam_id`, `last_heartbeat`, `status`) VALUES
(1, 'LAB1-PC01', 'Lab 101 - Systems Lab', '192.168.1.101', '00:1A:2B:3C:4D:01', NULL, NULL, NOW(), 'online'),
(2, 'LAB1-PC02', 'Lab 101 - Systems Lab', '192.168.1.102', '00:1A:2B:3C:4D:02', 4, 1, NOW(), 'in_exam'),
(3, 'LAB1-PC03', 'Lab 101 - Systems Lab', '192.168.1.103', '00:1A:2B:3C:4D:03', 5, 1, NOW(), 'in_exam'),
(4, 'LAB1-PC04', 'Lab 101 - Systems Lab', '192.168.1.104', '00:1A:2B:3C:4D:04', NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 MINUTE), 'offline'),
(5, 'LAB1-PC05', 'Lab 101 - Systems Lab', '192.168.1.105', '00:1A:2B:3C:4D:05', NULL, NULL, NOW(), 'online'),
(6, 'LAB1-PC06', 'Lab 101 - Systems Lab', '192.168.1.106', '00:1A:2B:3C:4D:06', NULL, NULL, NOW(), 'online'),
(7, 'LAB1-PC07', 'Lab 101 - Systems Lab', '192.168.1.107', '00:1A:2B:3C:4D:07', 6, 1, NOW(), 'in_exam'),
(8, 'LAB1-PC08', 'Lab 101 - Systems Lab', '192.168.1.108', '00:1A:2B:3C:4D:08', NULL, NULL, DATE_SUB(NOW(), INTERVAL 15 MINUTE), 'offline'),
(9, 'LAB1-PC09', 'Lab 101 - Systems Lab', '192.168.1.109', '00:1A:2B:3C:4D:09', NULL, NULL, NOW(), 'online'),
(10, 'LAB1-PC10', 'Lab 101 - Systems Lab', '192.168.1.110', '00:1A:2B:3C:4D:10', NULL, NULL, NOW(), 'online'),
(11, 'LAB2-PC01', 'Lab 102 - Network Lab', '192.168.2.101', '00:1A:2B:3C:5E:01', NULL, NULL, NOW(), 'online'),
(12, 'LAB2-PC02', 'Lab 102 - Network Lab', '192.168.2.102', '00:1A:2B:3C:5E:02', NULL, NULL, NOW(), 'online'),
(13, 'LAB2-PC03', 'Lab 102 - Network Lab', '192.168.2.103', '00:1A:2B:3C:5E:03', NULL, NULL, DATE_SUB(NOW(), INTERVAL 45 MINUTE), 'offline'),
(14, 'LAB2-PC04', 'Lab 102 - Network Lab', '192.168.2.104', '00:1A:2B:3C:5E:04', NULL, NULL, NOW(), 'online'),
(15, 'LAB2-PC05', 'Lab 102 - Network Lab', '192.168.2.105', '00:1A:2B:3C:5E:05', NULL, NULL, NOW(), 'online');

-- Sample Attempt for Completed Exam (Jane Smith - CS-301 Quiz 1)
INSERT INTO `exam_attempts` (`id`, `exam_id`, `student_id`, `lab_pc_id`, `start_time`, `end_time`, `submitted_at`, `total_score`, `status`, `violation_count`, `is_auto_submitted`, `ip_address`) VALUES
(1, 2, 5, 3, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 18 MINUTE, DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 18 MINUTE, 10.00, 'completed', 0, 0, '192.168.1.103');

-- Sample System Logs
INSERT INTO `system_logs` (`user_id`, `action`, `details`, `ip_address`, `user_agent`) VALUES
(1, 'SYSTEM_INIT', 'Portal database schema initialized with default lab configurations', '127.0.0.1', 'System Console'),
(1, 'USER_LOGIN', 'Admin logged into control dashboard', '127.0.0.1', 'Mozilla/5.0 Lab Incharge Console'),
(2, 'EXAM_CREATED', 'Prof. Alexander Vance published CS-401 Midterm Examination 2026', '192.168.1.10', 'Mozilla/5.0 Teacher Station');
