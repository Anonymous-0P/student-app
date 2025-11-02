<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in as evaluator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'evaluator'){
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['assignment_id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit();
}

$assignment_id = (int)$_POST['assignment_id'];
$evaluator_id = $_SESSION['user_id'];

try {
    // Get evaluation details
    $query = "SELECT sa.id as assignment_id, sa.assigned_at, sa.responded_at, sa.status as assignment_status,
                     s.id as submission_id, s.marks_obtained, s.max_marks, s.evaluator_remarks, s.evaluated_at,
                     sub.code as subject_code, sub.name as subject_name,
                     u.name as student_name, u.email as student_email,
                     mh.created_at as history_created_at
              FROM submission_assignments sa
              INNER JOIN submissions s ON sa.submission_id = s.id
              INNER JOIN subjects sub ON s.subject_id = sub.id
              INNER JOIN users u ON s.student_id = u.id
              LEFT JOIN marks_history mh ON s.id = mh.submission_id AND mh.evaluator_id = ?
              WHERE sa.id = ? AND sa.evaluator_id = ? AND (s.status = 'evaluated' OR s.evaluation_status = 'evaluated')";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $evaluator_id, $assignment_id, $evaluator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $evaluation = $result->fetch_assoc();
    
    if (!$evaluation) {
        echo '<div class="alert alert-warning">Evaluation summary not found or not completed yet.</div>';
        exit();
    }
    
    // Parse per-question marks if available
    $per_question_marks = [];
    if (isset($evaluation['per_question_marks']) && $evaluation['per_question_marks']) {
        $per_question_marks = json_decode($evaluation['per_question_marks'], true) ?: [];
    }
    
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary mb-3">
                <i class="fas fa-info-circle me-2"></i>Evaluation Details
            </h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Student:</strong></td>
                    <td><?= htmlspecialchars($evaluation['student_name']) ?></td>
                </tr>
                <tr>
                    <td><strong>Subject:</strong></td>
                    <td><?= htmlspecialchars($evaluation['subject_code']) ?> - <?= htmlspecialchars($evaluation['subject_name']) ?></td>
                </tr>
                <tr>
                    <td><strong>Completed:</strong></td>
                    <td><?= $evaluation['evaluated_at'] ? date('M j, Y g:i A', strtotime($evaluation['evaluated_at'])) : 'N/A' ?></td>
                </tr>
                <tr>
                    <td><strong>Final Score:</strong></td>
                    <td>
                        <span class="fw-bold text-primary">
                            <?= number_format($evaluation['marks_obtained'], 2) ?> / <?= number_format($evaluation['max_marks'], 2) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Percentage:</strong></td>
                    <td>
                        <span class="fw-bold">
                            <?php 
                            $percentage = 0;
                            if (isset($evaluation['percentage'])) {
                                $percentage = $evaluation['percentage'];
                            } elseif ($evaluation['max_marks'] > 0) {
                                $percentage = ($evaluation['marks_obtained'] / $evaluation['max_marks']) * 100;
                            }
                            echo number_format($percentage, 1);
                            ?>%
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Grade:</strong></td>
                    <td>
                        <?php 
                        $grade = 'N/A';
                        if (isset($evaluation['grade']) && $evaluation['grade']) {
                            $grade = $evaluation['grade'];
                        } else {
                            // Calculate grade from percentage
                            if ($percentage >= 90) $grade = 'A+';
                            elseif ($percentage >= 85) $grade = 'A';
                            elseif ($percentage >= 80) $grade = 'A-';
                            elseif ($percentage >= 75) $grade = 'B+';
                            elseif ($percentage >= 70) $grade = 'B';
                            elseif ($percentage >= 65) $grade = 'B-';
                            elseif ($percentage >= 60) $grade = 'C+';
                            elseif ($percentage >= 55) $grade = 'C';
                            elseif ($percentage >= 50) $grade = 'C-';
                            elseif ($percentage >= 35) $grade = 'D';
                            else $grade = 'F';
                        }
                        ?>
                        <span class="badge bg-primary fs-6">
                            <?= htmlspecialchars($grade) ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-primary mb-3">
                <i class="fas fa-chart-pie me-2"></i>Score Breakdown
            </h6>
            <div class="progress mb-3" style="height: 30px;">
                <?php 
                $progressColor = 'bg-danger';
                if ($percentage >= 80) $progressColor = 'bg-success';
                elseif ($percentage >= 60) $progressColor = 'bg-warning';
                elseif ($percentage >= 50) $progressColor = 'bg-info';
                ?>
                <div class="progress-bar <?= $progressColor ?>" role="progressbar" 
                     style="width: <?= $percentage ?>%" aria-valuenow="<?= $percentage ?>" 
                     aria-valuemin="0" aria-valuemax="100">
                    <?= number_format($percentage, 1) ?>%
                </div>
            </div>
            
            <?php if (!empty($per_question_marks)): ?>
            <div class="card">
                <div class="card-header py-2">
                    <small class="text-muted">
                        <i class="fas fa-list me-1"></i>Question-wise Marks
                    </small>
                </div>
                <div class="card-body py-2" style="max-height: 200px; overflow-y: auto;">
                    <div class="row g-2">
                        <?php foreach ($per_question_marks as $qnum => $marks): ?>
                        <div class="col-6">
                            <small>Q<?= $qnum ?>: <strong><?= number_format($marks, 2) ?></strong></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <h6 class="text-primary mb-3">
                <i class="fas fa-comment-alt me-2"></i>Evaluation Feedback
            </h6>
            <div class="card">
                <div class="card-body">
                    <?php if ($evaluation['evaluator_remarks']): ?>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($evaluation['evaluator_remarks'])) ?></p>
                    <?php else: ?>
                        <em class="text-muted">No feedback provided.</em>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-success">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <h6 class="mb-1">Evaluation Status: Completed</h6>
                        <small class="text-muted">
                            This evaluation has been successfully completed and saved. 
                            The student has been notified of their results.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">';
    echo '<i class="fas fa-exclamation-triangle me-2"></i>';
    echo 'Error loading evaluation summary: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}
?>