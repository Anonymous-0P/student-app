<?php
require_once('config/config.php');

$submission_id = 98;

// Create sample question-wise marks for EMA_10TH (38 questions total)
// Part 1: 8 questions × 1 mark
// Part 2: 8 questions × 1 mark  
// Part 3: 8 questions × 2 marks
// Part 4: 9 questions × 3 marks
// Part 5: 4 questions × 4 marks
// Part 6: 1 question × 5 marks

$question_marks = [];

// Part 1: Q1-Q8 (1 mark each)
for ($i = 1; $i <= 8; $i++) {
    $question_marks[$i] = 1.00; // Full marks
}

// Part 2: Q9-Q16 (1 mark each)
for ($i = 9; $i <= 16; $i++) {
    $question_marks[$i] = 1.00; // Full marks
}

// Part 3: Q17-Q24 (2 marks each)
for ($i = 17; $i <= 24; $i++) {
    $question_marks[$i] = 1.75; // Partial marks
}

// Part 4: Q25-Q33 (3 marks each)
for ($i = 25; $i <= 33; $i++) {
    $question_marks[$i] = 2.50; // Partial marks
}

// Part 5: Q34-Q37 (4 marks each)
for ($i = 34; $i <= 37; $i++) {
    $question_marks[$i] = 3.50; // Partial marks
}

// Part 6: Q38 (5 marks)
$question_marks[38] = 4.00; // Partial marks

// Calculate total
$total = array_sum($question_marks);

echo "=== Sample Question-wise Marks for Submission #98 ===\n\n";
echo "Total questions: " . count($question_marks) . "\n";
echo "Total marks: " . $total . "\n\n";

// Show first few questions
echo "Sample breakdown:\n";
echo "Q1 = {$question_marks[1]}/1\n";
echo "Q8 = {$question_marks[8]}/1\n";
echo "Q17 = {$question_marks[17]}/2\n";
echo "Q25 = {$question_marks[25]}/3\n";
echo "Q34 = {$question_marks[34]}/4\n";
echo "Q38 = {$question_marks[38]}/5\n\n";

// Convert to JSON
$json_data = json_encode($question_marks);

// Update the submission
$stmt = $conn->prepare("UPDATE submissions SET per_question_marks = ? WHERE id = ?");
$stmt->bind_param("si", $json_data, $submission_id);

if ($stmt->execute()) {
    echo "✓ Successfully updated submission #$submission_id with question-wise marks\n";
    echo "\nYou can now view this at:\n";
    echo "http://localhost/student-app/moderator/review_evaluation.php?id=98\n";
} else {
    echo "✗ Error updating submission: " . $conn->error . "\n";
}
?>
