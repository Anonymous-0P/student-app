<?php
session_start();

// Check if user is logged in and is an evaluator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';

// Get answer sheet ID from URL
$answer_sheet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$answer_sheet_id) {
    header("Location: assignments.php");
    exit();
}

// Check if evaluator is assigned to this answer sheet
$check_assignment_sql = "SELECT easa.*, as_main.exam_title, as_main.student_id, as_main.subject_id, 
                        u.name as student_name, u.email as student_email,
                        s.name as subject_name, s.code as subject_code
                        FROM evaluator_answer_sheet_assignments easa
                        JOIN answer_sheets as_main ON easa.answer_sheet_id = as_main.id
                        JOIN users u ON as_main.student_id = u.id
                        JOIN subjects s ON as_main.subject_id = s.id
                        WHERE easa.answer_sheet_id = ? AND easa.evaluator_id = ?";

$stmt = $pdo->prepare($check_assignment_sql);
$stmt->execute([$answer_sheet_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    $_SESSION['error'] = "You are not assigned to evaluate this answer sheet.";
    header("Location: assignments.php");
    exit();
}

// Handle evaluation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();

        if ($_POST['action'] === 'save_evaluation') {
            // Update marks for each question
            foreach ($_POST['question_marks'] as $question_id => $marks) {
                $marks = is_numeric($marks) ? (float)$marks : 0;
                $comments = isset($_POST['question_comments'][$question_id]) ? trim($_POST['question_comments'][$question_id]) : '';
                
                // Get max marks for validation
                $max_marks_sql = "SELECT max_marks FROM answer_sheet_questions WHERE id = ?";
                $max_stmt = $pdo->prepare($max_marks_sql);
                $max_stmt->execute([$question_id]);
                $max_marks = $max_stmt->fetchColumn();
                
                // Validate marks
                if ($marks > $max_marks) {
                    throw new Exception("Marks for question {$question_id} cannot exceed maximum marks ({$max_marks})");
                }
                
                // Update question marks and comments
                $update_question_sql = "UPDATE answer_sheet_questions 
                                      SET marks_obtained = ?, evaluator_comments = ?, is_evaluated = 1 
                                      WHERE id = ?";
                $stmt = $pdo->prepare($update_question_sql);
                $stmt->execute([$marks, $comments, $question_id]);
                
                // Record in marks history
                $history_sql = "INSERT INTO marks_history (answer_sheet_id, question_id, evaluator_id, marks_given, max_marks, action_type, created_by_role)
                               VALUES (?, ?, ?, ?, ?, 'evaluated', 'evaluator')";
                $hist_stmt = $pdo->prepare($history_sql);
                $hist_stmt->execute([$answer_sheet_id, $question_id, $_SESSION['user_id'], $marks, $max_marks]);
            }
            
            // Calculate total marks
            $total_sql = "SELECT SUM(marks_obtained) as total_obtained, SUM(max_marks) as total_possible 
                         FROM answer_sheet_questions WHERE answer_sheet_id = ?";
            $total_stmt = $pdo->prepare($total_sql);
            $total_stmt->execute([$answer_sheet_id]);
            $totals = $total_stmt->fetch(PDO::FETCH_ASSOC);
            
            $total_obtained = $totals['total_obtained'] ?? 0;
            $total_possible = $totals['total_possible'] ?? 0;
            $percentage = $total_possible > 0 ? round(($total_obtained / $total_possible) * 100, 2) : 0;
            
            // Determine grade
            $grade = 'F';
            if ($percentage >= 90) $grade = 'A+';
            elseif ($percentage >= 85) $grade = 'A';
            elseif ($percentage >= 80) $grade = 'A-';
            elseif ($percentage >= 75) $grade = 'B+';
            elseif ($percentage >= 70) $grade = 'B';
            elseif ($percentage >= 65) $grade = 'B-';
            elseif ($percentage >= 60) $grade = 'C+';
            elseif ($percentage >= 55) $grade = 'C';
            elseif ($percentage >= 50) $grade = 'C-';
            elseif ($percentage >= 45) $grade = 'D';
            
            // Save or update overall feedback
            $overall_feedback = trim($_POST['overall_feedback'] ?? '');
            $strengths = trim($_POST['strengths'] ?? '');
            $improvements = trim($_POST['improvements'] ?? '');
            $recommendations = trim($_POST['recommendations'] ?? '');
            
            $feedback_sql = "INSERT INTO evaluation_feedback 
                           (answer_sheet_id, evaluator_id, total_marks_obtained, total_marks_possible, percentage, grade, 
                            overall_feedback, strengths, improvements, recommendations)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE
                           total_marks_obtained = VALUES(total_marks_obtained),
                           total_marks_possible = VALUES(total_marks_possible),
                           percentage = VALUES(percentage),
                           grade = VALUES(grade),
                           overall_feedback = VALUES(overall_feedback),
                           strengths = VALUES(strengths),
                           improvements = VALUES(improvements),
                           recommendations = VALUES(recommendations),
                           updated_at = CURRENT_TIMESTAMP";
            
            $feedback_stmt = $pdo->prepare($feedback_sql);
            $feedback_stmt->execute([
                $answer_sheet_id, $_SESSION['user_id'], $total_obtained, $total_possible, 
                $percentage, $grade, $overall_feedback, $strengths, $improvements, $recommendations
            ]);
            
            // Update assignment status
            $status = isset($_POST['publish']) ? 'completed' : 'in_progress';
            $update_assignment_sql = "UPDATE evaluator_answer_sheet_assignments 
                                    SET status = ?, started_at = COALESCE(started_at, NOW())";
            if ($status === 'completed') {
                $update_assignment_sql .= ", completed_at = NOW()";
            }
            $update_assignment_sql .= " WHERE answer_sheet_id = ? AND evaluator_id = ?";
            
            $assign_stmt = $pdo->prepare($update_assignment_sql);
            $assign_stmt->execute([$status, $answer_sheet_id, $_SESSION['user_id']]);
            
            // Update answer sheet status
            $sheet_status = isset($_POST['publish']) ? 'evaluated' : 'under_evaluation';
            $update_sheet_sql = "UPDATE answer_sheets SET status = ? WHERE id = ?";
            $sheet_stmt = $pdo->prepare($update_sheet_sql);
            $sheet_stmt->execute([$sheet_status, $answer_sheet_id]);
            
            // Publish feedback if requested
            if (isset($_POST['publish'])) {
                $publish_sql = "UPDATE evaluation_feedback SET is_published = 1, published_at = NOW() 
                               WHERE answer_sheet_id = ? AND evaluator_id = ?";
                $pub_stmt = $pdo->prepare($publish_sql);
                $pub_stmt->execute([$answer_sheet_id, $_SESSION['user_id']]);
            }
            
            $pdo->commit();
            
            $success_message = isset($_POST['publish']) ? 
                "Evaluation completed and published successfully!" : 
                "Evaluation saved successfully!";
            $_SESSION['success'] = $success_message;
            
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error saving evaluation: " . $e->getMessage();
    }
}

