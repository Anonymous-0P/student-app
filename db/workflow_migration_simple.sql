-- ============================================================================
-- WORKFLOW SYSTEM MIGRATION - SIMPLIFIED
-- Implements new evaluation and moderation workflow
-- Date: November 12, 2025
-- ============================================================================

-- Step 1: Backup current status values
-- ============================================================================
ALTER TABLE submissions 
ADD COLUMN IF NOT EXISTS status_backup VARCHAR(50) AFTER status;

UPDATE submissions SET status_backup = status WHERE status_backup IS NULL;

-- Step 2: Modify status enum to include new workflow states
-- ============================================================================
ALTER TABLE submissions 
MODIFY COLUMN status ENUM(
    'Submitted',
    'Under Evaluation',
    'Evaluated (Pending Moderation)',
    'Under Moderation',
    'Moderation Completed',
    'Result Published',
    'Rejected',
    'Revision Required',
    'pending',
    'under_review',
    'evaluated',
    'approved',
    'rejected'
) DEFAULT 'Submitted';

-- Step 3: Migrate existing data to new status values
-- ============================================================================
UPDATE submissions 
SET status = CASE 
    WHEN status IN ('pending', 'Submitted') THEN 'Submitted'
    WHEN status IN ('under_review', 'Under Evaluation') THEN 'Under Evaluation'
    WHEN status IN ('evaluated', 'Evaluated (Pending Moderation)') THEN 'Evaluated (Pending Moderation)'
    WHEN status IN ('approved', 'Result Published') THEN 'Result Published'
    WHEN status = 'Rejected' THEN 'Rejected'
    ELSE 'Submitted'
END;

-- Step 4: Add new columns for workflow control
-- ============================================================================
ALTER TABLE submissions
ADD COLUMN IF NOT EXISTS annotated_pdf_visible_to_student BOOLEAN DEFAULT FALSE AFTER annotated_pdf_url;

ALTER TABLE submissions
ADD COLUMN IF NOT EXISTS results_visible_to_student BOOLEAN DEFAULT FALSE AFTER moderator_remarks;

ALTER TABLE submissions
ADD COLUMN IF NOT EXISTS evaluation_locked BOOLEAN DEFAULT FALSE AFTER evaluated_at;

ALTER TABLE submissions
ADD COLUMN IF NOT EXISTS moderation_locked BOOLEAN DEFAULT FALSE AFTER approved_at;

ALTER TABLE submissions
ADD COLUMN IF NOT EXISTS moderated_at TIMESTAMP NULL AFTER approved_at;

ALTER TABLE submissions
ADD COLUMN IF NOT EXISTS moderated_by INT NULL AFTER moderator_id;

ALTER TABLE submissions
ADD COLUMN IF NOT EXISTS moderation_notes TEXT NULL AFTER moderator_remarks;

ALTER TABLE submissions
ADD COLUMN IF NOT EXISTS result_published_at TIMESTAMP NULL AFTER moderated_at;

ALTER TABLE submissions
ADD COLUMN IF NOT EXISTS result_published_by INT NULL AFTER moderated_by;

-- Step 5: Create indexes for performance
-- ============================================================================
CREATE INDEX IF NOT EXISTS idx_submissions_results_visible ON submissions(results_visible_to_student);
CREATE INDEX IF NOT EXISTS idx_submissions_evaluation_locked ON submissions(evaluation_locked);
CREATE INDEX IF NOT EXISTS idx_submissions_moderation_locked ON submissions(moderation_locked);
CREATE INDEX IF NOT EXISTS idx_submissions_moderated_by ON submissions(moderated_by);

-- Step 6: Create workflow_logs table
-- ============================================================================
CREATE TABLE IF NOT EXISTS workflow_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    user_id INT NOT NULL,
    user_role ENUM('student', 'evaluator', 'moderator', 'admin') NOT NULL,
    from_status VARCHAR(50) NULL,
    to_status VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    notes TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_workflow_logs_submission (submission_id),
    INDEX idx_workflow_logs_user (user_id),
    INDEX idx_workflow_logs_status (to_status),
    INDEX idx_workflow_logs_created (created_at),
    
    CONSTRAINT fk_workflow_logs_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_workflow_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 7: Create workflow_notifications table
