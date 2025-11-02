<?php
session_start();
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is an evaluator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: evaluation_history.php");
    exit();
}

$evaluator_id = $_SESSION['user_id'];
$submission_id = intval($_GET['id']);

// Get evaluation details
$evaluation_query = "SELECT 
    s.id,
    s.submission_title,
    s.pdf_url as file_path,
    s.marks_obtained,
    s.max_marks,
    s.evaluator_remarks,
    s.evaluated_at,
    s.created_at,
    s.status,
    ea.id as assignment_id,
    ea.evaluation_deadline,
    ea.assigned_at,
    ea.status as assignment_status,
    subj.name as subject_name,
    subj.code as subject_code,
    subj.department,
    u.name as student_name,
    u.email as student_email,
    m.name as moderator_name
    FROM submissions s
    JOIN evaluator_assignments ea ON s.id = ea.submission_id
    JOIN subjects subj ON ea.subject_id = subj.id
    JOIN users u ON s.student_id = u.id
    LEFT JOIN users m ON ea.moderator_id = m.id
    WHERE s.id = ? AND ea.evaluator_id = ? AND ea.status = 'completed'";

$stmt = $conn->prepare($evaluation_query);
$evaluation = null;
if($stmt) {
    $stmt->bind_param("ii", $submission_id, $evaluator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $evaluation = $result ? $result->fetch_assoc() : null;
}

if (!$evaluation) {
    $_SESSION['error_message'] = "Evaluation not found or you don't have permission to view it.";
    header("Location: evaluation_history.php");
    exit();
}

$percentage = ($evaluation['marks_obtained'] / $evaluation['max_marks']) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Evaluation - Evaluator Portal</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .view-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 80px;
        }
        
        .view-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }
        
        .submission-header {
            background: rgba(108, 92, 231, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 5px solid #6c5ce7;
        }
        
        .marks-summary {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .evaluation-details {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="view-page">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="text-center text-white">
                        <h1 class="display-6 mb-3">
                            <i class="fas fa-eye me-3"></i>
                            View Evaluation
                        </h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb justify-content-center">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php" class="text-white-50">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="evaluation_history.php" class="text-white-50">History</a>
                                </li>
                                <li class="breadcrumb-item active text-white" aria-current="page">View</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="row">
                <!-- Left Panel - Submission View -->
                <div class="col-md-8">
                    <div class="view-container">
                        <!-- Submission Header -->
                        <div class="submission-header">
                            <h4 class="mb-3">
                                <i class="fas fa-file-alt me-2"></i>
                                <?php echo htmlspecialchars($evaluation['submission_title']); ?>
                            </h4>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <i class="fas fa-book me-2"></i>
                                                <strong>Subject:</strong> <?php echo htmlspecialchars($evaluation['subject_name']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <i class="fas fa-code me-2"></i>
                                                <strong>Code:</strong> <?php echo htmlspecialchars($evaluation['subject_code']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <i class="fas fa-user me-2"></i>
                                                <strong>Student:</strong> <?php echo htmlspecialchars($evaluation['student_name']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <i class="fas fa-calendar me-2"></i>
                                                <strong>Evaluated:</strong> <?php echo date('M j, Y g:i A', strtotime($evaluation['evaluated_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($evaluation['file_path'] && file_exists('../' . $evaluation['file_path'])): ?>
                                        <a href="../<?php echo htmlspecialchars($evaluation['file_path']); ?>" 
                                           target="_blank" class="btn btn-primary mb-2">
                                            <i class="fas fa-eye me-2"></i>View Submission
                                        </a>
                                        <br>
                                        <a href="../<?php echo htmlspecialchars($evaluation['file_path']); ?>" 
                                           download class="btn btn-outline-primary">
                                            <i class="fas fa-download me-2"></i>Download PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- PDF Viewer (Read-only) -->
                        <div class="pdf-viewer" style="border: 2px solid #dee2e6; border-radius: 8px; background: #f8f9fa; min-height: 500px;">
                            <?php if ($evaluation['file_path'] && file_exists('../' . $evaluation['file_path'])): ?>
                                <iframe src="../<?php echo htmlspecialchars($evaluation['file_path']); ?>" 
                                        width="100%" height="500px" style="border: none; border-radius: 8px;">
                                    <p>Your browser doesn't support PDF viewing. 
                                       <a href="../<?php echo htmlspecialchars($evaluation['file_path']); ?>" target="_blank">
                                           Click here to view the PDF
                                       </a>
                                    </p>
                                </iframe>
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                    <div class="text-center">
                                        <i class="fas fa-file-times fa-4x mb-3"></i>
                                        <h4>Submission File Not Available</h4>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Panel - Evaluation Summary -->
                <div class="col-md-4">
                    <div class="view-container">
                        <!-- Marks Summary -->
                        <div class="marks-summary">
                            <h2 class="mb-2">
                                <?php echo number_format($evaluation['marks_obtained'], 1); ?>/<?php echo number_format($evaluation['max_marks'], 1); ?>
                            </h2>
                            <h4 class="mb-2"><?php echo number_format($percentage, 1); ?>%</h4>
                            <p class="mb-0">
                                Grade: 
                                <?php
                                $grade = 'F';
                                if ($percentage >= 90) $grade = 'A+';
                                elseif ($percentage >= 80) $grade = 'A';
                                elseif ($percentage >= 70) $grade = 'B';
                                elseif ($percentage >= 60) $grade = 'C';
                                elseif ($percentage >= 50) $grade = 'D';
                                echo $grade;
                                ?>
                            </p>
                        </div>

                        <!-- Evaluation Details -->
                        <div class="evaluation-details">
                            <h5 class="mb-3">
                                <i class="fas fa-clipboard-check me-2"></i>Evaluation Details
                            </h5>

                            <!-- Timeline -->
                            <div class="mb-4">
                                <h6 class="mb-3">Timeline</h6>
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <small class="text-muted">
                                            <i class="fas fa-upload me-2"></i>
                                            Submitted: <?php echo date('M j, Y g:i A', strtotime($evaluation['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="timeline-item">
                                        <small class="text-muted">
                                            <i class="fas fa-user-plus me-2"></i>
                                            Assigned: <?php echo date('M j, Y g:i A', strtotime($evaluation['assigned_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="timeline-item">
                                        <small class="text-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            Evaluated: <?php echo date('M j, Y g:i A', strtotime($evaluation['evaluated_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Your Comments -->
                            <div class="mb-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-comment-alt me-2"></i>Your Evaluation Comments
                                </h6>
                                <div class="bg-light p-3 rounded">
                                    <?php if ($evaluation['evaluator_remarks']): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($evaluation['evaluator_remarks'])); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No comments provided.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Submission Info -->
                            <div class="mb-3">
                                <h6 class="mb-3">Submission Info</h6>
                                <p class="mb-2">
                                    <small>
                                        <strong>Department:</strong><br>
                                        <?php echo htmlspecialchars($evaluation['department']); ?>
                                    </small>
                                </p>
                                <p class="mb-2">
                                    <small>
                                        <strong>Student Email:</strong><br>
                                        <?php echo htmlspecialchars($evaluation['student_email']); ?>
                                    </small>
                                </p>
                                <p class="mb-2">
                                    <small>
                                        <strong>Moderator:</strong><br>
                                        <?php echo htmlspecialchars($evaluation['moderator_name']); ?>
                                    </small>
                                </p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <a href="evaluation_history.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to History
                                </a>
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-home me-2"></i>Dashboard
                                </a>
                            </div>
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