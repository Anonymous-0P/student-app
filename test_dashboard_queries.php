<?php
require_once('config/config.php');

// Test with Mod2 (ID: 38) who has evaluators assigned
$moderator_id = 38;

echo "Testing dashboard stats for Moderator ID: $moderator_id\n\n";

// Test subjects query
$subjects_query = "SELECT COUNT(DISTINCT ms.subject_id) as subject_count 
                   FROM moderator_subjects ms 
                   WHERE ms.moderator_id = ? AND ms.is_active = 1";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_assoc()['subject_count'];
echo "Subjects: $subjects\n";

// Test evaluators query
$evaluators_query = "SELECT COUNT(*) as evaluator_count 
                      FROM users 
                      WHERE moderator_id = ? AND role = 'evaluator' AND is_active = 1";
$stmt = $conn->prepare($evaluators_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$evaluators = $stmt->get_result()->fetch_assoc()['evaluator_count'];
echo "Evaluators: $evaluators\n\n";

// Test NEW submissions query (with evaluator join)
$submissions_query = "SELECT 
    COUNT(DISTINCT s.id) as total_submissions,
    SUM(CASE WHEN s.status = 'pending' OR s.status = 'Submitted' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN s.status = 'assigned' OR s.status = 'evaluating' OR s.status = 'Under Evaluation' THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN s.status = 'evaluated' OR s.evaluation_status = 'evaluated' THEN 1 ELSE 0 END) as evaluated,
    SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM submissions s
    LEFT JOIN users u ON s.evaluator_id = u.id
    WHERE u.moderator_id = ? OR s.moderator_id = ?";
$stmt = $conn->prepare($submissions_query);
$stmt->bind_param("ii", $moderator_id, $moderator_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

echo "=== SUBMISSION STATS ===\n";
echo "Total Submissions: {$stats['total_submissions']}\n";
echo "Pending: {$stats['pending']}\n";
echo "Under Review: {$stats['under_review']}\n";
echo "Evaluated: {$stats['evaluated']}\n";
echo "Approved: {$stats['approved']}\n\n";

// Test evaluator performance query
$evaluator_performance_query = "SELECT 
    u.id as evaluator_id,
    u.name as evaluator_name,
    u.email as evaluator_email,
    COUNT(DISTINCT s.id) as total_assignments,
    COUNT(DISTINCT CASE WHEN s.status = 'evaluated' OR s.evaluation_status = 'evaluated' THEN s.id END) as completed_assignments,
    COUNT(DISTINCT CASE WHEN s.status IN ('assigned', 'evaluating', 'Under Evaluation') THEN s.id END) as in_progress_assignments,
    COUNT(DISTINCT CASE WHEN s.status = 'pending' OR s.status = 'Submitted' THEN s.id END) as pending_assignments,
    AVG(CASE WHEN s.marks_obtained IS NOT NULL AND s.max_marks > 0 
        THEN (s.marks_obtained / s.max_marks) * 100 END) as avg_marks_percentage,
    COUNT(DISTINCT CASE WHEN (s.status = 'evaluated' OR s.evaluation_status = 'evaluated') 
        AND s.evaluated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        THEN s.id END) as evaluations_this_week
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluator_id
    WHERE u.role = 'evaluator' AND u.moderator_id = ? AND u.is_active = 1
    GROUP BY u.id, u.name, u.email
    ORDER BY completed_assignments DESC, u.name";

$stmt = $conn->prepare($evaluator_performance_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "=== EVALUATOR PERFORMANCE ===\n";
foreach($performance as $p) {
    echo "{$p['evaluator_name']}:\n";
    echo "  Total: {$p['total_assignments']}, Completed: {$p['completed_assignments']}, ";
    echo "In Progress: {$p['in_progress_assignments']}, Pending: {$p['pending_assignments']}\n";
    echo "  Avg Marks: " . number_format($p['avg_marks_percentage'], 1) . "%\n";
    echo "  This Week: {$p['evaluations_this_week']}\n\n";
}
?>
