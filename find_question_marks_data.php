<?php
require_once('config/config.php');

$result = $conn->query("SELECT id, per_question_marks FROM submissions WHERE per_question_marks IS NOT NULL LIMIT 5");

echo "=== Submissions with per_question_marks data ===\n\n";
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "Submission ID: " . $row['id'] . "\n";
        echo "Data: " . substr($row['per_question_marks'], 0, 100) . "...\n\n";
    }
} else {
    echo "No submissions found with per_question_marks data.\n";
    echo "The column was just added - evaluators need to save new evaluations.\n";
}
?>
