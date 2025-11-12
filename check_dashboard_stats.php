<?php
require_once('config/config.php');

// Check for moderator
$moderator_query = "SELECT id, name FROM users WHERE role = 'moderator' LIMIT 1";
$moderator = $conn->query($moderator_query)->fetch_assoc();

if(!$moderator) {
    die("No moderator found");
}

$moderator_id = $moderator['id'];
echo "Checking stats for Moderator: {$moderator['name']} (ID: {$moderator_id})\n\n";

// 1. Check subjects
echo "=== SUBJECTS ===\n";
$subjects_query = "SELECT COUNT(DISTINCT ms.subject_id) as count 
                   FROM moderator_subjects ms 
                   WHERE ms.moderator_id = $moderator_id AND ms.is_active = 1";
$result = $conn->query($subjects_query);
$subjects = $result->fetch_assoc()['count'];
echo "Assigned Subjects: $subjects\n\n";

// 2. Check evaluators
echo "=== EVALUATORS ===\n";
$eval_query = "SELECT id, name FROM users 
               WHERE moderator_id = $moderator_id AND role = 'evaluator' AND is_active = 1";
$evaluators = $conn->query($eval_query);
echo "Evaluators under supervision: " . $evaluators->num_rows . "\n";
while($e = $evaluators->fetch_assoc()) {
    echo "  - {$e['name']} (ID: {$e['id']})\n";
}
echo "\n";

// 3. Check submissions
echo "=== SUBMISSIONS ===\n";
$sub_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
    SUM(CASE WHEN status = 'evaluating' OR status = 'Under Evaluation' THEN 1 ELSE 0 END) as evaluating,
    SUM(CASE WHEN status = 'evaluated' OR evaluation_status = 'evaluated' THEN 1 ELSE 0 END) as evaluated,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
FROM submissions WHERE moderator_id = $moderator_id";
$sub_result = $conn->query($sub_query);
$subs = $sub_result->fetch_assoc();
echo "Total Submissions: {$subs['total']}\n";
echo "Pending: {$subs['pending']}\n";
echo "Submitted: {$subs['submitted']}\n";
echo "Assigned: {$subs['assigned']}\n";
echo "Evaluating: {$subs['evaluating']}\n";
echo "Evaluated: {$subs['evaluated']}\n";
echo "Approved: {$subs['approved']}\n\n";

// 4. Check status values
echo "=== ACTUAL STATUS VALUES ===\n";
$status_query = "SELECT DISTINCT status, COUNT(*) as count 
                 FROM submissions 
                 WHERE moderator_id = $moderator_id
                 GROUP BY status";
$statuses = $conn->query($status_query);
while($s = $statuses->fetch_assoc()) {
    echo "{$s['status']}: {$s['count']}\n";
}
echo "\n";

// 5. Check evaluation_status values
echo "=== EVALUATION_STATUS VALUES ===\n";
$eval_status_query = "SELECT DISTINCT evaluation_status, COUNT(*) as count 
                      FROM submissions 
                      WHERE moderator_id = $moderator_id
                      GROUP BY evaluation_status";
$eval_statuses = $conn->query($eval_status_query);
while($s = $eval_statuses->fetch_assoc()) {
    $status = $s['evaluation_status'] ?: 'NULL';
    echo "{$status}: {$s['count']}\n";
}
?>
