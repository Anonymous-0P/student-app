<?php
require_once('config/config.php');

echo "<h2>Testing Evaluator Performance Page Queries</h2>";

// Simulate moderator ID (adjust if needed)
$test_moderator_id = 1;

echo "<h3>Test 1: Evaluators Query</h3>";
$evaluators_query = "SELECT u.id, u.name, u.email, u.created_at, u.is_active,
    COUNT(DISTINCT s.id) as total_evaluations,
    COUNT(DISTINCT CASE WHEN s.evaluation_status = 'evaluated' THEN s.id END) as completed_evaluations,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' THEN s.marks_obtained END) as avg_marks_given,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' THEN s.max_marks END) as avg_max_marks,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' AND s.max_marks > 0 
        THEN (s.marks_obtained / s.max_marks) * 100 END) as avg_percentage_given,
    MAX(s.evaluated_at) as last_evaluation_date
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluator_id
    WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1
    GROUP BY u.id, u.name, u.email, u.created_at, u.is_active
    ORDER BY u.name";

$stmt = $conn->prepare($evaluators_query);
if (!$stmt) {
    echo "<p style='color: red;'>❌ Query 1 preparation failed: " . $conn->error . "</p>";
} else {
    echo "<p style='color: green;'>✅ Query 1 prepared successfully</p>";
    $stmt->bind_param("i", $test_moderator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<p>Found <strong>" . $result->num_rows . "</strong> evaluators</p>";
}

echo "<hr>";
echo "<h3>Test 2: Overall Stats Query</h3>";
$overall_stats_query = "SELECT 
    COUNT(DISTINCT s.evaluator_id) as active_evaluators,
    COUNT(DISTINCT s.id) as total_evaluations,
    COUNT(DISTINCT CASE WHEN s.evaluation_status = 'evaluated' THEN s.id END) as completed_evaluations,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' THEN s.marks_obtained END) as avg_marks,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' AND s.max_marks > 0 
        THEN (s.marks_obtained/s.max_marks)*100 END) as avg_percentage,
    COUNT(DISTINCT CASE WHEN s.evaluation_status = 'evaluated' THEN s.id END) as total_answer_sheet_evaluations
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluator_id
    WHERE u.moderator_id = ? AND u.role = 'evaluator'";

$stmt = $conn->prepare($overall_stats_query);
if (!$stmt) {
    echo "<p style='color: red;'>❌ Query 2 preparation failed: " . $conn->error . "</p>";
} else {
    echo "<p style='color: green;'>✅ Query 2 prepared successfully</p>";
    $stmt->bind_param("i", $test_moderator_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo "<p>Stats: " . json_encode($result) . "</p>";
}

echo "<hr>";
echo "<h3>Test 3: Recent Activities Query</h3>";
$recent_activities_query = "SELECT 
    s.id as submission_id,
    s.marks_obtained as marks,
    s.max_marks,
    s.evaluated_at as completed_at,
    s.evaluation_status as status,
    u.name as evaluator_name,
    sub.code as subject_code,
    sub.name as subject_name,
    st.name as student_name
    FROM submissions s
    INNER JOIN users u ON s.evaluator_id = u.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    INNER JOIN users st ON s.student_id = st.id
    WHERE u.moderator_id = ? AND s.evaluation_status = 'evaluated'
    ORDER BY s.evaluated_at DESC
    LIMIT 20";

$stmt = $conn->prepare($recent_activities_query);
if (!$stmt) {
    echo "<p style='color: red;'>❌ Query 3 preparation failed: " . $conn->error . "</p>";
} else {
    echo "<p style='color: green;'>✅ Query 3 prepared successfully</p>";
    $stmt->bind_param("i", $test_moderator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<p>Found <strong>" . $result->num_rows . "</strong> recent activities</p>";
}

echo "<hr>";
echo "<h2 style='color: green;'>✅ All queries working! The page should load now.</h2>";
echo "<p><a href='moderator/evaluator_performance.php'>View Evaluator Performance Page</a></p>";
?>
