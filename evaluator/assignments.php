<?php
session_start();

// Debug: Check current session
error_log("Current session user_id: " . ($_SESSION['user_id'] ?? 'Not set'));
error_log("Current session role: " . ($_SESSION['role'] ?? 'Not set'));
error_log("Current session name: " . ($_SESSION['name'] ?? 'Not set'));
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';
require_once '../includes/functions.php';

// Get legacy PDF submission assignments (keep for backward compatibility)
$pendingQuery = "
    SELECT sa.id as assignment_id, sa.assigned_at, sa.notes as assignment_notes,
           s.id as submission_id, s.original_filename, s.file_size, s.created_at as submitted_at,
           s.pdf_url, sub.code as subject_code, sub.name as subject_name,
           u.name as student_name, u.email as student_email
    FROM submission_assignments sa
    INNER JOIN submissions s ON sa.submission_id = s.id
    INNER JOIN subjects sub ON s.subject_id = sub.id
    INNER JOIN users u ON s.student_id = u.id
    WHERE sa.evaluator_id = ? AND sa.status = 'pending'
    ORDER BY sa.assigned_at DESC
";

$pendingStmt = $pdo->prepare($pendingQuery);
$pendingStmt->execute([$_SESSION['user_id']]);
$pendingAssignments = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

// Get accepted legacy assignments (currently evaluating)
$acceptedQuery = "
    SELECT sa.id as assignment_id, sa.assigned_at, sa.responded_at, sa.notes as assignment_notes,
           sa.status as assignment_status,
           s.id as submission_id, s.original_filename, s.file_size, s.created_at as submitted_at,
           s.pdf_url, s.status, s.marks_obtained, s.max_marks, s.evaluator_remarks,
           s.evaluated_at,
           sub.code as subject_code, sub.name as subject_name,
           u.name as student_name, u.email as student_email,
           CASE 
               WHEN s.evaluation_status = 'evaluated' OR s.status = 'evaluated' THEN 'completed'
               ELSE 'in_progress'
           END as evaluation_status
    FROM submission_assignments sa
    INNER JOIN submissions s ON sa.submission_id = s.id
    INNER JOIN subjects sub ON s.subject_id = sub.id
    INNER JOIN users u ON s.student_id = u.id
    WHERE sa.evaluator_id = ? AND sa.status = 'accepted'
    ORDER BY CASE WHEN s.status = 'evaluated' THEN s.evaluated_at ELSE sa.responded_at END DESC
";

$acceptedStmt = $pdo->prepare($acceptedQuery);
$acceptedStmt->execute([$_SESSION['user_id']]);
$acceptedAssignments = $acceptedStmt->fetchAll(PDO::FETCH_ASSOC);

// Get legacy statistics

include '../includes/header.php';
?>

<style>
.assignment-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid #667eea;
    margin-bottom: 1.5rem;
}

.assignment-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.assignment-card.pending {
    border-left-color: #ffc107;
}

.assignment-card.accepted {
    border-left-color: #28a745;
}