// Fetch answer sheet details with questions and answers
$answer_sheet_sql = "SELECT as_main.*, u.name as student_name, u.email as student_email,
                    s.name as subject_name, s.code as subject_code,
                    ef.total_marks_obtained, ef.total_marks_possible, ef.percentage, ef.grade,
                    ef.overall_feedback, ef.strengths, ef.improvements, ef.recommendations,
                    ef.is_published
                    FROM answer_sheets as_main
                    JOIN users u ON as_main.student_id = u.id
                    JOIN subjects s ON as_main.subject_id = s.id
                    LEFT JOIN evaluation_feedback ef ON as_main.id = ef.answer_sheet_id AND ef.evaluator_id = ?
                    WHERE as_main.id = ?";

$stmt = $pdo->prepare($answer_sheet_sql);
$stmt->execute([$_SESSION['user_id'], $answer_sheet_id]);
$answer_sheet = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch questions with answers
$questions_sql = "SELECT asq.*, sa.answer_text, sa.answer_file_path
                 FROM answer_sheet_questions asq
                 LEFT JOIN student_answers sa ON asq.id = sa.question_id
                 WHERE asq.answer_sheet_id = ?
                 ORDER BY asq.question_number";

$stmt = $pdo->prepare($questions_sql);
$stmt->execute([$answer_sheet_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                                <i class="fas fa-clipboard-check text-primary me-2"></i>
                                Evaluate Answer Sheet
                            </h4>
                            <p class="text-muted mb-0">Question-by-question evaluation with personalized feedback</p>
                        </div>
                        <div class="text-end">
                            <a href="assignments.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Assignments
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Answer Sheet Info -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3"><i class="fas fa-file-alt text-info me-2"></i><?= htmlspecialchars($answer_sheet['exam_title']) ?></h5>
                            <p><strong>Subject:</strong> <?= htmlspecialchars($answer_sheet['subject_name']) ?> (<?= htmlspecialchars($answer_sheet['subject_code']) ?>)</p>
                            <p><strong>Student:</strong> <?= htmlspecialchars($answer_sheet['student_name']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($answer_sheet['student_email']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="evaluation-summary p-3 bg-light rounded">
                                <h6 class="mb-3"><i class="fas fa-chart-pie text-success me-2"></i>Evaluation Summary</h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h4 class="text-primary mb-0" id="total-marks">
                                                <?= $answer_sheet['total_marks_obtained'] ?? '0' ?>
                                            </h4>
                                            <small class="text-muted">Marks Obtained</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h4 class="text-info mb-0"><?= $answer_sheet['total_marks'] ?></h4>
                                            <small class="text-muted">Total Marks</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success mb-0" id="percentage">
                                            <?= $answer_sheet['percentage'] ?? '0' ?>%
                                        </h4>
                                        <small class="text-muted">Percentage</small>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <span class="badge bg-secondary fs-6" id="grade-badge">
                                        Grade: <?= $answer_sheet['grade'] ?? 'Not Graded' ?>
                                    </span>
                                </div>
                            </div>
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

    <!-- Evaluation Form -->
    <form method="POST" id="evaluation-form">
        <input type="hidden" name="action" value="save_evaluation">
        
        <!-- Questions Section -->
        <div class="row">
            <div class="col-12">
                <?php foreach ($questions as $index => $question): ?>
                <div class="card shadow-sm border-0 mb-4 question-card">
                    <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="text-white mb-0">
                                <i class="fas fa-question-circle me-2"></i>
                                Question <?= $question['question_number'] ?>
                                <span class="badge bg-light text-dark ms-2"><?= $question['max_marks'] ?> marks</span>
                            </h6>
                            <span class="badge bg-<?= $question['is_evaluated'] ? 'success' : 'warning' ?>">
                                <?= $question['is_evaluated'] ? 'Evaluated' : 'Pending' ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Question Text -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-2">Question:</h6>
                            <div class="p-3 bg-light rounded">
                                <?= nl2br(htmlspecialchars($question['question_text'])) ?>
                            </div>
                        </div>

                        <!-- Student Answer -->
                        <div class="mb-4">
                            <h6 class="text-success mb-2">Student's Answer:</h6>
                            <div class="p-3 border rounded student-answer">
                                <?php if (!empty($question['answer_text'])): ?>
                                    <?= nl2br(htmlspecialchars($question['answer_text'])) ?>
                                <?php else: ?>
                                    <em class="text-muted">No answer provided</em>
                                <?php endif; ?>
                                
                                <?php if (!empty($question['answer_file_path'])): ?>
                                    <div class="mt-2">
                                        <a href="<?= htmlspecialchars($question['answer_file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-file me-1"></i> View Attached File
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Evaluation Section -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-star text-warning me-1"></i>
                                        Marks Awarded (Max: <?= $question['max_marks'] ?>)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" 
                                               class="form-control marks-input" 
                                               name="question_marks[<?= $question['id'] ?>]" 
                                               value="<?= $question['marks_obtained'] ?? '' ?>"
                                               min="0" 
                                               max="<?= $question['max_marks'] ?>" 
                                               step="0.25"
                                               data-max="<?= $question['max_marks'] ?>"
                                               placeholder="Enter marks">
                                        <span class="input-group-text">/ <?= $question['max_marks'] ?></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-comments text-info me-1"></i>
                                        Comments & Feedback
                                    </label>
                                    <textarea class="form-control" 
                                              name="question_comments[<?= $question['id'] ?>]" 
                                              rows="3" 
                                              placeholder="Provide specific feedback for this answer..."><?= htmlspecialchars($question['evaluator_comments'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Overall Feedback Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <h6 class="text-white mb-0">
                            <i class="fas fa-comment-alt me-2"></i>
                            Overall Evaluation Feedback
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-thumbs-up text-success me-1"></i>
                                        Strengths
                                    </label>
                                    <textarea class="form-control" name="strengths" rows="4" 
                                              placeholder="Highlight what the student did well..."><?= htmlspecialchars($answer_sheet['strengths'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                        Areas for Improvement
                                    </label>
                                    <textarea class="form-control" name="improvements" rows="4" 
                                              placeholder="Suggest areas where the student can improve..."><?= htmlspecialchars($answer_sheet['improvements'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-lightbulb text-info me-1"></i>
                                        Recommendations
                                    </label>
                                    <textarea class="form-control" name="recommendations" rows="4" 
                                              placeholder="Provide specific recommendations for future learning..."><?= htmlspecialchars($answer_sheet['recommendations'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-clipboard-list text-primary me-1"></i>
                                        Overall Feedback
                                    </label>
                                    <textarea class="form-control" name="overall_feedback" rows="4" 
                                              placeholder="Provide comprehensive feedback about the overall performance..."><?= htmlspecialchars($answer_sheet['overall_feedback'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <button type="submit" class="btn btn-success btn-lg me-3">
                            <i class="fas fa-save me-2"></i>Save Evaluation
                        </button>
                        <button type="submit" name="publish" value="1" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-paper-plane me-2"></i>Save & Publish Results
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-lg" onclick="autoSave()">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Auto Save Draft
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Real-time marks calculation
    $('.marks-input').on('input', function() {
        validateMarks(this);
        calculateTotal();
    });
    
    // Initial calculation
    calculateTotal();
    
    // Form validation
    $('#evaluation-form').on('submit', function(e) {
        let isValid = true;
        
        $('.marks-input').each(function() {
            if (!validateMarks(this)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fix the validation errors before submitting.');
        }
    });
    
    // Auto-save functionality
    setInterval(autoSave, 300000); // Auto-save every 5 minutes
});

function validateMarks(input) {
    const value = parseFloat(input.value);
    const max = parseFloat(input.dataset.max);
    const isValid = !isNaN(value) && value >= 0 && value <= max;
    
    if (input.value && !isValid) {
        input.classList.add('is-invalid');
        const feedback = input.parentElement.parentElement.querySelector('.invalid-feedback');
        feedback.textContent = `Marks must be between 0 and ${max}`;
        return false;
    } else {
        input.classList.remove('is-invalid');
        return true;
    }
}

function calculateTotal() {
    let totalObtained = 0;
    let totalPossible = 0;
    let allEvaluated = true;
    
    $('.marks-input').each(function() {
        const max = parseFloat(this.dataset.max);
        const obtained = parseFloat(this.value) || 0;
        
        if (this.value === '') {
            allEvaluated = false;
        }
        
        totalObtained += obtained;
        totalPossible += max;
    });
    
    const percentage = totalPossible > 0 ? (totalObtained / totalPossible * 100) : 0;
    
    // Update display
    $('#total-marks').text(totalObtained.toFixed(2));
    $('#percentage').text(percentage.toFixed(2) + '%');
    
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
    else if (percentage >= 45) grade = 'D';
    
    $('#grade-badge').text('Grade: ' + grade);
    
    // Update badge color based on grade
    const badgeElement = $('#grade-badge');
    badgeElement.removeClass('bg-success bg-warning bg-danger bg-secondary');
    
    if (percentage >= 70) badgeElement.addClass('bg-success');
    else if (percentage >= 50) badgeElement.addClass('bg-warning');
    else if (percentage >= 1) badgeElement.addClass('bg-danger');
    else badgeElement.addClass('bg-secondary');
}

function autoSave() {
    const formData = new FormData($('#evaluation-form')[0]);
    formData.set('action', 'save_evaluation');
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            // Show temporary success message
            const alertDiv = $('<div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;" role="alert">' +
                '<i class="fas fa-cloud-upload-alt me-2"></i>Draft saved automatically!' +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>');
            
            $('body').append(alertDiv);
            
            setTimeout(function() {
                alertDiv.alert('close');
            }, 3000);
        },
        error: function() {
            console.log('Auto-save failed');
        }
    });
}

// Smooth scroll to next question
function scrollToNextQuestion(currentIndex) {
    const nextCard = $('.question-card').eq(currentIndex + 1);
    if (nextCard.length) {
        $('html, body').animate({
            scrollTop: nextCard.offset().top - 100
        }, 500);
    }
}
</script>

<style>
.question-card {
    transition: transform 0.2s ease-in-out;
}

.question-card:hover {
    transform: translateY(-2px);
}

.student-answer {
    min-height: 100px;
    max-height: 300px;
    overflow-y: auto;
}

.evaluation-summary {
    border-left: 4px solid #007bff;
}

.marks-input.is-invalid {
    border-color: #dc3545;
}

.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.card-header.bg-gradient h6 {
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1.1rem;
}

.position-fixed {
    position: fixed !important;
}
</style>

<?php include '../includes/footer.php'; ?>