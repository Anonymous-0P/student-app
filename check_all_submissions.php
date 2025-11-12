<?php
require_once('config/config.php');

echo "=== ALL SUBMISSIONS ===\n";
$query = "SELECT 
    s.id,
    s.student_id,
    s.evaluator_id,
    s.moderator_id,
    s.status,
    s.evaluation_status,
    s.marks_obtained,
    s.max_marks,
    u_student.name as student_name,
    u_eval.name as evaluator_name,
    u_mod.name as moderator_name
FROM submissions s
LEFT JOIN users u_student ON s.student_id = u_student.id
LEFT JOIN users u_eval ON s.evaluator_id = u_eval.id
LEFT JOIN users u_mod ON s.moderator_id = u_mod.id
ORDER BY s.id DESC
LIMIT 20";

$result = $conn->query($query);

if($result->num_rows == 0) {
    echo "No submissions found in database!\n";
} else {
    echo "Total submissions: {$result->num_rows}\n\n";
    while($row = $result->fetch_assoc()) {
        echo "Submission ID: {$row['id']}\n";
        echo "  Student: {$row['student_name']} (ID: {$row['student_id']})\n";
        echo "  Evaluator: " . ($row['evaluator_name'] ?: 'None') . " (ID: " . ($row['evaluator_id'] ?: 'NULL') . ")\n";
        echo "  Moderator: " . ($row['moderator_name'] ?: 'None') . " (ID: " . ($row['moderator_id'] ?: 'NULL') . ")\n";
        echo "  Status: {$row['status']}\n";
        echo "  Evaluation Status: " . ($row['evaluation_status'] ?: 'NULL') . "\n";
        echo "  Marks: {$row['marks_obtained']}/{$row['max_marks']}\n";
        echo "---\n";
    }
}

echo "\n=== MODERATORS IN SYSTEM ===\n";
$mod_query = "SELECT id, name, email FROM users WHERE role = 'moderator'";
$mods = $conn->query($mod_query);
while($m = $mods->fetch_assoc()) {
    echo "ID {$m['id']}: {$m['name']} ({$m['email']})\n";
}

echo "\n=== EVALUATORS IN SYSTEM ===\n";
$eval_query = "SELECT id, name, email, moderator_id FROM users WHERE role = 'evaluator'";
$evals = $conn->query($eval_query);
while($e = $evals->fetch_assoc()) {
    echo "ID {$e['id']}: {$e['name']} - Moderator ID: " . ($e['moderator_id'] ?: 'NULL') . "\n";
}
?>
