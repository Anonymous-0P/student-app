<?php
include('../config/config.php');
include('../includes/header.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student'){
    header("Location: ../auth/login.php");
    exit;
}

// Get submissions with subject details and evaluator information
$stmt = $conn->prepare("SELECT s.*, sub.code as subject_code, sub.name as subject_name, 
                       u.name as evaluator_name,
                       CASE 
                           WHEN s.evaluation_status = 'evaluated' AND s.marks_obtained IS NOT NULL THEN 'Evaluated'
                           WHEN s.evaluation_status = 'under_review' THEN 'Under Review'
                           WHEN s.status = 'pending' THEN 'Pending Review'
                           WHEN s.status = 'approved' THEN 'Approved'
                           WHEN s.status = 'rejected' THEN 'Rejected'
                           ELSE 'Unknown'
                       END as status_display,
                       CASE WHEN s.max_marks > 0 THEN (s.marks_obtained/s.max_marks)*100 ELSE 0 END as percentage
                       FROM submissions s 
                       LEFT JOIN subjects sub ON s.subject_id = sub.id 
                       LEFT JOIN users u ON s.evaluator_id = u.id
                       WHERE s.student_id=? 
                       ORDER BY s.created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-fluid px-3">
    <div class="row">
        <div class="col-12">
            <div class="page-card">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2">
                    <h2 class="mb-0 fs-4 fs-sm-3">Your Submissions</h2>
                    <a class="btn btn-primary d-flex align-items-center gap-2 w-100 w-sm-auto justify-content-center" href="upload.php">
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
                            if($row['status_display'] == 'Evaluated') $statusClass = 'bg-success';
                            if($row['status_display'] == 'Approved') $statusClass = 'bg-success';
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
                                    <?php if($row['marks_obtained'] !== null && $row['max_marks'] > 0): ?>
                                        <div class="fw-bold text-success">
                                            <?= number_format((float)$row['marks_obtained'], 1) ?> / <?= number_format((float)$row['max_marks'], 1) ?>
                                        </div>
                                        <div class="small">
                                            <span class="badge bg-primary"><?= number_format($percentage, 1) ?>%</span>
                                            <span class="badge bg-secondary ms-1">Grade: <?= $grade ?></span>
                                        </div>
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
                                        <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" 
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
                                        <?php if($row['marks_obtained'] !== null): ?>
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="showDetailedResults(<?= htmlspecialchars(json_encode($row)) ?>)"
                                                    title="View Detailed Results">
                                                � Results
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
                        if($row['status_display'] == 'Evaluated') $statusClass = 'bg-success';
                        if($row['status_display'] == 'Approved') $statusClass = 'bg-success';
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
                    <div class="card mb-3 shadow-sm">
                        <div class="card-body">
                            <!-- Subject Header -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <?php if($row['subject_code']): ?>
                                        <h5 class="card-title mb-1 text-primary"><?= htmlspecialchars($row['subject_code']) ?></h5>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($row['subject_name']) ?></p>
                                    <?php else: ?>
                                        <h5 class="card-title text-muted">No subject</h5>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= $statusClass ?> text-white ms-2">
                                    <?= htmlspecialchars($row['status_display']) ?>
                                </span>
                            </div>
                            
                            <!-- Submission Info -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center text-muted small">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        <div>
                                            <div><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                                            <div class="text-muted"><?= date('g:i A', strtotime($row['created_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <?php if($row['evaluator_name']): ?>
                                        <div class="d-flex align-items-center text-muted small">
                                            <i class="fas fa-user-check me-2"></i>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($row['evaluator_name']) ?></div>
                                                <?php if($row['evaluated_at']): ?>
                                                    <div class="text-muted"><?= date('M j', strtotime($row['evaluated_at'])) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center text-muted small">
                                            <i class="fas fa-user-times me-2"></i>
                                            <span>Not assigned</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Marks Section -->
                            <?php if($row['marks_obtained'] !== null && $row['max_marks'] > 0): ?>
                                <div class="bg-light rounded p-3 mb-3">
                                    <div class="row g-2 text-center">
                                        <div class="col-4">
                                            <div class="fw-bold text-success fs-5">
                                                <?= number_format((float)$row['marks_obtained'], 1) ?>
                                            </div>
                                            <div class="small text-muted">Obtained</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold text-info fs-5">
                                                <?= number_format((float)$row['max_marks'], 1) ?>
                                            </div>
                                            <div class="small text-muted">Total</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold text-primary fs-5"><?= $grade ?></div>
                                            <div class="small text-muted"><?= number_format($percentage, 1) ?>%</div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-light rounded p-3 mb-3 text-center">
                                    <div class="text-muted">
                                        <i class="fas fa-clock me-2"></i>
                                        Not evaluated yet
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-file-pdf me-2"></i>View PDF
                                </a>
                                
                                <?php if($row['evaluator_remarks'] || $row['marks_obtained'] !== null): ?>
                                    <div class="row g-2">
                                        <?php if($row['evaluator_remarks']): ?>
                                            <div class="col-6">
                                                <button class="btn btn-outline-info w-100" 
                                                        onclick="showEvaluationFeedback('<?= htmlspecialchars(addslashes($row['evaluator_remarks'])) ?>', '<?= htmlspecialchars($row['subject_code']) ?>', '<?= number_format($percentage, 1) ?>%', '<?= $grade ?>')">
                                                    <i class="fas fa-comment me-2"></i>Feedback
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        <?php if($row['marks_obtained'] !== null): ?>
                                            <div class="col-<?= $row['evaluator_remarks'] ? '6' : '12' ?>">
                                                <button class="btn btn-outline-success w-100" 
                                                        onclick="showDetailedResults(<?= htmlspecialchars(json_encode($row)) ?>)">
                                                    <i class="fas fa-chart-bar me-2"></i>Results
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
</script>

<style>
/* Mobile-friendly enhancements */
@media (max-width: 576px) {
    .page-card {
        margin: 0 -15px;
        border-radius: 0;
        border-left: none;
        border-right: none;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    /* Better touch targets */
    .btn {
        min-height: 44px;
        padding: 0.75rem 1rem;
    }
    
    .btn-sm {
        min-height: 36px;
        padding: 0.5rem 0.75rem;
    }
    
    /* Improve readability */
    .card-title {
        font-size: 1.1rem;
        line-height: 1.3;
    }
    
    .small {
        font-size: 0.8rem;
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

<?php include('../includes/footer.php'); ?>
