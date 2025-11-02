<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';

// Get all submissions for this student
$submissions_sql = "SELECT as_main.*, s.name as subject_name, s.code as subject_code,
                   COUNT(easa.id) as total_evaluators,
                   COUNT(CASE WHEN easa.status = 'accepted' THEN 1 END) as accepted_evaluators,
                   COUNT(CASE WHEN easa.status = 'declined' THEN 1 END) as declined_evaluators,
                   COUNT(CASE WHEN easa.status = 'pending' THEN 1 END) as pending_evaluators,
                   ef.total_marks, ef.max_marks, ef.created_at as evaluated_at
                   FROM answer_sheets as_main
                   JOIN subjects s ON as_main.subject_id = s.id
                   LEFT JOIN evaluator_answer_sheet_assignments easa ON as_main.id = easa.answer_sheet_id
                   LEFT JOIN evaluation_feedback ef ON as_main.id = ef.answer_sheet_id
                   WHERE as_main.student_id = ?
                   GROUP BY as_main.id
                   ORDER BY as_main.submitted_at DESC";

$stmt = $pdo->prepare($submissions_sql);
$stmt->execute([$_SESSION['user_id']]);
$submissions = $stmt->fetchAll();

// Get submission statistics
$stats_sql = "SELECT 
              COUNT(*) as total_submissions,
              COUNT(CASE WHEN as_main.status = 'submitted' THEN 1 END) as pending_assignment,
              COUNT(CASE WHEN as_main.status = 'assigned' THEN 1 END) as assigned_submissions,
              COUNT(CASE WHEN as_main.status = 'completed' THEN 1 END) as completed_submissions
              FROM answer_sheets as_main
              WHERE as_main.student_id = ?";

$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch();

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-1">
                                <i class="fas fa-history text-primary me-2"></i>
                                Submission History
                            </h4>
                            <p class="text-muted mb-0">Track all your answer sheet submissions and their evaluation status</p>
                        </div>
                        <div>
                            <a href="submit_answer_sheet.php" class="btn btn-primary">
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

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-gradient-primary text-white">
                <div class="card-body text-center">
                    <div class="h3 mb-1"><?= $stats['total_submissions'] ?></div>
                    <div class="small opacity-75">Total Submissions</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-gradient-warning text-white">
                <div class="card-body text-center">
                    <div class="h3 mb-1"><?= $stats['pending_assignment'] ?></div>
                    <div class="small opacity-75">Awaiting Assignment</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-gradient-info text-white">
                <div class="card-body text-center">
                    <div class="h3 mb-1"><?= $stats['assigned_submissions'] ?></div>
                    <div class="small opacity-75">Under Evaluation</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-gradient-success text-white">
                <div class="card-body text-center">
                    <div class="h3 mb-1"><?= $stats['completed_submissions'] ?></div>
                    <div class="small opacity-75">Evaluated</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submissions List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        All Submissions
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($submissions)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Exam Details</th>
                                        <th>Subject</th>
                                        <th>Submitted</th>
                                        <th>Evaluator Status</th>
                                        <th>Evaluation Status</th>
                                        <th>Score</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $submission): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($submission['exam_title']) ?></div>
                                                <small class="text-muted">
                                                    Exam Date: <?= date('M j, Y', strtotime($submission['exam_date'])) ?>
                                                </small>
                                                <?php if ($submission['time_limit_minutes']): ?>
                                                    <br><small class="text-muted">Time: <?= $submission['time_limit_minutes'] ?> min</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= htmlspecialchars($submission['subject_code']) ?></span>
                                                <div class="small text-muted"><?= htmlspecialchars($submission['subject_name']) ?></div>
                                            </td>
                                            <td>
                                                <div><?= date('M j, Y', strtotime($submission['submitted_at'])) ?></div>
                                                <small class="text-muted"><?= date('g:i A', strtotime($submission['submitted_at'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <?php if ($submission['total_evaluators'] > 0): ?>
                                                        <div class="mb-1">
                                                            <span class="badge bg-success"><?= $submission['accepted_evaluators'] ?> Accepted</span>
                                                        </div>
                                                        <?php if ($submission['pending_evaluators'] > 0): ?>
                                                            <div class="mb-1">
                                                                <span class="badge bg-warning"><?= $submission['pending_evaluators'] ?> Pending</span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($submission['declined_evaluators'] > 0): ?>
                                                            <div class="mb-1">
                                                                <span class="badge bg-danger"><?= $submission['declined_evaluators'] ?> Declined</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No Evaluators</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($submission['status'] === 'submitted'): ?>
                                                    <span class="badge bg-warning">Awaiting Assignment</span>
                                                <?php elseif ($submission['status'] === 'assigned'): ?>
                                                    <span class="badge bg-info">Assigned</span>
                                                <?php elseif ($submission['status'] === 'in_progress'): ?>
                                                    <span class="badge bg-primary">In Progress</span>
                                                <?php elseif ($submission['status'] === 'completed'): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                    <?php if ($submission['evaluated_at']): ?>
                                                        <div class="small text-muted mt-1">
                                                            <?= date('M j, Y', strtotime($submission['evaluated_at'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($submission['total_marks'] !== null): ?>
                                                    <div class="fw-bold text-primary">
                                                        <?= $submission['total_marks'] ?> / <?= $submission['max_marks'] ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <?= number_format(($submission['total_marks'] / $submission['max_marks']) * 100, 1) ?>%
                                                    </div>
                                                    <div class="progress mt-1" style="height: 4px;">
                                                        <?php $percentage = ($submission['total_marks'] / $submission['max_marks']) * 100; ?>
                                                        <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group-vertical btn-group-sm">
                                                    <a href="submission_status.php?id=<?= $submission['id'] ?>" 
                                                       class="btn btn-outline-info btn-sm">
                                                        <i class="fas fa-eye"></i> View Status
                                                    </a>
                                                    
                                                    <?php if ($submission['pdf_path']): ?>
                                                        <a href="<?= htmlspecialchars($submission['pdf_path']) ?>" 
                                                           target="_blank" class="btn btn-outline-danger btn-sm">
                                                            <i class="fas fa-file-pdf"></i> PDF
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($submission['total_marks'] !== null): ?>
                                                        <a href="evaluation_results.php?id=<?= $submission['id'] ?>" 
                                                           class="btn btn-outline-success btn-sm">
                                                            <i class="fas fa-chart-line"></i> Results
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
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5>No Submissions Found</h5>
                            <p class="text-muted">You haven't submitted any answer sheets yet.</p>
                            <a href="submit_answer_sheet.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i> Submit Your First Answer Sheet
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.table tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.05);
}

.progress-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-group-vertical .btn {
    margin-bottom: 2px;
}

.btn-group-vertical .btn:last-child {
    margin-bottom: 0;
}

.badge {
    font-size: 0.75em;
}

@media (max-width: 768px) {
    .btn-group-vertical {
        width: 100%;
    }
    
    .btn-group-vertical .btn {
        width: 100%;
    }
}
</style>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Auto-refresh every 60 seconds
    setInterval(function() {
        location.reload();
    }, 60000);
});
</script>

<?php include '../includes/footer.php'; ?>