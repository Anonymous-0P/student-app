<?php
require_once('config/config.php');

// Check submission ID 98 (or change as needed)
$submission_id = 98;

$query = "SELECT 
    s.*,
    sub.code as subject_code,
    sub.name as subject_name,
    sub.grade_level
    FROM submissions s
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    WHERE s.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();

echo "<h2>Submission #$submission_id Debug Info</h2>";
echo "<pre>";
echo "Subject Code: " . ($submission['subject_code'] ?? 'NULL') . "\n";
echo "Subject Name: " . ($submission['subject_name'] ?? 'NULL') . "\n";
echo "Grade Level: " . ($submission['grade_level'] ?? 'NULL') . "\n";
echo "Max Marks: " . ($submission['max_marks'] ?? 'NULL') . "\n";
echo "Division (if exists): " . ($submission['division'] ?? 'NULL') . "\n";
echo "\n--- Per Question Marks ---\n";
echo "Raw per_question_marks field:\n";
echo ($submission['per_question_marks'] ?? 'NULL') . "\n\n";

if (!empty($submission['per_question_marks'])) {
    echo "Decoded JSON:\n";
    $per_question = json_decode($submission['per_question_marks'], true);
    print_r($per_question);
    echo "\nJSON Decode Error: " . json_last_error_msg() . "\n";
} else {
    echo "per_question_marks is EMPTY or NULL\n";
}

// Test the division detection logic
$division = isset($submission['division']) ? trim(strtolower($submission['division'])) : '';
if (empty($division) && isset($submission['grade_level'])) {
    $division = trim(strtolower($submission['grade_level']));
}
$subject_code = isset($submission['subject_code']) ? trim(strtolower($submission['subject_code'])) : '';

echo "\n--- Detection Logic ---\n";
echo "Division used: '$division'\n";
echo "Subject code used: '$subject_code'\n";

$is_10th = ($division === '10th' || $division === '10th class' || $division === '10' || $division === '10th grade' || strpos($subject_code, '10th') !== false);
echo "Is 10th grade: " . ($is_10th ? 'YES' : 'NO') . "\n";

echo "\n--- All Submission Fields ---\n";
print_r($submission);
echo "</pre>";
?>
