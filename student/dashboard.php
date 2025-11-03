<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];

// Load available subjects with an active template (optionally filtered by student's year/department)
$year = $_SESSION['year'] ?? null;
$department = $_SESSION['department'] ?? null;

// Get subjects with available question papers (limit to 3 for dashboard)
$subjectsQuery = "SELECT s.*, qp.id as paper_id, qp.title as paper_title, COUNT(qp.id) as paper_count
                  FROM subjects s 
                  LEFT JOIN question_papers qp ON qp.subject_id = s.id 
                  WHERE s.is_active = 1";
$types = '';
$params = [];
if ($year) { $subjectsQuery .= " AND (s.year IS NULL OR s.year = ?)"; $types .= 'i'; $params[] = $year; }
if ($department) { $subjectsQuery .= " AND (s.department IS NULL OR s.department = ?)"; $types .= 's'; $params[] = $department; }
$subjectsQuery .= " GROUP BY s.id ORDER BY s.code LIMIT 3";

$subjectsStmt = $conn->prepare($subjectsQuery);
if (!empty($params)) {
    $subjectsStmt->bind_param($types, ...$params);
}
$subjectsStmt->execute();
$subjects = $subjectsStmt->get_result();

// Get user info
$userStmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$userStmt->bind_param("i", $student_id);
$userStmt->execute();
$user_info = $userStmt->get_result()->fetch_assoc();

// Get comprehensive statistics
$statsStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_submissions,
        SUM(CASE WHEN evaluation_status = 'under_review' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN evaluation_status = 'evaluated' THEN 1 ELSE 0 END) as evaluated_submissions,
        AVG(CASE WHEN marks_obtained IS NOT NULL AND max_marks > 0 THEN (marks_obtained/max_marks)*100 END) as avg_percentage
    FROM submissions 
    WHERE student_id = ?
");
$statsStmt->bind_param("i", $student_id);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

