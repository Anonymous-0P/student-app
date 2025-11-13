<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';

// Get student's evaluated answer sheets
$evaluated_sheets_sql = "SELECT as_main.id, as_main.exam_title, as_main.exam_date, as_main.total_questions, 
                        as_main.total_marks, as_main.status, as_main.submitted_at,
                        s.name as subject_name, s.code as subject_code,
                        ef.total_marks_obtained, ef.total_marks_possible, ef.percentage, ef.grade,
                        ef.overall_feedback, ef.strengths, ef.improvements, ef.recommendations,
                        ef.is_published, ef.published_at, ef.created_at as evaluated_at,
                        u.name as evaluator_name
                        FROM answer_sheets as_main
                        JOIN subjects s ON as_main.subject_id = s.id
                        LEFT JOIN evaluation_feedback ef ON as_main.id = ef.answer_sheet_id
                        LEFT JOIN users u ON ef.evaluator_id = u.id
                        WHERE as_main.student_id = ? AND as_main.status IN ('evaluated', 'published')
                        ORDER BY as_main.exam_date DESC, ef.created_at DESC";

$stmt = $pdo->prepare($evaluated_sheets_sql);
$stmt->execute([$_SESSION['user_id']]);
$evaluated_sheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get answer sheet ID from URL for detailed view
$answer_sheet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detailed_view = false;
$answer_sheet_details = null;
$questions_with_evaluation = null;

