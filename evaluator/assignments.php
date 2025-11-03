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

<link href="css/evaluator-style.css" rel="stylesheet">
<style>
    /* Table responsive styling */
    .table-responsive {
        margin-top: 1rem;
    }
    
    .table th {
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.75rem;
        vertical-align: middle;
    }
    
    .table td {
        padding: 0.75rem;
        vertical-align: middle;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
        cursor: pointer;
    }
    
    /* Badge styling */
    .badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.625rem;
        font-weight: 600;
        color: #ffffff !important;
    }
    
    /* Mobile responsive */
    @media (max-width: 991.98px) {
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            min-width: 900px;
        }
        
        .table th,
        .table td {
            padding: 0.5rem;
            font-size: 0.875rem;
        }
        
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .btn-group .btn i {
            font-size: 0.75rem;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-card h5 {
            font-size: 1rem;
        }
        
        .table th,
        .table td {
            padding: 0.375rem;
            font-size: 0.8rem;
        }
        
        .btn-group {
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        
        .btn-group .btn {
            padding: 0.25rem 0.375rem;
            font-size: 0.7rem;
        }
        
        .small {
            font-size: 0.7rem;
        }
    }
</style>

<div class="evaluator-content">
<div class="container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-tasks me-2"></i>Assignment Management</h1>
                <p>Manage your evaluation assignments</p>
            </div>
        </div>
    </div>

    


    <!-- Legacy PDF Assignments - Pending -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card">
                <h5>
                    <i class="fas fa-hourglass-half me-2"></i>Pending Answersheet Submissions
                    <span class="badge bg-warning"><?= count($pendingAssignments) ?></span>
                </h5>
            <?php if (!empty($pendingAssignments)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Status</th>
                                <th>Subject</th>
                                <th>Student</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingAssignments as $assignment): ?>
                            <tr>
                                <td><span class="badge bg-warning">PENDING</span></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($assignment['subject_code']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($assignment['subject_name']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($assignment['student_name']) ?></td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($assignment['submitted_at'])) ?></div>
                                    <div class="small text-muted"><?= date('g:i A', strtotime($assignment['submitted_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-success btn-sm" onclick="handleAssignment(<?= $assignment['assignment_id'] ?>, 'accept')">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="handleAssignment(<?= $assignment['assignment_id'] ?>, 'deny')">
                                            <i class="fas fa-times"></i> Decline
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No pending assignments at the moment.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Legacy PDF Assignments - Accepted -->
    <div class="row">
        <div class="col-12">
            <div class="dashboard-card">
                <h5>
                    <i class="fas fa-tasks me-2"></i>Evaluation Answersheet
                    <span class="badge bg-success"><?= count($acceptedAssignments) ?></span>
                </h5>
            <?php if (!empty($acceptedAssignments)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Status</th>
                                <th>Subject</th>
                                <th>Student</th>
                                <th>Accepted</th>
                                <th>Evaluation Status</th>
                                <th>Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($acceptedAssignments as $assignment): ?>
                            <tr>
                                <td>
                                    <?php if ($assignment['evaluation_status'] === 'completed'): ?>
                                        <span class="badge bg-success">COMPLETED</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">IN PROGRESS</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($assignment['subject_code']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($assignment['subject_name']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($assignment['student_name']) ?></td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($assignment['responded_at'])) ?></div>
                                    <div class="small text-muted"><?= date('g:i A', strtotime($assignment['responded_at'])) ?></div>
                                </td>
                                <td>
                                    <?php if ($assignment['evaluation_status'] === 'completed'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>Completed
                                        </span>
                                        <?php if ($assignment['evaluated_at']): ?>
                                            <div class="small text-muted mt-1">
                                                <?= date('M j, Y g:i A', strtotime($assignment['evaluated_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-clock me-1"></i>In Progress
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($assignment['evaluation_status'] === 'completed' && $assignment['marks_obtained'] !== null): ?>
                                        <div class="text-primary fw-bold">
                                            <?= number_format($assignment['marks_obtained'], 2) ?> / <?= number_format($assignment['max_marks'], 2) ?>
                                        </div>
                                        <?php 
                                        $percentage = $assignment['max_marks'] > 0 ? ($assignment['marks_obtained'] / $assignment['max_marks']) * 100 : 0;
                                        ?>
                                        <div class="small text-muted">(<?= number_format($percentage, 1) ?>%)</div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($assignment['evaluation_status'] === 'completed'): ?>
                                            <a href="evaluate.php?id=<?= $assignment['submission_id'] ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <button class="btn btn-outline-success btn-sm" onclick="showEvaluationSummary(<?= $assignment['assignment_id'] ?>)">
                                                <i class="fas fa-chart-bar"></i> Summary
                                            </button>
                                        <?php else: ?>
                                            <a href="evaluate.php?id=<?= $assignment['submission_id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Continue
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No accepted assignments yet.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>
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