// Get recent evaluation results (same query as view_submissions.php)
$recentEvaluationsStmt = $conn->prepare("SELECT s.*, sub.code as subject_code, sub.name as subject_name, 
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
                       ORDER BY s.created_at DESC
                       LIMIT 3");
$recentEvaluationsStmt->bind_param("i", $student_id);
$recentEvaluationsStmt->execute();
$recentEvaluations = $recentEvaluationsStmt->get_result();

// Get purchased subjects for this student
$purchasedStmt = $conn->prepare("
    SELECT ps.*, s.code, s.name, s.description, s.department, s.year, s.semester, s.duration_days,
           DATEDIFF(ps.expiry_date, CURDATE()) as days_remaining,
           CASE 
               WHEN ps.expiry_date < CURDATE() THEN 'expired'
               WHEN DATEDIFF(ps.expiry_date, CURDATE()) <= 7 THEN 'expiring_soon'
               ELSE 'active'
           END as access_status
    FROM purchased_subjects ps
    JOIN subjects s ON ps.subject_id = s.id
    WHERE ps.student_id = ? AND ps.status = 'active'
    ORDER BY ps.purchase_date DESC
");
$purchasedStmt->bind_param("i", $student_id);
$purchasedStmt->execute();
$purchasedSubjects = $purchasedStmt->get_result();

$pageTitle = "Student Dashboard";
$isIndexPage = false;
require_once('../includes/header.php');
?>

<link href="css/student-style.css" rel="stylesheet">

<?php require_once('includes/sidebar.php'); ?>

<div class="dashboard-layout">
    <div class="main-content ">
        <div class="container-fluid px-1">
    <!-- Welcome Header -->
    <div class="page-header">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-home me-2"></i>Welcome back, <?= htmlspecialchars($user_info['name']) ?>!</h1>
            </div>
        </div>
    </div>
    
    <!-- Purchased Subjects Section -->
    <?php if ($purchasedSubjects->num_rows > 0): ?>
    <div class="row mb-2">
        <div class="col-12">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-graduation-cap me-2"></i>My Exams</h5>
                    <a href="browse_exams.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus"></i> Buy More
                    </a>
                </div>
                    <div class="row g-3">
                        <?php 
                        // Get grade_level from database for each subject
                        while ($subject = $purchasedSubjects->fetch_assoc()): 
                            // Initialize variables
                            $gradeLevel = null;
                            $marks = 100;
                            $duration = 180;
                            
                            // Try to get grade_level, fall back to year if column doesn't exist
                            try {
                                $subjectDetailsQuery = $conn->prepare("SELECT grade_level, year FROM subjects WHERE id = ?");
                                if ($subjectDetailsQuery) {
                                    $subjectDetailsQuery->bind_param("i", $subject['subject_id']);
                                    $subjectDetailsQuery->execute();
                                    $subjectDetails = $subjectDetailsQuery->get_result()->fetch_assoc();
                                    
                                    // Use grade_level if exists, otherwise convert year to grade
                                    if (isset($subjectDetails['grade_level'])) {
                                        $gradeLevel = $subjectDetails['grade_level'];
                                    } elseif (isset($subjectDetails['year'])) {
                                        $gradeLevel = $subjectDetails['year'] == 1 ? '10th' : ($subjectDetails['year'] == 2 ? '12th' : null);
                                    }
                                }
                            } catch (Exception $e) {
                                // If grade_level column doesn't exist, try with year only
                                $subjectDetailsQuery = $conn->prepare("SELECT year FROM subjects WHERE id = ?");
                                if ($subjectDetailsQuery) {
                                    $subjectDetailsQuery->bind_param("i", $subject['subject_id']);
                                    $subjectDetailsQuery->execute();
                                    $subjectDetails = $subjectDetailsQuery->get_result()->fetch_assoc();
                                    if (isset($subjectDetails['year'])) {
                                        $gradeLevel = $subjectDetails['year'] == 1 ? '10th' : ($subjectDetails['year'] == 2 ? '12th' : null);
                                    }
                                }
                            }
                            
                            // Query to get question paper details (marks, duration, and file info)
                            // Get the most recent active question paper for this subject
                            $paperQuery = $conn->prepare("SELECT id, marks, duration_minutes, file_path FROM question_papers WHERE subject_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
                            $paperId = null;
                            $paperFilePath = null;
                            if ($paperQuery) {
                                $paperQuery->bind_param("i", $subject['subject_id']);
                                $paperQuery->execute();
                                $paperDetails = $paperQuery->get_result()->fetch_assoc();
                                if ($paperDetails) {
                                    $paperId = $paperDetails['id'];
                                    $paperFilePath = $paperDetails['file_path'];
                                    $marks = $paperDetails['marks'] ?? 100;
                                    $duration = $paperDetails['duration_minutes'] ?? 180;
                                } else {
                                    // If no question paper found, use defaults
                                    $marks = 100;
                                    $duration = 180;
                                }
                            }
                        ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="card h-100 shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
                                    <!-- Grade Badge at Top -->
                                    <?php if ($gradeLevel): ?>
                                        <div class="px-3 pt-3">
                                            <span class="badge rounded-pill" style="background-color: #6366f1; font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                                <?= htmlspecialchars($gradeLevel) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <!-- Subject Title -->
                                        <h5 class="card-title mb-2" style="font-size: 1.1rem; font-weight: 600;">
                                            <?= htmlspecialchars($subject['name']) ?>
                                        </h5>
                                        
                                        <!-- Subject Code -->
                                      
                                        <!-- Marks and Duration -->
                                        <div class="d-flex gap-3 mb-3">
                                            <div class="text-muted small">
                                                <i class="fas fa-star me-1"></i><?= $marks ?> marks
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-clock me-1"></i><?= $duration ?>m
                                            </div>
                                        </div>
                                        
                                        <!-- Spacer -->
                                        <div class="mt-auto">
                                            <?php if ($subject['access_status'] !== 'expired'): ?>
                                                <!-- View Paper and Submit Buttons -->
                                                <div class="d-flex gap-2 mb-2">
                                                    <?php if ($paperId && $paperFilePath): ?>
                                                        <a href="pdf_viewer.php?paper_id=<?= $paperId ?>" 
                                                           class="btn btn-primary flex-fill" 
                                                           style="border-radius: 8px; font-weight: 500;">
                                                            <i class="fas fa-eye me-2"></i>View Paper
                                                        </a>
                                                        <a href="upload.php?subject_id=<?= $subject['id'] ?>" 
                                                           class="btn btn-success flex-fill" 
                                                           style="border-radius: 8px; font-weight: 500;">
                                                            <i class="fas fa-upload me-2"></i>Submit
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary w-100" 
                                                                disabled 
                                                                style="border-radius: 8px; font-weight: 500;">
                                                            <i class="fas fa-file-alt me-2"></i>No Paper Available
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Access Granted Badge -->
                                                <div class="text-center">
                                                    <small class="text-success">
                                                        <i class="fas fa-check-circle me-1"></i>Access granted
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <!-- Expired State -->
                                                <button class="btn btn-outline-secondary w-100 mb-2" disabled style="border-radius: 8px;">
                                                    <i class="fas fa-lock me-2"></i>Access Expired
                                                </button>
                                                <div class="text-center">
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-circle me-1"></i>Subscription ended
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Days Remaining -->
                                           
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
  
    <!-- Quick Actions -->
    
    <!-- Student Performance Analysis Section -->
    <?php
    // Get detailed analytics data for student analysis
    
    // 1. Subject-wise performance
    $subjectPerformance = $conn->prepare("
        SELECT 
            sub.code as subject_code,
            sub.name as subject_name,
            COUNT(s.id) as total_submissions,
            AVG(CASE WHEN s.marks_obtained IS NOT NULL AND s.max_marks > 0 THEN (s.marks_obtained/s.max_marks)*100 END) as avg_percentage,
            MAX(CASE WHEN s.marks_obtained IS NOT NULL AND s.max_marks > 0 THEN (s.marks_obtained/s.max_marks)*100 END) as best_percentage,
            SUM(CASE WHEN s.evaluation_status = 'evaluated' THEN 1 ELSE 0 END) as evaluated_count
        FROM submissions s
        JOIN subjects sub ON s.subject_id = sub.id
        WHERE s.student_id = ?
        GROUP BY sub.id, sub.code, sub.name
        ORDER BY avg_percentage DESC
    ");
    $subjectPerformance->bind_param("i", $student_id);
    $subjectPerformance->execute();
    $subjectResults = $subjectPerformance->get_result();

    // 2. Grade distribution
    $gradeDistribution = $conn->prepare("
        SELECT 
            CASE 
                WHEN (s.marks_obtained/s.max_marks)*100 >= 90 THEN 'A+'
                WHEN (s.marks_obtained/s.max_marks)*100 >= 80 THEN 'A'
                WHEN (s.marks_obtained/s.max_marks)*100 >= 70 THEN 'B+'
                WHEN (s.marks_obtained/s.max_marks)*100 >= 60 THEN 'B'
                WHEN (s.marks_obtained/s.max_marks)*100 >= 50 THEN 'C+'
                WHEN (s.marks_obtained/s.max_marks)*100 >= 35 THEN 'C'
                ELSE 'F'
            END as grade,
            COUNT(*) as count
        FROM submissions s
        WHERE s.student_id = ? AND s.evaluation_status = 'evaluated' AND s.marks_obtained IS NOT NULL AND s.max_marks > 0
        GROUP BY grade
        ORDER BY 
            CASE grade
                WHEN 'A+' THEN 1 WHEN 'A' THEN 2 WHEN 'B' THEN 3 
                WHEN 'C' THEN 4 WHEN 'D' THEN 5 WHEN 'F' THEN 6
            END
    ");
    $gradeDistribution->bind_param("i", $student_id);
    $gradeDistribution->execute();
    $gradeResults = $gradeDistribution->get_result();
    ?>
    
    <!-- Subject Selection -->
    
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

<!-- Evaluation Details Modal -->
<div class="modal fade" id="evaluationModal" tabindex="-1" aria-labelledby="evaluationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="evaluationModalLabel">
                    <i class="fas fa-star text-warning me-2"></i>Evaluation Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="evaluationContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="view_submissions.php" class="btn btn-primary">
                    <i class="fas fa-eye me-1"></i> View All Submissions
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Rating Modal -->
<div class="modal fade" id="ratingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-star text-warning me-2"></i>Rate Evaluator
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ratingContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitRatingBtn" onclick="submitRating()">
                    <i class="fas fa-paper-plane me-2"></i>Submit Rating
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.bg-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.card {
    border-radius: 15px !important;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
}

.btn {
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.display-5 {
    font-weight: 700;
}

@media (max-width: 768px) {
    .display-5 {
        font-size: 2rem;
    }
    
    /* Stack View Paper and Submit buttons vertically on mobile */
    .d-flex.gap-2 {
        flex-direction: column !important;
    }
    
    .d-flex.gap-2 .btn {
        width: 100% !important;
    }
}

/* Mobile-friendly enhancements for recent evaluation results */
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add loading animation to buttons
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.classList.contains('btn-outline-danger') && !this.hasAttribute('onclick')) {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                this.disabled = true;
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 1000);
            }
        });
    });
});

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

