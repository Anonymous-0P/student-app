<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';

$answer_sheet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get submission details
$submission_sql = "SELECT as_main.*, s.name as subject_name, s.code as subject_code,
                   u.name as student_name
                   FROM answer_sheets as_main
                   JOIN subjects s ON as_main.subject_id = s.id
                   JOIN users u ON as_main.student_id = u.id
                   WHERE as_main.id = ? AND as_main.student_id = ?";

$stmt = $pdo->prepare($submission_sql);
$stmt->execute([$answer_sheet_id, $_SESSION['user_id']]);
$submission = $stmt->fetch();

if (!$submission) {
    $_SESSION['error'] = "Submission not found.";
    header("Location: dashboard.php");
    exit();
}

// Get evaluator assignment status
$assignments_sql = "SELECT easa.*, u.name as evaluator_name, u.email as evaluator_email,
                    easa.status, easa.assigned_at, easa.accepted_at, easa.deadline
                    FROM evaluator_answer_sheet_assignments easa
                    JOIN users u ON easa.evaluator_id = u.id
                    WHERE easa.answer_sheet_id = ?
                    ORDER BY easa.status DESC, easa.assigned_at ASC";

$assign_stmt = $pdo->prepare($assignments_sql);
$assign_stmt->execute([$answer_sheet_id]);
$assignments = $assign_stmt->fetchAll();

// Count assignment statuses
$status_counts = [
    'pending' => 0,
    'accepted' => 0,
    'declined' => 0
];

foreach ($assignments as $assignment) {
    $status_counts[$assignment['status']]++;
}

// Get evaluation progress if accepted
$evaluation_sql = "SELECT ef.*, u.name as evaluator_name
                   FROM evaluation_feedback ef
                   JOIN users u ON ef.evaluator_id = u.id
                   WHERE ef.answer_sheet_id = ?";

$eval_stmt = $pdo->prepare($evaluation_sql);
$eval_stmt->execute([$answer_sheet_id]);
$evaluation = $eval_stmt->fetch();

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
                                <i class="fas fa-clipboard-check text-primary me-2"></i>
                                Submission Status
                            </h4>
                            <p class="text-muted mb-0">Track the progress of your answer sheet evaluation</p>
                        </div>
                        <div>
                            <a href="submit_answer_sheet.php" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-1"></i> New Submission
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submission Details -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>
                        Submission Details
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong class="text-muted">Exam Title:</strong>
                                <div><?= htmlspecialchars($submission['exam_title']) ?></div>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Subject:</strong>
                                <div><?= htmlspecialchars($submission['subject_code']) ?> - <?= htmlspecialchars($submission['subject_name']) ?></div>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Exam Date:</strong>
                                <div><?= date('F j, Y', strtotime($submission['exam_date'])) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong class="text-muted">Submitted On:</strong>
                                <div><?= date('F j, Y g:i A', strtotime($submission['submitted_at'])) ?></div>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Time Taken:</strong>
                                <div><?= $submission['time_limit_minutes'] ? $submission['time_limit_minutes'] . ' minutes' : 'Not specified' ?></div>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Current Status:</strong>
                                <div>
                                    <?php if ($submission['status'] === 'submitted'): ?>
                                        <span class="badge bg-warning">Awaiting Assignment</span>
                                    <?php elseif ($submission['status'] === 'assigned'): ?>
                                        <span class="badge bg-info">Assigned for Evaluation</span>
                                    <?php elseif ($submission['status'] === 'in_progress'): ?>
                                        <span class="badge bg-primary">Under Evaluation</span>
                                    <?php elseif ($submission['status'] === 'completed'): ?>
                                        <span class="badge bg-success">Evaluation Completed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($submission['pdf_path']): ?>
                    <div class="mt-3">
                        <strong class="text-muted">Answer Sheet PDF:</strong>
                        <div class="mt-2">
                            <a href="<?= htmlspecialchars($submission['pdf_path']) ?>" target="_blank" 
                               class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-file-pdf me-1"></i> View PDF
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Assignment Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Pending:</span>
                            <span class="badge bg-warning"><?= $status_counts['pending'] ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Accepted:</span>
                            <span class="badge bg-success"><?= $status_counts['accepted'] ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Declined:</span>
                            <span class="badge bg-danger"><?= $status_counts['declined'] ?></span>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <strong>Total Evaluators:</strong> <?= count($assignments) ?>
                    </div>
                </div>
            </div>

            <!-- Evaluation Results (if completed) -->
            <?php if ($evaluation): ?>
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-trophy me-2"></i>
                        Evaluation Results
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <h3 class="text-primary"><?= $evaluation['total_marks'] ?> / <?= $evaluation['max_marks'] ?></h3>
                        <p class="text-muted">Final Score</p>
                        <div class="progress mb-3">
                            <?php $percentage = ($evaluation['total_marks'] / $evaluation['max_marks']) * 100; ?>
                            <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                        </div>
                        <p><strong>Percentage:</strong> <?= number_format($percentage, 1) ?>%</p>
                    </div>
                    <div class="text-center">
                        <a href="../student/evaluation_results.php?id=<?= $answer_sheet_id ?>" 
                           class="btn btn-success btn-sm">
                            <i class="fas fa-eye me-1"></i> View Detailed Results
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Evaluator Assignment Timeline -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Evaluator Assignment Status
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($assignments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Evaluator</th>
                                        <th>Status</th>
                                        <th>Assigned Date</th>
                                        <th>Deadline</th>
                                        <th>Action Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-2">
                                                        <i class="fas fa-user-graduate text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($assignment['evaluator_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($assignment['evaluator_email']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($assignment['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock me-1"></i>Pending
                                                    </span>
                                                <?php elseif ($assignment['status'] === 'accepted'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Accepted
                                                    </span>
                                                <?php elseif ($assignment['status'] === 'declined'): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times me-1"></i>Declined
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M j, Y g:i A', strtotime($assignment['assigned_at'])) ?></td>
                                            <td>
                                                <?php if ($assignment['deadline']): ?>
                                                    <?= date('M j, Y', strtotime($assignment['deadline'])) ?>
                                                    <?php if (strtotime($assignment['deadline']) < time() && $assignment['status'] === 'pending'): ?>
                                                        <small class="text-danger">(Overdue)</small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($assignment['accepted_at']): ?>
                                                    <?= date('M j, Y g:i A', strtotime($assignment['accepted_at'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h5>No Evaluators Assigned</h5>
                            <p class="text-muted">There are no evaluators available for this subject. Please contact the administrator.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-refresh every 30 seconds to show updated status
    setInterval(function() {
        // Only refresh if no evaluation is completed
        <?php if (!$evaluation): ?>
        location.reload();
        <?php endif; ?>
    }, 30000);
    
    // Show tooltip for overdue assignments
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.avatar-sm {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background-color: #f8f9fa;
}

.progress-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card {
    transition: transform 0.2s ease-in-out;
}

.table tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.05);
}
</style>

<?php include '../includes/footer.php'; ?>