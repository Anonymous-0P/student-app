<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

include('../includes/header.php');
?>
<link rel="stylesheet" href="css/moderator-style.css">

<div class="moderator-content">
<?php

$moderator_id = $_SESSION['user_id'];
$submission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$submission_id) {
    $_SESSION['error_message'] = "Invalid submission ID";
    header("Location: dashboard.php");
    exit();
}

// Get submission details with evaluation
$query = "SELECT 
    s.*,
    sub.code as subject_code,
    sub.name as subject_name,
    student.name as student_name,
    student.email as student_email,
    evaluator.name as evaluator_name,
    evaluator.email as evaluator_email,
    ROUND((s.marks_obtained / s.max_marks) * 100, 2) as percentage,
    CASE 
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 90 THEN 'A+'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 80 THEN 'A'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 70 THEN 'B'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 60 THEN 'C'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 50 THEN 'D'
        ELSE 'F'
    END as grade
    FROM submissions s
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN users student ON s.student_id = student.id
    LEFT JOIN users evaluator ON s.evaluator_id = evaluator.id
    WHERE s.id = ? AND s.moderator_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $submission_id, $moderator_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();

if (!$submission) {
    $_SESSION['error_message'] = "Submission not found or you don't have permission to view it";
    header("Location: dashboard.php");
    exit();
}

// Check if evaluator ratings table exists and get rating if available
$rating = null;
$tableExists = $conn->query("SHOW TABLES LIKE 'evaluator_ratings'");
if ($tableExists && $tableExists->num_rows > 0 && $submission['evaluator_id']) {
    $rating_query = "SELECT * FROM evaluator_ratings WHERE submission_id = ? LIMIT 1";
    $rating_stmt = $conn->prepare($rating_query);
    $rating_stmt->bind_param("i", $submission_id);
    $rating_stmt->execute();
    $rating_result = $rating_stmt->get_result();
    $rating = $rating_result->fetch_assoc();
}

$page_title = "View Evaluation - Submission #" . $submission_id;
?>

<style>
body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}

.evaluation-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
}

.info-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
    border: none;
}

.info-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.info-value {
    font-size: 1.1rem;
    color: #212529;
    margin-bottom: 1rem;
}

