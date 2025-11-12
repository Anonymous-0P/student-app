<?php
require_once('config/config.php');

$moderator_id = 38;

$submissions_query = "SELECT 
    COUNT(DISTINCT s.id) as total_submissions,
    SUM(CASE WHEN (s.status = 'pending' OR s.status = 'Submitted') 
        AND (s.evaluation_status IS NULL OR s.evaluation_status NOT IN ('evaluated', 'approved')) 
        THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN s.status IN ('assigned', 'evaluating', 'Under Evaluation') 
        OR s.evaluation_status = 'under_review' 
        THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN s.status = 'evaluated' OR s.evaluation_status = 'evaluated' 
        THEN 1 ELSE 0 END) as evaluated,
    SUM(CASE WHEN s.status = 'approved' OR s.evaluation_status = 'approved' 
        THEN 1 ELSE 0 END) as approved
    FROM submissions s
    LEFT JOIN users u ON s.evaluator_id = u.id
    WHERE u.moderator_id = $moderator_id OR s.moderator_id = $moderator_id";

$result = $conn->query($submissions_query);
$stats = $result->fetch_assoc();

echo "=== REFINED SUBMISSION STATS ===\n";
echo "Total Submissions: {$stats['total_submissions']}\n";
echo "Pending: {$stats['pending']}\n";
echo "Under Review: {$stats['under_review']}\n";
echo "Evaluated: {$stats['evaluated']}\n";
echo "Approved: {$stats['approved']}\n\n";

// Show each submission's details
echo "=== BREAKDOWN BY SUBMISSION ===\n";
$detail_query = "SELECT s.id, s.status, s.evaluation_status 
                 FROM submissions s
                 LEFT JOIN users u ON s.evaluator_id = u.id
                 WHERE u.moderator_id = $moderator_id OR s.moderator_id = $moderator_id
                 ORDER BY s.id";
$details = $conn->query($detail_query);
while($row = $details->fetch_assoc()) {
    echo "ID {$row['id']}: status='{$row['status']}', eval_status='{$row['evaluation_status']}'\n";
}
?>
