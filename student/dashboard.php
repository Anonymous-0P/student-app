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
require_once('../includes/header.php');
?>

<link href="css/student-style.css" rel="stylesheet">

<div class="student-content">
<div class="container">
    <!-- Welcome Header -->
    <div class="page-header">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-home me-2"></i>Welcome back, <?= htmlspecialchars($user_info['name']) ?>!</h1>
                <p>Track your progress and manage your exams</p>
            </div>
        </div>
    </div>
    
    <!-- Purchased Subjects Section -->
    <?php if ($purchasedSubjects->num_rows > 0): ?>
    <div class="row mb-4">
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
                                                <!-- View Paper Button -->
                                                <?php if ($paperId && $paperFilePath): ?>
                                                    <a href="pdf_viewer.php?paper_id=<?= $paperId ?>" 
                                                       class="btn btn-primary w-100 mb-2" 
                                                       style="border-radius: 8px; font-weight: 500;">
                                                        <i class="fas fa-eye me-2"></i>View Paper
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-secondary w-100 mb-2" 
                                                            disabled 
                                                            style="border-radius: 8px; font-weight: 500;">
                                                        <i class="fas fa-file-alt me-2"></i>No Paper Available
                                                    </button>
                                                <?php endif; ?>
                                                
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
    
    <div class="row">
        <div class="col-12 mb-4">
            <div class="dashboard-card">
                <h5><i class="fas fa-star me-2"></i>Evaluation Results</h5>
                    <?php if ($recentEvaluations->num_rows === 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No submissions yet. Upload your first answer sheet to get started.</p>
                            <a class="btn btn-primary" href="upload.php">
                                <i class="fas fa-upload me-2"></i>Upload Answers
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Desktop Table View (hidden on mobile) -->
                        <div class="d-none d-lg-block">
                            <table class="table">
                                <thead>
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
                                <?php 
                                // Reset pointer for desktop table
                                $recentEvaluations->data_seek(0);
                                while($row = $recentEvaluations->fetch_assoc()): 
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
                                        elseif ($percentage >= 60) $grade = 'C+';
                                        elseif ($percentage >= 50) $grade = 'C';
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
                                            <span class="badge <?= $statusClass ?>">
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
                                            <div class="btn-group">
                                                <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" 
                                                   class="btn btn-sm btn-outline-primary" title="View PDF">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <?php if(!empty($row['annotated_pdf_url']) && file_exists('../' . $row['annotated_pdf_url'])): ?>
                                                    <a href="../<?= htmlspecialchars($row['annotated_pdf_url']) ?>" 
                                                       target="_blank"
                                                       class="btn btn-sm btn-success" 
                                                       title="Download Annotated Answer Sheet">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                <?php endif; ?>
                                                <?php if($row['evaluator_remarks']): ?>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            onclick="showEvaluationFeedback('<?= htmlspecialchars(addslashes($row['evaluator_remarks'])) ?>', '<?= htmlspecialchars($row['subject_code']) ?>', '<?= number_format($percentage, 1) ?>%', '<?= $grade ?>')"
                                                            title="View Evaluation Feedback">
                                                        💬 Feedback
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if($row['status_display'] == 'Evaluated' && $row['evaluator_id']): ?>
                                                    <button class="btn btn-sm btn-outline-warning" 
                                                            onclick="showRatingModal(<?= $row['evaluator_id'] ?>, '<?= htmlspecialchars($row['evaluator_name']) ?>', <?= $row['id'] ?>)"
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
                        <div class="d-block d-lg-none">
                            <?php 
                            // Reset the result pointer to display mobile cards
                            $recentEvaluationsStmt->execute();
                            $result = $recentEvaluationsStmt->get_result();
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
                                    <div class="row g-2">
                                        <!-- Row 1: View PDF and Download -->
                                        <div class="col-6">
                                            <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" 
                                               class="btn btn-sm btn-outline-primary w-100">
                                                <i class="fas fa-file-pdf me-1"></i>View PDF
                                            </a>
                                        </div>
                                        <?php if(!empty($row['annotated_pdf_url']) && file_exists('../' . $row['annotated_pdf_url'])): ?>
                                            <div class="col-6">
                                                <a href="../<?= htmlspecialchars($row['annotated_pdf_url']) ?>" 
                                                   target="_blank"
                                                   class="btn btn-sm btn-success w-100">
                                                    <i class="fas fa-download me-1"></i>Download
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Row 2: Feedback and Rate -->
                                        <?php if($row['evaluator_remarks']): ?>
                                            <div class="col-6">
                                                <button class="btn btn-sm btn-outline-info w-100" 
                                                        onclick="showEvaluationFeedback('<?= htmlspecialchars(addslashes($row['evaluator_remarks'])) ?>', '<?= htmlspecialchars($row['subject_code']) ?>', '<?= number_format($percentage, 1) ?>%', '<?= $grade ?>')">
                                                    <i class="fas fa-comment me-1"></i>Feedback
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if($row['status_display'] == 'Evaluated' && $row['evaluator_id']): ?>
                                            <div class="col-6">
                                                <button class="btn btn-sm btn-outline-warning w-100" 
                                                        onclick="showRatingModal(<?= $row['evaluator_id'] ?>, '<?= htmlspecialchars($row['evaluator_name']) ?>', <?= $row['id'] ?>)">
                                                    <i class="fas fa-star me-1"></i>Rate
                                                </button>
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

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="dashboard-card">
                <h5><i class="fas fa-rocket me-2"></i>Quick Actions</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="browse_exams.php" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-shopping-cart me-2"></i> Browse & Purchase
                            </a>
                            <small class="text-muted d-block mt-1">Explore available subjects</small>
                        </div>
                        <div class="col-md-4">
                            <a href="question_papers.php" class="btn btn-info btn-lg w-100">
                                <i class="fas fa-file-download me-2"></i> Question Papers
                            </a>
                            <small class="text-muted d-block mt-1">Access purchased subjects only</small>
                        </div>
                        <div class="col-md-4">
                            <a href="view_submissions.php" class="btn btn-outline-primary btn-lg w-100">
                                <i class="fas fa-history me-2"></i> View Submissions
                            </a>
                            <small class="text-muted d-block mt-1">Track your progress</small>
                        </div>
                    </div>
            </div>
        </div>
    </div>

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

    <!-- Performance Analysis Dashboard -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient text-black d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h5 class="mb-0">📊 Student Performance Analysis</h5>
                    <button class="btn btn-light btn-sm" onclick="toggleAnalysisSection()">
                        <i class="fas fa-expand-arrows-alt"></i> Toggle
                    </button>
                </div>
                <div class="card-body" id="analysisSection">
                    
                
                    <div class="row mb-4">
                        <!-- Subject Performance -->
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100 border-0 bg-light text-black">
                                <div class="card-header bg-info">
                                    <h6 class="mb-0"><i class="fas fa-books me-2 "></i>Subject Performance</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($subjectResults->num_rows > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Subject</th>
                                                        <th class="text-center">Avg %</th>
                                                        <th class="text-center">Best %</th>
                                                        <th class="text-center">Count</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while($subject = $subjectResults->fetch_assoc()): ?>
                                                        <?php
                                                        $avg = $subject['avg_percentage'] ? number_format($subject['avg_percentage'], 1) : 'N/A';
                                                        $best = $subject['best_percentage'] ? number_format($subject['best_percentage'], 1) : 'N/A';
                                                        $avgClass = '';
                                                        if ($subject['avg_percentage'] >= 80) $avgClass = 'text-success';
                                                        elseif ($subject['avg_percentage'] >= 60) $avgClass = 'text-warning';
                                                        else $avgClass = 'text-danger';
                                                        ?>
                                                        <tr>
                                                            <td class="fw-bold"><?= htmlspecialchars($subject['subject_code']) ?></td>
                                                            <td class="text-center <?= $avgClass ?>"><?= $avg ?></td>
                                                            <td class="text-center text-success"><?= $best ?></td>
                                                            <td class="text-center"><?= $subject['evaluated_count'] ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">No evaluated submissions yet</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Grade Distribution -->
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="fas fa-medal me-2"></i>Grade Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($gradeResults->num_rows > 0): ?>
                                        <?php 
                                        $totalGrades = 0;
                                        $grades = [];
                                        $gradeResults->data_seek(0);
                                        while($grade = $gradeResults->fetch_assoc()) {
                                            $grades[] = $grade;
                                            $totalGrades += $grade['count'];
                                        }
                                        ?>
                                        <div class="row g-2">
                                            <?php foreach($grades as $grade): ?>
                                                <?php
                                                $percentage = round(($grade['count'] / $totalGrades) * 100, 1);
                                                $badgeClass = '';
                                                switch($grade['grade']) {
                                                    case 'A+': case 'A': $badgeClass = 'bg-success'; break;
                                                    case 'B': $badgeClass = 'bg-info'; break;
                                                    case 'C': $badgeClass = 'bg-warning'; break;
                                                    case 'D': $badgeClass = 'bg-secondary'; break;
                                                    case 'F': $badgeClass = 'bg-danger'; break;
                                                }
                                                ?>
                                                <div class="col-6 col-md-4">
                                                    <div class="text-center p-2 border rounded">
                                                        <div class="badge <?= $badgeClass ?> mb-1"><?= $grade['grade'] ?></div>
                                                        <div class="fw-bold"><?= $grade['count'] ?></div>
                                                        <small class="text-muted"><?= $percentage ?>%</small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-medal fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">No grades available yet</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Insights -->
                    <!-- <div class="row">
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Performance Insights & Study Recommendations</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($stats['evaluated_submissions'] > 0): ?>
                                        <?php
                                        $totalEvaluated = $stats['evaluated_submissions'];
                                        $avgPercentage = $stats['avg_percentage'] ?? 0;
                                        
                                        // Generate insights
                                        $insights = [];
                                        if ($avgPercentage >= 90) {
                                            $insights[] = ['icon' => 'fas fa-star text-success', 'text' => 'Excellent performance! You\'re consistently scoring above 90%'];
                                        } elseif ($avgPercentage >= 80) {
                                            $insights[] = ['icon' => 'fas fa-thumbs-up text-success', 'text' => 'Great work! Your average is above 80%'];
                                        } elseif ($avgPercentage >= 70) {
                                            $insights[] = ['icon' => 'fas fa-chart-line text-warning', 'text' => 'Good progress! Try to aim for scores above 80%'];
                                        } else {
                                            $insights[] = ['icon' => 'fas fa-target text-danger', 'text' => 'Focus on improvement. Consider reviewing study methods'];
                                        }

                                        if ($totalEvaluated >= 10) {
                                            $insights[] = ['icon' => 'fas fa-graduation-cap text-info', 'text' => 'Very active with ' . $totalEvaluated . ' evaluated submissions'];
                                        } elseif ($totalEvaluated >= 5) {
                                            $insights[] = ['icon' => 'fas fa-book text-info', 'text' => 'Good submission frequency - ' . $totalEvaluated . ' evaluations'];
                                        }
                                        ?>

                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="text-primary mb-3"><i class="fas fa-brain me-1"></i>Key Insights:</h6>
                                                <div class="list-group list-group-flush">
                                                    <?php foreach($insights as $insight): ?>
                                                        <div class="list-group-item border-0 px-0 py-2">
                                                            <i class="<?= $insight['icon'] ?> me-2"></i>
                                                            <span><?= $insight['text'] ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="text-primary mb-3"><i class="fas fa-lightbulb me-1"></i>Study Tips:</h6>
                                                <ul class="list-unstyled small">
                                                    <?php if ($avgPercentage < 70): ?>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-1"></i>Review fundamentals before submissions</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-1"></i>Practice with sample questions</li>
                                                    <?php endif; ?>
                                                    <li class="mb-2"><i class="fas fa-check text-success me-1"></i>Review evaluator feedback carefully</li>
                                                    <li class="mb-2"><i class="fas fa-check text-success me-1"></i>Focus on subjects with lower averages</li>
                                                    <li class="mb-2"><i class="fas fa-check text-success me-1"></i>Maintain consistent submission schedule</li>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-lightbulb fa-3x text-muted mb-3"></i>
                                            <h6 class="text-muted">No Analysis Available Yet</h6>
                                            <p class="text-muted">Submit assignments to see personalized insights and performance analytics!</p>
                                            <a href="subjects.php" class="btn btn-primary">Browse Subjects</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div> -->

                    <!-- Report Generation -->
                    <div class="text-center text-black mt-4">
                        <div class="row g-2 justify-content-center">
                            <div class="col-6 col-md-auto">
                                <button class="btn btn-sm btn-success w-100" onclick="generateDetailedReport()">
                                    <i class="fas fa-file-pdf me-1"></i>Generate Report
                                </button>
                            </div>
                            <div class="col-6 col-md-auto">
                                <button class="btn btn-sm btn-warning text-white w-100" style="background: linear-gradient(90deg,#f7971e,#ffd200); border: none;" onclick="exportAnalytics()">
                                    <i class="fas fa-download me-1"></i>Export Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Evaluation Results -->
    

    <!-- Notifications Section -->
    <!-- <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Recent Notifications</h5>
                    <?php
                    // Get recent notifications
                    $notif_query = "
                        SELECT * FROM notifications 
                        WHERE user_id = ? AND type = 'evaluation_complete'
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ";
                    
                    try {
                        $notif_stmt = $conn->prepare($notif_query);
                        $notif_stmt->bind_param("i", $_SESSION['user_id']);
                        $notif_stmt->execute();
                        $notif_result = $notif_stmt->get_result();
                        $has_notifications = $notif_result->num_rows > 0;
                    } catch (Exception $e) {
                        $has_notifications = false;
                        $notifications = [];
                    }
                    ?>
                    <?php if ($has_notifications): ?>
                        <span class="badge bg-light text-info"><?= $notif_result->num_rows ?> New</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($has_notifications): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($notif = $notif_result->fetch_assoc()): ?>
                                <?php
                                $metadata = json_decode($notif['metadata'], true) ?? [];
                                $time_ago = time() - strtotime($notif['created_at']);
                                if ($time_ago < 3600) {
                                    $time_display = floor($time_ago / 60) . " minutes ago";
                                } elseif ($time_ago < 86400) {
                                    $time_display = floor($time_ago / 3600) . " hours ago";
                                } else {
                                    $time_display = floor($time_ago / 86400) . " days ago";
                                }
                                ?>
                                <div class="list-group-item py-3 <?= $notif['is_read'] ? '' : 'bg-light border-start border-3 border-info' ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 text-primary"><?= htmlspecialchars($notif['title']) ?></h6>
                                            <p class="mb-1 text-muted small"><?= htmlspecialchars($notif['message']) ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i><?= $time_display ?>
                                                <?php if (isset($metadata['evaluator_name'])): ?>
                                                    | <i class="fas fa-user me-1"></i><?= htmlspecialchars($metadata['evaluator_name']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <a href="view_submissions.php" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="text-muted mb-3">
                                <i class="fas fa-bell-slash fa-3x"></i>
                            </div>
                            <h6 class="text-muted">No New Notifications</h6>
                            <p class="text-muted small">You'll receive notifications here when your submissions are evaluated.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Subject Selection -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-alt text-primary me-2"></i>Available Question Papers</h5>
                    <a href="question_papers.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye me-1"></i> View All Papers
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($subjects->num_rows === 0): ?>
                        <div class="alert alert-info text-center py-4">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No Question Papers Available</h6>
                            <p class="text-muted small mb-0">Check back later for new question papers</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php 
                            $subjectCount = 0;
                            while(($row = $subjects->fetch_assoc()) && $subjectCount < 3): 
                                $subjectCount++;
                            ?>
                                <div class="col-md-4">
                                    <div class="card h-100 border-0 bg-light">
                                        <div class="card-body text-center p-4">
                                            <div class="mb-3">
                                                <i class="fas fa-graduation-cap fa-2x text-primary"></i>
                                            </div>
                                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($row['code']); ?></h6>
                                            <p class="text-muted small mb-3"><?php echo htmlspecialchars($row['name']); ?></p>
                                            <div class="small text-muted mb-3">
                                                <?php if($row['paper_count'] > 0): ?>
                                                    <i class="fas fa-file-alt me-1"></i><?php echo $row['paper_count']; ?> Paper(s) Available
                                                <?php else: ?>
                                                    <i class="fas fa-info-circle me-1"></i>No papers yet
                                                <?php endif; ?>
                                            </div>
                                            <?php if($row['paper_count'] > 0): ?>
                                                <a href="question_papers.php?subject_id=<?php echo (int)$row['id']; ?>" 
                                                   class="btn btn-primary btn-sm w-100">
                                                    <i class="fas fa-eye me-1"></i> View Papers
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary btn-sm w-100" disabled>
                                                    <i class="fas fa-clock me-1"></i> Coming Soon
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="question_papers.php" class="btn btn-outline-primary">
                                <i class="fas fa-plus-circle me-2"></i>View All Question Papers
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="row align-items-center">
                            <div class="col">
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1 text-warning"></i>
                                    Click "View Papers" to access question papers for each subject
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
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

<?php require_once('../includes/footer.php'); ?>
