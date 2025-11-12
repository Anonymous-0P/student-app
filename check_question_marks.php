<?php
require_once('config/config.php');

$submission_id = 98;

$stmt = $conn->prepare("SELECT per_question_marks, marks_obtained, subject_id FROM submissions WHERE id = ?");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo "=== Submission #98 Question Marks Data ===\n\n";
echo "Subject ID: " . $row['subject_id'] . "\n";
echo "Total marks_obtained: " . $row['marks_obtained'] . "\n\n";

echo "Raw per_question_marks:\n";
var_dump($row['per_question_marks']);
echo "\n\n";

if ($row['per_question_marks']) {
    echo "Decoded per_question_marks:\n";
    $decoded = json_decode($row['per_question_marks'], true);
    print_r($decoded);
    
    if ($decoded && is_array($decoded)) {
        echo "\n\nQuestion-wise breakdown:\n";
        $count = 0;
        foreach ($decoded as $q_num => $marks) {
            echo "Q$q_num = $marks marks\n";
            $count++;
            if ($count > 10) {
                echo "... and " . (count($decoded) - 10) . " more questions\n";
                break;
            }
        }
        
        echo "\n\nTotal questions: " . count($decoded) . "\n";
        echo "Total from questions: " . array_sum($decoded) . "\n";
    }
} else {
    echo "per_question_marks is NULL or empty\n";
}
?>
