<?php
session_start();
require_once('../config/config.php');
require_once('../includes/functions.php');

header('Content-Type: application/json');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$moderator_id = $_SESSION['user_id'];
$submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
$new_marks = isset($_POST['new_marks']) ? (float)$_POST['new_marks'] : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (!$submission_id || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Verify submission belongs to this moderator's subjects
$verify_query = "SELECT 
    s.id,
    s.marks_obtained as current_marks,
    s.max_marks,
    s.student_id,
    s.evaluator_id,
    u.name as student_name,
    e.name as evaluator_name,
    sub.name as subject_name
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    LEFT JOIN users e ON s.evaluator_id = e.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN moderator_subjects ms ON sub.id = ms.subject_id
    WHERE s.id = ? AND ms.moderator_id = ? AND ms.is_active = 1";

$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ii", $submission_id, $moderator_id);
$verify_stmt->execute();
$submission = $verify_stmt->get_result()->fetch_assoc();

if (!$submission) {
    echo json_encode(['success' => false, 'message' => 'Submission not found or unauthorized']);
    exit();
}

// Validate new marks
if ($new_marks < 0 || $new_marks > $submission['max_marks']) {
    echo json_encode(['success' => false, 'message' => 'Invalid marks. Must be between 0 and ' . $submission['max_marks']]);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Create marks_history table if it doesn't exist
    $create_history_table = "CREATE TABLE IF NOT EXISTS marks_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL,
        moderator_id INT NULL,
        evaluator_id INT NULL,
        old_marks DECIMAL(5,2) NULL,
        new_marks DECIMAL(5,2) NOT NULL,
        max_marks DECIMAL(5,2) NOT NULL,
        reason TEXT NULL,
        action_type ENUM('evaluated', 'override', 'review') NOT NULL,
        created_by_role ENUM('evaluator', 'moderator', 'admin') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES submissions(id)
    )";
    $conn->query($create_history_table);
    
    // Insert into marks history
    $history_query = "INSERT INTO marks_history 
        (submission_id, moderator_id, old_marks, new_marks, max_marks, reason, action_type, created_by_role) 
        VALUES (?, ?, ?, ?, ?, ?, 'override', 'moderator')";
    
    $history_stmt = $conn->prepare($history_query);
    $history_stmt->bind_param("iiddds", 
        $submission_id, 
        $moderator_id, 
        $submission['current_marks'], 
        $new_marks, 
        $submission['max_marks'], 
        $reason
    );
    $history_stmt->execute();
    
    // Update submission marks
    $update_query = "UPDATE submissions SET 
        marks_obtained = ?, 
        moderator_override = 1,
        moderator_override_reason = ?,
        moderator_override_date = NOW(),
        updated_at = NOW()
        WHERE id = ?";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("dsi", $new_marks, $reason, $submission_id);
    $update_stmt->execute();
    
    // Create notification for student about mark override
    try {
        $old_percentage = $submission['max_marks'] > 0 ? round(($submission['current_marks'] / $submission['max_marks']) * 100, 1) : 0;
        $new_percentage = $submission['max_marks'] > 0 ? round(($new_marks / $submission['max_marks']) * 100, 1) : 0;
        
        $notification_query = "INSERT INTO notifications 
            (user_id, type, title, message, related_id, metadata, created_at) 
            VALUES (?, 'marks_override', ?, ?, ?, ?, NOW())";
        
        $notification_title = "📊 Marks Updated - " . $submission['subject_name'];
        $notification_message = "Your marks have been updated by the moderator. New score: {$new_marks}/{$submission['max_marks']} ({$new_percentage}%). Previous: {$submission['current_marks']}/{$submission['max_marks']} ({$old_percentage}%).";
        
        $metadata = json_encode([
            'old_marks' => $submission['current_marks'],
            'new_marks' => $new_marks,
            'max_marks' => $submission['max_marks'],
            'old_percentage' => $old_percentage,
            'new_percentage' => $new_percentage,
            'moderator_name' => $_SESSION['name'],
            'reason' => $reason
        ]);
        
        $notification_stmt = $conn->prepare($notification_query);
        $notification_stmt->bind_param("issis", 
            $submission['student_id'], 
            $notification_title, 
            $notification_message, 
            $submission_id, 
            $metadata
        );
        $notification_stmt->execute();
    } catch (Exception $e) {
        // Notification failed, but continue (not critical)
        error_log("Failed to create notification: " . $e->getMessage());
    }
    
    // Create notification for evaluator about mark override
    if ($submission['evaluator_id']) {
        try {
            $evaluator_notification_title = "⚠️ Marks Override - " . $submission['subject_name'];
            $evaluator_notification_message = "The marks you assigned have been overridden by the moderator. Student: {$submission['student_name']}. Changed from {$submission['current_marks']} to {$new_marks} out of {$submission['max_marks']}.";
            
            $evaluator_metadata = json_encode([
                'old_marks' => $submission['current_marks'],
                'new_marks' => $new_marks,
                'max_marks' => $submission['max_marks'],
                'student_name' => $submission['student_name'],
                'moderator_name' => $_SESSION['name'],
                'reason' => $reason
            ]);
            
            $evaluator_notification_stmt = $conn->prepare($notification_query);
            $evaluator_notification_stmt->bind_param("issis", 
                $submission['evaluator_id'], 
                $evaluator_notification_title, 
                $evaluator_notification_message, 
                $submission_id, 
                $evaluator_metadata
            );
            $evaluator_notification_stmt->execute();
        } catch (Exception $e) {
            // Notification failed, but continue
            error_log("Failed to create evaluator notification: " . $e->getMessage());
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Marks successfully overridden from {$submission['current_marks']} to {$new_marks}. All relevant parties have been notified."
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error overriding marks: ' . $e->getMessage()
    ]);
}
?>