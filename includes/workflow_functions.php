<?php
/**
 * Workflow Management Functions
 * Handles all workflow transitions, logging, and notifications
 * Date: November 12, 2025
 */

// ============================================================================
// WORKFLOW STATUS CONSTANTS
// ============================================================================

define('STATUS_SUBMITTED', 'Submitted');
define('STATUS_UNDER_EVALUATION', 'Under Evaluation');
define('STATUS_EVALUATED_PENDING_MODERATION', 'Evaluated (Pending Moderation)');
define('STATUS_UNDER_MODERATION', 'Under Moderation');
define('STATUS_MODERATION_COMPLETED', 'Moderation Completed');
define('STATUS_RESULT_PUBLISHED', 'Result Published');
define('STATUS_REJECTED', 'Rejected');
define('STATUS_REVISION_REQUIRED', 'Revision Required');

// Notification types
define('NOTIF_SUBMISSION_RECEIVED', 'submission_received');
define('NOTIF_EVALUATION_COMPLETED', 'evaluation_completed');
define('NOTIF_MODERATION_COMPLETED', 'moderation_completed');
define('NOTIF_RESULT_PUBLISHED', 'result_published');
define('NOTIF_REVISION_REQUIRED', 'revision_required');
define('NOTIF_SUBMISSION_REJECTED', 'submission_rejected');

// ============================================================================
// WORKFLOW TRANSITION FUNCTIONS
// ============================================================================

/**
 * Transition submission to a new status with logging
 */
function transition_submission_status($conn, $submission_id, $new_status, $user_id, $user_role, $notes = null) {
    // Get current status
    $query = "SELECT status, student_id, evaluator_id, moderator_id FROM submissions WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $submission = $result->fetch_assoc();
    
    if (!$submission) {
        return ['success' => false, 'message' => 'Submission not found'];
    }
    
    $old_status = $submission['status'];
    
    // Validate transition
    $validation = validate_status_transition($old_status, $new_status, $user_role);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update submission status
        $update_query = "UPDATE submissions SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $submission_id);
        $stmt->execute();
        
        // Log the transition
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $action = get_action_description($old_status, $new_status);
        
        $log_query = "INSERT INTO workflow_logs (submission_id, user_id, user_role, from_status, to_status, action, notes, ip_address) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param("iissssss", $submission_id, $user_id, $user_role, $old_status, $new_status, $action, $notes, $ip_address);
        $stmt->execute();
        
        // Create notifications based on new status
        create_status_notifications($conn, $submission_id, $new_status, $submission);
        
        // Commit transaction
        $conn->commit();
        
        return ['success' => true, 'message' => 'Status updated successfully', 'old_status' => $old_status, 'new_status' => $new_status];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()];
    }
}

/**
 * Validate if status transition is allowed
 */
function validate_status_transition($from_status, $to_status, $user_role) {
    $valid_transitions = [
        // Student submissions
        STATUS_SUBMITTED => [STATUS_UNDER_EVALUATION, STATUS_REJECTED],
        
        // Evaluator actions
        STATUS_UNDER_EVALUATION => [STATUS_EVALUATED_PENDING_MODERATION, STATUS_SUBMITTED],
        
        // Moderator actions
        STATUS_EVALUATED_PENDING_MODERATION => [STATUS_UNDER_MODERATION, STATUS_REVISION_REQUIRED, STATUS_REJECTED],
        STATUS_UNDER_MODERATION => [STATUS_MODERATION_COMPLETED, STATUS_REVISION_REQUIRED],
        STATUS_MODERATION_COMPLETED => [STATUS_RESULT_PUBLISHED, STATUS_UNDER_MODERATION],
        
        // Revision flow
        STATUS_REVISION_REQUIRED => [STATUS_UNDER_EVALUATION, STATUS_EVALUATED_PENDING_MODERATION],
        
        // Final states
        STATUS_RESULT_PUBLISHED => [], // Final state, no transitions
        STATUS_REJECTED => [], // Final state, no transitions
    ];
    
    // Check if transition exists
    if (!isset($valid_transitions[$from_status])) {
        return ['valid' => false, 'message' => 'Invalid current status'];
    }
    
    if (!in_array($to_status, $valid_transitions[$from_status])) {
        return ['valid' => false, 'message' => "Transition from '$from_status' to '$to_status' is not allowed"];
    }
    
    // Role-based validation
    $role_permissions = [
        'student' => [STATUS_SUBMITTED],
        'evaluator' => [STATUS_UNDER_EVALUATION, STATUS_EVALUATED_PENDING_MODERATION],
        'moderator' => [STATUS_UNDER_MODERATION, STATUS_MODERATION_COMPLETED, STATUS_RESULT_PUBLISHED, STATUS_REVISION_REQUIRED, STATUS_REJECTED],
        'admin' => 'all' // Admin can do anything
    ];
    
    if ($user_role !== 'admin') {
        if (!isset($role_permissions[$user_role]) || !in_array($to_status, $role_permissions[$user_role])) {
            return ['valid' => false, 'message' => "User role '$user_role' cannot set status to '$to_status'"];
        }
    }
    
    return ['valid' => true, 'message' => 'Transition allowed'];
}

