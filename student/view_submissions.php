<?php
include('../config/config.php');
$isIndexPage = false;
include('../includes/header.php');
?>

<link href="../moderator/css/moderator-style.css" rel="stylesheet">
<style>
/* Fix button hover styles */
.btn-primary:hover,
.btn-success:hover,
.btn-info:hover,
.btn-outline-primary:hover,
.btn-outline-secondary:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-primary:hover {
    background-color: #0056b3 !important;
    border-color: #0056b3 !important;
    color: white !important;
}

.btn-success:hover {
    background-color: #157347 !important;
    border-color: #157347 !important;
    color: white !important;
}

.btn-info:hover {
    background-color: #0a86b3 !important;
    border-color: #0a86b3 !important;
    color: white !important;
}

.btn-outline-primary:hover {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    color: white !important;
}

.btn-outline-secondary:hover {
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    color: white !important;
}
</style>

<?php require_once('includes/sidebar.php'); ?>

<div class="dashboard-layout">
    <div class="main-content">

<?php
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student'){
    header("Location: ../auth/login.php");
    exit;
}

// Detect if publication column exists (migration may not yet be applied)
$hasPublishColumn = false;
$colCheck = $conn->query("SHOW COLUMNS FROM submissions LIKE 'is_published'");
if ($colCheck && $colCheck->num_rows === 1) { $hasPublishColumn = true; }

if ($hasPublishColumn) {
    $query = "SELECT s.*, sub.code as subject_code, sub.name as subject_name, 
                     u.name as evaluator_name,
                     CASE 
                         WHEN s.is_published = 1 AND s.marks_obtained IS NOT NULL THEN 'Published'
                         WHEN s.evaluation_status = 'evaluated' THEN 'Awaiting Approval'
                         WHEN s.evaluation_status = 'under_review' THEN 'Under Review'
                         WHEN s.status = 'pending' THEN 'Pending Review'
                         WHEN s.status = 'rejected' THEN 'Rejected'
                         ELSE 'Unknown'
                     END as status_display,
                     CASE WHEN s.is_published = 1 AND s.max_marks > 0 THEN (s.marks_obtained/s.max_marks)*100 ELSE 0 END as percentage
                     FROM submissions s 
                     LEFT JOIN subjects sub ON s.subject_id = sub.id 
                     LEFT JOIN users u ON s.evaluator_id = u.id
                     WHERE s.student_id=? 
                     ORDER BY s.created_at DESC";
} else {
    // Fallback query without is_published (treat evaluated as awaiting approval and hide marks)
    $query = "SELECT s.*, sub.code as subject_code, sub.name as subject_name, 
                     u.name as evaluator_name,
                     CASE 
                         WHEN s.evaluation_status = 'evaluated' AND s.marks_obtained IS NOT NULL THEN 'Awaiting Approval'
                         WHEN s.evaluation_status = 'under_review' THEN 'Under Review'
                         WHEN s.status = 'pending' THEN 'Pending Review'
                         WHEN s.status = 'rejected' THEN 'Rejected'
                         ELSE 'Unknown'
                     END as status_display,
                     0 as percentage,
                     0 as is_published
                     FROM submissions s 
                     LEFT JOIN subjects sub ON s.subject_id = sub.id 
                     LEFT JOIN users u ON s.evaluator_id = u.id
                     WHERE s.student_id=? 
                     ORDER BY s.created_at DESC";
}

