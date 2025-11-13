-- Migration: Add evaluation schemes table
-- Run this to enable evaluation scheme upload and management

CREATE TABLE IF NOT EXISTS evaluation_schemes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    
    INDEX idx_schemes_subject (subject_id),
    INDEX idx_schemes_active (is_active),
    INDEX idx_schemes_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign keys separately (only if tables exist)
-- Run these manually if subjects/users tables are missing
-- ALTER TABLE evaluation_schemes ADD CONSTRAINT fk_schemes_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE;
-- ALTER TABLE evaluation_schemes ADD CONSTRAINT fk_schemes_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE;
