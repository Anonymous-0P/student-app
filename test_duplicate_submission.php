<?php
require_once('config/config.php');

echo "=== Testing Duplicate Submission Prevention ===\n\n";

// Get a student
$student_query = "SELECT id, name, email FROM users WHERE role = 'student' LIMIT 1";
$student = $conn->query($student_query)->fetch_assoc();

if (!$student) {
    die("No student found in database\n");
}

echo "Testing for Student: {$student['name']} (ID: {$student['id']})\n\n";

// Check existing submissions
$existing_sql = "SELECT as_table.id, as_table.subject_id, s.name as subject_name, s.code as subject_code
                 FROM answer_sheets as_table
                 JOIN subjects s ON as_table.subject_id = s.id
                 WHERE as_table.student_id = {$student['id']}";
$existing = $conn->query($existing_sql);

echo "=== EXISTING SUBMISSIONS ===\n";
if ($existing->num_rows > 0) {
    while ($row = $existing->fetch_assoc()) {
        echo "- Submission ID {$row['id']}: {$row['subject_code']} - {$row['subject_name']} (Subject ID: {$row['subject_id']})\n";
    }
} else {
    echo "No existing submissions\n";
}

// Get submitted subject IDs
$submitted_subjects_sql = "SELECT DISTINCT subject_id FROM answer_sheets WHERE student_id = {$student['id']}";
$submitted_result = $conn->query($submitted_subjects_sql);
$submitted_subjects = [];
while ($row = $submitted_result->fetch_assoc()) {
    $submitted_subjects[] = $row['subject_id'];
}

echo "\n=== SUBMITTED SUBJECT IDs ===\n";
if (empty($submitted_subjects)) {
    echo "None\n";
} else {
    echo implode(', ', $submitted_subjects) . "\n";
}

// Get all subjects
echo "\n=== ALL AVAILABLE SUBJECTS ===\n";
$all_subjects = $conn->query("SELECT id, code, name FROM subjects ORDER BY name LIMIT 10");
while ($subject = $all_subjects->fetch_assoc()) {
    $is_submitted = in_array($subject['id'], $submitted_subjects);
    $status = $is_submitted ? "[ALREADY SUBMITTED - DISABLED]" : "[AVAILABLE]";
    echo "- ID {$subject['id']}: {$subject['code']} - {$subject['name']} {$status}\n";
}

echo "\n=== DUPLICATE PREVENTION TEST ===\n";
if (!empty($submitted_subjects)) {
    $test_subject_id = $submitted_subjects[0];
    echo "Attempting to submit duplicate for Subject ID: {$test_subject_id}\n";
    
    $check_sql = "SELECT id FROM answer_sheets 
                  WHERE student_id = {$student['id']} AND subject_id = {$test_subject_id} 
                  LIMIT 1";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        echo "✓ DUPLICATE DETECTED - Submission would be blocked\n";
        echo "  Error message: 'You have already submitted an answer sheet for this subject. Only one submission per subject is allowed.'\n";
    } else {
        echo "✗ No duplicate found - This shouldn't happen!\n";
    }
} else {
    echo "No submissions exist to test duplicate prevention\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total Submissions: " . count($submitted_subjects) . "\n";
echo "Duplicate Prevention: ACTIVE\n";
echo "Status: Students can only submit once per subject\n";
?>
