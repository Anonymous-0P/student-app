<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$moderator_id = $_SESSION['user_id'];

// Get POST data
$submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
$question_marks = isset($_POST['question_marks']) ? $_POST['question_marks'] : '{}';
$marks_obtained = isset($_POST['marks_obtained']) ? floatval($_POST['marks_obtained']) : 0;
$max_marks = isset($_POST['max_marks']) ? floatval($_POST['max_marks']) : 0;
$moderator_remarks = isset($_POST['moderator_remarks']) ? trim($_POST['moderator_remarks']) : '';

// Validate submission ID
if (!$submission_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission ID']);
    exit();
}

// Validate marks
if ($marks_obtained > $max_marks) {
    echo json_encode(['success' => false, 'message' => 'Marks obtained cannot exceed maximum marks']);
    exit();
}

// Verify moderator has access to this submission
$check_query = "SELECT s.id FROM submissions s
                LEFT JOIN users evaluator ON s.evaluator_id = evaluator.id
                WHERE s.id = ? AND (evaluator.moderator_id = ? OR s.moderator_id = ?)";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("iii", $submission_id, $moderator_id, $moderator_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Submission not found or access denied']);
    exit();
}

// Calculate percentage and grade
$percentage = ($marks_obtained / $max_marks) * 100;

$grade = 'F';
if ($percentage >= 90) $grade = 'A+';
else if ($percentage >= 85) $grade = 'A';
else if ($percentage >= 80) $grade = 'A-';
else if ($percentage >= 75) $grade = 'B+';
else if ($percentage >= 70) $grade = 'B';
else if ($percentage >= 65) $grade = 'B-';
else if ($percentage >= 60) $grade = 'C+';
else if ($percentage >= 55) $grade = 'C';
else if ($percentage >= 50) $grade = 'C-';
else if ($percentage >= 35) $grade = 'D';

// Update submission with moderator's review
$update_query = "UPDATE submissions 
                 SET per_question_marks = ?,
                     marks_obtained = ?,
                     moderator_remarks = ?,
                     moderated_by = ?,
                     moderated_at = NOW(),
                     updated_at = NOW()
                 WHERE id = ?";

$stmt = $conn->prepare($update_query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("sdsii", $question_marks, $marks_obtained, $moderator_remarks, $moderator_id, $submission_id);

if ($stmt->execute()) {
    // Log the action
    $log_query = "INSERT INTO activity_log (user_id, action, details, created_at) 
                  VALUES (?, 'moderator_review_updated', ?, NOW())";
    $log_stmt = $conn->prepare($log_query);
    if ($log_stmt) {
        $details = "Updated submission #$submission_id - Marks: $marks_obtained/$max_marks, Grade: $grade";
        $log_stmt->bind_param("is", $moderator_id, $details);
        $log_stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Moderator review saved successfully',
        'data' => [
            'marks_obtained' => $marks_obtained,
            'max_marks' => $max_marks,
            'percentage' => round($percentage, 2),
            'grade' => $grade
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save review: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