.stat-widget {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 1.5rem;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.stat-widget::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.btn-accept {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    color: white;
}

.btn-deny {
    background: linear-gradient(135deg, #dc3545, #fd7e14);
    border: none;
    color: white;
}

.btn-accept:hover, .btn-deny:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    color: white;
}

.badge-pending {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
}

.badge-accepted {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.badge-completed {
    background: linear-gradient(135deg, #6f42c1, #007bff);
}

.assignment-card.completed {
    border-left-color: #6f42c1;
    background: linear-gradient(135deg, rgba(111, 66, 193, 0.05), rgba(0, 123, 255, 0.05));
}

.file-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
}

.modal-assignment {
    backdrop-filter: blur(10px);
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2"><i class="fas fa-tasks text-primary"></i> Assignment Management</h1>
                    <p class="text-muted mb-0">Manage your evaluation assignments</p>
                </div>
                <!-- <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="pending_evaluations.php" class="btn btn-primary">
                        <i class="fas fa-clock"></i> Evaluations
                    </a>
                </div> -->
            </div>
        </div>
    </div>

    


    <!-- Legacy PDF Assignments - Pending -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="fas fa-hourglass-half text-warning"></i> 
                Pending Answersheet Submissions
                <span class="badge bg-warning"><?= count($pendingAssignments) ?></span>
            </h4>
            <?php if (!empty($pendingAssignments)): ?>
                <?php foreach ($pendingAssignments as $assignment): ?>
                    <div class="assignment-card pending">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-2">
                                    <span class="badge badge-pending">PENDING</span>
                                    <?= htmlspecialchars($assignment['subject_code']) ?> - <?= htmlspecialchars($assignment['subject_name']) ?>
                                </h5>
                                <div class="mb-2">
                                    <strong>Student:</strong> <?= htmlspecialchars($assignment['student_name']) ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Submitted:</strong> 
                                    <span class="text-muted"><?= date('M j, Y g:i A', strtotime($assignment['submitted_at'])) ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong>Assigned:</strong> 
                                    <span class="text-muted"><?= date('M j, Y g:i A', strtotime($assignment['assigned_at'])) ?></span>
                                </div>
                            </div>
                            <!-- <div class="col-md-3">
                                <div class="file-info">
                                    <div class="small text-muted mb-1">File Details</div>
                                    <div class="fw-bold"><?= htmlspecialchars($assignment['original_filename'] ?: 'submission.pdf') ?></div>
                                    <div class="small text-muted">
                                        Size: <?= number_format($assignment['file_size'] / 1024, 2) ?> KB
                                    </div>
                                    <?php if ($assignment['pdf_url']): ?>
                                        <a href="<?= htmlspecialchars($assignment['pdf_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="fas fa-file-pdf"></i> View PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div> -->
                            <div class="col-md-3 text-end">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-accept" onclick="handleAssignment(<?= $assignment['assignment_id'] ?>, 'accept')">
                                        <i class="fas fa-check"></i> Accept Assignment
                                    </button>
                                    <button class="btn btn-deny" onclick="handleAssignment(<?= $assignment['assignment_id'] ?>, 'deny')">
                                        <i class="fas fa-times"></i> Decline Assignment
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="showDetails(<?= $assignment['assignment_id'] ?>, '<?= htmlspecialchars($assignment['student_name']) ?>', '<?= htmlspecialchars($assignment['subject_code']) ?>')">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="assignment-card">
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Pending PDF Assignments</h5>
                        <p class="text-muted">You have no pending PDF assignment requests at the moment.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Legacy PDF Assignments - Accepted -->
    <div class="row">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="fas fa-tasks text-success"></i> 
                Evaluation Answersheet
                <span class="badge bg-success"><?= count($acceptedAssignments) ?></span>
            </h4>
            <?php if (!empty($acceptedAssignments)): ?>
                <?php foreach ($acceptedAssignments as $assignment): ?>
                    <div class="assignment-card <?= $assignment['evaluation_status'] === 'completed' ? 'completed' : 'accepted' ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-2">
                                    <?php if ($assignment['evaluation_status'] === 'completed'): ?>
                                        <span class="badge badge-completed">COMPLETED</span>
                                    <?php else: ?>
                                        <span class="badge badge-accepted">IN PROGRESS</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($assignment['subject_code']) ?> - <?= htmlspecialchars($assignment['subject_name']) ?>
                                </h5>
                                <div class="mb-2">
                                    <strong>Student:</strong> <?= htmlspecialchars($assignment['student_name']) ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Accepted:</strong> 
                                    <span class="text-muted"><?= date('M j, Y g:i A', strtotime($assignment['responded_at'])) ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong>Status:</strong> 
                                    <?php if ($assignment['evaluation_status'] === 'completed'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>Evaluation Completed
                                        </span>
                                        <?php if ($assignment['evaluated_at']): ?>
                                            <div class="small text-muted mt-1">
                                                <i class="fas fa-calendar-check me-1"></i>
                                                Completed: <?= date('M j, Y g:i A', strtotime($assignment['evaluated_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-clock me-1"></i>In Progress
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($assignment['evaluation_status'] === 'completed' && $assignment['marks_obtained'] !== null): ?>
                                <div class="mb-2">
                                    <strong>Final Score:</strong> 
                                    <span class="text-primary fw-bold">
                                        <?= number_format($assignment['marks_obtained'], 2) ?> / <?= number_format($assignment['max_marks'], 2) ?>
                                        <?php 
                                        $percentage = $assignment['max_marks'] > 0 ? ($assignment['marks_obtained'] / $assignment['max_marks']) * 100 : 0;
                                        ?>
                                        (<?= number_format($percentage, 1) ?>%)
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <!-- <div class="file-info">
                                    <div class="small text-muted mb-1">File Details</div>
                                    <div class="fw-bold"><?= htmlspecialchars($assignment['original_filename'] ?: 'submission.pdf') ?></div>
                                    <div class="small text-muted">
                                        Size: <?= number_format($assignment['file_size'] / 1024, 2) ?> KB
                                    </div>
                                    <?php if ($assignment['pdf_url']): ?>
                                        <a href="<?= htmlspecialchars($assignment['pdf_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="fas fa-file-pdf"></i> View PDF
                                        </a>
                                    <?php endif; ?>
                                </div> -->
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="d-grid gap-2">
                                    <?php if ($assignment['evaluation_status'] === 'completed'): ?>
                                        <a href="evaluate.php?id=<?= $assignment['submission_id'] ?>" class="btn btn-success">
                                            <i class="fas fa-check-circle"></i> View Completed Evaluation
                                        </a>
                                        <button class="btn btn-outline-success btn-sm" onclick="showEvaluationSummary(<?= $assignment['assignment_id'] ?>)">
                                            <i class="fas fa-eye"></i> View Summary
                                        </button>
                                        <div class="small text-success mt-1">
                                            <i class="fas fa-calendar-check"></i> 
                                            Evaluation Completed
                                        </div>
                                    <?php else: ?>
                                        <a href="evaluate.php?id=<?= $assignment['submission_id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Continue Evaluation
                                        </a>
                                        <div class="small text-info mt-1">
                                            <i class="fas fa-clock"></i> 
                                            Evaluation in Progress
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="assignment-card">
                    <div class="text-center py-4">
                        <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Accepted PDF Assignments</h5>
                        <p class="text-muted">You haven't accepted any PDF assignments yet.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assignment Action Modal -->
<div class="modal fade modal-assignment" id="assignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignmentModalTitle">Assignment Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignmentForm">
                <?php csrf_input(); ?>
                <div class="modal-body">
                    <input type="hidden" id="assignmentId" name="assignment_id">
                    <input type="hidden" id="assignmentAction" name="action">
                    
                    <div class="mb-3">
                        <label for="assignmentNotes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="assignmentNotes" name="notes" rows="3" 
                                  placeholder="Add any notes about your decision..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> <span id="actionNote"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="confirmBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Evaluation Summary Modal -->
<div class="modal fade" id="evaluationSummaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-clipboard-check me-2"></i>Evaluation Summary
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="evaluationSummaryContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2">Loading evaluation summary...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Define functions in global scope to be available immediately
function handleAssignment(assignmentId, action) {
    console.log('handleAssignment called with:', assignmentId, action);
    
    document.getElementById('assignmentId').value = assignmentId;
    document.getElementById('assignmentAction').value = action;
    
    const title = action === 'accept' ? 'Accept Assignment' : 'Decline Assignment';
    const btnClass = action === 'accept' ? 'btn-success' : 'btn-danger';
    const note = action === 'accept' ? 
        'By accepting this assignment, all other evaluators will be automatically denied access to this submission.' :
        'By declining this assignment, you will not be able to evaluate this submission.';
    
    document.getElementById('assignmentModalTitle').textContent = title;
    document.getElementById('confirmBtn').textContent = action === 'accept' ? 'Accept' : 'Decline';
    document.getElementById('confirmBtn').className = 'btn ' + btnClass;
    document.getElementById('actionNote').textContent = note;
    
    // Use the global modal reference or create new one
    const modal = window.currentModal || new bootstrap.Modal(document.getElementById('assignmentModal'));
    modal.show();
}

function showDetails(assignmentId, studentName, subjectCode) {
    alert(`Assignment Details:\nStudent: ${studentName}\nSubject: ${subjectCode}\nAssignment ID: ${assignmentId}`);
}

function showEvaluationSummary(assignmentId) {
    const modal = new bootstrap.Modal(document.getElementById('evaluationSummaryModal'));
    modal.show();
    
    // Load evaluation summary data
    fetch('get_evaluation_summary.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `assignment_id=${assignmentId}`
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('evaluationSummaryContent').innerHTML = data;
    })
    .catch(error => {
        document.getElementById('evaluationSummaryContent').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Error loading evaluation summary: ${error.message}
            </div>`;
    });
}



function showAlert(message, type) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertAdjacentHTML('afterbegin', alertHtml);
    
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal
    if (typeof currentModal === 'undefined') {
        window.currentModal = new bootstrap.Modal(document.getElementById('assignmentModal'));
    }
    
    // Add form event listener
    const assignmentForm = document.getElementById('assignmentForm');
    if (assignmentForm) {
        assignmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const confirmBtn = document.getElementById('confirmBtn');
            const originalText = confirmBtn.textContent;
            
            // Debug logging
            console.log('Form submission started');
            console.log('Assignment ID:', formData.get('assignment_id'));
            console.log('Action:', formData.get('action'));
            
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            fetch('handle_assignment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                console.log('Parsed data:', data);
                if (data.status === 'success') {
                    currentModal.hide();
                    showAlert(data.message, 'success');
                    if (data.reload) {
                        setTimeout(() => location.reload(), 1500);
                    }
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showAlert('An error occurred: ' + error.message, 'error');
            })
            .finally(() => {
                confirmBtn.disabled = false;
                confirmBtn.textContent = originalText;
            });
        });
    }
});
</script>

<?php include('../includes/footer.php'); ?>
<script>
// Auto-reload the page every 10 seconds
setInterval(function() {
    window.location.reload();
}, 10000);
</script>