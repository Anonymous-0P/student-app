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
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (!$submission_id || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Submission ID and reason are required']);
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

// Update submission status to request re-evaluation
$update_query = "UPDATE submissions SET 
                 status = 'under_review',
                 evaluation_status = 'revision_needed',
                 moderator_remarks = ?,
                 updated_at = NOW()
                 WHERE id = ?";

$stmt = $conn->prepare($update_query);
$stmt->bind_param("si", $reason, $submission_id);

if ($stmt->execute()) {
    // Send notification email to evaluator
    if ($submission['evaluator_email']) {
        $to = $submission['evaluator_email'];
        $subject = "Re-evaluation Required - Submission #" . $submission_id;
        
        $message = "Dear " . $submission['evaluator_name'] . ",\n\n";
        $message .= "The moderator has requested a re-evaluation for submission #" . $submission_id . ".\n\n";
        $message .= "Reason for re-evaluation:\n" . $reason . "\n\n";
        $message .= "Please review the submission again and update your evaluation accordingly.\n\n";
        $message .= "You can access the submission in your evaluator dashboard.\n\n";
        $message .= "Best regards,\n";
        $message .= "Evaluation Team";
        
        $headers = "From: noreply@student-app.local\r\n";
        $headers .= "Reply-To: noreply@student-app.local\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        @mail($to, $subject, $message, $headers);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Re-evaluation request sent successfully',
        'submission_id' => $submission_id
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to request re-evaluation: ' . $conn->error
    ]);
}

$conn->close();
?>
