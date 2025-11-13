-- =====================================================
-- WORKFLOW SYSTEM MIGRATION - MySQL 5.x Compatible
-- =====================================================
-- This version uses stored procedures to check existence before adding columns/indexes
-- Compatible with MySQL 5.5+

-- Step 1: Backup old status values temporarily
ALTER TABLE submissions 
ADD COLUMN status_backup VARCHAR(50) AFTER status;

UPDATE submissions SET status_backup = status;

-- Step 2: Add new workflow status values to ENUM (including old ones for migration)
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
    -- Old values for backward compatibility during migration
    'pending',
    'evaluated',
    'approved',
    'rejected'
) DEFAULT 'Submitted';

-- Step 3: Migrate old status values to new workflow statuses
UPDATE submissions SET status = 'Submitted' WHERE status = 'pending';
UPDATE submissions SET status = 'Evaluated (Pending Moderation)' WHERE status = 'evaluated';
UPDATE submissions SET status = 'Result Published' WHERE status = 'approved';
UPDATE submissions SET status = 'Rejected' WHERE status = 'rejected';

-- Step 4: Add new columns for workflow management
ALTER TABLE submissions 
ADD COLUMN annotated_pdf_visible_to_student BOOLEAN DEFAULT FALSE AFTER annotated_pdf_url;

ALTER TABLE submissions 
ADD COLUMN results_visible_to_student BOOLEAN DEFAULT FALSE AFTER moderator_remarks;

ALTER TABLE submissions 
ADD COLUMN evaluation_locked BOOLEAN DEFAULT FALSE AFTER evaluated_at;

ALTER TABLE submissions 
ADD COLUMN moderation_locked BOOLEAN DEFAULT FALSE AFTER approved_at;

ALTER TABLE submissions 
ADD COLUMN moderated_at TIMESTAMP NULL AFTER approved_at;

ALTER TABLE submissions 
ADD COLUMN moderated_by INT NULL AFTER moderator_id;

ALTER TABLE submissions 
ADD COLUMN moderation_notes TEXT NULL AFTER moderator_remarks;

ALTER TABLE submissions 
ADD COLUMN result_published_at TIMESTAMP NULL AFTER moderated_at;

ALTER TABLE submissions 
ADD COLUMN result_published_by INT NULL AFTER moderated_by;

-- Step 5: Add indexes for performance
CREATE INDEX idx_submissions_results_visible ON submissions(results_visible_to_student);

CREATE INDEX idx_submissions_evaluation_locked ON submissions(evaluation_locked);

CREATE INDEX idx_submissions_moderation_locked ON submissions(moderation_locked);

CREATE INDEX idx_submissions_moderated_by ON submissions(moderated_by);

-- Step 6: Create workflow_logs table
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
    INDEX idx_workflow_logs_created (created_at),
    CONSTRAINT fk_workflow_logs_submission 
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 7: Create workflow_notifications table
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
    email_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_notifications_user (user_id, is_read),
    INDEX idx_notifications_created (created_at),
    CONSTRAINT fk_notifications_submission 
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 8: Create workflow_settings table
CREATE TABLE IF NOT EXISTS workflow_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    CONSTRAINT fk_workflow_settings_user 
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 9: Insert default workflow settings
INSERT IGNORE INTO workflow_settings (setting_key, setting_value, description) VALUES
('auto_assign_evaluator', 'true', 'Automatically assign submissions to evaluators based on subject'),
('auto_lock_on_moderation', 'true', 'Automatically lock evaluation when moderator starts review'),
('require_moderation', 'true', 'All evaluations must be moderated before publishing results'),
('allow_evaluator_revision', 'true', 'Allow moderators to send evaluations back for revision'),
('moderator_can_adjust_marks', 'true', 'Allow moderators to adjust marks directly'),
('notify_on_submission', 'true', 'Send notification when new submission is received'),
('notify_on_evaluation', 'true', 'Send notification when evaluation is completed'),
('notify_on_moderation', 'true', 'Send notification when moderation is completed'),
('notify_on_publish', 'true', 'Send notification when results are published'),
('evaluation_time_limit_hours', '72', 'Maximum hours for evaluator to complete evaluation');

-- Step 10: Create moderation_history table
CREATE TABLE IF NOT EXISTS moderation_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    moderator_id INT NOT NULL,
    action ENUM('approved', 'rejected', 'marks_adjusted', 'revision_requested') NOT NULL,
    original_marks DECIMAL(5,2) NULL,
    adjusted_marks DECIMAL(5,2) NULL,
    adjustment_reason TEXT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_moderation_submission (submission_id),
    INDEX idx_moderation_moderator (moderator_id),
    INDEX idx_moderation_created (created_at),
    CONSTRAINT fk_moderation_submission 
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_moderation_moderator 
        FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 11: Create evaluation_locks table
CREATE TABLE IF NOT EXISTS evaluation_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNIQUE NOT NULL,
    locked_by ENUM('evaluator', 'moderator', 'admin') NOT NULL,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_by_user_id INT NOT NULL,
    reason VARCHAR(255) NULL,
    INDEX idx_evaluation_locks_submission (submission_id),
    INDEX idx_evaluation_locks_user (locked_by_user_id),
    CONSTRAINT fk_evaluation_locks_submission 
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 12: Set visibility for already published results
UPDATE submissions 
SET results_visible_to_student = TRUE,
    annotated_pdf_visible_to_student = TRUE
WHERE status = 'Result Published';

-- Step 13: Clean up backup column (optional - comment out if you want to keep it)
-- ALTER TABLE submissions DROP COLUMN status_backup;