/**
 * Get action description for logging
 */
function get_action_description($from_status, $to_status) {
    $actions = [
        STATUS_SUBMITTED => 'Submission created',
        STATUS_UNDER_EVALUATION => 'Evaluation started',
        STATUS_EVALUATED_PENDING_MODERATION => 'Evaluation completed, pending moderation',
        STATUS_UNDER_MODERATION => 'Moderation started',
        STATUS_MODERATION_COMPLETED => 'Moderation completed',
        STATUS_RESULT_PUBLISHED => 'Results published to student',
        STATUS_REVISION_REQUIRED => 'Revision requested',
        STATUS_REJECTED => 'Submission rejected'
    ];
    
    return $actions[$to_status] ?? "Status changed from $from_status to $to_status";
}

// ============================================================================
// EVALUATION LOCK FUNCTIONS
// ============================================================================

/**
 * Check if evaluation is locked
 */
function is_evaluation_locked($conn, $submission_id) {
    $query = "SELECT evaluation_locked FROM submissions WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? (bool)$row['evaluation_locked'] : false;
}

/**
 * Lock evaluation (prevent evaluator from editing)
 */
function lock_evaluation($conn, $submission_id, $locked_by_user_id, $locked_by_role, $reason = null) {
    $conn->begin_transaction();
    
    try {
        // Update submission
        $update_query = "UPDATE submissions SET evaluation_locked = TRUE WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        
        // Create lock record
        $lock_query = "INSERT INTO evaluation_locks (submission_id, locked_by, locked_by_user_id, reason) 
                       VALUES (?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE 
                       locked_by = VALUES(locked_by),
                       locked_at = CURRENT_TIMESTAMP,
                       locked_by_user_id = VALUES(locked_by_user_id),
                       reason = VALUES(reason)";
        $stmt = $conn->prepare($lock_query);
        $stmt->bind_param("isis", $submission_id, $locked_by_role, $locked_by_user_id, $reason);
        $stmt->execute();
        
        $conn->commit();
        return ['success' => true, 'message' => 'Evaluation locked successfully'];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Failed to lock evaluation: ' . $e->getMessage()];
    }
}

/**
 * Unlock evaluation (allow evaluator to edit again)
 */
function unlock_evaluation($conn, $submission_id) {
    $conn->begin_transaction();
    
    try {
        $update_query = "UPDATE submissions SET evaluation_locked = FALSE WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        
        $delete_query = "DELETE FROM evaluation_locks WHERE submission_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        
        $conn->commit();
        return ['success' => true, 'message' => 'Evaluation unlocked successfully'];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Failed to unlock evaluation: ' . $e->getMessage()];
    }
}

// ============================================================================
// NOTIFICATION FUNCTIONS
// ============================================================================

/**
 * Create notifications based on status change
 */
function create_status_notifications($conn, $submission_id, $new_status, $submission_data) {
    $notifications = [];
    
    switch ($new_status) {
        case STATUS_SUBMITTED:
        case STATUS_UNDER_EVALUATION:
            // Notify evaluator
            if ($submission_data['evaluator_id']) {
                $notifications[] = [
                    'user_id' => $submission_data['evaluator_id'],
                    'type' => NOTIF_SUBMISSION_RECEIVED,
                    'title' => 'New Submission Assigned',
                    'message' => "A new submission #{$submission_id} has been assigned to you for evaluation."
                ];
            }
            break;
            
        case STATUS_EVALUATED_PENDING_MODERATION:
            // Notify moderator and student
            if ($submission_data['moderator_id']) {
                $notifications[] = [
                    'user_id' => $submission_data['moderator_id'],
                    'type' => NOTIF_EVALUATION_COMPLETED,
                    'title' => 'Evaluation Completed - Pending Your Review',
                    'message' => "Submission #{$submission_id} has been evaluated and is pending your moderation."
                ];
            }
            if ($submission_data['student_id']) {
                $notifications[] = [
                    'user_id' => $submission_data['student_id'],
                    'type' => NOTIF_EVALUATION_COMPLETED,
                    'title' => 'Your Submission Has Been Evaluated',
                    'message' => "Your submission #{$submission_id} has been evaluated. Results will be available after moderator review."
                ];
            }
            break;
            
        case STATUS_MODERATION_COMPLETED:
            // Notify student (results not yet visible)
            if ($submission_data['student_id']) {
                $notifications[] = [
                    'user_id' => $submission_data['student_id'],
                    'type' => NOTIF_MODERATION_COMPLETED,
                    'title' => 'Moderation Completed',
                    'message' => "Your submission #{$submission_id} has been moderated. Results will be published soon."
                ];
            }
            break;
            
        case STATUS_RESULT_PUBLISHED:
            // Notify student (results now visible)
            if ($submission_data['student_id']) {
                $notifications[] = [
                    'user_id' => $submission_data['student_id'],
                    'type' => NOTIF_RESULT_PUBLISHED,
                    'title' => 'Your Results Are Now Available!',
                    'message' => "Your results for submission #{$submission_id} have been published. You can now view your marks and feedback."
                ];
            }
            break;
            
        case STATUS_REVISION_REQUIRED:
            // Notify evaluator
            if ($submission_data['evaluator_id']) {
                $notifications[] = [
                    'user_id' => $submission_data['evaluator_id'],
                    'type' => NOTIF_REVISION_REQUIRED,
                    'title' => 'Revision Required',
                    'message' => "The moderator has requested a revision for submission #{$submission_id}. Please review and update your evaluation."
                ];
            }
            break;
            
        case STATUS_REJECTED:
            // Notify student
            if ($submission_data['student_id']) {
                $notifications[] = [
                    'user_id' => $submission_data['student_id'],
                    'type' => NOTIF_SUBMISSION_REJECTED,
                    'title' => 'Submission Rejected',
                    'message' => "Your submission #{$submission_id} has been rejected. Please check the remarks for details."
                ];
            }
            break;
    }
    
    // Insert all notifications
    foreach ($notifications as $notif) {
        create_workflow_notification(
            $conn,
            $submission_id,
            $notif['user_id'],
            $notif['type'],
            $notif['title'],
            $notif['message']
        );
    }
}

/**
 * Create a workflow notification
 */
function create_workflow_notification($conn, $submission_id, $user_id, $type, $title, $message) {
    $query = "INSERT INTO workflow_notifications (submission_id, user_id, notification_type, title, message) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisss", $submission_id, $user_id, $type, $title, $message);
    $stmt->execute();
    
    // Send email if auto-notify is enabled
    $setting = get_workflow_setting($conn, 'auto_notify_email');
    if ($setting == '1') {
        send_workflow_email($conn, $submission_id, $user_id, $title, $message);
    }
}

