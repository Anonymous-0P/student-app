<?php
// Include config first to set headers before any output
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

// Include header after authentication check
include('../includes/header.php');

$moderator_id = $_SESSION['user_id'];
$success = $error = '';

// Handle mark override
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['override_marks'])) {
    $submission_id = (int)$_POST['submission_id'];
    $new_marks = (float)$_POST['new_marks'];
    $remarks = trim($_POST['remarks']);
    
    // Update submission marks
    $stmt = $conn->prepare("UPDATE submissions SET marks_obtained = ?, moderator_remarks = ?, status = 'approved' WHERE id = ?");
    $stmt->bind_param("dsi", $new_marks, $remarks, $submission_id);
    
    if($stmt->execute()) {
        // Record in marks history
        $stmt2 = $conn->prepare("INSERT INTO marks_history (submission_id, moderator_id, marks_given, max_marks, remarks, action_type, created_by_role) 
                                SELECT ?, ?, ?, max_marks, ?, 'revised', 'moderator' FROM submissions WHERE id = ?");
        $stmt2->bind_param("iddsi", $submission_id, $moderator_id, $new_marks, $remarks, $submission_id);
        $stmt2->execute();
        
        $success = "Marks successfully overridden and submission approved.";
    } else {
        $error = "Failed to update marks.";
    }
}

// Handle approval/rejection
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_submission'])) {
    $submission_id = (int)$_POST['submission_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $remarks = trim($_POST['remarks']);
    
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    $stmt = $conn->prepare("UPDATE submissions SET status = ?, moderator_remarks = ?, approved_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $remarks, $submission_id);
    
    if($stmt->execute()) {
        // Record in marks history
        $stmt2 = $conn->prepare("INSERT INTO marks_history (submission_id, moderator_id, marks_given, max_marks, remarks, action_type, created_by_role) 
                                SELECT ?, ?, marks_obtained, max_marks, ?, ?, 'moderator' FROM submissions WHERE id = ?");
        $stmt2->bind_param("iidsi", $submission_id, $moderator_id, $remarks, $action, $submission_id);
        $stmt2->execute();
        
        $success = "Submission " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";
    } else {
        $error = "Failed to update submission status.";
    }
}

// Get submissions for review
$filter_status = $_GET['status'] ?? 'evaluated';
$filter_subject = $_GET['subject'] ?? '';
$filter_evaluator = $_GET['evaluator'] ?? '';

$query = "SELECT s.id, s.submission_title, s.marks_obtained, s.max_marks, s.status, 
          s.evaluator_remarks, s.moderator_remarks, s.evaluated_at, s.created_at,
          u.name as student_name, u.roll_no,
          ev.name as evaluator_name,
          subj.name as subject_name, subj.code as subject_code,
          (s.marks_obtained / s.max_marks * 100) as percentage
          FROM submissions s
          JOIN users u ON s.student_id = u.id
          LEFT JOIN users ev ON s.evaluator_id = ev.id
          LEFT JOIN assignments a ON s.assignment_id = a.id
          LEFT JOIN subjects subj ON a.subject_id = subj.id
          WHERE s.moderator_id = ?";

$params = [$moderator_id];
$types = "i";

if($filter_status && $filter_status !== 'all') {
    $query .= " AND s.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if($filter_subject) {
    $query .= " AND subj.id = ?";
    $params[] = $filter_subject;
    $types .= "i";
}

if($filter_evaluator) {
    $query .= " AND s.evaluator_id = ?";
    $params[] = $filter_evaluator;
    $types .= "i";
}

$query .= " ORDER BY s.evaluated_at DESC, s.created_at DESC";

$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available subjects for filter
$subjects_query = "SELECT DISTINCT s.id, s.name, s.code FROM subjects s
                   JOIN moderator_subjects ms ON s.id = ms.subject_id
                   WHERE ms.moderator_id = ? AND ms.is_active = 1
                   ORDER BY s.name";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available evaluators for filter
$evaluators_query = "SELECT DISTINCT u.id, u.name FROM users u
                     WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1
                     ORDER BY u.name";
$stmt = $conn->prepare($evaluators_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$evaluators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$stats = [
    'total' => 0,
    'pending_approval' => 0,
    'discrepancies' => 0,
    'avg_score' => 0
];

$total_marks = 0;
$total_obtained = 0;

foreach($submissions as $submission) {
    $stats['total']++;
    if($submission['status'] === 'evaluated') {
        $stats['pending_approval']++;
    }
    if($submission['percentage'] < 40 || $submission['percentage'] > 95) {
        $stats['discrepancies']++;
    }
    $total_marks += $submission['max_marks'];
    $total_obtained += $submission['marks_obtained'];
}

if($stats['total'] > 0) {
    $stats['avg_score'] = ($total_obtained / $total_marks) * 100;
}
?>

<style>
.marks-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: none;
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
}

.marks-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.submission-row {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.submission-row:hover {
    border-color: #667eea;
    background-color: #f8f9ff;
}

.score-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
}

.score-excellent { background: linear-gradient(135deg, #00b894, #00cec9); }
.score-good { background: linear-gradient(135deg, #6c5ce7, #a29bfe); }
.score-average { background: linear-gradient(135deg, #fdcb6e, #f39c12); }
.score-poor { background: linear-gradient(135deg, #e17055, #d63031); }

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
}

.discrepancy-alert {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 0.75rem;
    border-radius: 5px;
    margin-bottom: 1rem;
}

.high-discrepancy {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.marks-input {
    width: 80px;
    display: inline-block;
}
</style>

<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-0">
                    <i class="fas fa-check-double"></i> Marks Overview & Cross-Check
                </h1>
                <p class="mb-0 mt-2 opacity-75">Review evaluator marks and ensure consistency across submissions</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="dashboard.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h3><?= $stats['total'] ?></h3>
                <small>Total Submissions</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #fdcb6e 0%, #f39c12 100%);">
                <h3><?= $stats['pending_approval'] ?></h3>
                <small>Pending Approval</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #e17055 0%, #d63031 100%);">
                <h3><?= $stats['discrepancies'] ?></h3>
                <small>Potential Issues</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);">
                <h3><?= number_format($stats['avg_score'], 1) ?>%</h3>
                <small>Average Score</small>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="marks-card">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status Filter</label>
                <select name="status" class="form-select">
                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="evaluated" <?= $filter_status === 'evaluated' ? 'selected' : '' ?>>Awaiting Approval</option>
                    <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject Filter</label>
                <select name="subject" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach($subjects as $subject): ?>
                    <option value="<?= $subject['id'] ?>" <?= $filter_subject == $subject['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($subject['name']) ?> (<?= $subject['code'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Evaluator Filter</label>
                <select name="evaluator" class="form-select">
                    <option value="">All Evaluators</option>
                    <?php foreach($evaluators as $evaluator): ?>
                    <option value="<?= $evaluator['id'] ?>" <?= $filter_evaluator == $evaluator['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($evaluator['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">Filter</button>
                <a href="marks_overview.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>

    <!-- Submissions List -->
    <div class="marks-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="fas fa-list text-primary"></i> Submission Reviews
            </h5>
            <span class="badge bg-info"><?= is_array($submissions) ? count($submissions) : 0 ?> submissions</span>
        </div>

        <?php if(empty($submissions)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-search fa-3x mb-3"></i>
                <p>No submissions found matching your filters</p>
            </div>
        <?php else: ?>
            <div class="submission-list">
                <?php foreach($submissions as $submission): ?>
                    <?php 
                    $percentage = $submission['percentage'];
                    $scoreClass = $percentage >= 80 ? 'score-excellent' : 
                                 ($percentage >= 70 ? 'score-good' : 
                                 ($percentage >= 50 ? 'score-average' : 'score-poor'));
                    
                    $isDiscrepancy = $percentage < 40 || $percentage > 95;
                    ?>
                    
                    <div class="submission-row <?= $isDiscrepancy ? 'border-warning' : '' ?>">
                        <?php if($isDiscrepancy): ?>
                        <div class="<?= $percentage < 40 ? 'high-discrepancy' : 'discrepancy-alert' ?> mb-3">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Attention Required:</strong> 
                            <?= $percentage < 40 ? 'Very low score detected' : 'Exceptionally high score detected' ?>
                            - Please review for consistency
                        </div>
                        <?php endif; ?>
                        
                        <div class="row align-items-center">
                            <div class="col-md-1">
                                <div class="score-circle <?= $scoreClass ?>">
                                    <?= number_format($percentage, 0) ?>%
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <h6 class="mb-1"><?= htmlspecialchars($submission['submission_title'] ?: 'Submission #' . $submission['id']) ?></h6>
                                <div class="text-muted small">
                                    <div><strong>Student:</strong> <?= htmlspecialchars($submission['student_name']) ?>
                                        <?php if($submission['roll_no']): ?>
                                        (<?= htmlspecialchars($submission['roll_no']) ?>)
                                        <?php endif; ?>
                                    </div>
                                    <div><strong>Subject:</strong> <?= htmlspecialchars($submission['subject_name'] ?? 'N/A') ?></div>
                                    <div><strong>Evaluator:</strong> <?= htmlspecialchars($submission['evaluator_name'] ?? 'Unassigned') ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h5 class="mb-0"><?= $submission['marks_obtained'] ?>/<?= $submission['max_marks'] ?></h5>
                                    <small class="text-muted">Current Marks</small>
                                    <?php if($submission['evaluated_at']): ?>
                                    <div class="small text-muted">
                                        Evaluated: <?= date('M j, g:i A', strtotime($submission['evaluated_at'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <span class="badge bg-<?= $submission['status'] === 'evaluated' ? 'warning' : 
                                                        ($submission['status'] === 'approved' ? 'success' : 'danger') ?> w-100">
                                    <?= ucfirst($submission['status']) ?>
                                </span>
                                <?php if($submission['evaluator_remarks']): ?>
                                <div class="mt-1">
                                    <small class="text-muted" data-bs-toggle="tooltip" title="<?= htmlspecialchars($submission['evaluator_remarks']) ?>">
                                        <i class="fas fa-comment"></i> Evaluator remarks
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="action-buttons">
                                    <!-- View Submission -->
                                    <button class="btn btn-sm btn-outline-info" onclick="viewSubmission(<?= $submission['id'] ?>)" title="View Submission">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if($submission['status'] === 'evaluated'): ?>
                                    <!-- Override Marks -->
                                    <button class="btn btn-sm btn-outline-warning" onclick="showOverrideModal(<?= $submission['id'] ?>, <?= $submission['marks_obtained'] ?>, <?= $submission['max_marks'] ?>)" title="Override Marks">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <!-- Approve -->
                                    <button class="btn btn-sm btn-outline-success" onclick="showApprovalModal(<?= $submission['id'] ?>, 'approve')" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    
                                    <!-- Reject/Send Back -->
                                    <button class="btn btn-sm btn-outline-danger" onclick="showApprovalModal(<?= $submission['id'] ?>, 'reject')" title="Send Back for Review">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if($submission['moderator_remarks']): ?>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <strong>Moderator Remarks:</strong> <?= htmlspecialchars($submission['moderator_remarks']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Override Marks Modal -->
<div class="modal fade" id="overrideModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Override Marks</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="override_submission_id">
                    
                    <div class="mb-3">
                        <label class="form-label">New Marks</label>
                        <div class="input-group">
                            <input type="number" name="new_marks" id="override_marks" class="form-control" step="0.01" min="0" required>
                            <span class="input-group-text">/ <span id="override_max_marks"></span></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks <small class="text-muted">(Required for override)</small></label>
                        <textarea name="remarks" class="form-control" rows="3" required 
                                  placeholder="Explain the reason for overriding the evaluator's marks..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Note:</strong> Overriding marks will automatically approve the submission and notify the evaluator.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="override_marks" class="btn btn-warning">Override Marks</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="approval_title">Approve Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="approval_submission_id">
                    <input type="hidden" name="action" id="approval_action">
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3" 
                                  placeholder="Optional remarks for this decision..."></textarea>
                    </div>
                    
                    <div class="alert" id="approval_alert">
                        <i class="fas fa-info-circle"></i>
                        <span id="approval_message">This will approve the submission with current marks.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="approve_submission" class="btn" id="approval_button">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showOverrideModal(submissionId, currentMarks, maxMarks) {
    document.getElementById('override_submission_id').value = submissionId;
    document.getElementById('override_marks').value = currentMarks;
    document.getElementById('override_max_marks').textContent = maxMarks;
    document.getElementById('override_marks').max = maxMarks;
    
    new bootstrap.Modal(document.getElementById('overrideModal')).show();
}

function showApprovalModal(submissionId, action) {
    document.getElementById('approval_submission_id').value = submissionId;
    document.getElementById('approval_action').value = action;
    
    if (action === 'approve') {
        document.getElementById('approval_title').textContent = 'Approve Submission';
        document.getElementById('approval_message').textContent = 'This will approve the submission with current marks.';
        document.getElementById('approval_alert').className = 'alert alert-success';
        document.getElementById('approval_button').className = 'btn btn-success';
        document.getElementById('approval_button').textContent = 'Approve';
    } else {
        document.getElementById('approval_title').textContent = 'Send Back for Review';
        document.getElementById('approval_message').textContent = 'This will send the submission back to the evaluator for review.';
        document.getElementById('approval_alert').className = 'alert alert-warning';
        document.getElementById('approval_button').className = 'btn btn-danger';
        document.getElementById('approval_button').textContent = 'Send Back';
    }
    
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

function viewSubmission(submissionId) {
    // Open submission in new window/tab
    window.open(`../submissions/view.php?id=${submissionId}`, '_blank');
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-refresh every 3 minutes
    setTimeout(function() {
        location.reload();
    }, 180000);
});

// Real-time validation for override marks
document.getElementById('override_marks').addEventListener('input', function() {
    const value = parseFloat(this.value);
    const max = parseFloat(this.max);
    
    if (value > max) {
        this.setCustomValidity(`Marks cannot exceed ${max}`);
    } else if (value < 0) {
        this.setCustomValidity('Marks cannot be negative');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include('../includes/footer.php'); ?>