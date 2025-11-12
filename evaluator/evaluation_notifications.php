<?php
session_start();

// Check if user is logged in and is an evaluator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        if ($_POST['action'] === 'accept_assignment') {
            $assignment_id = (int)$_POST['assignment_id'];
            
            // Get assignment details
            $assignment_sql = "SELECT easa.*, as_main.id as answer_sheet_id, as_main.status as sheet_status
                             FROM evaluator_answer_sheet_assignments easa
                             JOIN answer_sheets as_main ON easa.answer_sheet_id = as_main.id
                             WHERE easa.id = ? AND easa.evaluator_id = ? AND easa.status = 'pending'";
            
            $stmt = $pdo->prepare($assignment_sql);
            $stmt->execute([$assignment_id, $_SESSION['user_id']]);
            $assignment = $stmt->fetch();
            
            if (!$assignment) {
                throw new Exception("Assignment not found or already processed.");
            }
            
            // Check if answer sheet is still available (not accepted by another evaluator)
            $check_sql = "SELECT COUNT(*) FROM evaluator_answer_sheet_assignments 
                         WHERE answer_sheet_id = ? AND status = 'accepted'";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$assignment['answer_sheet_id']]);
            $accepted_count = $check_stmt->fetchColumn();
            
            if ($accepted_count > 0) {
                throw new Exception("This answer sheet has already been accepted by another evaluator.");
            }
            
            // Accept the assignment
            $accept_sql = "UPDATE evaluator_answer_sheet_assignments 
                          SET status = 'accepted', accepted_at = NOW() 
                          WHERE id = ?";
            $accept_stmt = $pdo->prepare($accept_sql);
            $accept_stmt->execute([$assignment_id]);
            
            // Decline all other pending assignments for this answer sheet
            $decline_others_sql = "UPDATE evaluator_answer_sheet_assignments 
                                  SET status = 'auto_declined', accepted_at = NOW() 
                                  WHERE answer_sheet_id = ? AND id != ? AND status = 'pending'";
            $decline_stmt = $pdo->prepare($decline_others_sql);
            $decline_stmt->execute([$assignment['answer_sheet_id'], $assignment_id]);
            
            // Update answer sheet status
            $update_sheet_sql = "UPDATE answer_sheets 
                               SET status = 'assigned', assigned_evaluator_id = ? 
                               WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sheet_sql);
            $update_stmt->execute([$_SESSION['user_id'], $assignment['answer_sheet_id']]);
            
            // Create notification for student
            $student_notification_sql = "INSERT INTO notifications 
                                       (user_id, title, message, type, reference_id, created_at)
                                       SELECT as_main.student_id, 
                                              'Answer Sheet Assigned for Evaluation',
                                              CONCAT('Your answer sheet for \"', as_main.exam_title, '\" has been assigned to an evaluator and evaluation will begin soon.'),
                                              'evaluation_assigned',
                                              as_main.id,
                                              NOW()
                                       FROM answer_sheets as_main
                                       WHERE as_main.id = ?";
            
            $student_notif_stmt = $pdo->prepare($student_notification_sql);
            $student_notif_stmt->execute([$assignment['answer_sheet_id']]);

            // Send acceptance email to student (no marks)
            try {
                $details_sql = "SELECT u.name AS student_name, u.email AS student_email, s.name AS subject_name, s.code AS subject_code
                                FROM answer_sheets as_main
                                JOIN users u ON as_main.student_id = u.id
                                JOIN subjects s ON as_main.subject_id = s.id
                                WHERE as_main.id = ?";
                $details_stmt = $pdo->prepare($details_sql);
                $details_stmt->execute([$assignment['answer_sheet_id']]);
                $details = $details_stmt->fetch(PDO::FETCH_ASSOC);
                if ($details) {
                    require_once('../includes/mail_helper.php');
                    $emailResult = sendEvaluationAcceptedEmail(
                        $details['student_email'],
                        $details['student_name'],
                        $details['subject_code'],
                        $details['subject_name'],
                        $assignment['answer_sheet_id']
                    );
                    if (!$emailResult['success']) {
                        error_log('Failed to send acceptance email (answer_sheets flow): ' . $emailResult['message']);
                    }
                }
            } catch (Exception $e) {
                error_log('Error sending acceptance email (answer_sheets flow): ' . $e->getMessage());
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Assignment accepted successfully! You can now evaluate the answer sheet.',
                'redirect' => 'evaluate_answer_sheet.php?id=' . $assignment['answer_sheet_id']
            ]);
            
        } elseif ($_POST['action'] === 'decline_assignment') {
            $assignment_id = (int)$_POST['assignment_id'];
            $decline_reason = trim($_POST['decline_reason'] ?? '');
            
            // Decline the assignment
            $decline_sql = "UPDATE evaluator_answer_sheet_assignments 
                          SET status = 'declined', accepted_at = NOW(), notes = ? 
                          WHERE id = ? AND evaluator_id = ? AND status = 'pending'";
            
            $stmt = $pdo->prepare($decline_sql);
            $stmt->execute([$decline_reason, $assignment_id, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Assignment not found or already processed.");
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Assignment declined successfully.'
            ]);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Get pending assignment notifications for this evaluator
$notifications_sql = "SELECT easa.*, as_main.*, s.name as subject_name, s.code as subject_code,
                     u.name as student_name, u.email as student_email,
                     easa.assigned_at, easa.deadline
                     FROM evaluator_answer_sheet_assignments easa
                     JOIN answer_sheets as_main ON easa.answer_sheet_id = as_main.id
                     JOIN subjects s ON as_main.subject_id = s.id
                     JOIN users u ON as_main.student_id = u.id
                     WHERE easa.evaluator_id = ? AND easa.status = 'pending'
                     ORDER BY easa.assigned_at DESC";

$stmt = $pdo->prepare($notifications_sql);
$stmt->execute([$_SESSION['user_id']]);
$pending_assignments = $stmt->fetchAll();

// Get recent assignment history
$history_sql = "SELECT easa.*, as_main.*, s.name as subject_name, s.code as subject_code,
               u.name as student_name, easa.status, easa.accepted_at
               FROM evaluator_answer_sheet_assignments easa
               JOIN answer_sheets as_main ON easa.answer_sheet_id = as_main.id
               JOIN subjects s ON as_main.subject_id = s.id
               JOIN users u ON as_main.student_id = u.id
               WHERE easa.evaluator_id = ? AND easa.status IN ('accepted', 'declined', 'auto_declined')
               ORDER BY easa.accepted_at DESC LIMIT 10";

$history_stmt = $pdo->prepare($history_sql);
$history_stmt->execute([$_SESSION['user_id']]);
$assignment_history = $history_stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-1">
                                <i class="fas fa-bell text-primary me-2"></i>
                                Evaluation Notifications
                            </h4>
                            <p class="text-muted mb-0">Review and respond to evaluation assignment requests</p>
                        </div>
                        <div>
                            <span class="badge bg-primary fs-6">
                                <?= count($pending_assignments) ?> Pending
                            </span>
                            <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Assignments -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Pending Assignment Requests (<?= count($pending_assignments) ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($pending_assignments)): ?>
                        <?php foreach ($pending_assignments as $assignment): ?>
                            <div class="card border-start border-warning border-4 mb-3" data-assignment-id="<?= $assignment['id'] ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-start">
                                                <div class="avatar-circle me-3">
                                                    <i class="fas fa-user-graduate text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-2">
                                                        <?= htmlspecialchars($assignment['exam_title']) ?>
                                                        <span class="badge bg-info ms-2"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                                    </h6>
                                                    
                                                    <div class="row mb-2">
                                                        <div class="col-sm-6">
                                                            <small class="text-muted">
                                                                <i class="fas fa-user me-1"></i>
                                                                <strong>Student:</strong> <?= htmlspecialchars($assignment['student_name']) ?>
                                                            </small>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <small class="text-muted">
                                                                <i class="fas fa-book me-1"></i>
                                                                <strong>Subject:</strong> <?= htmlspecialchars($assignment['subject_name']) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-2">
                                                        <div class="col-sm-6">
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                <strong>Exam Date:</strong> <?= date('M j, Y', strtotime($assignment['exam_date'])) ?>
                                                            </small>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <strong>Submitted:</strong> <?= date('M j, Y g:i A', strtotime($assignment['submitted_at'])) ?>
                                                            </small>
                                                        </div>
                                                    </div>

                                                    <?php if ($assignment['deadline']): ?>
                                                    <div class="row mb-2">
                                                        <div class="col-12">
                                                            <small class="text-muted">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                                <strong>Response Deadline:</strong> 
                                                                <?= date('M j, Y', strtotime($assignment['deadline'])) ?>
                                                                <?php if (strtotime($assignment['deadline']) < time()): ?>
                                                                    <span class="text-danger">(Overdue)</span>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <?php if ($assignment['time_limit_minutes']): ?>
                                                    <div class="mb-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-stopwatch me-1"></i>
                                                            <strong>Time Taken:</strong> <?= $assignment['time_limit_minutes'] ?> minutes
                                                        </small>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="d-flex flex-column gap-2">
                                                <?php if ($assignment['pdf_path']): ?>
                                                <a href="<?= htmlspecialchars($assignment['pdf_path']) ?>" target="_blank" 
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-file-pdf me-1"></i> Preview Answer Sheet
                                                </a>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-success btn-sm accept-btn" 
                                                        data-assignment-id="<?= $assignment['id'] ?>">
                                                    <i class="fas fa-check me-1"></i> Accept Evaluation
                                                </button>
                                                
                                                <button type="button" class="btn btn-outline-danger btn-sm decline-btn" 
                                                        data-assignment-id="<?= $assignment['id'] ?>">
                                                    <i class="fas fa-times me-1"></i> Decline
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h5>No Pending Assignments</h5>
                            <p class="text-muted">You're all caught up! No evaluation requests at the moment.</p>
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="fas fa-tachometer-alt me-1"></i> Go to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignment History -->
    <?php if (!empty($assignment_history)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Assignment History
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Exam Title</th>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Action Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignment_history as $history): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($history['exam_title']) ?></div>
                                            <small class="text-muted">Exam: <?= date('M j, Y', strtotime($history['exam_date'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($history['student_name']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?= htmlspecialchars($history['subject_code']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($history['status'] === 'accepted'): ?>
                                                <span class="badge bg-success">Accepted</span>
                                            <?php elseif ($history['status'] === 'declined'): ?>
                                                <span class="badge bg-danger">Declined</span>
                                            <?php elseif ($history['status'] === 'auto_declined'): ?>
                                                <span class="badge bg-secondary">Auto-Declined</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M j, Y g:i A', strtotime($history['accepted_at'])) ?></td>
                                        <td>
                                            <?php if ($history['status'] === 'accepted'): ?>
                                                <a href="evaluate_answer_sheet.php?id=<?= $history['answer_sheet_id'] ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit me-1"></i> Evaluate
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Decline Reason Modal -->
<div class="modal fade" id="declineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Decline Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="decline-form">
                    <input type="hidden" id="decline-assignment-id">
                    <div class="mb-3">
                        <label for="decline-reason" class="form-label">Reason for declining (optional):</label>
                        <textarea class="form-control" id="decline-reason" rows="3" 
                                placeholder="Please provide a brief reason for declining this assignment..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-decline">
                    <i class="fas fa-times me-1"></i> Confirm Decline
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Accept assignment
    $('.accept-btn').click(function() {
        const assignmentId = $(this).data('assignment-id');
        const btn = $(this);
        const originalText = btn.html();
        
        if (confirm('Are you sure you want to accept this evaluation assignment?')) {
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Accepting...');
            
            $.ajax({
                url: 'evaluation_notifications.php',
                type: 'POST',
                data: {
                    action: 'accept_assignment',
                    assignment_id: assignmentId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showAlert('success', response.message);
                        
                        // Remove the assignment card with animation
                        const card = $(`[data-assignment-id="${assignmentId}"]`);
                        card.fadeOut(500, function() {
                            $(this).remove();
                            
                            // Check if no more pending assignments
                            if ($('.card[data-assignment-id]').length === 0) {
                                location.reload();
                            }
                        });
                        
                        // Redirect after showing success
                        setTimeout(() => {
                            window.location.href = response.redirect;
                        }, 1500);
                        
                    } else {
                        showAlert('danger', response.message);
                        btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    showAlert('danger', 'An error occurred. Please try again.');
                    btn.prop('disabled', false).html(originalText);
                }
            });
        }
    });
    
    // Decline assignment - show modal
    $('.decline-btn').click(function() {
        const assignmentId = $(this).data('assignment-id');
        $('#decline-assignment-id').val(assignmentId);
        $('#declineModal').modal('show');
    });
    
    // Confirm decline
    $('#confirm-decline').click(function() {
        const assignmentId = $('#decline-assignment-id').val();
        const reason = $('#decline-reason').val();
        const btn = $(this);
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Declining...');
        
        $.ajax({
            url: 'evaluation_notifications.php',
            type: 'POST',
            data: {
                action: 'decline_assignment',
                assignment_id: assignmentId,
                decline_reason: reason
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('info', response.message);
                    
                    // Remove the assignment card
                    const card = $(`[data-assignment-id="${assignmentId}"]`);
                    card.fadeOut(500, function() {
                        $(this).remove();
                        
                        // Check if no more pending assignments
                        if ($('.card[data-assignment-id]').length === 0) {
                            location.reload();
                        }
                    });
                    
                    $('#declineModal').modal('hide');
                    
                } else {
                    showAlert('danger', response.message);
                }
                btn.prop('disabled', false).html(originalText);
            },
            error: function() {
                showAlert('danger', 'An error occurred. Please try again.');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Auto-refresh every 60 seconds to check for new assignments
    setInterval(function() {
        // Only reload if there are no pending actions
        if (!$('button:disabled').length) {
            location.reload();
        }
    }, 60000);
});

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Remove existing alerts
    $('.alert').remove();
    
    // Add new alert at the top
    $('.container-fluid').prepend(alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.avatar-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2em;
}

.border-start.border-warning.border-4 {
    border-left-width: 4px !important;
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.btn-sm {
    font-size: 0.875rem;
}

.table tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.05);
}

.badge {
    font-size: 0.75em;
}
</style>

<?php include '../includes/footer.php'; ?>