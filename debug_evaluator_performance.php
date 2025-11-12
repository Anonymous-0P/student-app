<?php
require_once('config/config.php');

$moderator_id = 38;

echo "=== CURRENT EVALUATOR PERFORMANCE QUERY ===\n\n";

$evaluator_performance_query = "SELECT 
    u.id as evaluator_id,
    u.name as evaluator_name,
    u.email as evaluator_email,
    COUNT(DISTINCT s.id) as total_assignments,
    COUNT(DISTINCT CASE WHEN s.status = 'evaluated' OR s.evaluation_status = 'evaluated' THEN s.id END) as completed_assignments,
    COUNT(DISTINCT CASE WHEN s.status IN ('assigned', 'evaluating', 'Under Evaluation') THEN s.id END) as in_progress_assignments,
    COUNT(DISTINCT CASE WHEN s.status = 'pending' OR s.status = 'Submitted' THEN s.id END) as pending_assignments
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluator_id
    WHERE u.role = 'evaluator' AND u.moderator_id = $moderator_id AND u.is_active = 1
    GROUP BY u.id, u.name, u.email
    ORDER BY completed_assignments DESC, u.name";

$result = $conn->query($evaluator_performance_query);
$performance = $result->fetch_all(MYSQLI_ASSOC);

echo "Individual Evaluator Stats:\n";
foreach($performance as $p) {
    echo "\n{$p['evaluator_name']}:\n";
    echo "  Total: {$p['total_assignments']}\n";
    echo "  Completed: {$p['completed_assignments']}\n";
    echo "  In Progress: {$p['in_progress_assignments']}\n";
    echo "  Pending: {$p['pending_assignments']}\n";
}

echo "\n\n=== SUMMARY TOTALS (Current Logic) ===\n";
echo "Total Evaluators: " . count($performance) . "\n";
echo "Completed: " . array_sum(array_column($performance, 'completed_assignments')) . "\n";
echo "In Progress: " . array_sum(array_column($performance, 'in_progress_assignments')) . "\n";
echo "Pending: " . array_sum(array_column($performance, 'pending_assignments')) . "\n";

echo "\n\n=== WHAT'S WRONG ===\n";
echo "The pending count includes ALL submissions with status='Submitted',\n";
echo "even if they have evaluation_status='evaluated'.\n\n";

echo "=== DETAILED BREAKDOWN ===\n";
$detail_query = "SELECT 
    s.id,
    s.status,
    s.evaluation_status,
    u.name as evaluator_name,
    CASE 
        WHEN s.status = 'evaluated' OR s.evaluation_status = 'evaluated' THEN 'COMPLETED'
        WHEN s.status IN ('assigned', 'evaluating', 'Under Evaluation') THEN 'IN_PROGRESS'
        WHEN s.status = 'pending' OR s.status = 'Submitted' THEN 'PENDING'
        ELSE 'OTHER'
    END as current_count_as,
    CASE 
        WHEN s.status = 'evaluated' OR s.evaluation_status = 'evaluated' THEN 'COMPLETED'
        WHEN s.status IN ('assigned', 'evaluating', 'Under Evaluation') OR s.evaluation_status = 'under_review' THEN 'IN_PROGRESS'
        WHEN (s.status = 'pending' OR s.status = 'Submitted') 
             AND (s.evaluation_status IS NULL OR s.evaluation_status NOT IN ('evaluated', 'approved', 'under_review')) 
             THEN 'PENDING'
        ELSE 'OTHER'
    END as should_count_as
FROM submissions s
LEFT JOIN users u ON s.evaluator_id = u.id
WHERE u.moderator_id = $moderator_id
ORDER BY s.id";

$details = $conn->query($detail_query);
echo "\nID  | Evaluator | Status      | Eval_Status   | Currently   | Should Be\n";
echo "----+-----------+-------------+---------------+-------------+------------\n";
while($row = $details->fetch_assoc()) {
    printf("%-3s | %-9s | %-11s | %-13s | %-11s | %-11s\n",
        $row['id'],
        substr($row['evaluator_name'], 0, 9),
        $row['status'],
        $row['evaluation_status'] ?: 'NULL',
        $row['current_count_as'],
        $row['should_count_as']
    );
}

echo "\n\n=== CORRECTED QUERY ===\n";
$corrected_query = "SELECT 
    u.id as evaluator_id,
    u.name as evaluator_name,
    COUNT(DISTINCT s.id) as total_assignments,
    COUNT(DISTINCT CASE WHEN s.status = 'evaluated' OR s.evaluation_status = 'evaluated' 
        THEN s.id END) as completed_assignments,
    COUNT(DISTINCT CASE WHEN s.status IN ('assigned', 'evaluating', 'Under Evaluation') 
        OR s.evaluation_status = 'under_review'
        THEN s.id END) as in_progress_assignments,
    COUNT(DISTINCT CASE 
        WHEN (s.status = 'pending' OR s.status = 'Submitted') 
        AND (s.evaluation_status IS NULL OR s.evaluation_status NOT IN ('evaluated', 'approved', 'under_review'))
        THEN s.id END) as pending_assignments
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluator_id
    WHERE u.role = 'evaluator' AND u.moderator_id = $moderator_id AND u.is_active = 1
    GROUP BY u.id, u.name
    ORDER BY completed_assignments DESC, u.name";

$result = $conn->query($corrected_query);
$corrected_performance = $result->fetch_all(MYSQLI_ASSOC);

echo "\nCorrected Individual Stats:\n";
foreach($corrected_performance as $p) {
    echo "\n{$p['evaluator_name']}:\n";
    echo "  Total: {$p['total_assignments']}\n";
    echo "  Completed: {$p['completed_assignments']}\n";
    echo "  In Progress: {$p['in_progress_assignments']}\n";
    echo "  Pending: {$p['pending_assignments']}\n";
}

echo "\n\n=== CORRECTED SUMMARY TOTALS ===\n";
echo "Total Evaluators: " . count($corrected_performance) . "\n";
echo "Completed: " . array_sum(array_column($corrected_performance, 'completed_assignments')) . "\n";
echo "In Progress: " . array_sum(array_column($corrected_performance, 'in_progress_assignments')) . "\n";
echo "Pending: " . array_sum(array_column($corrected_performance, 'pending_assignments')) . "\n";
?>