function showEvaluationDetails(evaluation) {
    const percentage = evaluation.percentage;
    let gradeInfo = '';
    let badgeClass = 'bg-danger';
    
    if (percentage >= 90) {
        gradeInfo = 'A+ (Excellent)';
        badgeClass = 'bg-success';
    } else if (percentage >= 80) {
        gradeInfo = 'A (Very Good)';
        badgeClass = 'bg-success';
    } else if (percentage >= 70) {
        gradeInfo = 'B (Good)';
        badgeClass = 'bg-warning';
    } else if (percentage >= 60) {
        gradeInfo = 'C (Satisfactory)';
        badgeClass = 'bg-info';
    } else if (percentage >= 50) {
        gradeInfo = 'D (Pass)';
        badgeClass = 'bg-secondary';
    } else {
        gradeInfo = 'F (Fail)';
        badgeClass = 'bg-danger';
    }
    
    const content = `
        <div class="row">
            <div class="col-md-6">
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <h6 class="card-title">Subject Information</h6>
                        <p class="mb-1"><strong>Code:</strong> ${evaluation.subject_code}</p>
                        <p class="mb-1"><strong>Name:</strong> ${evaluation.subject_name}</p>
                        <p class="mb-0"><strong>Submission Date:</strong> ${new Date(evaluation.created_at).toLocaleDateString()}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <h6 class="card-title">Evaluation Results</h6>
                        <p class="mb-1"><strong>Marks:</strong> ${parseFloat(evaluation.marks_obtained).toFixed(1)} / ${parseFloat(evaluation.max_marks).toFixed(1)}</p>
                        <p class="mb-1"><strong>Percentage:</strong> <span class="badge ${badgeClass} text-white">${percentage.toFixed(1)}%</span></p>
                        <p class="mb-0"><strong>Grade:</strong> ${gradeInfo}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card border-0 bg-light mb-3">
            <div class="card-body">
                <h6 class="card-title">Evaluator Feedback</h6>
                <div class="bg-white p-3 rounded border">
                    ${evaluation.evaluator_remarks ? evaluation.evaluator_remarks.replace(/\n/g, '<br>') : 'No feedback provided.'}
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Evaluation Details</h6>
                        <p class="mb-1"><strong>Evaluator:</strong> ${evaluation.evaluator_name || 'Anonymous'}</p>
                        <p class="mb-0"><strong>Evaluated On:</strong> ${new Date(evaluation.evaluated_at).toLocaleDateString()} at ${new Date(evaluation.evaluated_at).toLocaleTimeString()}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Quick Actions</h6>
                        <a href="view_submissions.php" class="btn btn-sm btn-outline-primary mb-2 d-block">
                            <i class="fas fa-eye me-1"></i> View All Submissions
                        </a>
                        <a href="question_papers.php?subject_id=${evaluation.subject_id}" class="btn btn-sm btn-outline-secondary d-block">
                            <i class="fas fa-file-alt me-1"></i> Practice More
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('evaluationContent').innerHTML = content;
    document.getElementById('evaluationModalLabel').innerHTML = `
        <i class="fas fa-star text-warning me-2"></i>Evaluation: ${evaluation.subject_code}
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('evaluationModal'));
    modal.show();
}

