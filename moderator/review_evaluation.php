<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

include('../includes/header.php');

$moderator_id = $_SESSION['user_id'];
$submission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$submission_id) {
    $_SESSION['error_message'] = "Invalid submission ID";
    header("Location: submissions.php");
    exit();
}

// Get complete submission details with evaluation
$query = "SELECT 
    s.*,
    sub.code as subject_code,
    sub.name as subject_name,
    sub.grade_level,
    student.name as student_name,
    student.email as student_email,
    student.roll_no,
    evaluator.name as evaluator_name,
    evaluator.email as evaluator_email,
    qp.title as question_paper_title,
    ROUND((s.marks_obtained / s.max_marks) * 100, 2) as percentage,
    CASE 
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 90 THEN 'A+'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 85 THEN 'A'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 80 THEN 'A-'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 75 THEN 'B+'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 70 THEN 'B'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 65 THEN 'B-'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 60 THEN 'C+'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 55 THEN 'C'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 50 THEN 'C-'
        WHEN (s.marks_obtained / s.max_marks) * 100 >= 35 THEN 'D'
        ELSE 'F'
    END as grade
    FROM submissions s
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN users student ON s.student_id = student.id
    LEFT JOIN users evaluator ON s.evaluator_id = evaluator.id
    LEFT JOIN question_papers qp ON s.question_paper_id = qp.id
    LEFT JOIN assignments a ON s.assignment_id = a.id
    WHERE s.id = ? AND (evaluator.moderator_id = ? OR s.moderator_id = ?)";

$stmt = $conn->prepare($query);
if(!$stmt) {
    die("Query preparation failed: " . $conn->error);
}
$stmt->bind_param("iii", $submission_id, $moderator_id, $moderator_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();

if (!$submission) {
    $_SESSION['error_message'] = "Submission not found or you don't have permission to view it";
    header("Location: submissions.php");
    exit();
}

// Check if submission has been evaluated
$is_evaluated = ((isset($submission['evaluation_status']) && $submission['evaluation_status'] === 'evaluated') || $submission['status'] === 'evaluated');
?>

<link rel="stylesheet" href="css/moderator-style.css">

<style>
body {
    background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
    min-height: 100vh;
}

.review-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2.5rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 25px 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.content-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    border: 1px solid rgba(0,0,0,0.05);
}

.pdf-viewer-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
}

.pdf-viewer-card iframe {
    border: 2px solid #e9ecef;
    border-radius: 10px;
}

.marks-card {
    background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
    color: white;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 6px 20px rgba(0,184,148,0.3);
    margin-bottom: 1.5rem;
}