.grade-badge {
    display: inline-block;
    padding: 1rem 2rem;
    border-radius: 50%;
    font-size: 2rem;
    font-weight: bold;
    color: white;
    min-width: 80px;
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.grade-A\+, .grade-A { background: linear-gradient(135deg, #00b894, #00cec9); }
.grade-B { background: linear-gradient(135deg, #74b9ff, #0984e3); }
.grade-C { background: linear-gradient(135deg, #fdcb6e, #e17055); }
.grade-D, .grade-F { background: linear-gradient(135deg, #fd79a8, #d63031); }

.pdf-viewer-container {
    background: white;
    border-radius: 15px;
    padding: 1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.marks-breakdown {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
}

.rating-stars {
    color: #ffa502;
    font-size: 1.2rem;
}

.rating-section {
    background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
    border-radius: 15px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}
</style>

<div class="evaluation-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Evaluation Review
                </h2>
                <p class="mb-0 opacity-75">Submission #<?= $submission_id ?></p>
            </div>
            <a href="marks_access.php?evaluator_id=<?= $submission['evaluator_id'] ?>" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back to Marks
            </a>
        </div>
    </div>
</div>

<div class="container">
    <div class="row">
        <!-- Left Column - PDF Viewer -->
        <div class="col-lg-8">
            <?php if ($submission['pdf_url'] && file_exists('../' . $submission['pdf_url'])): ?>
                <div class="pdf-viewer-container">
                    <h5 class="mb-3"><i class="fas fa-file-pdf text-danger me-2"></i>Student Answer Sheet</h5>
                    <iframe src="../<?= htmlspecialchars($submission['pdf_url']) ?>" 
                            width="100%" 
                            height="800px" 
                            style="border: none; border-radius: 10px;">
                        Your browser doesn't support PDF viewing.
                    </iframe>
                    <div class="mt-3">
                        <a href="../<?= htmlspecialchars($submission['pdf_url']) ?>" 
                           target="_blank" 
                           class="btn btn-outline-primary">
                            <i class="fas fa-external-link-alt me-2"></i>Open in New Tab
                        </a>
                        <a href="../<?= htmlspecialchars($submission['pdf_url']) ?>" 
                           download 
                           class="btn btn-outline-secondary">
                            <i class="fas fa-download me-2"></i>Download PDF
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($submission['annotated_pdf_url']) && file_exists('../' . $submission['annotated_pdf_url'])): ?>
                <div class="pdf-viewer-container">
                    <h5 class="mb-3"><i class="fas fa-file-pdf text-success me-2"></i>Annotated Answer Sheet</h5>
                    <iframe src="../<?= htmlspecialchars($submission['annotated_pdf_url']) ?>" 
                            width="100%" 
                            height="800px" 
                            style="border: none; border-radius: 10px;">
                        Your browser doesn't support PDF viewing.
                    </iframe>
                    <div class="mt-3">
                        <a href="../<?= htmlspecialchars($submission['annotated_pdf_url']) ?>" 
                           target="_blank" 
                           class="btn btn-outline-success">
                            <i class="fas fa-external-link-alt me-2"></i>Open Annotated PDF
                        </a>
                        <a href="../<?= htmlspecialchars($submission['annotated_pdf_url']) ?>" 
                           download 
                           class="btn btn-outline-secondary">
                            <i class="fas fa-download me-2"></i>Download Annotated PDF
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="info-card text-center py-5">
                    <i class="fas fa-file-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">PDF Not Available</h5>
                    <p class="text-muted">The submission file could not be found.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column - Evaluation Details -->
        <div class="col-lg-4">
            <!-- Marks & Grade -->
            <div class="info-card text-center">
                <h5 class="mb-3">Evaluation Results</h5>
                
                <div class="mb-4">
                    <div class="grade-badge grade-<?= $submission['grade'] ?>">
                        <?= $submission['grade'] ?>
                    </div>
                </div>
                
                <div class="marks-breakdown">
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <h3 class="text-success mb-0"><?= number_format($submission['marks_obtained'], 1) ?></h3>
                            <small class="text-muted">Obtained</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-info mb-0"><?= number_format($submission['max_marks'], 1) ?></h3>
                            <small class="text-muted">Total</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-primary mb-0"><?= number_format($submission['percentage'], 1) ?>%</h3>
                            <small class="text-muted">Percentage</small>
                        </div>
                    </div>
                </div>
                
                <span class="status-badge bg-success text-white">
                    <i class="fas fa-check-circle me-1"></i>Evaluated
                </span>
            </div>

            <!-- Student Information -->
            <div class="info-card">
                <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Student Details</h6>
                <div class="info-label">Name</div>
                <div class="info-value"><?= htmlspecialchars($submission['student_name']) ?></div>
                
                <div class="info-label">Email</div>
                <div class="info-value"><?= htmlspecialchars($submission['student_email']) ?></div>
                
                <div class="info-label">Subject</div>
                <div class="info-value">
                    <span class="badge bg-secondary"><?= htmlspecialchars($submission['subject_code']) ?></span>
                    <?= htmlspecialchars($submission['subject_name']) ?>
                </div>
            </div>

            <!-- Evaluator Information -->
            <div class="info-card">
                <h6 class="text-success mb-3"><i class="fas fa-user-check me-2"></i>Evaluator Details</h6>
                <div class="info-label">Name</div>
                <div class="info-value"><?= htmlspecialchars($submission['evaluator_name'] ?: 'Not Assigned') ?></div>
                
                <?php if ($submission['evaluator_email']): ?>
                <div class="info-label">Email</div>
                <div class="info-value"><?= htmlspecialchars($submission['evaluator_email']) ?></div>
                <?php endif; ?>
                
                <div class="info-label">Evaluated On</div>
                <div class="info-value">
                    <?php if ($submission['evaluated_at']): ?>
                        <?= date('F j, Y', strtotime($submission['evaluated_at'])) ?>
                        <br>
                        <small class="text-muted"><?= date('g:i A', strtotime($submission['evaluated_at'])) ?></small>
                    <?php else: ?>
                        <span class="text-muted">Not evaluated yet</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Evaluator Remarks -->
            <?php if ($submission['evaluator_remarks']): ?>
            <div class="info-card">
                <h6 class="text-info mb-3"><i class="fas fa-comment-alt me-2"></i>Evaluator Feedback</h6>
                <div class="bg-light p-3 rounded">
                    <p class="mb-0"><?= nl2br(htmlspecialchars($submission['evaluator_remarks'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Student Rating (if available) -->
            <?php if ($rating): ?>
            <div class="rating-section">
                <h6 class="mb-3"><i class="fas fa-star me-2"></i>Student Rating</h6>
                
                <div class="mb-3">
                    <div class="info-label">Overall Rating</div>
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= $rating['overall_rating'] ? '' : 'text-muted' ?>"></i>
                        <?php endfor; ?>
                        <span class="ms-2 text-dark"><?= $rating['overall_rating'] ?>/5</span>
                    </div>
                </div>
                
                <div class="mb-2">
                    <div class="info-label">Evaluation Quality</div>
                    <span class="badge bg-<?= $rating['evaluation_quality'] == 'excellent' ? 'success' : ($rating['evaluation_quality'] == 'good' ? 'primary' : 'secondary') ?>">
                        <?= ucfirst($rating['evaluation_quality']) ?>
                    </span>
                </div>
                
                <div class="mb-2">
                    <div class="info-label">Feedback Helpfulness</div>
                    <span class="badge bg-<?= $rating['feedback_helpfulness'] == 'very_helpful' ? 'success' : ($rating['feedback_helpfulness'] == 'helpful' ? 'primary' : 'secondary') ?>">
                        <?= ucfirst(str_replace('_', ' ', $rating['feedback_helpfulness'])) ?>
                    </span>
                </div>
                
                <?php if ($rating['comments']): ?>
                <div class="mt-3">
                    <div class="info-label">Student Comments</div>
                    <div class="bg-white p-2 rounded">
                        <small><?= htmlspecialchars($rating['comments']) ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="info-card">
                <h6 class="mb-3">Actions</h6>
                <div class="action-buttons">
                    <button class="btn btn-warning flex-fill" onclick="overrideMarks()">
                        <i class="fas fa-edit me-2"></i>Override Marks
                    </button>
                    <a href="mailto:<?= htmlspecialchars($submission['student_email']) ?>" class="btn btn-outline-primary flex-fill">
                        <i class="fas fa-envelope me-2"></i>Contact Student
                    </a>
                    <a href="mailto:<?= htmlspecialchars($submission['evaluator_email']) ?>" class="btn btn-outline-success flex-fill">
                        <i class="fas fa-envelope me-2"></i>Contact Evaluator
                    </a>
                </div>
            </div>

            <!-- Submission Timeline -->
            <div class="info-card">
                <h6 class="text-secondary mb-3"><i class="fas fa-clock me-2"></i>Timeline</h6>
                <div class="timeline">
                    <div class="mb-3">
                        <div class="info-label">Submitted</div>
                        <div><?= date('M j, Y g:i A', strtotime($submission['created_at'])) ?></div>
                    </div>
                    <?php if ($submission['evaluated_at']): ?>
                    <div class="mb-3">
                        <div class="info-label">Evaluated</div>
                        <div><?= date('M j, Y g:i A', strtotime($submission['evaluated_at'])) ?></div>
                    </div>
                    <div>
                        <div class="info-label">Turnaround Time</div>
                        <div>
                            <?php
                            $diff = strtotime($submission['evaluated_at']) - strtotime($submission['created_at']);
                            $days = floor($diff / (60 * 60 * 24));
                            $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
                            echo $days > 0 ? $days . ' day' . ($days > 1 ? 's' : '') . ' ' : '';
                            echo $hours . ' hour' . ($hours != 1 ? 's' : '');
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function overrideMarks() {
    const currentMarks = <?= $submission['marks_obtained'] ?>;
    const maxMarks = <?= $submission['max_marks'] ?>;
    const submissionId = <?= $submission_id ?>;
    
    const newMarks = prompt(`Current marks: ${currentMarks}/${maxMarks}\n\nEnter new marks obtained (0-${maxMarks}):`, currentMarks);
    
    if (newMarks === null) return;
    
    const marks = parseFloat(newMarks);
    if (isNaN(marks) || marks < 0 || marks > maxMarks) {
        alert('Invalid marks. Please enter a number between 0 and ' + maxMarks);
        return;
    }
    
    const reason = prompt('Please provide a reason for overriding marks:');
    if (!reason || reason.trim() === '') {
        alert('Reason is required for mark override');
        return;
    }
    
    if (confirm(`Are you sure you want to override marks to ${marks}/${maxMarks}?\n\nReason: ${reason}\n\nThis action will be logged.`)) {
        fetch('override_marks.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `submission_id=${submissionId}&new_marks=${marks}&max_marks=${maxMarks}&reason=${encodeURIComponent(reason)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Marks updated successfully');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to update marks'));
            }
        })
        .catch(error => {
            alert('Error updating marks: ' + error.message);
        });
    }
}
</script>

</div><!-- Close moderator-content -->

<?php include('../includes/footer.php'); ?>