// Student Analysis Section Functions
function toggleAnalysisSection() {
    const section = document.getElementById('analysisSection');
    const button = event.target;
    
    if (section.style.display === 'none') {
        section.style.display = 'block';
        button.innerHTML = '<i class="fas fa-compress-arrows-alt"></i> Collapse';
    } else {
        section.style.display = 'none';
        button.innerHTML = '<i class="fas fa-expand-arrows-alt"></i> Expand';
    }
}

function generateDetailedReport() {
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
    button.disabled = true;
    
    // Simulate report generation (you can integrate with actual PDF generation)
    setTimeout(() => {
        // Create a simple report summary
        const reportData = {
            studentName: '<?= htmlspecialchars($user_info['name']) ?>',
            totalSubmissions: <?= $stats['total_submissions'] ?>,
            evaluatedSubmissions: <?= $stats['evaluated_submissions'] ?>,
            averagePercentage: <?= $stats['avg_percentage'] ? number_format($stats['avg_percentage'], 1) : 'null' ?>,
            generatedAt: new Date().toLocaleString()
        };
        
        // Create and download a simple report
        const reportContent = `
STUDENT PERFORMANCE REPORT
==========================

Student: ${reportData.studentName}
Generated: ${reportData.generatedAt}

SUMMARY
-------
Total Submissions: ${reportData.totalSubmissions}
Evaluated Submissions: ${reportData.evaluatedSubmissions}
Average Performance: ${reportData.averagePercentage || 'N/A'}%

This is a basic report. For detailed analytics, 
please contact your academic advisor.
        `;
        
        const blob = new Blob([reportContent], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `student_report_${new Date().getTime()}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        // Reset button
        button.innerHTML = originalText;
        button.disabled = false;
        
        // Show success message
        showNotification('Report generated successfully!', 'success');
    }, 2000);
}

function exportAnalytics() {
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exporting...';
    button.disabled = true;
    
    // Collect analytics data
    setTimeout(() => {
        const analyticsData = {
            student_id: <?= $student_id ?>,
            student_name: '<?= htmlspecialchars($user_info['name']) ?>',
            export_date: new Date().toISOString(),
            statistics: {
                total_submissions: <?= $stats['total_submissions'] ?>,
                under_review: <?= $stats['under_review'] ?>,
                evaluated_submissions: <?= $stats['evaluated_submissions'] ?>,
                average_percentage: <?= $stats['avg_percentage'] ? number_format($stats['avg_percentage'], 1) : 'null' ?>
            }
        };
        
        // Create CSV format
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Metric,Value\n";
        csvContent += `Student Name,${analyticsData.student_name}\n`;
        csvContent += `Export Date,${analyticsData.export_date}\n`;
        csvContent += `Total Submissions,${analyticsData.statistics.total_submissions}\n`;
        csvContent += `Under Review,${analyticsData.statistics.under_review}\n`;
        csvContent += `Evaluated Submissions,${analyticsData.statistics.evaluated_submissions}\n`;
        csvContent += `Average Percentage,${analyticsData.statistics.average_percentage || 'N/A'}\n`;
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `student_analytics_${new Date().getTime()}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Reset button
        button.innerHTML = originalText;
        button.disabled = false;
        
        // Show success message
        showNotification('Analytics data exported successfully!', 'success');
    }, 1500);
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}

// Rating functionality
let currentRatingData = {};

function showRatingModal(evaluatorId, evaluatorName, submissionId) {
    currentRatingData = {
        evaluatorId: evaluatorId,
        evaluatorName: evaluatorName,
        submissionId: submissionId
    };
    
    const content = `
        <div class="text-center mb-4">
            <div class="mb-3">
                <i class="fas fa-user-tie fa-3x text-primary mb-2"></i>
                <h6 class="mb-0">Rate Evaluator</h6>
                <p class="text-muted small mb-0">${evaluatorName}</p>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-12">
                <label class="form-label fw-bold">Overall Rating</label>
                <div class="rating-stars text-center mb-3" id="overallRating">
                    <i class="fas fa-star rating-star" data-rating="1"></i>
                    <i class="fas fa-star rating-star" data-rating="2"></i>
                    <i class="fas fa-star rating-star" data-rating="3"></i>
                    <i class="fas fa-star rating-star" data-rating="4"></i>
                    <i class="fas fa-star rating-star" data-rating="5"></i>
                </div>
                <div class="text-center">
                    <small class="text-muted" id="ratingText">Click stars to rate</small>
                </div>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6 mb-3">
                <label class="form-label">Evaluation Quality</label>
                <select class="form-select" id="evaluationQuality">
                    <option value="">Select rating</option>
                    <option value="excellent">Excellent</option>
                    <option value="good">Good</option>
                    <option value="average">Average</option>
                    <option value="poor">Poor</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Feedback Helpfulness</label>
                <select class="form-select" id="feedbackHelpfulness">
                    <option value="">Select rating</option>
                    <option value="very_helpful">Very Helpful</option>
                    <option value="helpful">Helpful</option>
                    <option value="somewhat_helpful">Somewhat Helpful</option>
                    <option value="not_helpful">Not Helpful</option>
                </select>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Additional Comments (Optional)</label>
            <textarea class="form-control" id="ratingComments" rows="3" 
                      placeholder="Share your experience with this evaluator..."></textarea>
        </div>
        
        <div class="alert alert-info small">
            <i class="fas fa-info-circle me-2"></i>
            Your rating will help improve the evaluation process. All ratings are confidential.
        </div>
    `;
    
    document.getElementById('ratingContent').innerHTML = content;
    
    // Initialize rating stars
    initializeRatingStars();
    
    const modal = new bootstrap.Modal(document.getElementById('ratingModal'));
    modal.show();
}

function initializeRatingStars() {
    const stars = document.querySelectorAll('.rating-star');
    let selectedRating = 0;
    
    stars.forEach((star, index) => {
        star.style.color = '#ddd';
        star.style.cursor = 'pointer';
        star.style.fontSize = '1.5rem';
        star.style.margin = '0 2px';
        
        star.addEventListener('mouseenter', () => {
            for (let i = 0; i <= index; i++) {
                stars[i].style.color = '#ffc107';
            }
            for (let i = index + 1; i < stars.length; i++) {
                stars[i].style.color = '#ddd';
            }
            updateRatingText(index + 1);
        });
        
        star.addEventListener('click', () => {
            selectedRating = index + 1;
            for (let i = 0; i <= index; i++) {
                stars[i].style.color = '#ffc107';
                stars[i].classList.add('selected');
            }
            for (let i = index + 1; i < stars.length; i++) {
                stars[i].style.color = '#ddd';
                stars[i].classList.remove('selected');
            }
            updateRatingText(selectedRating);
            currentRatingData.rating = selectedRating;
        });
    });
    
    // Reset on mouse leave
    document.getElementById('overallRating').addEventListener('mouseleave', () => {
        stars.forEach((star, index) => {
            if (selectedRating > index) {
                star.style.color = '#ffc107';
            } else {
                star.style.color = '#ddd';
            }
        });
        updateRatingText(selectedRating);
    });
}

function updateRatingText(rating) {
    const texts = {
        0: 'Click stars to rate',
        1: 'Poor - Needs significant improvement',
        2: 'Fair - Below expectations',
        3: 'Good - Meets expectations',
        4: 'Very Good - Above expectations',
        5: 'Excellent - Outstanding performance'
    };
    document.getElementById('ratingText').textContent = texts[rating] || texts[0];
}

function submitRating() {
    const rating = currentRatingData.rating;
    const evaluationQuality = document.getElementById('evaluationQuality').value;
    const feedbackHelpfulness = document.getElementById('feedbackHelpfulness').value;
    const comments = document.getElementById('ratingComments').value;
    
    // Validation
    if (!rating) {
        showNotification('Please select an overall rating', 'warning');
        return;
    }
    
    if (!evaluationQuality) {
        showNotification('Please rate the evaluation quality', 'warning');
        return;
    }
    
    if (!feedbackHelpfulness) {
        showNotification('Please rate the feedback helpfulness', 'warning');
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submitRatingBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    submitBtn.disabled = true;
    
    // Prepare data
    const ratingData = {
        evaluator_id: currentRatingData.evaluatorId,
        submission_id: currentRatingData.submissionId,
        overall_rating: rating,
        evaluation_quality: evaluationQuality,
        feedback_helpfulness: feedbackHelpfulness,
        comments: comments
    };
    
    // Submit rating via AJAX
    fetch('submit_evaluator_rating.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(ratingData)
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            showNotification('Rating submitted successfully! Thank you for your feedback.', 'success');
            bootstrap.Modal.getInstance(document.getElementById('ratingModal')).hide();
        } else {
            showNotification(data.message || 'Failed to submit rating. Please try again.', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        showNotification('An error occurred. Please try again.', 'danger');
    });
}
</script>

        </div>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>
