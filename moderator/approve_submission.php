<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$moderator_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
$moderator_remarks = isset($_POST['moderator_remarks']) ? trim($_POST['moderator_remarks']) : '';

if (!$submission_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission ID']);
    exit();
}

// Verify moderator owns this submission
$verify_query = "SELECT s.*, st.email as student_email, st.name as student_name, 
                 ev.email as evaluator_email, ev.name as evaluator_name
                 FROM submissions s
                 LEFT JOIN users st ON s.student_id = st.id
                 LEFT JOIN users ev ON s.evaluator_id = ev.id
                 WHERE s.id = ? AND s.moderator_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $submission_id, $moderator_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();

if (!$submission) {
    echo json_encode(['success' => false, 'message' => 'Submission not found or access denied']);
    exit();
}

// Check if already approved
if ($submission['status'] === 'approved') {
    echo json_encode(['success' => false, 'message' => 'Submission already approved']);
    exit();
}

// Update submission status to approved
$update_query = "UPDATE submissions SET 
                 status = 'approved',
                 moderator_remarks = ?,
                 approved_at = NOW(),
                 final_marks = marks_obtained,
                 final_feedback = CONCAT_WS('\\n\\n', evaluator_remarks, ?)
                 WHERE id = ?";

$stmt = $conn->prepare($update_query);
$stmt->bind_param("ssi", $moderator_remarks, $moderator_remarks, $submission_id);

if ($stmt->execute()) {
    // Send notification email to student
    if ($submission['student_email']) {
        $to = $submission['student_email'];
        $subject = "Your Submission Has Been Approved - Submission #" . $submission_id;
        
        $message = "Dear " . $submission['student_name'] . ",\n\n";
        $message .= "Good news! Your submission (#" . $submission_id . ") has been reviewed and approved by the moderator.\n\n";
        $message .= "Final Marks: " . $submission['marks_obtained'] . "/" . $submission['max_marks'] . "\n";
        $message .= "Percentage: " . round(($submission['marks_obtained'] / $submission['max_marks']) * 100, 2) . "%\n\n";
        
        if ($moderator_remarks) {
            $message .= "Moderator's Remarks:\n" . $moderator_remarks . "\n\n";
        }
        
        $message .= "You can view your complete evaluation results in your dashboard.\n\n";
        $message .= "Best regards,\n";
        $message .= "Evaluation Team";
        
        $headers = "From: noreply@student-app.local\r\n";
        $headers .= "Reply-To: noreply@student-app.local\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        @mail($to, $subject, $message, $headers);
    }
    
    // Send notification to evaluator
    if ($submission['evaluator_email']) {
        $to = $submission['evaluator_email'];
        $subject = "Submission Approved - Submission #" . $submission_id;
        
        $message = "Dear " . $submission['evaluator_name'] . ",\n\n";
        $message .= "The submission (#" . $submission_id . ") you evaluated has been reviewed and approved by the moderator.\n\n";
        $message .= "Thank you for your thorough evaluation work.\n\n";
        $message .= "Best regards,\n";
        $message .= "Evaluation Team";
        
        $headers = "From: noreply@student-app.local\r\n";
        $headers .= "Reply-To: noreply@student-app.local\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        @mail($to, $subject, $message, $headers);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Submission approved successfully',
        'submission_id' => $submission_id
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to approve submission: ' . $conn->error
    ]);
}

$conn->close();
?>
