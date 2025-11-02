<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('evaluator');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log incoming request
error_log("Assignment handler called - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';
$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (!in_array($action, ['accept', 'deny'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

if ($assignment_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid assignment ID']);
    exit;
}

// Start transaction
$pdo->beginTransaction();

try {
    // First, verify this assignment belongs to the current evaluator and is still pending
    $checkStmt = $pdo->prepare("
        SELECT sa.id, sa.submission_id, sa.status, s.student_id, s.subject_id, 
               sub.code as subject_code, u.name as student_name
        FROM submission_assignments sa
        INNER JOIN submissions s ON sa.submission_id = s.id
        INNER JOIN subjects sub ON s.subject_id = sub.id
        INNER JOIN users u ON s.student_id = u.id
        WHERE sa.id = ? AND sa.evaluator_id = ? AND sa.status = 'pending'
    ");
    $checkStmt->execute([$assignment_id, $_SESSION['user_id']]);
    $assignment = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        throw new Exception('Assignment not found or not available');
    }
    
    $submission_id = $assignment['submission_id'];
    $subject_id = $assignment['subject_id'];
    $student_name = $assignment['student_name'];
    $subject_code = $assignment['subject_code'];
    
    if ($action === 'accept') {
        // Check if another evaluator has already accepted this submission
        $acceptedStmt = $pdo->prepare("
            SELECT evaluator_id 
            FROM submission_assignments 
            WHERE submission_id = ? AND status = 'accepted'
        ");
        $acceptedStmt->execute([$submission_id]);
        $acceptedResult = $acceptedStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($acceptedResult) {
            throw new Exception('This assignment has already been accepted by another evaluator');
        }
        
        // Accept this assignment
        $updateStmt = $pdo->prepare("
            UPDATE submission_assignments 
            SET status = 'accepted', responded_at = NOW(), notes = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$notes, $assignment_id]);
        
        // Deny all other pending assignments for this submission
        $denyOthersStmt = $pdo->prepare("
            UPDATE submission_assignments 
            SET status = 'denied', responded_at = NOW(), notes = 'Automatically denied - another evaluator accepted'
            WHERE submission_id = ? AND evaluator_id != ? AND status = 'pending'
        ");
        $denyOthersStmt->execute([$submission_id, $_SESSION['user_id']]);
        
        // Update submission status and assign evaluator - Mark as under review
        $updateSubmissionStmt = $pdo->prepare("
            UPDATE submissions 
            SET status = 'assigned', evaluator_id = ?, evaluation_status = 'under_review'
            WHERE id = ?
        ");
        $updateSubmissionStmt->execute([$_SESSION['user_id'], $submission_id]);
        
        // Create evaluation record if evaluations table exists
        try {
            $createEvaluationStmt = $pdo->prepare("
                INSERT INTO evaluations (submission_id, evaluator_id, assignment_id, status, started_at, created_at) 
                VALUES (?, ?, ?, 'in_progress', NOW(), NOW())
                ON DUPLICATE KEY UPDATE status = 'in_progress', started_at = NOW()
            ");
            $createEvaluationStmt->execute([$submission_id, $_SESSION['user_id'], $assignment_id]);
        } catch (Exception $e) {
            // Evaluations table might not exist yet, that's okay
            error_log("Could not create evaluation record: " . $e->getMessage());
        }
        
        // Create notifications for other evaluators
        $otherEvaluatorsStmt = $pdo->prepare("
            SELECT DISTINCT evaluator_id 
            FROM submission_assignments 
            WHERE submission_id = ? AND evaluator_id != ? AND status = 'denied'
        ");
        $otherEvaluatorsStmt->execute([$submission_id, $_SESSION['user_id']]);
        $otherEvaluators = $otherEvaluatorsStmt->fetchAll();
        
        $notifyStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, metadata, created_at) 
            VALUES (?, 'assignment_denied', ?, ?, ?, ?, NOW())
        ");
        
        foreach ($otherEvaluators as $evaluator) {
            $notifyTitle = "Assignment No Longer Available";
            $notifyMessage = "The assignment for {$subject_code} ({$student_name}) has been accepted by another evaluator.";
            $metadata = json_encode(['subject_code' => $subject_code, 'student_name' => $student_name]);
            $notifyStmt->execute([$evaluator['evaluator_id'], $notifyTitle, $notifyMessage, $submission_id, $metadata]);
        }
        
        // Notify student - Assignment is now under review
        $studentNotifyStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, metadata, created_at) 
            VALUES (?, 'under_review', ?, ?, ?, ?, NOW())
        ");
        $studentTitle = "📝 Assignment Under Review";
        $studentMessage = "Great news! Your submission for {$subject_code} is now under evaluation. You will be notified once the review is complete.";
        $metadata = json_encode([
            'subject_code' => $subject_code, 
            'evaluator_name' => $_SESSION['name'] ?? 'Evaluator',
            'status' => 'under_review',
            'stage' => 'evaluation_started'
        ]);
        $studentNotifyStmt->execute([$assignment['student_id'], $studentTitle, $studentMessage, $submission_id, $metadata]);
        
        $message = 'Assignment accepted successfully! You are now responsible for evaluating this submission.';
        
    } else { // deny
        // Deny this assignment
        $updateStmt = $pdo->prepare("
            UPDATE submission_assignments 
            SET status = 'denied', responded_at = NOW(), notes = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$notes, $assignment_id]);
        
        // Check if there are any other pending assignments for this submission
        $remainingStmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM submission_assignments 
            WHERE submission_id = ? AND status = 'pending'
        ");
        $remainingStmt->execute([$submission_id]);
        $remaining = $remainingStmt->fetch(PDO::FETCH_ASSOC);
        
        // If no pending assignments remain, mark submission as unassigned
        if ($remaining['count'] == 0) {
            $updateSubmissionStmt = $pdo->prepare("
                UPDATE submissions 
                SET status = 'pending'
                WHERE id = ?
            ");
            $updateSubmissionStmt->execute([$submission_id]);
            
            // Notify student that assignment needs reassignment
            $studentNotifyStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, related_id, created_at) 
                VALUES (?, 'assignment_denied', ?, ?, ?, NOW())
            ");
            $studentTitle = "Assignment Requires Attention";
            $studentMessage = "Your submission for {$subject_code} requires reassignment. Please contact your instructor.";
            $studentNotifyStmt->execute([$assignment['student_id'], $studentTitle, $studentMessage, $submission_id]);
        }
        
        $message = 'Assignment declined successfully.';
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => $message,
        'action' => $action,
        'reload' => true
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollback();
    
    error_log("Assignment handling error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}
?>