$stmt = $conn->prepare($query);
if(!$stmt){
    // Show graceful error instead of fatal
    echo '<div class="alert alert-danger m-3">Database error preparing submissions query: ' . htmlspecialchars($conn->error) . '<br>Run migration file <code>db/add_publish_gating_columns.sql</code> and refresh.</div>';
    $result = new class {
        public $num_rows = 0; public function fetch_assoc(){ return null; }
    }; // empty result shim
} else {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<div class="container-fluid px-2 px-md-3">
    <div class="row">
        <div class="col-12">
            <div class="page-card">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2">
                    <h2 class="mb-0 fs-5 fs-md-4">Your Submissions</h2>
                    <a class="btn btn-primary d-flex align-items-center gap-2 w-100 w-sm-auto justify-content-center py-2" href="upload.php">
                        <i class="fas fa-plus"></i>
                        <span>New Upload</span>
                    </a>
                </div>
            
            <?php if($result->num_rows == 0): ?>
                <div class="text-center py-5">
                    <div class="text-muted mb-3 fs-1">📄</div>
                    <h5 class="text-muted">No submissions yet</h5>
                    <p class="text-muted px-3">Upload your first answer sheet to get started</p>
                    <a class="btn btn-primary btn-lg" href="upload.php">
                        <i class="fas fa-upload me-2"></i>Upload Answers
                    </a>
                </div>
            <?php else: ?>
                <!-- Desktop Table View (hidden on mobile) -->
                <div class="table-responsive d-none d-lg-block">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Marks & Grade</th>
                                <th>Evaluator</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <?php 
                            // Status badge styling
                            $statusClass = 'bg-warning';
                            if($row['status_display'] == 'Published') $statusClass = 'bg-success';
                            if($row['status_display'] == 'Awaiting Approval') $statusClass = 'bg-warning';
                            if($row['status_display'] == 'Rejected') $statusClass = 'bg-danger';
                            if($row['status_display'] == 'Under Review') $statusClass = 'bg-info';
                            
                            // File size formatting
                            $fileSize = '';
                            if($row['file_size']) {
                                $fileSize = number_format($row['file_size'] / 1024, 1) . ' KB';
                            }
                            
                            // Create proper PDF viewer URL
                            $viewUrl = "view_pdf.php?submission_id=" . $row['id'];
                            
                            // Grade calculation
                            $percentage = $row['percentage'];
                            $grade = 'N/A';
                            if ($row['marks_obtained'] !== null && $row['max_marks'] > 0) {
                                if ($percentage >= 90) $grade = 'A+';
                                elseif ($percentage >= 80) $grade = 'A';
                                elseif ($percentage >= 70) $grade = 'B';
                                elseif ($percentage >= 60) $grade = 'C';
                                elseif ($percentage >= 50) $grade = 'D';
                                else $grade = 'F';
                            }
                            ?>
                            <tr>
                                <td>
                                    <?php if($row['subject_code']): ?>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['subject_code']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['subject_name']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">No subject</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?> text-white">
                                        <?= htmlspecialchars($row['status_display']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($row['is_published'] == 1 && $row['marks_obtained'] !== null && $row['max_marks'] > 0): ?>
                                        <div class="fw-bold text-success">
                                            <?= number_format((float)$row['marks_obtained'], 1) ?> / <?= number_format((float)$row['max_marks'], 1) ?>
                                        </div>
                                        <div class="small">
                                            <span class="badge bg-primary"><?= number_format($percentage, 1) ?>%</span>
                                            <span class="badge bg-secondary ms-1">Grade: <?= $grade ?></span>
                                        </div>
                                    <?php elseif($row['evaluation_status'] == 'evaluated'): ?>
                                        <span class="text-muted">Awaiting Moderator Approval</span>
                                    <?php else: ?>
                                        <span class="text-muted">Not evaluated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['evaluator_name']): ?>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['evaluator_name']) ?></div>
                                        <?php if($row['evaluated_at']): ?>
                                            <div class="small text-muted">
                                                <?= date('M j, Y g:i A', strtotime($row['evaluated_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                                    <div class="small text-muted"><?= date('g:i A', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= htmlspecialchars($viewUrl) ?>" 
                                           class="btn btn-sm btn-outline-primary" title="View PDF">
                                            📄 View
                                        </a>
                                        <?php if($row['evaluator_remarks']): ?>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="showEvaluationFeedback('<?= htmlspecialchars(addslashes($row['evaluator_remarks'])) ?>', '<?= htmlspecialchars($row['subject_code']) ?>', '<?= number_format($percentage, 1) ?>%', '<?= $grade ?>')"
                                                    title="View Evaluation Feedback">
                                                💬 Feedback
                                            </button>
                                        <?php endif; ?>
                                        <?php if($row['is_published'] == 1 && $row['marks_obtained'] !== null && $row['status_display'] == 'Published'): ?>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="showRateEvaluator(<?= $row['id'] ?>, '<?= htmlspecialchars($row['subject_code']) ?>')"
                                                    title="Rate Evaluator">
                                                ⭐ Rate
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Card View (hidden on desktop) -->
                <div class="d-lg-none">
                    <?php 
                    // Reset the result pointer to display mobile cards
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while($row = $result->fetch_assoc()): 
                        // Status badge styling
                        $statusClass = 'bg-warning';
                        if($row['status_display'] == 'Published') $statusClass = 'bg-success';
                        if($row['status_display'] == 'Awaiting Approval') $statusClass = 'bg-warning';
                        if($row['status_display'] == 'Rejected') $statusClass = 'bg-danger';
                        if($row['status_display'] == 'Under Review') $statusClass = 'bg-info';
                        
                        // Create proper PDF viewer URL
                        $viewUrl = "view_pdf.php?submission_id=" . $row['id'];
                        
                        // Grade calculation
                        $percentage = $row['percentage'];
                        $grade = 'N/A';
                        if ($row['marks_obtained'] !== null && $row['max_marks'] > 0) {
                            if ($percentage >= 90) $grade = 'A+';
                            elseif ($percentage >= 80) $grade = 'A';
                            elseif ($percentage >= 70) $grade = 'B';
                            elseif ($percentage >= 60) $grade = 'C';
                            elseif ($percentage >= 50) $grade = 'D';
                            else $grade = 'F';
                        }
                    ?>
                    <div class="card mb-3 shadow-sm border-0">
                        <div class="card-body p-3">
                            <!-- Subject Header -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1 me-2">
                                    <?php if($row['subject_code']): ?>
                                        <h6 class="card-title mb-1 text-primary fw-bold"><?= htmlspecialchars($row['subject_code']) ?></h6>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($row['subject_name']) ?></p>
                                    <?php else: ?>
                                        <h6 class="card-title text-muted">No subject</h6>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= $statusClass ?> text-white">
                                    <?= htmlspecialchars($row['status_display']) ?>
                                </span>
                            </div>
                            
                            <!-- Submission Info -->
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <div class="small">
                                        <div class="text-muted mb-1">
                                            <i class="fas fa-calendar-alt me-1"></i> Submitted
                                        </div>
                                        <div class="fw-semibold"><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= date('g:i A', strtotime($row['created_at'])) ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="small">
                                        <div class="text-muted mb-1">
                                            <i class="fas fa-user-check me-1"></i> Evaluator
                                        </div>
                                        <?php if($row['evaluator_name']): ?>
                                            <div class="fw-semibold"><?= htmlspecialchars($row['evaluator_name']) ?></div>
                                            <?php if($row['evaluated_at']): ?>
                                                <div class="text-muted" style="font-size: 0.75rem;"><?= date('M j, Y', strtotime($row['evaluated_at'])) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="text-muted">Not assigned</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Marks Section -->
                            <?php if($row['is_published'] == 1 && $row['marks_obtained'] !== null && $row['max_marks'] > 0): ?>
                                <div class="bg-light rounded p-2 mb-2">
                                    <div class="row g-2 text-center">
                                        <div class="col-4">
                                            <div class="fw-bold text-success" style="font-size: 1.25rem;">
                                                <?= number_format((float)$row['marks_obtained'], 1) ?>
                                            </div>
                                            <div class="small text-muted mobile-marks-label">Obtained</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold text-info" style="font-size: 1.25rem;">
                                                <?= number_format((float)$row['max_marks'], 1) ?>
                                            </div>
                                            <div class="small text-muted mobile-marks-label">Total</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold text-primary" style="font-size: 1.25rem;"><?= $grade ?></div>
                                            <div class="small text-muted mobile-marks-label"><?= number_format($percentage, 1) ?>%</div>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif($row['evaluation_status'] == 'evaluated'): ?>
                                <div class="bg-light rounded p-2 mb-2 text-center">
                                    <div class="text-muted small">
                                        <i class="fas fa-hourglass-half me-1"></i>
                                        Awaiting Moderator Approval
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-light rounded p-2 mb-2 text-center">
                                    <div class="text-muted small">
                                        <i class="fas fa-clock me-1"></i>
                                        Not evaluated yet
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <a href="<?= htmlspecialchars($viewUrl) ?>"
                                   class="btn btn-outline-primary">
                                    📄 View PDF
                                </a>
                                
                                <?php if($row['evaluator_remarks'] || ($row['is_published'] == 1 && $row['marks_obtained'] !== null)): ?>
                                    <div class="row g-2">
                                        <?php if($row['evaluator_remarks']): ?>
                                            <div class="col-6">
                                                <button class="btn btn-outline-info w-100 mobile-action-btn" 
                                                        onclick="showEvaluationFeedback('<?= htmlspecialchars(addslashes($row['evaluator_remarks'])) ?>', '<?= htmlspecialchars($row['subject_code']) ?>', '<?= number_format($percentage, 1) ?>%', '<?= $grade ?>')">
                                                    💬 Feedback
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        <?php if($row['is_published'] == 1 && $row['marks_obtained'] !== null && $row['status_display'] == 'Published'): ?>
                                            <div class="col-<?= $row['evaluator_remarks'] ? '6' : '12' ?>">
                                                <button class="btn btn-outline-warning w-100 mobile-action-btn" 
                                                        onclick="showRateEvaluator(<?= $row['id'] ?>, '<?= htmlspecialchars($row['subject_code']) ?>')">
                                                    ⭐ Rate
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal for evaluation feedback -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fs-sm-5">
                    <i class="fas fa-comment-alt text-info me-2"></i>Evaluation Feedback
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="feedbackContent"></div>
            </div>
            <div class="modal-footer d-flex flex-column flex-sm-row gap-2">
                <button type="button" class="btn btn-secondary w-100 w-sm-auto" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for detailed results -->
<div class="modal fade" id="resultsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fs-sm-5">
                    <i class="fas fa-chart-bar text-success me-2"></i>Detailed Results
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="resultsContent"></div>
            </div>
            <div class="modal-footer d-flex flex-column flex-sm-row gap-2">
                <button type="button" class="btn btn-secondary w-100 w-sm-auto order-2 order-sm-1" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary w-100 w-sm-auto order-1 order-sm-2" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Results
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for rating evaluator -->
<div class="modal fade" id="rateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-star text-warning me-2"></i>Rate Evaluator
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <p class="text-muted mb-3">How would you rate the evaluation for <strong id="ratingSubject"></strong>?</p>
                    <div class="rating-stars mb-3" id="ratingStars">
                        <i class="fas fa-star star-icon" data-rating="1"></i>
                        <i class="fas fa-star star-icon" data-rating="2"></i>
                        <i class="fas fa-star star-icon" data-rating="3"></i>
                        <i class="fas fa-star star-icon" data-rating="4"></i>
                        <i class="fas fa-star star-icon" data-rating="5"></i>
                    </div>
                    <p class="text-muted small mb-0" id="ratingText">Click on a star to rate</p>
                </div>
                <div class="mb-3">
                    <label for="ratingComment" class="form-label">Additional Comments (Optional)</label>
                    <textarea class="form-control" id="ratingComment" rows="3" placeholder="Share your experience..."></textarea>
                </div>
                <input type="hidden" id="ratingSubmissionId">
                <input type="hidden" id="selectedRating" value="0">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="submitRating()">
                    <i class="fas fa-paper-plane me-2"></i>Submit Rating
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.rating-stars {
    font-size: 2.5rem;
    cursor: pointer;
}

.star-icon {
    color: #ddd;
    transition: color 0.2s ease;
    margin: 0 0.25rem;
}

.star-icon:hover,
.star-icon.active {
    color: #ffc107;
}

.star-icon.active {
    transform: scale(1.1);
}
</style>

<script>
function showEvaluationFeedback(feedback, subjectCode, percentage, grade) {
    const content = `
        <div class="row g-2 mb-3">
            <div class="col-12 col-sm-4">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small mb-1">Subject</div>
                        <div class="h6 text-primary mb-0">${subjectCode}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-4">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small mb-1">Score</div>
                        <div class="h6 text-success mb-0">${percentage}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-4">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small mb-1">Grade</div>
                        <div class="h6 text-info mb-0">${grade}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card border-0 bg-light">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0 text-white">
                    <i class="fas fa-comment-dots me-2"></i>Evaluator's Feedback
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="p-3 bg-white" style="min-height: 120px; max-height: 300px; overflow-y: auto;">
                    <div class="text-break">${feedback.replace(/\n/g, '<br>')}</div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('feedbackContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
}

function showDetailedResults(submission) {
    const percentage = submission.percentage;
    let gradeInfo = '';
    let performanceClass = '';
    
    if (percentage >= 90) {
        gradeInfo = 'A+ - Excellent Performance';
        performanceClass = 'text-success';
    } else if (percentage >= 80) {
        gradeInfo = 'A - Very Good Performance';
        performanceClass = 'text-success';
    } else if (percentage >= 70) {
        gradeInfo = 'B - Good Performance';
        performanceClass = 'text-warning';
    } else if (percentage >= 60) {
        gradeInfo = 'C - Satisfactory Performance';
        performanceClass = 'text-info';
    } else if (percentage >= 50) {
        gradeInfo = 'D - Passing Performance';
        performanceClass = 'text-secondary';
    } else {
        gradeInfo = 'F - Needs Improvement';
        performanceClass = 'text-danger';
    }
    
    const content = `
        <div class="row mb-3">
            <div class="col-12">
                <div class="card border-0 bg-primary text-white">
                    <div class="card-body text-center py-3">
                        <div class="h5 card-title mb-1">${submission.subject_code}</div>
                        <div class="small opacity-75">${submission.subject_name}</div>
                        <div class="small opacity-75 mt-1">Evaluation Results</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-2 mb-3">
            <div class="col-6 col-sm-3">
                <div class="card border-0 bg-light text-center h-100">
                    <div class="card-body py-3">
                        <i class="fas fa-star fa-lg text-warning mb-2"></i>
                        <div class="h6 mb-1">${parseFloat(submission.marks_obtained).toFixed(1)}</div>
                        <div class="small text-muted">Marks Obtained</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="card border-0 bg-light text-center h-100">
                    <div class="card-body py-3">
                        <i class="fas fa-trophy fa-lg text-info mb-2"></i>
                        <div class="h6 mb-1">${parseFloat(submission.max_marks).toFixed(1)}</div>
                        <div class="small text-muted">Total Marks</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="card border-0 bg-light text-center h-100">
                    <div class="card-body py-3">
                        <i class="fas fa-percentage fa-lg text-success mb-2"></i>
                        <div class="h6 mb-1">${percentage.toFixed(1)}%</div>
                        <div class="small text-muted">Percentage</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="card border-0 bg-light text-center h-100">
                    <div class="card-body py-3">
                        <i class="fas fa-medal fa-lg ${performanceClass.replace('text-', 'text-')} mb-2"></i>
                        <div class="h6 mb-1 ${performanceClass}">${gradeInfo.split(' - ')[0]}</div>
                        <div class="small text-muted">Grade</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-2 mb-3">
            <div class="col-12 col-lg-6">
                <div class="card border-0 bg-light h-100">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0 text-white">
                            <i class="fas fa-chart-line me-2"></i>Performance Analysis
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="h6 ${performanceClass} mb-2">${gradeInfo}</div>
                        <div class="progress mb-3" style="height: 24px;">
                            <div class="progress-bar ${performanceClass.replace('text-', 'bg-')} d-flex align-items-center justify-content-center" 
                                 style="width: ${percentage}%" 
                                 role="progressbar">
                                <small class="fw-bold">${percentage.toFixed(1)}%</small>
                            </div>
                        </div>
                        <div class="small text-muted">
                            ${percentage >= 90 ? 'Outstanding work! Keep up the excellent performance.' :
                              percentage >= 80 ? 'Very good work! You\'re performing well.' :
                              percentage >= 70 ? 'Good effort! Continue to improve.' :
                              percentage >= 60 ? 'Satisfactory work. Focus on areas for improvement.' :
                              percentage >= 50 ? 'You passed! Work harder for better results.' :
                              'Additional study and practice needed.'}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card border-0 bg-light h-100">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0 text-white">
                            <i class="fas fa-info-circle me-2"></i>Submission Details
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 small">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Evaluator:</span>
                                    <span class="fw-semibold">${submission.evaluator_name || 'Anonymous'}</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Submitted:</span>
                                    <span>${new Date(submission.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Evaluated:</span>
                                    <span>${submission.evaluated_at ? new Date(submission.evaluated_at).toLocaleDateString() : 'N/A'}</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">File:</span>
                                    <span class="text-break small">${submission.original_filename || 'N/A'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        ${submission.evaluator_remarks ? `
        <div class="row">
            <div class="col-12">
                <div class="card border-0 bg-light">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0 text-white">
                            <i class="fas fa-comment-dots me-2"></i>Detailed Feedback from Evaluator
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="bg-white p-3" style="max-height: 200px; overflow-y: auto;">
                            <div class="text-break">${submission.evaluator_remarks.replace(/\n/g, '<br>')}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        ` : ''}
    `;
    
    document.getElementById('resultsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('resultsModal')).show();
}

function showRateEvaluator(submissionId, subjectCode) {
    document.getElementById('ratingSubmissionId').value = submissionId;
    document.getElementById('ratingSubject').textContent = subjectCode;
    document.getElementById('selectedRating').value = '0';
    document.getElementById('ratingComment').value = '';
    document.getElementById('ratingText').textContent = 'Click on a star to rate';
    
    // Reset stars
    document.querySelectorAll('.star-icon').forEach(star => {
        star.classList.remove('active');
    });
    
    new bootstrap.Modal(document.getElementById('rateModal')).show();
}

// Star rating interaction
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star-icon');
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            document.getElementById('selectedRating').value = rating;
            
            // Update star display
            stars.forEach(s => {
                const starRating = s.getAttribute('data-rating');
                if (starRating <= rating) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
            
            // Update text
            const ratingTexts = {
                '1': 'Poor - Not satisfied',
                '2': 'Fair - Below expectations',
                '3': 'Good - Satisfactory',
                '4': 'Very Good - Above expectations',
                '5': 'Excellent - Outstanding!'
            };
            document.getElementById('ratingText').textContent = ratingTexts[rating];
        });
        
        // Hover effect
        star.addEventListener('mouseenter', function() {
            const rating = this.getAttribute('data-rating');
            stars.forEach(s => {
                const starRating = s.getAttribute('data-rating');
                if (starRating <= rating) {
                    s.style.color = '#ffc107';
                }
            });
        });
        
        star.addEventListener('mouseleave', function() {
            const selectedRating = document.getElementById('selectedRating').value;
            stars.forEach(s => {
                const starRating = s.getAttribute('data-rating');
                if (starRating <= selectedRating) {
                    s.style.color = '#ffc107';
                } else {
                    s.style.color = '#ddd';
                }
            });
        });
    });
});

function submitRating() {
    const submissionId = document.getElementById('ratingSubmissionId').value;
    const rating = document.getElementById('selectedRating').value;
    const comment = document.getElementById('ratingComment').value;
    
    if (rating === '0') {
        alert('Please select a rating before submitting.');
        return;
    }
    
    // Disable submit button
    event.target.disabled = true;
    event.target.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    
    // Send rating via AJAX
    fetch('submit_rating.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            submission_id: submissionId,
            rating: rating,
            comment: comment
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Thank you for your rating!');
            bootstrap.Modal.getInstance(document.getElementById('rateModal')).hide();
            // Optionally reload the page or update UI
            location.reload();
        } else {
            alert('Error submitting rating: ' + (data.message || 'Unknown error'));
            event.target.disabled = false;
            event.target.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Rating';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting rating. Please try again.');
        event.target.disabled = false;
        event.target.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Rating';
    });
}
</script>

<style>
/* Mobile action buttons styling */
.mobile-action-btn {
    padding: 0.5rem 0.75rem !important;
    font-size: 0.875rem !important;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    border-width: 1px !important;
    min-height: 38px;
}

/* Page card styling */
.page-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

/* Mobile card improvements */
.d-lg-none .card {
    border-radius: 12px !important;
    overflow: hidden;
}

.d-lg-none .card-body {
    padding: 1rem !important;
}

/* Mobile-friendly enhancements */
@media (max-width: 991.98px) {
    /* Prevent horizontal overflow */
    body {
        overflow-x: hidden !important;
    }
    
    .main-content {
        overflow-x: hidden !important;
        width: 100% !important;
        max-width: 100vw !important;
        box-sizing: border-box !important;
        padding: 0.5rem !important;
    }
    
    .container-fluid {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
    }
    
    .page-card {
        padding: 1rem !important;
        margin-bottom: 1rem !important;
    }
    
    .page-card h2 {
        font-size: 1.25rem !important;
    }
    
    /* Mobile card layout */
    .d-lg-none .card {
        margin-bottom: 0.75rem !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
    }
    
    .d-lg-none .card-body {
        padding: 1rem !important;
    }
    
    .d-lg-none .card-title {
        font-size: 1rem !important;
        line-height: 1.3 !important;
        margin-bottom: 0.25rem !important;
    }
    
    /* Button improvements */
    .btn {
        font-size: 0.9rem !important;
        font-weight: 500 !important;
        border-radius: 8px !important;
    }
    
    .btn-primary {
        padding: 0.75rem 1rem !important;
    }
    
    /* Mobile action buttons - specific override */
    .mobile-action-btn {
        padding: 0.75rem 0.5rem !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        line-height: 1.4 !important;
        min-height: 42px !important;
        white-space: nowrap !important;
    }
    
    /* Mobile marks labels - make text visible */
    .mobile-marks-label {
        font-size: 0.75rem !important;
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
    }
    
    .mobile-action-btn i {
        font-size: 0.85rem !important;
    }
    
    /* View PDF button */
    .btn-primary {
        font-weight: 500 !important;
    }
    
    /* Spacing adjustments */
    .gap-2 {
        gap: 0.5rem !important;
    }
    
    .mb-2 {
        margin-bottom: 0.5rem !important;
    }
    
    .mb-3 {
        margin-bottom: 0.75rem !important;
    }
    
    /* Badge adjustments */
    .badge {
        font-size: 0.7rem !important;
        padding: 0.35rem 0.5rem !important;
        white-space: normal !important;
    }
    
    /* Small text */
    .small {
        font-size: 0.75rem !important;
    }
    
    /* Info sections */
    .bg-light.rounded {
        padding: 0.75rem !important;
    }
    
    /* Ensure no element causes overflow */
    * {
        max-width: 100%;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        box-sizing: border-box !important;
    }
}
    
    /* Modal improvements */
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    
    .modal-content {
        border-radius: 0.5rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    /* Progress bar text visibility */
    .progress-bar {
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    /* Better spacing for info cards */
    .bg-light .card-body {
        padding: 0.75rem;
    }
}

@media (min-width: 576px) and (max-width: 991px) {
    /* Tablet specific styles */
    .modal-dialog-scrollable .modal-content {
        max-height: 90vh;
    }
}

/* Custom scrollbar for modal content */
.modal-body {
    scrollbar-width: thin;
    scrollbar-color: #6c757d #f8f9fa;
}

.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track {
    background: #f8f9fa;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #6c757d;
    border-radius: 3px;
}

/* Ensure proper text wrapping */
.text-break {
    word-wrap: break-word;
    word-break: break-word;
}

/* Status badge improvements */
.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
}

/* Touch-friendly action buttons */
.btn-outline-primary,
.btn-outline-info,
.btn-outline-success {
    border-width: 2px;
}

.btn-outline-primary:hover,
.btn-outline-info:hover,
.btn-outline-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Animation for better UX */
.card {
    transition: all 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Loading states */
.btn:disabled {
    opacity: 0.6;
    transform: none !important;
}
</style>

    </div>
</div>

<?php include('../includes/footer.php'); ?>
