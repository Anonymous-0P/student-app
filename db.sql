-- ============================================================================
-- STUDENT EXAM SYSTEM - COMPLETE DATABASE SCHEMA FOR PRODUCTION DEPLOYMENT
-- Version: 1.0
-- Created: November 2025
-- ============================================================================

-- Create database
CREATE DATABASE IF NOT EXISTS student_photo_app;
USE student_photo_app;

-- Disable foreign key checks temporarily for clean installation
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- CORE SYSTEM TABLES
-- ============================================================================

-- Users table (students, faculty, moderators, evaluators, admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    roll_no VARCHAR(50) UNIQUE NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','faculty','moderator','evaluator','admin') NOT NULL,
    course VARCHAR(100) NULL,
    year INT NULL,
    department VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    is_active TINYINT(1) DEFAULT 1,
    moderator_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active),
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subjects master table
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    department VARCHAR(100) NULL,
    year INT NULL,
    semester INT NULL,
    price DECIMAL(10,2) DEFAULT 100.00,
    duration_days INT DEFAULT 365,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subjects_code (code),
    INDEX idx_subjects_department (department),
    INDEX idx_subjects_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PURCHASE SYSTEM TABLES
-- ============================================================================

-- Shopping cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    price DECIMAL(10,2) DEFAULT 100.00,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cart_student (student_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (student_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchased subjects table
CREATE TABLE IF NOT EXISTS purchased_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    price_paid DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    payment_id VARCHAR(100) NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE NULL,
    INDEX idx_purchased_subjects_student (student_id),
    INDEX idx_purchased_subjects_status (status),
    INDEX idx_purchased_subjects_expiry (expiry_date),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_purchased_subject (student_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment transactions table
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payment_id VARCHAR(100) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('dummy_gateway', 'credit_card', 'debit_card', 'paypal') DEFAULT 'dummy_gateway',
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_transactions_student (student_id),
    INDEX idx_payment_transactions_status (payment_status),
    INDEX idx_payment_transactions_date (created_at),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- QUESTION PAPERS SYSTEM
-- ============================================================================

-- Question papers table
CREATE TABLE IF NOT EXISTS question_papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT DEFAULT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    grade_level ENUM('10th', '11th', '12th') NOT NULL,
    exam_type ENUM('unit_test', 'mid_term', 'final', 'practice', 'assignment') DEFAULT 'practice',
    marks INT DEFAULT 100,
    duration_minutes INT DEFAULT 180,
    instructions TEXT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_question_papers_subject (subject_id),
    INDEX idx_question_papers_grade (grade_level),
    INDEX idx_question_papers_active (is_active),
    INDEX idx_question_papers_exam_type (exam_type),
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question paper downloads tracking
CREATE TABLE IF NOT EXISTS question_paper_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_paper_id INT NOT NULL,
    student_id INT NOT NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    INDEX idx_qp_downloads_paper (question_paper_id),
    INDEX idx_qp_downloads_student (student_id),
    INDEX idx_qp_downloads_date (downloaded_at),
    FOREIGN KEY (question_paper_id) REFERENCES question_papers(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SUBMISSION AND EVALUATION SYSTEM
-- ============================================================================

-- Submissions table (answer sheets uploaded by students)
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NULL,
    pdf_url VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NULL,
    file_size INT NULL,
    status ENUM('pending', 'under_review', 'evaluated', 'rejected') DEFAULT 'pending',
    evaluation_status ENUM('not_assigned', 'assigned', 'under_review', 'evaluated', 'revision_needed') DEFAULT 'not_assigned',
    marks_obtained DECIMAL(5,2) DEFAULT NULL,
    max_marks DECIMAL(5,2) DEFAULT 100.00,
    evaluator_id INT NULL,
    evaluator_remarks TEXT NULL,
    evaluated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_submissions_student (student_id),
    INDEX idx_submissions_subject (subject_id),
    INDEX idx_submissions_status (status),
    INDEX idx_submissions_evaluation_status (evaluation_status),
    INDEX idx_submissions_evaluator (evaluator_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluator ratings table (student feedback on evaluators)
CREATE TABLE IF NOT EXISTS evaluator_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    evaluator_id INT NOT NULL,
    submission_id INT DEFAULT NULL,
    overall_rating TINYINT(1) NOT NULL CHECK (overall_rating BETWEEN 1 AND 5),
    evaluation_quality ENUM('excellent', 'good', 'average', 'poor') NOT NULL,
    feedback_helpfulness ENUM('very_helpful', 'helpful', 'somewhat_helpful', 'not_helpful') NOT NULL,
    comments TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_evaluator_ratings_evaluator (evaluator_id),
    INDEX idx_evaluator_ratings_student (student_id),
    INDEX idx_evaluator_ratings_submission (submission_id),
    INDEX idx_evaluator_ratings_overall (overall_rating),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_evaluator_submission (student_id, evaluator_id, submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- NOTIFICATIONS SYSTEM
-- ============================================================================

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('evaluation_request','evaluation_assigned','evaluation_completed','system','general') NOT NULL DEFAULT 'general',
    reference_id INT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_read (is_read),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ASSIGNMENT AND MANAGEMENT TABLES
-- ============================================================================

-- Moderator subjects assignment
CREATE TABLE IF NOT EXISTS moderator_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_moderator_subjects_moderator (moderator_id),
    INDEX idx_moderator_subjects_subject (subject_id),
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_moderator_subject (moderator_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluator subjects assignment
CREATE TABLE IF NOT EXISTS evaluator_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluator_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_evaluator_subjects_evaluator (evaluator_id),
    INDEX idx_evaluator_subjects_subject (subject_id),
    FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evaluator_subject (evaluator_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- QUESTION BANK SYSTEM (Optional for future use)
-- ============================================================================

-- Question templates
CREATE TABLE IF NOT EXISTS question_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    easy_count INT DEFAULT 0,
    medium_count INT DEFAULT 0,
    hard_count INT DEFAULT 0,
    total_marks INT DEFAULT 0,
    duration_minutes INT DEFAULT 60,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_question_templates_subject (subject_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions bank
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    question_text TEXT NOT NULL,
    difficulty ENUM('easy','medium','hard') NOT NULL,
    marks INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_questions_subject (subject_id),
    INDEX idx_questions_difficulty (difficulty),
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- INITIAL SAMPLE DATA FOR SYSTEM SETUP
-- ============================================================================

-- Create admin user (password: admin123)
INSERT INTO users (name, email, password, role, is_active) VALUES
('System Administrator', 'admin@thetaexams.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role);

-- Sample subjects with pricing
INSERT INTO subjects (code, name, description, department, year, semester, price, duration_days, is_active) VALUES
('CS101', 'Introduction to Programming', 'Learn the fundamentals of programming using C++ and Python. Perfect for beginners with hands-on projects and assignments.', 'Computer Science', 1, 1, 79.99, 365, 1),
('CS201', 'Data Structures', 'Comprehensive course covering fundamental data structures including arrays, linked lists, stacks, queues, trees, and graphs with practical implementations.', 'Computer Science', 2, 3, 99.99, 365, 1),
('CS202', 'Database Systems', 'Complete database systems course covering SQL, database design, normalization, and database management concepts with real-world projects.', 'Computer Science', 2, 3, 89.99, 365, 1),
('CS301', 'Operating Systems', 'Understand the core concepts of operating systems including process management, memory management, file systems, and system security.', 'Computer Science', 3, 5, 119.99, 365, 1),
('CS302', 'Computer Networks', 'Comprehensive networking course covering protocols, network architecture, security, and network programming with lab exercises.', 'Computer Science', 3, 5, 109.99, 365, 1),
('MATH101', 'Calculus I', 'Introduction to differential and integral calculus with applications in engineering and computer science.', 'Mathematics', 1, 1, 69.99, 365, 1),
('MATH201', 'Linear Algebra', 'Vector spaces, matrices, eigenvalues, and linear transformations with applications in data science and machine learning.', 'Mathematics', 2, 3, 89.99, 365, 1),
('PHY101', 'Physics I', 'Fundamental physics concepts including mechanics, waves, thermodynamics, and their applications in technology.', 'Physics', 1, 1, 94.99, 365, 1),
('ENG101', 'Technical Writing', 'Essential technical writing skills for engineers and computer scientists including documentation and communication.', 'English', 1, 1, 59.99, 365, 1),
('CHEM101', 'General Chemistry', 'Basic chemistry principles including atomic structure, chemical bonding, and reactions with laboratory work.', 'Chemistry', 1, 1, 84.99, 365, 1)
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    price = VALUES(price),
    duration_days = VALUES(duration_days);

-- Sample question templates for core subjects
INSERT INTO question_templates (subject_id, easy_count, medium_count, hard_count, total_marks, duration_minutes, is_active)
SELECT s.id, 3, 4, 3, 100, 120, 1 FROM subjects s WHERE s.code IN ('CS201', 'CS202', 'CS301')
ON DUPLICATE KEY UPDATE 
    easy_count = VALUES(easy_count), 
    medium_count = VALUES(medium_count), 
    hard_count = VALUES(hard_count), 
    total_marks = VALUES(total_marks);

-- Sample questions for Data Structures (CS201)
INSERT INTO questions (subject_id, question_text, difficulty, marks, is_active)
SELECT s.id, 'Explain the concept of a stack with a real-world example and list its basic operations.', 'easy', 10, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'What is the difference between array and linked list? Provide advantages and disadvantages.', 'easy', 10, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'Compare linear search and binary search algorithms with time complexity analysis.', 'easy', 10, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'Describe how a binary search tree works and implement insertion operation.', 'medium', 15, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'Explain the working of Quick Sort algorithm with example and complexity analysis.', 'medium', 15, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'Implement a queue using two stacks and analyze the time complexity of operations.', 'medium', 15, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'Design and implement an AVL tree with rotation operations for balancing.', 'hard', 25, 1 FROM subjects s WHERE s.code='CS201'
ON DUPLICATE KEY UPDATE 
    question_text = VALUES(question_text),
    difficulty = VALUES(difficulty),
    marks = VALUES(marks);

-- Create sample student for testing (password: student123)
INSERT INTO users (name, email, roll_no, password, role, course, year, department, is_active) VALUES
('Test Student', 'student@test.com', 'STU001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Computer Science', 2, 'Computer Science', 1)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    course = VALUES(course),
    year = VALUES(year),
    department = VALUES(department);

-- Create sample evaluator (password: evaluator123)
INSERT INTO users (name, email, password, role, department, is_active) VALUES
('Test Evaluator', 'evaluator@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'evaluator', 'Computer Science', 1)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    department = VALUES(department);

-- Create sample moderator (password: moderator123)
INSERT INTO users (name, email, password, role, department, is_active) VALUES
('Test Moderator', 'moderator@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator', 'Computer Science', 1)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    department = VALUES(department);

-- Assign evaluator to subjects
INSERT INTO evaluator_subjects (evaluator_id, subject_id)
SELECT u.id, s.id 
FROM users u, subjects s 
WHERE u.email = 'evaluator@test.com' 
AND s.code IN ('CS201', 'CS202', 'CS301')
ON DUPLICATE KEY UPDATE assigned_at = CURRENT_TIMESTAMP;

-- Assign moderator to subjects
INSERT INTO moderator_subjects (moderator_id, subject_id)
SELECT u.id, s.id 
FROM users u, subjects s 
WHERE u.email = 'moderator@test.com' 
AND s.code IN ('CS201', 'CS202', 'CS301')
ON DUPLICATE KEY UPDATE assigned_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- SYSTEM CONFIGURATION AND FINAL SETUP
-- ============================================================================

-- Update admin password securely (Change this in production!)
-- Default password for all test accounts: "password123"
-- Admin email: admin@thetaexams.com
-- Student email: student@test.com  
-- Evaluator email: evaluator@test.com
-- Moderator email: moderator@test.com

-- Insert welcome notification for admin
INSERT INTO notifications (user_id, title, message, type, created_at) 
SELECT 
    u.id,
    'Welcome to ThetaExams System',
    'Welcome to the ThetaExams student evaluation system. The database has been successfully configured with sample data. Please review the system settings and create additional users as needed.',
    'system',
    NOW()
FROM users u 
WHERE u.email = 'admin@thetaexams.com'
ON DUPLICATE KEY UPDATE message = VALUES(message);

-- ============================================================================
-- DATABASE VIEWS FOR REPORTING (Optional)
-- ============================================================================

-- View for student performance summary
CREATE OR REPLACE VIEW student_performance_summary AS
SELECT 
    u.id as student_id,
    u.name as student_name,
    u.email as student_email,
    u.roll_no,
    COUNT(s.id) as total_submissions,
    COUNT(CASE WHEN s.evaluation_status = 'evaluated' THEN 1 END) as evaluated_submissions,
    AVG(CASE WHEN s.marks_obtained IS NOT NULL THEN (s.marks_obtained/s.max_marks)*100 END) as average_percentage,
    MAX(CASE WHEN s.marks_obtained IS NOT NULL THEN (s.marks_obtained/s.max_marks)*100 END) as best_percentage,
    COUNT(ps.id) as purchased_subjects_count
FROM users u
LEFT JOIN submissions s ON u.id = s.student_id
LEFT JOIN purchased_subjects ps ON u.id = ps.student_id AND ps.status = 'active'
WHERE u.role = 'student'
GROUP BY u.id, u.name, u.email, u.roll_no;

-- View for evaluator workload
CREATE OR REPLACE VIEW evaluator_workload AS
SELECT 
    u.id as evaluator_id,
    u.name as evaluator_name,
    u.email as evaluator_email,
    COUNT(s.id) as total_assigned,
    COUNT(CASE WHEN s.evaluation_status = 'evaluated' THEN 1 END) as completed_evaluations,
    COUNT(CASE WHEN s.evaluation_status IN ('assigned', 'under_review') THEN 1 END) as pending_evaluations,
    AVG(er.overall_rating) as average_rating
FROM users u
LEFT JOIN submissions s ON u.id = s.evaluator_id
LEFT JOIN evaluator_ratings er ON u.id = er.evaluator_id
WHERE u.role = 'evaluator'
GROUP BY u.id, u.name, u.email;

-- ============================================================================
-- SYSTEM INFORMATION
-- ============================================================================

-- Display system setup completion message
SELECT 
    'ThetaExams Database Setup Complete!' AS message,
    'Version 1.0' AS version,
    NOW() AS setup_time,
    (SELECT COUNT(*) FROM subjects WHERE is_active = 1) AS active_subjects,
    (SELECT COUNT(*) FROM users WHERE role = 'student') AS total_students,
    (SELECT COUNT(*) FROM users WHERE role = 'evaluator') AS total_evaluators,
    'Check users table for login credentials' AS note;

-- ============================================================================
-- END OF DATABASE SETUP
-- ============================================================================

-- IMPORTANT NOTES FOR PRODUCTION DEPLOYMENT:
-- 1. Change all default passwords before going live
-- 2. Update email addresses to real ones
-- 3. Configure proper SSL certificates for HTTPS
-- 4. Set up regular database backups
-- 5. Review and adjust file upload limits
-- 6. Configure email server for notifications
-- 7. Set up proper firewall rules
-- 8. Enable database query logging for monitoring
-- 9. Create additional admin users and remove test accounts
-- 10. Update system configuration in config/config.php