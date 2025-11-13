-- ============================================================================
-- WORKFLOW VIEWS
-- Create database views for easier querying
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