-- ============================================================================
CREATE TABLE IF NOT EXISTS workflow_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    user_id INT NOT NULL,
    notification_type ENUM(
        'submission_received',
        'evaluation_completed',
        'moderation_completed',
        'result_published',
        'revision_required',
        'submission_rejected'
    ) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_emailed BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    
    INDEX idx_workflow_notifications_user (user_id),
    INDEX idx_workflow_notifications_submission (submission_id),
    INDEX idx_workflow_notifications_read (is_read),
    INDEX idx_workflow_notifications_type (notification_type),
    
    CONSTRAINT fk_workflow_notifications_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_workflow_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 8: Create workflow_settings table
-- ============================================================================
CREATE TABLE IF NOT EXISTS workflow_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    
    CONSTRAINT fk_workflow_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 9: Insert default workflow settings
-- ============================================================================
INSERT IGNORE INTO workflow_settings (setting_key, setting_value, description) VALUES
('auto_assign_evaluator', '1', 'Automatically assign submissions to subject evaluator'),
('evaluator_can_edit_after_submit', '0', 'Allow evaluator to edit after submission'),
('moderator_can_override_marks', '1', 'Allow moderator to override evaluator marks'),
('auto_notify_email', '1', 'Send email notifications on status changes'),
('auto_publish_after_moderation', '0', 'Automatically publish results after moderation'),
('show_annotated_pdf_to_student', '1', 'Show annotated PDF to student after result publication'),
('allow_student_resubmission', '0', 'Allow students to resubmit after rejection'),
('moderation_required', '1', 'Require moderator approval before publishing results'),
('max_revision_rounds', '2', 'Maximum number of revision rounds allowed'),
('evaluation_deadline_days', '7', 'Days evaluator has to complete evaluation');

-- Step 10: Create moderation_history table
-- ============================================================================
CREATE TABLE IF NOT EXISTS moderation_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    moderator_id INT NOT NULL,
    action ENUM('approved', 'rejected', 'marks_adjusted', 'revision_requested') NOT NULL,
    original_marks DECIMAL(5,2) NULL,
    adjusted_marks DECIMAL(5,2) NULL,
    adjustment_reason TEXT NULL,
    moderation_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_moderation_history_submission (submission_id),
    INDEX idx_moderation_history_moderator (moderator_id),
    INDEX idx_moderation_history_action (action),
    
    CONSTRAINT fk_moderation_history_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_moderation_history_moderator FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 11: Create evaluation_locks table
-- ============================================================================
CREATE TABLE IF NOT EXISTS evaluation_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNIQUE NOT NULL,
    locked_by ENUM('evaluator', 'moderator', 'admin') NOT NULL,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_by_user_id INT NOT NULL,
    reason VARCHAR(255) NULL,
    
    INDEX idx_evaluation_locks_submission (submission_id),
    INDEX idx_evaluation_locks_user (locked_by_user_id),
    
    CONSTRAINT fk_evaluation_locks_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_evaluation_locks_user FOREIGN KEY (locked_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 12: Add foreign keys for new user references in submissions
-- ============================================================================
-- Check if foreign keys exist before adding
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'submissions' 
    AND CONSTRAINT_NAME = 'fk_moderated_by');

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE submissions ADD CONSTRAINT fk_moderated_by FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "Foreign key fk_moderated_by already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'submissions' 
    AND CONSTRAINT_NAME = 'fk_result_published_by');

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE submissions ADD CONSTRAINT fk_result_published_by FOREIGN KEY (result_published_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "Foreign key fk_result_published_by already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 13: Set initial visibility flags
-- ============================================================================
UPDATE submissions 
SET results_visible_to_student = FALSE 
WHERE status NOT IN ('Result Published', 'Moderation Completed');

UPDATE submissions 
SET annotated_pdf_visible_to_student = FALSE 
WHERE status NOT IN ('Result Published');

UPDATE submissions 
SET results_visible_to_student = TRUE 
WHERE status = 'Result Published';

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
