-- ============================================================================
-- WORKFLOW SYSTEM MIGRATION
-- Implements new evaluation and moderation workflow
-- Date: November 12, 2025
-- ============================================================================

-- Step 1: Update submissions table with new status values
-- ============================================================================

-- Backup current status values
ALTER TABLE submissions 
ADD COLUMN status_backup VARCHAR(50) AFTER status;

UPDATE submissions SET status_backup = status;

-- Modify status enum to include new workflow states
ALTER TABLE submissions 
MODIFY COLUMN status ENUM(
    'Submitted',                    -- Student submitted, assigned to evaluator
    'Under Evaluation',             -- Evaluator is working on it
    'Evaluated (Pending Moderation)', -- Evaluator completed, waiting for moderator
    'Under Moderation',             -- Moderator is reviewing
    'Moderation Completed',         -- Moderator approved, ready to publish
    'Result Published',             -- Final results visible to student
    'Rejected',                     -- Rejected by moderator
    'Revision Required'             -- Sent back to evaluator for revision
) DEFAULT 'Submitted';

-- Migrate existing data to new status values
UPDATE submissions 
SET status = CASE 
    WHEN status_backup = 'pending' THEN 'Submitted'
    WHEN status_backup = 'under_review' THEN 'Under Evaluation'
    WHEN status_backup = 'evaluated' THEN 'Evaluated (Pending Moderation)'
    WHEN status_backup = 'approved' THEN 'Result Published'
    WHEN status_backup = 'rejected' THEN 'Rejected'
    ELSE 'Submitted'
END;

-- Add new columns for workflow control
ALTER TABLE submissions
ADD COLUMN annotated_pdf_visible_to_student BOOLEAN DEFAULT FALSE AFTER annotated_pdf_url,
ADD COLUMN results_visible_to_student BOOLEAN DEFAULT FALSE AFTER moderator_remarks,
ADD COLUMN evaluation_locked BOOLEAN DEFAULT FALSE AFTER evaluated_at,
ADD COLUMN moderation_locked BOOLEAN DEFAULT FALSE AFTER approved_at,
ADD COLUMN moderated_at TIMESTAMP NULL AFTER approved_at,
ADD COLUMN moderated_by INT NULL AFTER moderator_id,
ADD COLUMN moderation_notes TEXT NULL AFTER moderator_remarks,
ADD COLUMN result_published_at TIMESTAMP NULL AFTER moderated_at,
ADD COLUMN result_published_by INT NULL AFTER moderated_by;

-- Add foreign keys for new user references
ALTER TABLE submissions
ADD CONSTRAINT fk_moderated_by FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_result_published_by FOREIGN KEY (result_published_by) REFERENCES users(id) ON DELETE SET NULL;

-- Add indexes for performance
CREATE INDEX idx_submissions_results_visible ON submissions(results_visible_to_student);
CREATE INDEX idx_submissions_evaluation_locked ON submissions(evaluation_locked);
CREATE INDEX idx_submissions_moderation_locked ON submissions(moderation_locked);
CREATE INDEX idx_submissions_moderated_by ON submissions(moderated_by);

-- Step 2: Create workflow_logs table for audit trail
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
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Create workflow_notifications table
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
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Create workflow_settings table for configuration
-- ============================================================================

CREATE TABLE IF NOT EXISTS workflow_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default workflow settings
INSERT INTO workflow_settings (setting_key, setting_value, description) VALUES
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

