-- Create database
CREATE DATABASE IF NOT EXISTS student_photo_app;
USE student_photo_app;

-- Users table (extended with student profile fields and user management)
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
  FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Submissions table
CREATE TABLE IF NOT EXISTS submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  pdf_url VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Subjects master
CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  name VARCHAR(150) NOT NULL,
  department VARCHAR(100) NULL,
  year INT NULL,
  semester INT NULL,
  is_active TINYINT(1) DEFAULT 1
);

-- Question templates (per subject rules)
CREATE TABLE IF NOT EXISTS question_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  easy_count INT DEFAULT 0,
  medium_count INT DEFAULT 0,
  hard_count INT DEFAULT 0,
  total_marks INT DEFAULT 0,
  duration_minutes INT DEFAULT 60,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- Questions bank
CREATE TABLE IF NOT EXISTS questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  question_text TEXT NOT NULL,
  difficulty ENUM('easy','medium','hard') NOT NULL,
  marks INT DEFAULT 1,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- Seed Subjects (sample)
INSERT INTO subjects (code, name, department, year, semester, is_active) VALUES
  ('CS201', 'Data Structures', 'Computer Science', 2, 3, 1),
  ('CS202', 'Database Systems', 'Computer Science', 2, 3, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), department=VALUES(department), year=VALUES(year), semester=VALUES(semester), is_active=VALUES(is_active);

-- Seed Template for Data Structures
INSERT INTO question_templates (subject_id, easy_count, medium_count, hard_count, total_marks, duration_minutes, is_active)
SELECT s.id, 2, 2, 1, 50, 60, 1 FROM subjects s WHERE s.code='CS201'
ON DUPLICATE KEY UPDATE easy_count=VALUES(easy_count), medium_count=VALUES(medium_count), hard_count=VALUES(hard_count), total_marks=VALUES(total_marks), duration_minutes=VALUES(duration_minutes), is_active=VALUES(is_active);

-- Seed Questions for Data Structures
INSERT INTO questions (subject_id, question_text, difficulty, marks, is_active)
SELECT s.id, 'Explain the concept of a stack with a real-world example.', 'easy', 5, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'What is the difference between array and linked list?', 'easy', 5, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'Describe how a binary search works and its time complexity.', 'medium', 10, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'Explain the working of Quick Sort and analyze its best, average, and worst-case complexities.', 'medium', 10, 1 FROM subjects s WHERE s.code='CS201'
UNION ALL SELECT s.id, 'Design a data structure to implement an LRU cache and explain operations with complexities.', 'hard', 20, 1 FROM subjects s WHERE s.code='CS201';

-- Moderator Subjects Assignment table
CREATE TABLE IF NOT EXISTS moderator_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_moderator_subject (moderator_id, subject_id)
);

-- Moderator Specializations table
CREATE TABLE IF NOT EXISTS moderator_specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Evaluator Subjects Assignment table
CREATE TABLE IF NOT EXISTS evaluator_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluator_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evaluator_subject (evaluator_id, subject_id)
);
