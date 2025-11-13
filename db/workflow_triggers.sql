-- ============================================================================
-- WORKFLOW TRIGGERS
-- Automatic workflow management triggers
-- ============================================================================

DELIMITER $$

-- Trigger: Auto-lock evaluation when moderator starts review
DROP TRIGGER IF EXISTS trg_lock_on_moderation$$
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
DROP TRIGGER IF EXISTS trg_lock_on_publish$$
CREATE TRIGGER trg_lock_on_publish
BEFORE UPDATE ON submissions
FOR EACH ROW
BEGIN
    IF NEW.status = 'Result Published' AND OLD.status = 'Moderation Completed' THEN
        SET NEW.evaluation_locked = TRUE;
        SET NEW.moderation_locked = TRUE;
        IF NEW.result_published_at IS NULL THEN
            SET NEW.result_published_at = CURRENT_TIMESTAMP;
        END IF;
    END IF;
END$$

-- Trigger: Auto-set visibility flags on result publication
DROP TRIGGER IF EXISTS trg_set_visibility_on_publish$$
CREATE TRIGGER trg_set_visibility_on_publish
BEFORE UPDATE ON submissions
FOR EACH ROW
BEGIN
    IF NEW.status = 'Result Published' AND OLD.status != 'Result Published' THEN
        SET NEW.results_visible_to_student = TRUE;
        -- Set annotated PDF visibility based on workflow setting (default TRUE)
        SET NEW.annotated_pdf_visible_to_student = TRUE;
    END IF;
END$$

DELIMITER ;