-- Step 5: Create moderation_history table
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
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 6: Create evaluation_locks table
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
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (locked_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 7: Create stored procedures for workflow transitions
-- ============================================================================

DELIMITER $$

-- Procedure: Log workflow transition
CREATE PROCEDURE log_workflow_transition(
    IN p_submission_id INT,
    IN p_user_id INT,
    IN p_user_role VARCHAR(20),
    IN p_from_status VARCHAR(50),
    IN p_to_status VARCHAR(50),
    IN p_action VARCHAR(100),
    IN p_notes TEXT,
    IN p_ip_address VARCHAR(45)
)
BEGIN
    INSERT INTO workflow_logs (
        submission_id, user_id, user_role, from_status, to_status, 
        action, notes, ip_address
    ) VALUES (
        p_submission_id, p_user_id, p_user_role, p_from_status, p_to_status,
        p_action, p_notes, p_ip_address
    );
END$$

-- Procedure: Create workflow notification
CREATE PROCEDURE create_workflow_notification(
    IN p_submission_id INT,
    IN p_user_id INT,
    IN p_notification_type VARCHAR(50),
    IN p_title VARCHAR(255),
    IN p_message TEXT
)
BEGIN
    INSERT INTO workflow_notifications (
        submission_id, user_id, notification_type, title, message
    ) VALUES (
        p_submission_id, p_user_id, p_notification_type, p_title, p_message
    );
END$$

-- Procedure: Lock evaluation
CREATE PROCEDURE lock_evaluation(
    IN p_submission_id INT,
    IN p_locked_by VARCHAR(20),
    IN p_locked_by_user_id INT,
    IN p_reason VARCHAR(255)
)
BEGIN
    -- Update submission lock status
    UPDATE submissions 
    SET evaluation_locked = TRUE 
    WHERE id = p_submission_id;
    
    -- Create lock record
    INSERT INTO evaluation_locks (
        submission_id, locked_by, locked_by_user_id, reason
    ) VALUES (
        p_submission_id, p_locked_by, p_locked_by_user_id, p_reason
    ) ON DUPLICATE KEY UPDATE
        locked_by = p_locked_by,
        locked_at = CURRENT_TIMESTAMP,
        locked_by_user_id = p_locked_by_user_id,
        reason = p_reason;
END$$

-- Procedure: Check if evaluation is locked
CREATE FUNCTION is_evaluation_locked(p_submission_id INT)
RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE v_locked BOOLEAN;
    SELECT evaluation_locked INTO v_locked
    FROM submissions
    WHERE id = p_submission_id;
    RETURN IFNULL(v_locked, FALSE);
END$$

DELIMITER ;

-- Step 8: Create views for easier querying
-- ============================================================================

-- View: Submissions pending evaluation
CREATE OR REPLACE VIEW v_pending_evaluations AS
SELECT 
    s.*,
    st.name as student_name,
    st.email as student_email,
    st.roll_no,
    sub.name as subject_name,
    sub.code as subject_code,
    e.name as evaluator_name,
    e.email as evaluator_email,
    DATEDIFF(NOW(), s.created_at) as days_pending
FROM submissions s
LEFT JOIN users st ON s.student_id = st.id
LEFT JOIN subjects sub ON s.subject_id = sub.id
LEFT JOIN users e ON s.evaluator_id = e.id
WHERE s.status IN ('Submitted', 'Under Evaluation')
ORDER BY s.created_at ASC;

-- View: Submissions pending moderation
CREATE OR REPLACE VIEW v_pending_moderations AS
SELECT 
    s.*,
    st.name as student_name,
    st.email as student_email,
    e.name as evaluator_name,
    e.email as evaluator_email,
    m.name as moderator_name,
    m.email as moderator_email,
    sub.name as subject_name,
    sub.code as subject_code,
    DATEDIFF(NOW(), s.evaluated_at) as days_pending_moderation
FROM submissions s
LEFT JOIN users st ON s.student_id = st.id
LEFT JOIN users e ON s.evaluator_id = e.id
LEFT JOIN users m ON s.moderator_id = m.id
LEFT JOIN subjects sub ON s.subject_id = sub.id
WHERE s.status = 'Evaluated (Pending Moderation)'
ORDER BY s.evaluated_at ASC;

-- View: Published results
CREATE OR REPLACE VIEW v_published_results AS
SELECT 
    s.*,
    st.name as student_name,
    st.email as student_email,
    st.roll_no,
    e.name as evaluator_name,
    m.name as moderator_name,
    sub.name as subject_name,
    sub.code as subject_code,
    ROUND((s.marks_obtained / s.max_marks) * 100, 2) as percentage
FROM submissions s
LEFT JOIN users st ON s.student_id = st.id
LEFT JOIN users e ON s.evaluator_id = e.id
LEFT JOIN users m ON s.moderated_by = m.id
LEFT JOIN subjects sub ON s.subject_id = sub.id
WHERE s.status = 'Result Published'
AND s.results_visible_to_student = TRUE
ORDER BY s.result_published_at DESC;

-- Step 9: Create triggers for automatic workflow management
-- ============================================================================

DELIMITER $$

-- Trigger: Auto-lock evaluation when moderator starts review
CREATE TRIGGER trg_lock_on_moderation
BEFORE UPDATE ON submissions
FOR EACH ROW
BEGIN
    IF NEW.status = 'Under Moderation' AND OLD.status = 'Evaluated (Pending Moderation)' THEN
        SET NEW.evaluation_locked = TRUE;
        SET NEW.moderation_locked = FALSE;
    END IF;
END$$

-- Trigger: Auto-lock moderation when result is published
CREATE TRIGGER trg_lock_on_publish
BEFORE UPDATE ON submissions
FOR EACH ROW
BEGIN
    IF NEW.status = 'Result Published' AND OLD.status = 'Moderation Completed' THEN
        SET NEW.evaluation_locked = TRUE;
        SET NEW.moderation_locked = TRUE;
        SET NEW.result_published_at = CURRENT_TIMESTAMP;
    END IF;
END$$

-- Trigger: Auto-set visibility flags on result publication
CREATE TRIGGER trg_set_visibility_on_publish
BEFORE UPDATE ON submissions
FOR EACH ROW
BEGIN
    IF NEW.status = 'Result Published' AND OLD.status != 'Result Published' THEN
        SET NEW.results_visible_to_student = TRUE;
        -- Check workflow setting for annotated PDF visibility
        SET NEW.annotated_pdf_visible_to_student = (
            SELECT setting_value = '1' 
            FROM workflow_settings 
            WHERE setting_key = 'show_annotated_pdf_to_student'
        );
    END IF;
END$$

DELIMITER ;

-- Step 10: Data integrity checks
-- ============================================================================

-- Ensure all submissions have proper workflow status
UPDATE submissions 
SET status = 'Submitted' 
WHERE status IS NULL OR status = '';

-- Ensure visibility flags are set correctly
UPDATE submissions 
SET results_visible_to_student = FALSE 
WHERE status NOT IN ('Result Published', 'Moderation Completed');

UPDATE submissions 
SET annotated_pdf_visible_to_student = FALSE 
WHERE status NOT IN ('Result Published');

-- Step 11: Grant necessary permissions (if needed)
-- ============================================================================

-- Note: Adjust these based on your database user setup
-- GRANT SELECT, INSERT, UPDATE ON workflow_logs TO 'student_app_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON workflow_notifications TO 'student_app_user'@'localhost';
-- GRANT SELECT ON workflow_settings TO 'student_app_user'@'localhost';

-- Step 12: Verification queries
-- ============================================================================

-- Verify status migration
SELECT 
    status, 
    COUNT(*) as count,
    GROUP_CONCAT(DISTINCT status_backup) as original_statuses
FROM submissions
GROUP BY status;

-- Verify new columns exist
DESCRIBE submissions;

-- Verify new tables exist
SHOW TABLES LIKE 'workflow_%';
SHOW TABLES LIKE 'moderation_%';
SHOW TABLES LIKE 'evaluation_locks';

-- Verify views created
SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- Check workflow settings
SELECT * FROM workflow_settings;

-- ============================================================================
-- ROLLBACK SCRIPT (Use only if needed to revert changes)
-- ============================================================================

/*
-- Drop new tables
DROP TABLE IF EXISTS evaluation_locks;
DROP TABLE IF EXISTS moderation_history;
DROP TABLE IF EXISTS workflow_notifications;
DROP TABLE IF EXISTS workflow_logs;
DROP TABLE IF EXISTS workflow_settings;

-- Drop views
DROP VIEW IF EXISTS v_published_results;
DROP VIEW IF EXISTS v_pending_moderations;
DROP VIEW IF EXISTS v_pending_evaluations;

-- Drop triggers
DROP TRIGGER IF EXISTS trg_set_visibility_on_publish;
DROP TRIGGER IF EXISTS trg_lock_on_publish;
DROP TRIGGER IF EXISTS trg_lock_on_moderation;

-- Drop stored procedures and functions
DROP PROCEDURE IF EXISTS lock_evaluation;
DROP FUNCTION IF EXISTS is_evaluation_locked;
DROP PROCEDURE IF EXISTS create_workflow_notification;
DROP PROCEDURE IF EXISTS log_workflow_transition;

-- Restore original status
UPDATE submissions SET status = status_backup;
ALTER TABLE submissions DROP COLUMN status_backup;

-- Revert status enum to original
ALTER TABLE submissions 
MODIFY COLUMN status ENUM('pending', 'under_review', 'evaluated', 'rejected') DEFAULT 'pending';

-- Drop new columns
ALTER TABLE submissions 
DROP FOREIGN KEY fk_moderated_by,
DROP FOREIGN KEY fk_result_published_by,
DROP COLUMN annotated_pdf_visible_to_student,
DROP COLUMN results_visible_to_student,
DROP COLUMN evaluation_locked,
DROP COLUMN moderation_locked,
DROP COLUMN moderated_at,
DROP COLUMN moderated_by,
DROP COLUMN moderation_notes,
DROP COLUMN result_published_at,
DROP COLUMN result_published_by;
*/

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
