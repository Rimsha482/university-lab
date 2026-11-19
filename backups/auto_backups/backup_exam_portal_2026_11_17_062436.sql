-- ==========================================================
-- Smart University Proctoring Portal - Database Backup
-- Generated: 2026-11-17 06:24:36
-- Host: localhost | Database: exam_portal_db
-- ==========================================================

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_code` varchar(30) NOT NULL,
  `course_name` varchar(150) NOT NULL,
  `department` varchar(100) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_code` (`course_code`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `courses` VALUES
('1', 'CS-401', 'Web Engineering & Cloud Architecture', 'Computer Science & IT', '2', 'Full-stack application development, secure web protocols, PHP/MySQL, RESTful APIs, and cloud microservices.', '2026-11-17 10:12:14'),
('2', 'CS-301', 'Database Management Systems', 'Computer Science & IT', '2', 'Relational models, SQL optimization, ACID transactions, normalization, and indexing techniques.', '2026-11-17 10:12:14'),
('3', 'CS-201', 'Data Structures & Algorithms', 'Computer Science & IT', '3', 'Linear & non-linear data structures, asymptotic complexity, sorting/searching algorithms, and dynamic programming.', '2026-11-17 10:12:14');

DROP TABLE IF EXISTS `exam_attempts`;
CREATE TABLE `exam_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `lab_pc_id` int(11) DEFAULT NULL,
  `start_time` datetime DEFAULT current_timestamp(),
  `end_time` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `total_score` decimal(6,2) DEFAULT 0.00,
  `status` enum('in_progress','completed','disqualified','timed_out') NOT NULL DEFAULT 'in_progress',
  `violation_count` int(11) NOT NULL DEFAULT 0,
  `is_auto_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`),
  KEY `lab_pc_id` (`lab_pc_id`),
  CONSTRAINT `exam_attempts_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_attempts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_attempts_ibfk_3` FOREIGN KEY (`lab_pc_id`) REFERENCES `lab_pcs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `exam_attempts` VALUES
('1', '2', '5', '3', '2026-11-14 10:12:14', '2026-11-14 10:30:14', '2026-11-14 10:30:14', '10.00', 'completed', '0', '0', '192.168.1.103'),
('2', '1', '4', NULL, '2026-11-17 10:24:11', '2026-11-17 10:24:28', '2026-11-17 10:24:28', '2.00', 'completed', '1', '0', '::1');

DROP TABLE IF EXISTS `exams`;
CREATE TABLE `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `exam_code` varchar(30) NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 45,
  `total_marks` decimal(6,2) NOT NULL DEFAULT 50.00,
  `passing_marks` decimal(6,2) NOT NULL DEFAULT 20.00,
  `max_violations` int(11) NOT NULL DEFAULT 3,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('draft','published','ongoing','completed') NOT NULL DEFAULT 'draft',
  `shuffle_questions` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `exam_code` (`exam_code`),
  KEY `course_id` (`course_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exams_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `exams` VALUES
('1', '1', '2', 'CS-401 Midterm Examination 2026', 'Strictly proctored exam. Fullscreen lock and tab monitoring are enabled. Any violation is logged instantly to the proctoring dashboard.', 'EXAM-WEB-2026', '30', '20.00', '10.00', '3', '2026-11-17 09:12:14', '2026-11-24 10:12:14', 'published', '1', '2026-11-17 10:12:14'),
('2', '2', '2', 'CS-301 Quiz 1: SQL & Schema Normalization', 'First comprehensive quiz covering relational algebra, joins, and normal forms (1NF to BCNF).', 'QUIZ-SQL-2026', '20', '10.00', '5.00', '3', '2026-11-12 10:12:14', '2026-11-15 10:12:14', 'completed', '0', '2026-11-17 10:12:14'),
('3', '3', '3', 'CS-201 Final Practical Assessment', 'Covers binary search trees, graph traversals, and algorithmic complexity.', 'EXAM-DSA-2026', '60', '40.00', '20.00', '2', '2026-11-19 10:12:14', '2026-11-22 10:12:14', 'published', '1', '2026-11-17 10:12:14');

DROP TABLE IF EXISTS `lab_pcs`;
CREATE TABLE `lab_pcs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pc_name` varchar(50) NOT NULL,
  `lab_room` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `mac_address` varchar(50) DEFAULT NULL,
  `current_student_id` int(11) DEFAULT NULL,
  `current_exam_id` int(11) DEFAULT NULL,
  `last_heartbeat` datetime DEFAULT NULL,
  `status` enum('online','offline','in_exam','locked') NOT NULL DEFAULT 'offline',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `pc_name` (`pc_name`),
  KEY `current_student_id` (`current_student_id`),
  KEY `current_exam_id` (`current_exam_id`),
  CONSTRAINT `lab_pcs_ibfk_1` FOREIGN KEY (`current_student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_pcs_ibfk_2` FOREIGN KEY (`current_exam_id`) REFERENCES `exams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lab_pcs` VALUES
('1', 'LAB1-PC01', 'Lab 101 - Systems Lab', '192.168.1.101', '00:1A:2B:3C:4D:01', NULL, NULL, '2026-11-17 10:12:14', 'online', '2026-11-17 10:12:14'),
('2', 'LAB1-PC02', 'Lab 101 - Systems Lab', '192.168.1.102', '00:1A:2B:3C:4D:02', '4', '1', '2026-11-17 10:12:14', 'in_exam', '2026-11-17 10:12:14'),
('3', 'LAB1-PC03', 'Lab 101 - Systems Lab', '192.168.1.103', '00:1A:2B:3C:4D:03', '5', '1', '2026-11-17 10:12:14', 'in_exam', '2026-11-17 10:12:14'),
('4', 'LAB1-PC04', 'Lab 101 - Systems Lab', '192.168.1.104', '00:1A:2B:3C:4D:04', NULL, NULL, '2026-11-17 10:07:14', 'offline', '2026-11-17 10:12:14'),
('5', 'LAB1-PC05', 'Lab 101 - Systems Lab', '192.168.1.105', '00:1A:2B:3C:4D:05', NULL, NULL, '2026-11-17 10:12:14', 'online', '2026-11-17 10:12:14'),
('6', 'LAB1-PC06', 'Lab 101 - Systems Lab', '192.168.1.106', '00:1A:2B:3C:4D:06', NULL, NULL, '2026-11-17 10:12:14', 'online', '2026-11-17 10:12:14'),
('7', 'LAB1-PC07', 'Lab 101 - Systems Lab', '192.168.1.107', '00:1A:2B:3C:4D:07', '6', '1', '2026-11-17 10:12:14', 'in_exam', '2026-11-17 10:12:14'),
('8', 'LAB1-PC08', 'Lab 101 - Systems Lab', '192.168.1.108', '00:1A:2B:3C:4D:08', NULL, NULL, '2026-11-17 09:57:14', 'offline', '2026-11-17 10:12:14'),
('9', 'LAB1-PC09', 'Lab 101 - Systems Lab', '192.168.1.109', '00:1A:2B:3C:4D:09', NULL, NULL, '2026-11-17 10:12:14', 'online', '2026-11-17 10:12:14'),
('10', 'LAB1-PC10', 'Lab 101 - Systems Lab', '192.168.1.110', '00:1A:2B:3C:4D:10', NULL, NULL, '2026-11-17 10:12:14', 'online', '2026-11-17 10:12:14'),
('11', 'LAB2-PC01', 'Lab 102 - Network Lab', '192.168.2.101', '00:1A:2B:3C:5E:01', NULL, NULL, '2026-11-17 10:12:14', 'online', '2026-11-17 10:12:14'),
('12', 'LAB2-PC02', 'Lab 102 - Network Lab', '192.168.2.102', '00:1A:2B:3C:5E:02', NULL, NULL, '2026-11-17 10:12:14', 'online', '2026-11-17 10:12:14'),
('13', 'LAB2-PC03', 'Lab 102 - Network Lab', '192.168.2.103', '00:1A:2B:3C:5E:03', NULL, NULL, '2026-11-17 09:27:14', 'offline', '2026-11-17 10:12:14'),
('14', 'LAB2-PC04', 'Lab 102 - Network Lab', '192.168.2.104', '00:1A:2B:3C:5E:04', NULL, NULL, '2026-11-17 10:12:14', 'online', '2026-11-17 10:12:14'),
('15', 'LAB2-PC05', 'Lab 102 - Network Lab', '192.168.2.105', '00:1A:2B:3C:5E:05', NULL, NULL, '2026-11-17 10:12:14', 'online', '2026-11-17 10:12:14');

DROP TABLE IF EXISTS `proctor_logs`;
CREATE TABLE `proctor_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `violation_type` varchar(50) NOT NULL,
  `violation_message` varchar(255) NOT NULL,
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `timestamp` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `attempt_id` (`attempt_id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `proctor_logs_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proctor_logs_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proctor_logs_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `proctor_logs` VALUES
('1', '2', '1', '4', 'tab_switch', 'Tab switch detected', 'high', '2026-11-17 10:24:28');

DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('mcq','coding','short') NOT NULL DEFAULT 'mcq',
  `option_a` text DEFAULT NULL,
  `option_b` text DEFAULT NULL,
  `option_c` text DEFAULT NULL,
  `option_d` text DEFAULT NULL,
  `correct_option` varchar(20) NOT NULL,
  `marks` decimal(5,2) NOT NULL DEFAULT 2.00,
  `question_image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `questions` VALUES
('1', '1', 'Which HTTP method is specifically defined as idempotent and primarily used to request representations of a specified resource?', 'mcq', 'POST', 'GET', 'PATCH', 'CONNECT', 'B', '2.00', NULL, '2026-11-17 10:12:14'),
('2', '1', 'In PHP and MySQL web applications, which technique is the primary defense against SQL Injection vulnerabilities?', 'mcq', 'HTML entity escaping with htmlspecialchars()', 'Prepared Statements with Parameterized Queries (PDO/MySQLi)', 'MD5 hashing input data', 'Client-side JavaScript form validation', 'B', '2.00', NULL, '2026-11-17 10:12:14'),
('3', '1', 'Which HTTP status code corresponds to \"401 Unauthorized\" according to RFC standards?', 'mcq', 'Resource does not exist', 'The request lacks valid authentication credentials for the target resource', 'The server understood the request but refuses to authorize it', 'The payload sent exceeded server capacity', 'B', '2.00', NULL, '2026-11-17 10:12:14'),
('4', '1', 'What is the primary role of the JavaScript Event Loop in modern web browsers?', 'mcq', 'To compile TypeScript into native machine code', 'To execute synchronous rendering cycles in multi-threaded hardware', 'To continuously monitor the Call Stack and Task/Microtask Queues for asynchronous callback execution', 'To encrypt WebSocket handshakes', 'C', '2.00', NULL, '2026-11-17 10:12:14'),
('5', '1', 'Which security flag prevents a cookie from being accessed by client-side JavaScript via document.cookie?', 'mcq', 'Secure', 'SameSite=Strict', 'HttpOnly', 'Domain-Locked', 'C', '2.00', NULL, '2026-11-17 10:12:14'),
('6', '1', 'In relational database design, a relation is in Third Normal Form (3NF) if it is in 2NF and:', 'mcq', 'It has composite primary keys on all tables', 'Every non-prime attribute is non-transitively dependent on the candidate key', 'All multivalued dependencies are decomposed', 'Every column has a NOT NULL constraint', 'B', '2.00', NULL, '2026-11-17 10:12:14'),
('7', '1', 'Which header is recommended by modern web standards to mitigate Cross-Site Scripting (XSS) and data injection attacks?', 'mcq', 'Content-Security-Policy (CSP)', 'X-Powered-By', 'Access-Control-Allow-Origin: *', 'Cache-Control: private', 'A', '2.00', NULL, '2026-11-17 10:12:14'),
('8', '1', 'In PHP, what is the default session garbage collection storage mechanism on standard Apache installations?', 'mcq', 'In-memory Redis cache', 'Temporary server files on local disk (session.save_path)', 'Client browser local storage', 'PostgreSQL database table', 'B', '2.00', NULL, '2026-11-17 10:12:14'),
('9', '1', 'What does the acronym REST stand for in web API architecture?', 'mcq', 'Remote Enterprise Server Technology', 'Representational State Transfer', 'Responsive Encrypted Socket Tunnel', 'Recursive Entity System Table', 'B', '2.00', NULL, '2026-11-17 10:12:14'),
('10', '1', 'Write the output or purpose of the PHP function `password_verify($password, $hash)`:', 'mcq', 'Returns MD5 checksum of given string', 'Verifies that a password matches a bcrypt or argon2 hash securely without timing leaks', 'Decrypts an AES encrypted password', 'Resets user credentials in MySQL', 'B', '2.00', NULL, '2026-11-17 10:12:14');

DROP TABLE IF EXISTS `student_answers`;
CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` varchar(20) DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  `marks_awarded` decimal(5,2) DEFAULT 0.00,
  `is_correct` tinyint(1) DEFAULT 0,
  `answered_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_attempt_question` (`attempt_id`,`question_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `student_answers` VALUES
('1', '2', '1', 'B', NULL, '2.00', '1', '2026-11-17 10:24:28');

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_logs` VALUES
('1', '1', 'SYSTEM_INIT', 'Portal database schema initialized with default lab configurations', '127.0.0.1', 'System Console', '2026-11-17 10:12:14'),
('2', '1', 'USER_LOGIN', 'Admin logged into control dashboard', '127.0.0.1', 'Mozilla/5.0 Lab Incharge Console', '2026-11-17 10:12:14'),
('3', '2', 'EXAM_CREATED', 'Prof. Alexander Vance published CS-401 Midterm Examination 2026', '192.168.1.10', 'Mozilla/5.0 Teacher Station', '2026-11-17 10:12:14'),
('4', '4', 'USER_LOGIN_SUCCESS', 'User student logged in successfully as student', '::1', 'curl/8.13.0', '2026-11-17 10:23:29'),
('5', '1', 'USER_LOGIN_SUCCESS', 'User admin logged in successfully as admin', '::1', 'curl/8.13.0', '2026-11-17 10:23:42'),
('6', '2', 'USER_LOGIN_SUCCESS', 'User teacher logged in successfully as teacher', '::1', 'curl/8.13.0', '2026-11-17 10:23:55'),
('7', '4', 'USER_LOGIN_SUCCESS', 'User student logged in successfully as student', '::1', 'curl/8.13.0', '2026-11-17 10:24:11'),
('8', '4', 'EXAM_STARTED', 'Candidate started exam \'CS-401 Midterm Examination 2026\' on attempt #2', '::1', 'curl/8.13.0', '2026-11-17 10:24:11'),
('9', '4', 'EXAM_SUBMITTED', 'Student John Doe finished exam #1 with score 2/20.00 (Status: completed)', '::1', 'curl/8.13.0', '2026-11-17 10:24:28'),
('10', '1', 'USER_LOGIN_SUCCESS', 'User admin logged in successfully as admin', '::1', 'curl/8.13.0', '2026-11-17 10:24:36');

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` VALUES
('1', 'institute_name', 'University Institute of Science & Technology', 'Official institute header title', '2026-11-17 10:12:14'),
('2', 'portal_title', 'Smart Exam & Lab Proctoring Portal', 'Portal branding name', '2026-11-17 10:12:14'),
('3', 'max_tab_switches', '3', 'Default maximum tab switches before disqualification', '2026-11-17 10:12:14'),
('4', 'allow_student_registration', '1', '1 = Enabled, 0 = Disabled', '2026-11-17 10:12:14'),
('5', 'heartbeat_interval_sec', '10', 'Student workstation ping frequency in seconds', '2026-11-17 10:12:14'),
('6', 'session_timeout_min', '120', 'Inactivity timeout in minutes', '2026-11-17 10:12:14');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `roll_number` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default_avatar.png',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` VALUES
('1', 'admin', 'admin@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'System Administrator', 'admin', NULL, '+1-555-0100', 'default_avatar.png', 'active', '2026-11-17 10:12:14'),
('2', 'teacher', 'teacher@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Prof. Alexander Vance', 'teacher', NULL, '+1-555-0101', 'default_avatar.png', 'active', '2026-11-17 10:12:14'),
('3', 'dr.sarah', 'sarah@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Dr. Sarah Jenkins', 'teacher', NULL, '+1-555-0102', 'default_avatar.png', 'active', '2026-11-17 10:12:14'),
('4', 'student', 'student@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'John Doe', 'student', 'CS-2024-001', '+1-555-0150', 'default_avatar.png', 'active', '2026-11-17 10:12:14'),
('5', 'jane.smith', 'jane@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Jane Smith', 'student', 'CS-2024-002', '+1-555-0151', 'default_avatar.png', 'active', '2026-11-17 10:12:14'),
('6', 'ahmed.k', 'ahmed@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Ahmed Khan', 'student', 'CS-2024-003', '+1-555-0152', 'default_avatar.png', 'active', '2026-11-17 10:12:14'),
('7', 'emily.w', 'emily@university.edu', '$2y$10$2wPvffnVUh8Gih9a55Jr6OU5QmNqEDaqy9z3DCpuOjB1i9pYJYqtG', 'Emily Watson', 'student', 'CS-2024-004', '+1-555-0153', 'default_avatar.png', 'active', '2026-11-17 10:12:14');

SET FOREIGN_KEY_CHECKS=1;