if ($answer_sheet_id) {
    // Verify this answer sheet belongs to the student
    $detail_sql = "SELECT as_main.*, s.name as subject_name, s.code as subject_code,
                   ef.total_marks_obtained, ef.total_marks_possible, ef.percentage, ef.grade,
                   ef.overall_feedback, ef.strengths, ef.improvements, ef.recommendations,
                   ef.is_published, ef.published_at,
                   u.name as evaluator_name
                   FROM answer_sheets as_main
                   JOIN subjects s ON as_main.subject_id = s.id
                   LEFT JOIN evaluation_feedback ef ON as_main.id = ef.answer_sheet_id
                   LEFT JOIN users u ON ef.evaluator_id = u.id
                   WHERE as_main.id = ? AND as_main.student_id = ? AND ef.is_published = 1";
    
    $detail_stmt = $pdo->prepare($detail_sql);
    $detail_stmt->execute([$answer_sheet_id, $_SESSION['user_id']]);
    $answer_sheet_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($answer_sheet_details) {
        $detailed_view = true;
        
        // Get questions with evaluation details
        $questions_sql = "SELECT asq.*, sa.answer_text, sa.answer_file_path
                         FROM answer_sheet_questions asq
                         LEFT JOIN student_answers sa ON asq.id = sa.question_id
                         WHERE asq.answer_sheet_id = ?
                         ORDER BY asq.question_number";
        
        $questions_stmt = $pdo->prepare($questions_sql);
        $questions_stmt->execute([$answer_sheet_id]);
        $questions_with_evaluation = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$isIndexPage = false;
include '../includes/header.php';
?>

<?php require_once('includes/sidebar.php'); ?>

<div class="dashboard-layout">
    <div class="main-content">

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-1">
                                <i class="fas fa-chart-line text-primary me-2"></i>
                                <?= $detailed_view ? 'Detailed Evaluation Results' : 'My Evaluation Results' ?>
                            </h4>
                            <p class="text-muted mb-0">
                                <?= $detailed_view ? 'Question-by-question evaluation feedback' : 'View your answer sheet evaluations and feedback' ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <?php if ($detailed_view): ?>
                                <a href="evaluation_results.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Results
                                </a>
                            <?php else: ?>
                                <a href="dashboard.php" class="btn btn-outline-primary">
                                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($detailed_view && $answer_sheet_details): ?>
        <!-- Detailed View -->
        
        <!-- Answer Sheet Summary -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-3">
                                    <i class="fas fa-file-alt text-info me-2"></i>
                                    <?= htmlspecialchars($answer_sheet_details['exam_title']) ?>
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Subject:</strong> <?= htmlspecialchars($answer_sheet_details['subject_name']) ?> (<?= htmlspecialchars($answer_sheet_details['subject_code']) ?>)</p>
                                        <p><strong>Exam Date:</strong> <?= date('M j, Y', strtotime($answer_sheet_details['exam_date'])) ?></p>
                                        <p><strong>Evaluator:</strong> <?= htmlspecialchars($answer_sheet_details['evaluator_name']) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Total Questions:</strong> <?= $answer_sheet_details['total_questions'] ?></p>
                                        <p><strong>Submitted:</strong> <?= date('M j, Y g:i A', strtotime($answer_sheet_details['submitted_at'])) ?></p>
                                        <p><strong>Published:</strong> <?= date('M j, Y g:i A', strtotime($answer_sheet_details['published_at'])) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="evaluation-summary p-3 bg-light rounded text-center">
                                    <h6 class="mb-3"><i class="fas fa-trophy text-warning me-2"></i>Your Performance</h6>
                                    <div class="mb-3">
                                        <h2 class="text-primary mb-0"><?= $answer_sheet_details['total_marks_obtained'] ?></h2>
                                        <small class="text-muted">out of <?= $answer_sheet_details['total_marks_possible'] ?> marks</small>
                                    </div>
                                    <div class="mb-3">
                                        <h3 class="text-success mb-0"><?= $answer_sheet_details['percentage'] ?>%</h3>
                                    </div>
                                    <span class="badge bg-secondary fs-6">Grade: <?= $answer_sheet_details['grade'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Questions and Evaluation -->
        <div class="row mb-4">
            <div class="col-12">
                <?php foreach ($questions_with_evaluation as $index => $question): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="text-white mb-0">
                                <i class="fas fa-question-circle me-2"></i>
                                Question <?= $question['question_number'] ?>
                            </h6>
                            <div class="text-white">
                                <span class="me-3">
                                    <i class="fas fa-star me-1"></i>
                                    <?= $question['marks_obtained'] ?? '0' ?>/<?= $question['max_marks'] ?> marks
                                </span>
                                <span class="badge bg-light text-dark">
                                    <?= $question['marks_obtained'] ? round(($question['marks_obtained'] / $question['max_marks']) * 100, 1) . '%' : '0%' ?>
                                </span>
                            </div>
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

                        <!-- Your Answer -->
                        <div class="mb-4">
                            <h6 class="text-success mb-2">Your Answer:</h6>
                            <div class="p-3 border rounded">
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

                        <!-- Evaluator Feedback -->
                        <?php if (!empty($question['evaluator_comments'])): ?>
                        <div class="mb-3">
                            <h6 class="text-info mb-2">
                                <i class="fas fa-comments me-1"></i>
                                Evaluator Feedback:
                            </h6>
                            <div class="p-3 bg-info bg-opacity-10 border-start border-info border-4 rounded">
                                <?= nl2br(htmlspecialchars($question['evaluator_comments'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Overall Feedback -->
        <?php if (!empty($answer_sheet_details['overall_feedback']) || !empty($answer_sheet_details['strengths']) || !empty($answer_sheet_details['improvements']) || !empty($answer_sheet_details['recommendations'])): ?>
        <div class="row">
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
                            <?php if (!empty($answer_sheet_details['strengths'])): ?>
                            <div class="col-md-6 mb-4">
                                <h6 class="text-success mb-2">
                                    <i class="fas fa-thumbs-up me-1"></i>
                                    Strengths
                                </h6>
                                <div class="p-3 bg-success bg-opacity-10 border-start border-success border-4 rounded">
                                    <?= nl2br(htmlspecialchars($answer_sheet_details['strengths'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($answer_sheet_details['improvements'])): ?>
                            <div class="col-md-6 mb-4">
                                <h6 class="text-warning mb-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Areas for Improvement
                                </h6>
                                <div class="p-3 bg-warning bg-opacity-10 border-start border-warning border-4 rounded">
                                    <?= nl2br(htmlspecialchars($answer_sheet_details['improvements'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <?php if (!empty($answer_sheet_details['recommendations'])): ?>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-info mb-2">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    Recommendations
                                </h6>
                                <div class="p-3 bg-info bg-opacity-10 border-start border-info border-4 rounded">
                                    <?= nl2br(htmlspecialchars($answer_sheet_details['recommendations'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($answer_sheet_details['overall_feedback'])): ?>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-primary mb-2">
                                    <i class="fas fa-clipboard-list me-1"></i>
                                    Overall Feedback
                                </h6>
                                <div class="p-3 bg-primary bg-opacity-10 border-start border-primary border-4 rounded">
                                    <?= nl2br(htmlspecialchars($answer_sheet_details['overall_feedback'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- List View -->
        
        <!-- Statistics -->
        <?php 
        $total_sheets = count($evaluated_sheets);
        $published_sheets = array_filter($evaluated_sheets, function($sheet) { return $sheet['is_published']; });
        $avg_percentage = $total_sheets > 0 ? array_sum(array_column($published_sheets, 'percentage')) / count($published_sheets) : 0;
        $grade_counts = array_count_values(array_column($published_sheets, 'grade'));
        ?>
        
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="h2 text-primary mb-2"><?= $total_sheets ?></div>
                        <div class="small text-muted">Total Evaluations</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="h2 text-success mb-2"><?= count($published_sheets) ?></div>
                        <div class="small text-muted">Published Results</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="h2 text-info mb-2"><?= number_format($avg_percentage, 1) ?>%</div>
                        <div class="small text-muted">Average Score</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="h2 text-warning mb-2"><?= $grade_counts['A'] ?? 0 ?></div>
                        <div class="small text-muted">A Grades</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Evaluations List -->
        <div class="row">
            <div class="col-12">
                <?php if (!empty($evaluated_sheets)): ?>
                    <?php foreach ($evaluated_sheets as $sheet): ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-2">
                                        <?php if ($sheet['is_published']): ?>
                                            <span class="badge bg-success me-2">PUBLISHED</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning me-2">PENDING</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($sheet['exam_title']) ?>
                                    </h5>
                                    <div class="mb-2">
                                        <strong>Subject:</strong> <?= htmlspecialchars($sheet['subject_code']) ?> - <?= htmlspecialchars($sheet['subject_name']) ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Exam Date:</strong> <?= date('M j, Y', strtotime($sheet['exam_date'])) ?>
                                    </div>
                                    <?php if ($sheet['evaluator_name']): ?>
                                    <div class="mb-2">
                                        <strong>Evaluator:</strong> <?= htmlspecialchars($sheet['evaluator_name']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <?php if ($sheet['is_published']): ?>
                                    <div class="text-center p-3 bg-light rounded">
                                        <div class="h4 text-primary mb-1"><?= $sheet['total_marks_obtained'] ?>/<?= $sheet['total_marks_possible'] ?></div>
                                        <div class="h5 text-success mb-2"><?= $sheet['percentage'] ?>%</div>
                                        <span class="badge bg-secondary"><?= $sheet['grade'] ?></span>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center p-3 bg-light rounded">
                                        <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                                        <div class="small text-muted">Evaluation Pending</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3 text-end">
                                    <?php if ($sheet['is_published']): ?>
                                        <a href="evaluation_results.php?id=<?= $sheet['id'] ?>" class="btn btn-primary mb-2">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                        <div class="small text-muted">
                                            Published: <?= date('M j, Y', strtotime($sheet['published_at'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary" disabled>
                                            <i class="fas fa-lock me-1"></i> Not Available
                                        </button>
                                        <div class="small text-muted">Results pending</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-clipboard-list fa-4x text-muted mb-4"></i>
                            <h5 class="text-muted mb-3">No Evaluations Available</h5>
                            <p class="text-muted">You don't have any evaluated answer sheets yet. Complete your exams to see results here.</p>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt me-1"></i> Go to Dashboard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.evaluation-summary {
    border-left: 4px solid #007bff;
}

.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.card-header.bg-gradient h6 {
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.border-4 {
    border-width: 4px !important;
}

.bg-opacity-10 {
    --bs-bg-opacity: 0.1;
}
</style>

    </div>
</div>

<?php include '../includes/footer.php'; ?>