<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();
        
        if ($_POST['action'] === 'submit_answer_sheet') {
            $subject_id = (int)$_POST['subject_id'];
            $exam_title = trim($_POST['exam_title']);
            $exam_date = $_POST['exam_date'];
            $time_taken = (int)$_POST['time_taken'];
            
            // Validate inputs
            if (empty($subject_id) || empty($exam_title) || empty($exam_date)) {
                throw new Exception("Please fill in all required fields.");
            }
            
            // Check if student has already submitted for this subject
            // Strong duplicate check: block ANY previous submission for this subject
            $check_duplicate_sql = "SELECT id FROM answer_sheets WHERE student_id = ? AND subject_id = ? LIMIT 1";
            $check_stmt = $pdo->prepare($check_duplicate_sql);
            $check_stmt->execute([$_SESSION['user_id'], $subject_id]);
            if ($check_stmt->fetch()) {
                throw new Exception("You have already submitted an answer sheet for this subject. Only one submission per subject is allowed.");
            }
            
            // Handle PDF upload
            $pdf_path = null;
            if (isset($_FILES['answer_sheet_pdf']) && $_FILES['answer_sheet_pdf']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/answer_sheets/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_info = pathinfo($_FILES['answer_sheet_pdf']['name']);
                $file_name = 'answer_sheet_' . $_SESSION['user_id'] . '_' . time() . '.pdf';
                $pdf_path = $upload_dir . $file_name;
                
                if (!move_uploaded_file($_FILES['answer_sheet_pdf']['tmp_name'], $pdf_path)) {
                    throw new Exception("Failed to upload PDF file.");
                }
            }
            
            // Create answer sheet record
            $answer_sheet_sql = "INSERT INTO answer_sheets 
                               (student_id, subject_id, exam_title, exam_date, time_limit_minutes, 
                                status, submitted_at, pdf_path) 
                               VALUES (?, ?, ?, ?, ?, 'submitted', NOW(), ?)";
            
            $stmt = $pdo->prepare($answer_sheet_sql);
            $stmt->execute([
                $_SESSION['user_id'], 
                $subject_id, 
                $exam_title, 
                $exam_date, 
                $time_taken,
                $pdf_path
            ]);
            
            $answer_sheet_id = $pdo->lastInsertId();
            
            // Get all evaluators for this subject
            $evaluators_sql = "SELECT DISTINCT u.id, u.name, u.email 
                             FROM users u 
                             JOIN evaluator_subjects es ON u.id = es.evaluator_id 
                             WHERE es.subject_id = ? AND u.role = 'evaluator' AND u.is_active = 1";
            
            $eval_stmt = $pdo->prepare($evaluators_sql);
            $eval_stmt->execute([$subject_id]);
            $evaluators = $eval_stmt->fetchAll();
            
            if (empty($evaluators)) {
                throw new Exception("No evaluators found for this subject. Please contact administrator.");
            }
            
            // Get moderator for this subject (or default moderator)
            $moderator_sql = "SELECT u.id FROM users u 
                            LEFT JOIN moderator_subjects ms ON u.id = ms.moderator_id 
                            WHERE u.role = 'moderator' AND u.is_active = 1 
                            AND (ms.subject_id = ? OR ms.subject_id IS NULL) 
                            ORDER BY ms.subject_id DESC LIMIT 1";
            
            $mod_stmt = $pdo->prepare($moderator_sql);
            $mod_stmt->execute([$subject_id]);
            $moderator = $mod_stmt->fetch();
            
            if (!$moderator) {
                throw new Exception("No moderator found for assignment. Please contact administrator.");
            }
            
            // Create assignment requests for all evaluators
            $assignment_sql = "INSERT INTO evaluator_answer_sheet_assignments 
                             (answer_sheet_id, evaluator_id, moderator_id, deadline, status, notes)
                             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 'pending', ?)";
            
            $assignment_stmt = $pdo->prepare($assignment_sql);
            $assignment_notes = "New answer sheet submission for evaluation: " . $exam_title;
            
            foreach ($evaluators as $evaluator) {
                $assignment_stmt->execute([
                    $answer_sheet_id,
                    $evaluator['id'],
                    $moderator['id'],
                    $assignment_notes
                ]);
            }
            
            // Create notifications for evaluators
            $notification_sql = "INSERT INTO notifications 
                               (user_id, title, message, type, reference_id, created_at)
                               VALUES (?, ?, ?, 'evaluation_request', ?, NOW())";
            
            $notification_stmt = $pdo->prepare($notification_sql);
            
            // Get subject name for notification
            $subject_info_sql = "SELECT name, code FROM subjects WHERE id = ?";
            $sub_stmt = $pdo->prepare($subject_info_sql);
            $sub_stmt->execute([$subject_id]);
            $subject_info = $sub_stmt->fetch();
            
            $notification_title = "New Answer Sheet for Evaluation";
            $notification_message = "Student has submitted an answer sheet for {$subject_info['name']} ({$subject_info['code']}). " .
                                  "Exam: {$exam_title}. Click to view and accept/decline evaluation.";
            
            foreach ($evaluators as $evaluator) {
                $notification_stmt->execute([
                    $evaluator['id'],
                    $notification_title,
                    $notification_message,
                    $answer_sheet_id
                ]);
            }
            
            $pdo->commit();
            
            // Send submission confirmation email to student
            try {
                $student_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $student_stmt->execute([$_SESSION['user_id']]);
                $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
                if ($student && $subject_info) {
                    require_once('../includes/mail_helper.php');
                    $emailRes = sendSubmissionReceivedEmail(
                        $student['email'],
                        $student['name'],
                        $subject_info['code'],
                        $subject_info['name'],
                        'http://localhost/student-app/student/submission_status.php?id=' . $answer_sheet_id
                    );
                    if (!$emailRes['success']) {
                        error_log('Submission email failed: ' . $emailRes['message']);
                    }
                }
            } catch (Exception $e) {
                // Do not block the flow on email errors
                error_log('Error sending submission email: ' . $e->getMessage());
            }
            
            $_SESSION['success'] = "Answer sheet submitted successfully! Evaluators have been notified.";
            header("Location: submission_status.php?id=" . $answer_sheet_id);
            exit();
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get student's subjects
$subjects_sql = "SELECT DISTINCT s.* FROM subjects s 
                JOIN users u ON u.course = s.name OR u.department LIKE CONCAT('%', s.name, '%')
                WHERE u.id = ? AND u.role = 'student'
                UNION
                SELECT * FROM subjects ORDER BY name";

