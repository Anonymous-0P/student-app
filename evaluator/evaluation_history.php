<?php
session_start();
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is an evaluator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: ../auth/login.php");
    exit();
}

$evaluator_id = $_SESSION['user_id'];

// Get evaluation history
$history_query = "SELECT 
    ea.id as assignment_id,
    ea.submission_id,
    ea.evaluation_deadline,
    ea.assigned_at,
    ea.status,
    s.submission_title,
    s.marks_obtained,
    s.max_marks,
    s.evaluator_remarks,
    s.evaluated_at,
    s.status as submission_status,
    subj.name as subject_name,
    subj.code as subject_code,
    u.name as student_name,
    m.name as moderator_name
    FROM evaluator_assignments ea
    JOIN submissions s ON ea.submission_id = s.id
    JOIN subjects subj ON ea.subject_id = subj.id
    JOIN users u ON s.student_id = u.id
    LEFT JOIN users m ON ea.moderator_id = m.id
    WHERE ea.evaluator_id = ? AND ea.status = 'completed'
    ORDER BY s.evaluated_at DESC";

$stmt = $conn->prepare($history_query);
$evaluation_history = [];
if($stmt) {
    $stmt->bind_param("i", $evaluator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $evaluation_history = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation History - Evaluator Portal</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="css/evaluator-style.css" rel="stylesheet">
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="evaluator-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row">
                    <div class="col-12">
                        <h1><i class="fas fa-history me-2"></i>Evaluation History</h1>
                        <p>Review your completed evaluations</p>
                    </div>
                </div>
            </div>

            <!-- History Container -->
            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h5><i class="fas fa-history me-2"></i>Completed Evaluations</h5>
                        <?php if (empty($evaluation_history)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No completed evaluations yet.</p>
                            </div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Submission</th>
                                        <th>Student</th>
                                        <th>Subject</th>
                                        <th>Marks</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                            <?php foreach ($evaluation_history as $evaluation): ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars($evaluation['submission_title']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($evaluation['student_name']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($evaluation['subject_code']); ?></span>
                                        <br><small><?php echo htmlspecialchars($evaluation['subject_name']); ?></small>
                                    </td>
                                    <td>
                                        <strong class="text-success">
                                            <?php 
                                            $percentage = ($evaluation['marks_obtained'] / $evaluation['max_marks']) * 100;
                                            echo number_format($evaluation['marks_obtained'], 1) . '/' . number_format($evaluation['max_marks'], 1);
                                            ?>
                                        </strong>
                                        <br><small>(<?php echo number_format($percentage, 1); ?>%)</small>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($evaluation['evaluated_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="view_evaluation.php?id=<?php echo $evaluation['submission_id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <?php include('../includes/footer.php'); ?>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/css/bootstrap.min.css"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>