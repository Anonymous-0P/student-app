<?php
require_once('config/config.php');

$submission_id = 99;

echo "=== Checking Submission #$submission_id ===\n\n";

// Get submission details
$query = "SELECT 
    s.id,
    s.marks_obtained,
    s.max_marks,
    s.per_question_marks,
    s.evaluator_remarks,
    s.status,
    s.evaluation_status,
    sub.code as subject_code,
    u_eval.name as evaluator_name
FROM submissions s
LEFT JOIN assignments a ON s.assignment_id = a.id
LEFT JOIN subjects sub ON a.subject_id = sub.id
LEFT JOIN users u_eval ON s.evaluator_id = u_eval.id
WHERE s.id = $submission_id";

$result = $conn->query($query);

if (!$result || $result->num_rows == 0) {
    die("Submission #$submission_id not found\n");
}

$submission = $result->fetch_assoc();

echo "Submission ID: {$submission['id']}\n";
echo "Evaluator: {$submission['evaluator_name']}\n";
echo "Subject Code: {$submission['subject_code']}\n";
echo "Status: {$submission['status']}\n";
echo "Evaluation Status: {$submission['evaluation_status']}\n";
echo "Marks Obtained: {$submission['marks_obtained']}\n";
echo "Max Marks: {$submission['max_marks']}\n\n";

echo "=== PER QUESTION MARKS (RAW) ===\n";
if ($submission['per_question_marks']) {
    echo "Raw Data: {$submission['per_question_marks']}\n\n";
    
    $per_question = json_decode($submission['per_question_marks'], true);
    
    if ($per_question && is_array($per_question)) {
        echo "=== DECODED QUESTION MARKS ===\n";
        echo "Total Questions: " . count($per_question) . "\n\n";
        
        $total = 0;
        foreach ($per_question as $q_num => $marks) {
            echo "Q{$q_num}: " . number_format($marks, 2) . "\n";
            $total += $marks;
        }
        echo "\nCalculated Total: " . number_format($total, 2) . "\n";
        echo "Stored Total: {$submission['marks_obtained']}\n";
        
        if (abs($total - $submission['marks_obtained']) > 0.01) {
            echo "\n⚠️ WARNING: Total mismatch! Difference: " . number_format(abs($total - $submission['marks_obtained']), 2) . "\n";
        } else {
            echo "\n✓ Totals match!\n";
        }
    } else {
        echo "ERROR: Failed to decode JSON or not an array\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
    }
} else {
    echo "NULL or EMPTY - No question-wise marks stored\n\n";
    echo "⚠️ This is why the moderator sees the warning message.\n";
    echo "The evaluator did not save question-wise breakdown.\n";
}

echo "\n=== EVALUATOR REMARKS ===\n";
echo $submission['evaluator_remarks'] ?: "No remarks\n";
?>