$stmt = $pdo->prepare($subjects_sql);
$stmt->execute([$_SESSION['user_id']]);
$subjects = $stmt->fetchAll();

// Get subjects that student has already submitted for
$submitted_subjects_sql = "SELECT DISTINCT subject_id FROM answer_sheets WHERE student_id = ?";
$submitted_stmt = $pdo->prepare($submitted_subjects_sql);
$submitted_stmt->execute([$_SESSION['user_id']]);
$submitted_subjects = $submitted_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get student's recent submissions
$recent_submissions_sql = "SELECT as_main.*, s.name as subject_name, s.code as subject_code,
                          COUNT(easa.id) as evaluator_count,
                          COUNT(CASE WHEN easa.status = 'accepted' THEN 1 END) as accepted_count
                          FROM answer_sheets as_main
                          JOIN subjects s ON as_main.subject_id = s.id
                          LEFT JOIN evaluator_answer_sheet_assignments easa ON as_main.id = easa.answer_sheet_id
                          WHERE as_main.student_id = ?
                          GROUP BY as_main.id
                          ORDER BY as_main.submitted_at DESC LIMIT 5";

$recent_stmt = $pdo->prepare($recent_submissions_sql);
$recent_stmt->execute([$_SESSION['user_id']]);
$recent_submissions = $recent_stmt->fetchAll();

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
                                <i class="fas fa-upload text-primary me-2"></i>
                                Submit Answer Sheet
                            </h4>
                            <p class="text-muted mb-0">Upload your completed answer sheet for evaluation</p>
                        </div>
                        <div class="text-end">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Submission Form -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-file-upload me-2"></i>
                        Answer Sheet Submission Form
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="submission-form">
                        <input type="hidden" name="action" value="submit_answer_sheet">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subject_id" class="form-label">
                                        <i class="fas fa-book text-info me-1"></i>
                                        Subject <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" id="subject_id" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <?php 
                                            $is_submitted = in_array($subject['id'], $submitted_subjects);
                                            ?>
                                            <option value="<?= $subject['id'] ?>" <?= $is_submitted ? 'disabled' : '' ?>>
                                                <?= htmlspecialchars($subject['code']) ?> - <?= htmlspecialchars($subject['name']) ?>
                                                <?= $is_submitted ? ' (Already Submitted)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        You can only submit one answer sheet per subject
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="exam_title" class="form-label">
                                        <i class="fas fa-clipboard-list text-success me-1"></i>
                                        Exam Title <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="exam_title" name="exam_title" 
                                           placeholder="e.g., Mid-term Examination, Final Exam, Assignment 1" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="exam_date" class="form-label">
                                        <i class="fas fa-calendar text-warning me-1"></i>
                                        Exam Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="exam_date" name="exam_date" 
                                           max="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="time_taken" class="form-label">
                                        <i class="fas fa-clock text-primary me-1"></i>
                                        Time Taken (minutes)
                                    </label>
                                    <input type="number" class="form-control" id="time_taken" name="time_taken" 
                                           placeholder="e.g., 120" min="1" max="300">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="answer_sheet_pdf" class="form-label">
                                <i class="fas fa-file-pdf text-danger me-1"></i>
                                Answer Sheet PDF <span class="text-danger">*</span>
                            </label>
                            <input type="file" class="form-control" id="answer_sheet_pdf" name="answer_sheet_pdf" 
                                   accept=".pdf" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Upload your completed answer sheet as a PDF file (Max size: 10MB)
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirm_submission" required>
                                <label class="form-check-label" for="confirm_submission">
                                    I confirm that this is my original work and I understand that once submitted, 
                                    this answer sheet will be sent to evaluators for assessment.
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Reset Form
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-1"></i> Submit Answer Sheet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recent Submissions & Info -->
        <div class="col-lg-4">
            <!-- Submission Guidelines -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        Submission Guidelines
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="mb-2">
                            <i class="fas fa-check text-success me-1"></i>
                            Ensure your PDF is clear and readable
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-check text-success me-1"></i>
                            All pages should be properly scanned
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-check text-success me-1"></i>
                            File size should not exceed 10MB
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-check text-success me-1"></i>
                            Submit within the deadline
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-exclamation-triangle text-danger me-1"></i>
                            <strong>Only one submission per subject is allowed</strong>
                        </div>
                        <div class="mb-0">
                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                            Once submitted, you cannot modify your answers
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Submissions -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Submissions
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_submissions)): ?>
                        <?php foreach ($recent_submissions as $submission): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="small fw-bold"><?= htmlspecialchars($submission['exam_title']) ?></div>
                                <div class="small text-muted">
                                    <?= htmlspecialchars($submission['subject_name']) ?> | 
                                    <?= date('M j, Y', strtotime($submission['submitted_at'])) ?>
                                </div>
                                <div class="small">
                                    Status: 
                                    <?php if ($submission['accepted_count'] > 0): ?>
                                        <span class="badge bg-success">Assigned</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pending Assignment</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="submission_history.php" class="btn btn-sm btn-outline-primary">
                                View All Submissions
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <div>No submissions yet</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // File upload validation
    $('#answer_sheet_pdf').on('change', function() {
        const file = this.files[0];
        if (file) {
            // Check file size (10MB = 10 * 1024 * 1024 bytes)
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must not exceed 10MB');
                this.value = '';
                return;
            }
            
            // Check file type
            if (file.type !== 'application/pdf') {
                alert('Please upload a PDF file only');
                this.value = '';
                return;
            }
        }
    });

    // Form submission
    $('#submission-form').on('submit', function(e) {
        const confirmCheckbox = $('#confirm_submission');
        if (!confirmCheckbox.is(':checked')) {
            e.preventDefault();
            alert('Please confirm your submission by checking the checkbox');
            return;
        }

        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="fas fa-spinner fa-spin me-1"></i> Submitting...');
    });

    // Set default exam date to today
    $('#exam_date').val(new Date().toISOString().split('T')[0]);
});
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}
</style>

<?php include '../includes/footer.php'; ?>