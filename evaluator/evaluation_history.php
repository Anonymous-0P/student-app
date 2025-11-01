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
    <style>
        .history-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 80px;
        }
        
        .history-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }
        
        .history-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .marks-display {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
        }
        
        .subject-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="history-page">
        <div class="container">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="text-center text-white">
                        <h1 class="display-5 mb-3">
                            <i class="fas fa-history me-3"></i>
                            Evaluation History
                        </h1>
                        <p class="lead">Review your completed evaluations</p>
                    </div>
                </div>
            </div>

            <!-- History Container -->
            <div class="row">
                <div class="col-12">
                    <div class="history-container">
                        <?php if (empty($evaluation_history)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-list fa-4x text-muted mb-4"></i>
                                <h3 class="text-muted mb-3">No Completed Evaluations</h3>
                                <p class="text-muted">Your completed evaluations will appear here.</p>
                                <a href="pending_evaluations.php" class="btn btn-primary">
                                    <i class="fas fa-clipboard-check me-2"></i>View Pending Evaluations
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($evaluation_history as $evaluation): ?>
                                <div class="history-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center mb-2">
                                                <h5 class="mb-0 me-3">
                                                    <?php echo htmlspecialchars($evaluation['submission_title']); ?>
                                                </h5>
                                                <span class="subject-badge">
                                                    <?php echo htmlspecialchars($evaluation['subject_code']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-1 text-muted">
                                                        <i class="fas fa-book me-2"></i>
                                                        <?php echo htmlspecialchars($evaluation['subject_name']); ?>
                                                    </p>
                                                    <p class="mb-1 text-muted">
                                                        <i class="fas fa-user me-2"></i>
                                                        <?php echo htmlspecialchars($evaluation['student_name']); ?>
                                                    </p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-1 text-muted">
                                                        <i class="fas fa-calendar me-2"></i>
                                                        Evaluated: <?php echo date('M j, Y g:i A', strtotime($evaluation['evaluated_at'])); ?>
                                                    </p>
                                                    <p class="mb-1 text-muted">
                                                        <i class="fas fa-user-tie me-2"></i>
                                                        Moderator: <?php echo htmlspecialchars($evaluation['moderator_name']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <?php if ($evaluation['evaluator_remarks']): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <strong>Your Comments:</strong> 
                                                        <?php echo nl2br(htmlspecialchars(substr($evaluation['evaluator_remarks'], 0, 100))); ?>
                                                        <?php if (strlen($evaluation['evaluator_remarks']) > 100): ?>...<?php endif; ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-4 text-end">
                                            <div class="marks-display mb-2">
                                                <?php 
                                                $percentage = ($evaluation['marks_obtained'] / $evaluation['max_marks']) * 100;
                                                echo number_format($evaluation['marks_obtained'], 1) . '/' . number_format($evaluation['max_marks'], 1);
                                                ?>
                                                <br>
                                                <small><?php echo number_format($percentage, 1); ?>%</small>
                                            </div>
                                            <div>
                                                <a href="view_evaluation.php?id=<?php echo $evaluation['submission_id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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