.grade-display {
    font-size: 4rem;
    font-weight: bold;
    margin: 1rem 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.marks-breakdown {
    display: flex;
    justify-content: space-around;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid rgba(255,255,255,0.3);
}

.marks-item h3 {
    font-size: 2.5rem;
    margin: 0;
    font-weight: bold;
}

.marks-item small {
    font-size: 0.9rem;
    opacity: 0.9;
}

.info-section {
    margin-bottom: 1.5rem;
}

.info-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.info-value {
    font-size: 1.1rem;
    color: #212529;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 3px solid #667eea;
}

.remarks-box {
    background: #f8f9fa;
    border-left: 4px solid #00b894;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1rem;
}

.timeline-item {
    padding: 1rem;
    border-left: 3px solid #667eea;
    margin-left: 1rem;
    margin-bottom: 1rem;
    position: relative;
}

.timeline-item:before {
    content: '';
    width: 12px;
    height: 12px;
    background: #667eea;
    border-radius: 50%;
    position: absolute;
    left: -7.5px;
    top: 1.2rem;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
    display: inline-block;
}

.status-evaluated {
    background: linear-gradient(135deg, #00b894, #00cec9);
    color: white;
}

.status-pending {
    background: linear-gradient(135deg, #fdcb6e, #e17055);
    color: white;
}

.action-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.pdf-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.pdf-tab {
    padding: 1rem 2rem;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: #6c757d;
    transition: all 0.3s ease;
}

.pdf-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
}

.pdf-tab:hover {
    color: #667eea;
}

.pdf-content {
    display: none;
}

.pdf-content.active {
    display: block;
}

.not-evaluated-notice {
    background: linear-gradient(135deg, #fdcb6e, #e17055);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    text-align: center;
    margin: 2rem 0;
}

.alert-warning-custom {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.download-btn-group {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}
</style>

<div class="review-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2">
                    <i class="fas fa-clipboard-check me-3"></i>
                    Moderator Review
                </h1>
                <!-- <p class="mb-0 opacity-75">
                    Submission #<?= $submission_id ?> 
                    <?php if($submission['submission_title']): ?>
                        - <?= htmlspecialchars($submission['submission_title']) ?>
                    <?php endif; ?>
                </p> -->
            </div>
            <div class="col-md-4 text-md-end">
                <a href="submissions.php?status=evaluated" class="btn btn-light btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Back to Submissions
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4">
    <div class="row">
        <!-- Left Column - PDF Viewers -->
        <div class="col-lg-8">
            <?php if ($is_evaluated): ?>
                <!-- Annotated PDF Viewer -->
                <div class="pdf-viewer-card">
                    <?php if ($submission['annotated_pdf_url'] && file_exists('../' . $submission['annotated_pdf_url'])): ?>
                    <div id="annotated-pdf" class="pdf-content active">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i class="fas fa-file-signature text-success me-2"></i>
                                Evaluator's Annotated Answer Sheet
                            </h5>
                            <!-- <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i>With Evaluator Annotations
                            </span> -->
                        </div>
                        
                        <!-- <div class="alert-warning-custom">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This is the annotated version uploaded by the evaluator with their marks and comments.
                        </div> -->
                        
                        <iframe src="../<?= htmlspecialchars($submission['annotated_pdf_url']) ?>" 
                                width="100%" 
                                height="850px">
                            Your browser doesn't support PDF viewing.
                        </iframe>
                        
                        <div class="download-btn-group">
                            <a href="../<?= htmlspecialchars($submission['annotated_pdf_url']) ?>" 
                               target="_blank" 
                               class="btn btn-outline-success">
                                <i class="fas fa-external-link-alt me-2"></i>Open in New Tab
                            </a>
                            <a href="../<?= htmlspecialchars($submission['annotated_pdf_url']) ?>" 
                               download 
                               class="btn btn-outline-secondary">
                                <i class="fas fa-download me-2"></i>Download Annotated
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div id="annotated-pdf" class="pdf-content active">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>No Annotated PDF:</strong> The evaluator did not upload an annotated version of this answer sheet.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="not-evaluated-notice">
                    <i class="fas fa-hourglass-half fa-3x mb-3"></i>
                    <h3>Submission Not Yet Evaluated</h3>
                    <p class="mb-0">This submission is still pending evaluation. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column - Evaluation Details -->
        <div class="col-lg-4">
            <?php if ($is_evaluated): ?>
                <!-- Marks & Grade Card -->
                <!-- <div class="marks-card">
                    <h5 class="mb-3">Evaluation Results</h5>
                    <div class="grade-display"><?= $submission['grade'] ?></div>
                    
                    <div class="marks-breakdown">
                        <div class="marks-item">
                            <h3><?= number_format($submission['marks_obtained'], 1) ?></h3>
                            <small>Marks Obtained</small>
                        </div>
                        <div class="marks-item">
                            <h3><?= number_format($submission['max_marks'], 1) ?></h3>
                            <small>Total Marks</small>
                        </div>
                        <div class="marks-item">
                            <h3><?= number_format($submission['percentage'], 1) ?>%</h3>
                            <small>Percentage</small>
                        </div>
                    </div>
                </div> -->

                <!-- Evaluator Remarks -->
                <?php if ($submission['evaluator_remarks']): ?>
                <!-- <div class="content-card">
                    <h6 class="text-success mb-3">
                        <i class="fas fa-comment-dots me-2"></i>Evaluator's Feedback
                    </h6>
                    <div class="remarks-box">
                        <?= nl2br(htmlspecialchars($submission['evaluator_remarks'])) ?>
                    </div>
                </div> -->
                <?php endif; ?>
            <?php endif; ?>

            <!-- Student Information -->
            <!-- <div class="content-card">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-user-graduate me-2"></i>Student Information
                </h6>
                
                <div class="info-section">
                    <div class="info-label">Student Name</div>
                    <div class="info-value">
                        <?= htmlspecialchars($submission['student_name']) ?>
                        <?php if ($submission['roll_no']): ?>
                            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($submission['roll_no']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-section">
                    <div class="info-label">Email</div>
                    <div class="info-value">
                        <a href="mailto:<?= htmlspecialchars($submission['student_email']) ?>">
                            <?= htmlspecialchars($submission['student_email']) ?>
                        </a>
                    </div>
                </div>

                <div class="info-section">
                    <div class="info-label">Subject</div>
                    <div class="info-value">
                        <span class="badge bg-primary me-2"><?= htmlspecialchars($submission['subject_code']) ?></span>
                        <?= htmlspecialchars($submission['subject_name']) ?>
                        <?php if ($submission['grade_level']): ?>
                            <span class="badge bg-secondary ms-2">Grade <?= $submission['grade_level'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($submission['assignment_title']) && $submission['assignment_title']): ?>
                <div class="info-section">
                    <div class="info-label">Assignment</div>
                    <div class="info-value">
                        <?= htmlspecialchars($submission['assignment_title']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($submission['question_paper_title']): ?>
                <div class="info-section">
                    <div class="info-label">Question Paper</div>
                    <div class="info-value">
                        <?= htmlspecialchars($submission['question_paper_title']) ?>
                        <?php if ($submission['qp_total_marks']): ?>
                            <span class="badge bg-info ms-2"><?= $submission['qp_total_marks'] ?> marks</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div> -->

            <!-- Evaluator Information -->
            <?php if ($submission['evaluator_name']): ?>
            <!-- <div class="content-card">
                <h6 class="text-success mb-3">
                    <i class="fas fa-user-check me-2"></i>Evaluator Information
                </h6>
                
                <div class="info-section">
                    <div class="info-label">Evaluator Name</div>
                    <div class="info-value">
                        <?= htmlspecialchars($submission['evaluator_name']) ?>
                    </div>
                </div>

                <div class="info-section">
                    <div class="info-label">Email</div>
                    <div class="info-value">
                        <a href="mailto:<?= htmlspecialchars($submission['evaluator_email']) ?>">
                            <?= htmlspecialchars($submission['evaluator_email']) ?>
                        </a>
                    </div>
                </div>

                <?php if ($submission['evaluated_at']): ?>
                <div class="info-section">
                    <div class="info-label">Evaluated On</div>
                    <div class="info-value">
                        <?= date('F j, Y \a\t g:i A', strtotime($submission['evaluated_at'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div> -->
            <?php endif; ?>

            <!-- Moderator Actions -->
            <?php if ($is_evaluated): ?>
            <!-- Evaluation Form/Details -->
            <div class="content-card">
                <h6 class="mb-3">
                    <i class="fas fa-clipboard-list me-2"></i>Evaluation Details
                </h6>
                
                <form id="moderatorReviewForm">
                    <input type="hidden" name="submission_id" value="<?= $submission_id ?>">
                    
                    <?php
                    // Get per-question marks if available with error handling
                    $per_question = [];
                    if (!empty($submission['per_question_marks'])) {
                        $decoded = json_decode($submission['per_question_marks'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $per_question = $decoded;
                            error_log("MODERATOR REVIEW: Successfully decoded per_question_marks for submission {$submission['id']}: " . print_r($per_question, true));
                        } else {
                            error_log("MODERATOR REVIEW: Failed to decode per_question_marks for submission {$submission['id']}: " . json_last_error_msg());
                        }
                    } else {
                        error_log("MODERATOR REVIEW: No per_question_marks data for submission {$submission['id']}");
                    }
                    
                    // Ensure marks_obtained has a value (needed for display later)
                    $marks_value = isset($submission['marks_obtained']) && $submission['marks_obtained'] !== '' && $submission['marks_obtained'] !== null 
                        ? floatval($submission['marks_obtained']) 
                        : 0;
                    
                    // Determine question structure based on subject code and division
                    // Use division if available, otherwise use grade_level
                    $division = isset($submission['division']) ? trim(strtolower($submission['division'])) : '';
                    if (empty($division) && isset($submission['grade_level'])) {
                        $division = trim(strtolower($submission['grade_level']));
                    }
                    $subject_code = isset($submission['subject_code']) ? trim(strtolower($submission['subject_code'])) : '';
                    $show_question_breakdown = false;
                    $parts = [];
                    $q_max = $submission['max_marks'];
                    
                    // Check if this is a 10th grade submission with detailed structure
                    if ($division === '10th' || $division === '10th class' || $division === '10' || $division === '10th grade' || strpos($subject_code, '10th') !== false) {
                        $show_question_breakdown = true;
                        
                        if ($subject_code === 'thk_10th') {
                            $parts = [
                                ['label' => 'Part 1', 'count' => 6, 'marks' => 1],
                                ['label' => 'Part 2', 'count' => 4, 'marks' => 1],
                                ['label' => 'Part 3', 'count' => 7, 'marks' => 1],
                                ['label' => 'Part 4', 'count' => 10, 'marks' => 2],
                                ['label' => 'Part 5', 'count' => 2, 'marks' => 3],
                                ['label' => 'Part 6', 'count' => 1, 'marks' => 3],
                                ['label' => 'Part 7', 'count' => 1, 'marks' => 3],
                                ['label' => 'Part 8', 'count' => 1, 'marks' => 3],
                                ['label' => 'Part 9', 'count' => 6, 'marks' => 3],
                                ['label' => 'Part 10', 'count' => 1, 'marks' => 4],
                                ['label' => 'Part 12', 'count' => 2, 'marks' => 4],
                                ['label' => 'Part 13', 'count' => 1, 'marks' => 4],
                                ['label' => 'Part 14', 'count' => 1, 'marks' => 5],
                                ['label' => 'Part 15', 'count' => 1, 'marks' => 5],
                            ];
                            $q_max = 100;
                        } else if ($subject_code === 'eng_10th' || $subject_code === 'kan_10th') {
                            if ($subject_code === 'eng_10th') {
                                $parts = [
                                    ['label' => 'Part 1', 'count' => 4, 'marks' => 1],
                                    ['label' => 'Part 2', 'count' => 12, 'marks' => 1],
                                    ['label' => 'Part 3', 'count' => 1, 'marks' => 2],
                                    ['label' => 'Part 4', 'count' => 7, 'marks' => 2],
                                    ['label' => 'Part 5', 'count' => 2, 'marks' => 3],
                                    ['label' => 'Part 6', 'count' => 4, 'marks' => 3],
                                    ['label' => 'Part 7', 'count' => 1, 'marks' => 3],
                                    ['label' => 'Part 8', 'count' => 1, 'marks' => 3],
                                    ['label' => 'Part 9', 'count' => 1, 'marks' => 3],
                                    ['label' => 'Part 10', 'count' => 1, 'marks' => 4],
                                    ['label' => 'Part 11', 'count' => 1, 'marks' => 4],
                                    ['label' => 'Part 12', 'count' => 1, 'marks' => 4],
                                    ['label' => 'Part 13', 'count' => 1, 'marks' => 4],
                                    ['label' => 'Part 14', 'count' => 1, 'marks' => 5],
                                ];
                            } else { // kan_10th
                                $parts = [
                                    ['label' => 'Part 1', 'count' => 4, 'marks' => 1],
                                    ['label' => 'Part 2', 'count' => 12, 'marks' => 1],
                                    ['label' => 'Part 3', 'count' => 8, 'marks' => 2],
                                    ['label' => 'Part 4', 'count' => 1, 'marks' => 3],
                                    ['label' => 'Part 5', 'count' => 1, 'marks' => 3],
                                    ['label' => 'Part 6', 'count' => 1, 'marks' => 3],
                                    ['label' => 'Part 7', 'count' => 1, 'marks' => 3],
                                    ['label' => 'Part 8', 'count' => 5, 'marks' => 3],
                                    ['label' => 'Part 9', 'count' => 1, 'marks' => 4],
                                    ['label' => 'Part 10', 'count' => 1, 'marks' => 4],
                                    ['label' => 'Part 11', 'count' => 1, 'marks' => 4],
                                    ['label' => 'Part 12', 'count' => 1, 'marks' => 4],
                                    ['label' => 'Part 13', 'count' => 1, 'marks' => 5],
                                ];
                            }
                            $q_max = 80;
                        } else if ($subject_code === 'kma_10th' || $subject_code === 'ema_10th') {
                            $parts = [
                                ['label' => 'Part 1', 'count' => 8, 'marks' => 1],
                                ['label' => 'Part 2', 'count' => 8, 'marks' => 1],
                                ['label' => 'Part 3', 'count' => 8, 'marks' => 2],
                                ['label' => 'Part 4', 'count' => 9, 'marks' => 3],
                                ['label' => 'Part 5', 'count' => 4, 'marks' => 4],
                                ['label' => 'Part 6', 'count' => 1, 'marks' => 5],
                            ];
                            $q_max = 80;
                        }
                    }
                    
                    // Show question breakdown if structure is available
                    if ($show_question_breakdown && !empty($parts)):
                        $q_number = 1;
                        $has_per_question_data = !empty($per_question);
                    ?>
                    
                    <!-- Evaluation Form Section -->
                    <div class="card mb-3" style="border: 2px solid #e3e6ea; border-radius: 8px;">
                        <div class="card-header bg-light" style="border-bottom: 2px solid #e3e6ea;">
                            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas fa-edit me-2"></i>Evaluation Form
                                </span>
                                <?php if (!$has_per_question_data): ?>
                                    <span class="badge bg-warning text-dark">No question-wise marks - Please fill</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">Editable by Moderator</span>
                                <?php endif; ?>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <!-- Marks Allocation Section -->
                            <div class="accordion" id="marksAccordion">
                                <div class="accordion-item" style="border: none; border-bottom: 1px solid #e3e6ea;">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#marksAllocation" aria-expanded="true">
                                            <i class="fas fa-calculator me-2"></i>
                                            <strong>Marks Allocation</strong>
                                        </button>
                                    </h2>
                                    <div id="marksAllocation" class="accordion-collapse collapse show" data-bs-parent="#marksAccordion">
                                        <div class="accordion-body p-3" style="background-color: #f8f9fa;">
                                            <?php if (!$has_per_question_data): ?>
                                            <div class="alert alert-warning mb-3" style="font-size: 0.85rem;">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <strong>Note:</strong> The evaluator gave a total of <strong><?= number_format($marks_value, 2) ?> marks</strong> but didn't fill question-wise breakdown. 
                                                Please enter individual question marks below and click "Calculate Total" to verify.
                                            </div>
                                            <?php else: ?>
                                            <!-- <div class="alert alert-info mb-3" style="font-size: 0.85rem;">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Question-wise marks from evaluator.</strong> You can review and edit these marks if needed. Click "Calculate Total" after any changes.
                                            </div> -->
                                            <?php endif; ?>
                                            
                                            <div style="max-height: 400px; overflow-y: auto;">
                                                <?php foreach ($parts as $part_index => $part): ?>
                                                    <div class="mb-3 pb-2" style="border-bottom: 1px solid #dee2e6;">
                                                        <div class="mb-2 px-2 py-1" style="background-color: #e7f3ff; border-left: 3px solid #0d6efd;">
                                                            <strong style="color: #0d6efd; font-size: 0.9rem;">
                                                                <?= $part['label'] ?>:
                                                            </strong>
                                                        </div>
                                                        <div class="px-2">
                                                            <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                                    <label class="form-label mb-0" style="min-width: 35px; font-size: 0.85rem; font-weight: 500;">
                                                                        Q<?= $q_number ?>
                                                                    </label>
                                                                    <input type="number"
                                                                           class="form-control form-control-sm question-mark-input"
                                                                           name="question_marks[<?= $q_number ?>]"
                                                                           value="<?= isset($per_question[$q_number]) ? number_format($per_question[$q_number], 2) : '0.00' ?>"
                                                                           min="0"
                                                                           max="<?= $part['marks'] ?>"
                                                                           step="0.25"
                                                                           data-max="<?= $part['marks'] ?>"
                                                                           style="max-width: 70px; text-align: center;"
                                                                           placeholder="0">
                                                                    <span class="text-muted" style="font-size: 0.85rem;">/ <?= $part['marks'] ?></span>
                                                                </div>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <div class="mt-3 d-grid">
                                                <button type="button" class="btn btn-primary btn-sm" onclick="calculateTotalFromQuestions()">
                                                    <i class="fas fa-calculator me-2"></i>Calculate Total from Questions
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Evaluation Info -->
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Evaluated by:</strong> <?= htmlspecialchars($submission['evaluator_name']) ?>
                        <br>
                        <strong>Evaluation Date:</strong> <?= date('M j, Y \a\t g:i A', strtotime($submission['evaluated_at'])) ?>
                    </div>
                    
                    <!-- Total Marks Section -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Marks Obtained <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control" 
                                   value="<?= number_format($marks_value, 2) ?>" 
                                   step="0.25"
                                   min="0"
                                   max="<?= $q_max ?>"
                                   id="marks_obtained"
                                   name="marks_obtained"
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Max Marks</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="max_marks"
                                   value="<?= $q_max ?>" 
                                   readonly
                                   style="background-color: #f8f9fa;">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Percentage</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="percentage"
                                   value="<?= number_format($submission['percentage'], 2) ?>%" 
                                   readonly
                                   style="background-color: #f8f9fa;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Grade</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="grade"
                                   value="<?= $submission['grade'] ?>" 
                                   readonly
                                   style="background-color: #f8f9fa; font-size: 1.2rem; font-weight: bold; text-align: center;">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Evaluator's Remarks</label>
                        <textarea class="form-control" 
                                  rows="4" 
                                  readonly
                                  style="background-color: #f8f9fa;"><?= htmlspecialchars($submission['evaluator_remarks'] ?? '') ?></textarea>
                    </div>
                    
                    <?php if($submission['evaluation_notes']): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Evaluation Notes</label>
                        <textarea class="form-control" 
                                  rows="3" 
                                  readonly
                                  style="background-color: #f8f9fa;"><?= htmlspecialchars($submission['evaluation_notes']) ?></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <h6 class="mb-3 text-primary">
                        <i class="fas fa-user-shield me-2"></i>Moderator's Review
                    </h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Moderator's Remarks (Optional)</label>
                        <textarea class="form-control" 
                                  rows="3" 
                                  name="moderator_remarks"
                                  id="moderator_remarks"
                                  placeholder="Add your comments or feedback here..."><?= htmlspecialchars($submission['moderator_remarks'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success btn-lg" onclick="saveModeratorReview()">
                            <i class="fas fa-save me-2"></i>Save Moderator Review
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-undo me-2"></i>Reset Changes
                        </button>
                    </div>
                    
                    <div class="d-none">
                        <!-- Hidden placeholder for removed alert -->
                    </div>
                </form>
            </div>
            
            <!-- <div class="content-card">
                <h6 class="mb-3">
                    <i class="fas fa-tools me-2"></i>Moderator Actions
                </h6>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-warning action-btn" onclick="overrideMarks()">
                        <i class="fas fa-edit me-2"></i>Override Marks
                    </button>
                    
                    <button class="btn btn-success action-btn" onclick="approveSubmission()">
                        <i class="fas fa-check-circle me-2"></i>Approve Evaluation
                    </button>
                    
                    <button class="btn btn-danger action-btn" onclick="rejectSubmission()">
                        <i class="fas fa-times-circle me-2"></i>Request Re-evaluation
                    </button>
                    
                    <a href="mailto:<?= htmlspecialchars($submission['evaluator_email']) ?>" 
                       class="btn btn-outline-primary action-btn">
                        <i class="fas fa-envelope me-2"></i>Contact Evaluator
                    </a>
                    
                    <a href="mailto:<?= htmlspecialchars($submission['student_email']) ?>" 
                       class="btn btn-outline-info action-btn">
                        <i class="fas fa-envelope me-2"></i>Contact Student
                    </a>
                </div>
            </div> -->
            <?php endif; ?>

            <!-- Status Badge -->
            <div class="content-card text-center">
                <div class="info-label mb-2">Current Status</div>
                <span class="status-badge status-<?= $submission['status'] ?>">
                    <?= ucfirst(str_replace('_', ' ', $submission['status'])) ?>
                </span>
                <?php $isPublished = !empty($submission['is_published']); ?>
                <?php if ($submission['status'] === 'evaluated' && !$isPublished): ?>
                    <div class="mt-3">
                        <button type="button" class="btn btn-success" onclick="approveAndPublish()">
                            <i class="fas fa-check-circle me-2"></i>Approve and Publish to Student
                        </button>
                    </div>
                <?php elseif ($isPublished): ?>
                    <div class="mt-3">
                        <span class="badge bg-success">Published & Visible to Student</span>
                    </div>
                <?php endif; ?>
            </div>
<script>
function approveAndPublish() {
    if (!confirm('Are you sure you want to approve and publish this evaluation? This will make the results visible to the student.')) return;
    const submissionId = <?= $submission_id ?>;
    fetch('publish_evaluation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'submission_id=' + encodeURIComponent(submissionId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Evaluation published and now visible to student!');
            location.reload();
        } else {
            alert('✗ Error: ' + (data.message || 'Failed to publish evaluation'));
        }
    })
    .catch(error => {
        alert('✗ Error publishing evaluation: ' + error.message);
    });
}
</script>
        </div>
    </div>
</div>

<script>
// Calculate total marks from question-wise inputs
function calculateTotalFromQuestions() {
    const questionInputs = document.querySelectorAll('.question-mark-input');
    let total = 0;
    
    questionInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        const max = parseFloat(input.getAttribute('data-max')) || 0;
        
        if (value > max) {
            alert(`Question ${input.name} has marks (${value}) greater than maximum (${max}). Please correct it.`);
            input.focus();
            return;
        }
        
        total += value;
    });
    
    const maxMarks = parseFloat(document.getElementById('max_marks').value) || 0;
    
    if (total > maxMarks) {
        alert(`Total marks (${total}) cannot exceed maximum marks (${maxMarks})`);
        return;
    }
    
    // Update marks obtained
    document.getElementById('marks_obtained').value = total.toFixed(2);
    
    // Update percentage
    const percentage = (total / maxMarks * 100).toFixed(2);
    document.getElementById('percentage').value = percentage + '%';
    
    // Update grade
    let grade = 'F';
    if (percentage >= 90) grade = 'A+';
    else if (percentage >= 85) grade = 'A';
    else if (percentage >= 80) grade = 'A-';
    else if (percentage >= 75) grade = 'B+';
    else if (percentage >= 70) grade = 'B';
    else if (percentage >= 65) grade = 'B-';
    else if (percentage >= 60) grade = 'C+';
    else if (percentage >= 55) grade = 'C';
    else if (percentage >= 50) grade = 'C-';
    else if (percentage >= 35) grade = 'D';
    
    document.getElementById('grade').value = grade;
    
    alert(`Total marks calculated: ${total.toFixed(2)}/${maxMarks}\nPercentage: ${percentage}%\nGrade: ${grade}`);
}

// Save moderator review with question-wise marks
function saveModeratorReview() {
    const submissionId = <?= $submission_id ?>;
    const questionInputs = document.querySelectorAll('.question-mark-input');
    const questionMarks = {};
    
    // Collect all question marks
    questionInputs.forEach(input => {
        const questionNum = input.name.match(/\[(\d+)\]/)[1];
        questionMarks[questionNum] = parseFloat(input.value) || 0;
    });
    
    const marksObtained = parseFloat(document.getElementById('marks_obtained').value) || 0;
    const maxMarks = parseFloat(document.getElementById('max_marks').value) || 0;
    const moderatorRemarks = document.getElementById('moderator_remarks').value;
    
    // Validate
    if (marksObtained > maxMarks) {
        alert('Marks obtained cannot exceed maximum marks!');
        return;
    }
    
    if (!confirm('Are you sure you want to save these changes?\n\nThis will update the submission with your review.')) {
        return;
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('submission_id', submissionId);
    formData.append('question_marks', JSON.stringify(questionMarks));
    formData.append('marks_obtained', marksObtained);
    formData.append('max_marks', maxMarks);
    formData.append('moderator_remarks', moderatorRemarks);
    
    // Send to server
    fetch('save_moderator_review.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Moderator review saved successfully!');
            location.reload();
        } else {
            alert('✗ Error: ' + (data.message || 'Failed to save review'));
        }
    })
    .catch(error => {
        alert('✗ Error saving review: ' + error.message);
    });
}

// Auto-calculate when marks_obtained changes
document.addEventListener('DOMContentLoaded', function() {
    const marksInput = document.getElementById('marks_obtained');
    const maxMarksInput = document.getElementById('max_marks');
    
    if (marksInput && maxMarksInput) {
        marksInput.addEventListener('input', function() {
            const marks = parseFloat(this.value) || 0;
            const maxMarks = parseFloat(maxMarksInput.value) || 0;
            
            if (marks > maxMarks) {
                alert('Marks obtained cannot exceed maximum marks');
                this.value = maxMarks;
                return;
            }
            
            const percentage = (marks / maxMarks * 100).toFixed(2);
            document.getElementById('percentage').value = percentage + '%';
            
            let grade = 'F';
            if (percentage >= 90) grade = 'A+';
            else if (percentage >= 85) grade = 'A';
            else if (percentage >= 80) grade = 'A-';
            else if (percentage >= 75) grade = 'B+';
            else if (percentage >= 70) grade = 'B';
            else if (percentage >= 65) grade = 'B-';
            else if (percentage >= 60) grade = 'C+';
            else if (percentage >= 55) grade = 'C';
            else if (percentage >= 50) grade = 'C-';
            else if (percentage >= 35) grade = 'D';
            
            document.getElementById('grade').value = grade;
        });
    }
});

function overrideMarks() {
    const currentMarks = <?= $submission['marks_obtained'] ?? 0 ?>;
    const maxMarks = <?= $submission['max_marks'] ?? 100 ?>;
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

function approveSubmission() {
    const submissionId = <?= $submission_id ?>;
    const remarks = prompt('Optional: Add remarks for approval (or leave blank):');
    
    if (remarks === null) return; // User cancelled
    
    if (confirm('Are you sure you want to approve this evaluation?\n\nThis will finalize the marks and notify the student.')) {
        fetch('approve_submission.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `submission_id=${submissionId}&moderator_remarks=${encodeURIComponent(remarks || '')}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Submission approved successfully');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to approve submission'));
            }
        })
        .catch(error => {
            alert('Error approving submission: ' + error.message);
        });
    }
}

function rejectSubmission() {
    const submissionId = <?= $submission_id ?>;
    const reason = prompt('Please provide a reason for requesting re-evaluation:');
    
    if (!reason || reason.trim() === '') {
        if (reason !== null) alert('Reason is required for re-evaluation request');
        return;
    }
    
    if (confirm('Are you sure you want to request re-evaluation?\n\nReason: ' + reason + '\n\nThis will notify the evaluator to review this submission again.')) {
        fetch('request_reevaluation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `submission_id=${submissionId}&reason=${encodeURIComponent(reason)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Re-evaluation request sent successfully');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to send re-evaluation request'));
            }
        })
        .catch(error => {
            alert('Error sending re-evaluation request: ' + error.message);
        });
    }
}

// Add smooth fade-in animation
document.addEventListener('DOMContentLoaded', function() {
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.5s';
        document.body.style.opacity = '1';
    }, 100);
});
</script>

<?php include('../includes/footer.php'); ?>