/**
 * Send workflow notification email
 */
function send_workflow_email($conn, $submission_id, $user_id, $title, $message) {
    // Get user email
    $query = "SELECT email, name FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user || !$user['email']) {
        return false;
    }
    
    $to = $user['email'];
    $subject = $title;
    $email_message = "Dear {$user['name']},\n\n";
    $email_message .= $message . "\n\n";
    $email_message .= "Please log in to your dashboard to view more details.\n\n";
    $email_message .= "Best regards,\n";
    $email_message .= "Evaluation System";
    
    $headers = "From: noreply@student-app.local\r\n";
    $headers .= "Reply-To: noreply@student-app.local\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $sent = @mail($to, $subject, $email_message, $headers);
    
    // Update notification record
    if ($sent) {
        $update_query = "UPDATE workflow_notifications 
                        SET is_emailed = TRUE, email_sent_at = NOW() 
                        WHERE submission_id = ? AND user_id = ? AND title = ?
                        ORDER BY created_at DESC LIMIT 1";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("iis", $submission_id, $user_id, $title);
        $stmt->execute();
    }
    
    return $sent;
}

/**
 * Get unread notifications for a user
 */
function get_user_notifications($conn, $user_id, $limit = 10, $unread_only = false) {
    $query = "SELECT wn.*, s.submission_title, sub.name as subject_name
              FROM workflow_notifications wn
              LEFT JOIN submissions s ON wn.submission_id = s.id
              LEFT JOIN subjects sub ON s.subject_id = sub.id
              WHERE wn.user_id = ?";
    
    if ($unread_only) {
        $query .= " AND wn.is_read = FALSE";
    }
    
    $query .= " ORDER BY wn.created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Mark notification as read
 */
function mark_notification_read($conn, $notification_id, $user_id) {
    $query = "UPDATE workflow_notifications 
              SET is_read = TRUE, read_at = NOW() 
              WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

// ============================================================================
// WORKFLOW SETTINGS FUNCTIONS
// ============================================================================

/**
 * Get a workflow setting value
 */
function get_workflow_setting($conn, $setting_key, $default = null) {
    $query = "SELECT setting_value FROM workflow_settings WHERE setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $setting_key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['setting_value'] : $default;
}

/**
 * Update a workflow setting
 */
function update_workflow_setting($conn, $setting_key, $setting_value, $user_id = null) {
    $query = "UPDATE workflow_settings 
              SET setting_value = ?, updated_by = ?, updated_at = NOW() 
              WHERE setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sis", $setting_value, $user_id, $setting_key);
    return $stmt->execute();
}

// ============================================================================
// VISIBILITY CONTROL FUNCTIONS
// ============================================================================

/**
 * Check if results are visible to student
 */
function are_results_visible_to_student($conn, $submission_id) {
    $query = "SELECT results_visible_to_student FROM submissions WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? (bool)$row['results_visible_to_student'] : false;
}

/**
 * Check if annotated PDF is visible to student
 */
function is_annotated_pdf_visible_to_student($conn, $submission_id) {
    $query = "SELECT annotated_pdf_visible_to_student FROM submissions WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? (bool)$row['annotated_pdf_visible_to_student'] : false;
}

/**
 * Set result visibility for student
 */
function set_result_visibility($conn, $submission_id, $visible) {
    $query = "UPDATE submissions SET results_visible_to_student = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $visible_int = $visible ? 1 : 0;
    $stmt->bind_param("ii", $visible_int, $submission_id);
    return $stmt->execute();
}

/**
 * Set annotated PDF visibility for student
 */
function set_annotated_pdf_visibility($conn, $submission_id, $visible) {
    $query = "UPDATE submissions SET annotated_pdf_visible_to_student = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $visible_int = $visible ? 1 : 0;
    $stmt->bind_param("ii", $visible_int, $submission_id);
    return $stmt->execute();
}

// ============================================================================
// AUTO-ASSIGNMENT FUNCTIONS
// ============================================================================

/**
 * Auto-assign submission to subject evaluator
 */
function auto_assign_evaluator($conn, $submission_id, $subject_id) {
    // Get active evaluator for this subject
    $query = "SELECT u.id as evaluator_id
              FROM users u
              INNER JOIN evaluator_subjects es ON u.id = es.evaluator_id
              WHERE es.subject_id = ? 
              AND u.role = 'evaluator' 
              AND u.is_active = 1
              AND es.is_active = 1
              ORDER BY (
                  SELECT COUNT(*) 
                  FROM submissions s 
                  WHERE s.evaluator_id = u.id 
                  AND s.status IN ('Submitted', 'Under Evaluation')
              ) ASC
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $evaluator = $result->fetch_assoc();
    
    if (!$evaluator) {
        return ['success' => false, 'message' => 'No active evaluator found for this subject'];
    }
    
    // Get moderator for this evaluator
    $mod_query = "SELECT moderator_id FROM users WHERE id = ?";
    $stmt = $conn->prepare($mod_query);
    $stmt->bind_param("i", $evaluator['evaluator_id']);
    $stmt->execute();
    $mod_result = $stmt->get_result();
    $mod_data = $mod_result->fetch_assoc();
    $moderator_id = $mod_data['moderator_id'] ?? null;
    
    // Update submission with evaluator and moderator
    $update_query = "UPDATE submissions 
                     SET evaluator_id = ?, moderator_id = ? 
                     WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iii", $evaluator['evaluator_id'], $moderator_id, $submission_id);
    
    if ($stmt->execute()) {
        return [
            'success' => true, 
            'evaluator_id' => $evaluator['evaluator_id'],
            'moderator_id' => $moderator_id,
            'message' => 'Evaluator assigned successfully'
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to assign evaluator'];
    }
}

// ============================================================================
// MODERATION HISTORY FUNCTIONS
// ============================================================================

/**
 * Record moderation action
 */
function record_moderation_action($conn, $submission_id, $moderator_id, $action, $original_marks = null, $adjusted_marks = null, $reason = null, $notes = null) {
    $query = "INSERT INTO moderation_history 
              (submission_id, moderator_id, action, original_marks, adjusted_marks, adjustment_reason, moderation_notes) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisddss", $submission_id, $moderator_id, $action, $original_marks, $adjusted_marks, $reason, $notes);
    return $stmt->execute();
}

/**
 * Get moderation history for a submission
 */
function get_moderation_history($conn, $submission_id) {
    $query = "SELECT mh.*, u.name as moderator_name
              FROM moderation_history mh
              LEFT JOIN users u ON mh.moderator_id = u.id
              WHERE mh.submission_id = ?
              ORDER BY mh.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>
