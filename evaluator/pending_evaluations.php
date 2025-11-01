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

// Get pending evaluations
$pending_evaluations_query = "SELECT 
    ea.id as assignment_id,
    ea.submission_id,
    ea.evaluation_deadline,
    ea.assigned_at,
    ea.status,
    s.submission_title,
    s.pdf_url as file_path,
    s.created_at as submission_date,
    s.status as submission_status,
    subj.name as subject_name,
    subj.code as subject_code,
    subj.department,
    u.name as student_name,
    u.email as student_email,
    m.name as moderator_name
    FROM evaluator_assignments ea
    JOIN submissions s ON ea.submission_id = s.id
    JOIN subjects subj ON ea.subject_id = subj.id
    JOIN users u ON s.student_id = u.id
    LEFT JOIN users m ON ea.moderator_id = m.id
    WHERE ea.evaluator_id = ? AND ea.status IN ('assigned', 'in_progress')
    ORDER BY ea.evaluation_deadline ASC, ea.assigned_at DESC";

$stmt = $conn->prepare($pending_evaluations_query);
$pending_evaluations = [];
if($stmt) {
    $stmt->bind_param("i", $evaluator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pending_evaluations = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Evaluations - Evaluator Portal</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .evaluations-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 80px;
        }
        
        .evaluations-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }
        
        .evaluation-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .evaluation-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .priority-urgent {
            border-left: 5px solid #dc3545;
        }
        
        .priority-normal {
            border-left: 5px solid #28a745;
        }
        
        .priority-warning {
            border-left: 5px solid #ffc107;
        }
        
        .deadline-urgent {
            color: #dc3545;
            font-weight: 600;
        }
        
        .deadline-warning {
            color: #fd7e14;
            font-weight: 600;
        }
        
        .deadline-normal {
            color: #28a745;
        }
        
        .student-info {
            background: rgba(108, 92, 231, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 10px 0;
        }
        
        .subject-tag {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .btn-evaluate-now {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-evaluate-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .filter-tabs {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 5px;
            margin-bottom: 20px;
        }
        
        .filter-tab {
            background: transparent;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            margin: 2px;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            background: rgba(255, 255, 255, 0.9);
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="evaluations-page">
        <div class="container">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="text-center text-white">
                        <h1 class="display-5 mb-3">
                            <i class="fas fa-clipboard-list me-3"></i>
                            Pending Evaluations
                        </h1>
                        <p class="lead">Review and evaluate assigned submissions</p>
                    </div>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="filter-tabs text-center">
                        <button class="filter-tab active" onclick="filterEvaluations('all')">
                            <i class="fas fa-list me-2"></i>All Pending (<?php echo count($pending_evaluations); ?>)
                        </button>
                        <button class="filter-tab" onclick="filterEvaluations('urgent')">
                            <i class="fas fa-exclamation-triangle me-2"></i>Urgent
                        </button>
                        <button class="filter-tab" onclick="filterEvaluations('due-today')">
                            <i class="fas fa-calendar-day me-2"></i>Due Today
                        </button>
                    </div>
                </div>
            </div>

            <!-- Evaluations Container -->
            <div class="row">
                <div class="col-12">
                    <div class="evaluations-container">
                        <?php if (empty($pending_evaluations)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-check fa-4x text-success mb-4"></i>
                                <h3 class="text-muted mb-3">No Pending Evaluations</h3>
                                <p class="text-muted">Great job! You're all caught up with your evaluations.</p>
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        <?php else: ?>
                            <div id="evaluations-list">
                                <?php foreach ($pending_evaluations as $evaluation): ?>
                                    <?php
                                    $deadline = strtotime($evaluation['evaluation_deadline']);
                                    $now = time();
                                    $days_left = ceil(($deadline - $now) / (24 * 60 * 60));
                                    
                                    $priority_class = 'priority-normal';
                                    $deadline_class = 'deadline-normal';
                                    
                                    if ($days_left < 0) {
                                        $priority_class = 'priority-urgent';
                                        $deadline_class = 'deadline-urgent';
                                        $deadline_text = 'Overdue by ' . abs($days_left) . ' days';
                                    } elseif ($days_left == 0) {
                                        $priority_class = 'priority-urgent';
                                        $deadline_class = 'deadline-urgent';
                                        $deadline_text = 'Due Today';
                                    } elseif ($days_left <= 2) {
                                        $priority_class = 'priority-warning';
                                        $deadline_class = 'deadline-warning';
                                        $deadline_text = 'Due in ' . $days_left . ' day' . ($days_left > 1 ? 's' : '');
                                    } else {
                                        $deadline_text = 'Due in ' . $days_left . ' days';
                                    }
                                    ?>
                                    
                                    <div class="evaluation-card <?php echo $priority_class; ?>" 
                                         data-priority="<?php echo ($days_left <= 0) ? 'urgent' : (($days_left <= 2) ? 'warning' : 'normal'); ?>"
                                         data-due-date="<?php echo date('Y-m-d', $deadline); ?>">
                                        
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <!-- Submission Title -->
                                                <div class="d-flex align-items-center mb-3">
                                                    <h5 class="mb-0 me-3">
                                                        <i class="fas fa-file-alt me-2 text-primary"></i>
                                                        <?php echo htmlspecialchars($evaluation['submission_title']); ?>
                                                    </h5>
                                                    <span class="subject-tag">
                                                        <?php echo htmlspecialchars($evaluation['subject_code']); ?>
                                                    </span>
                                                </div>
                                                
                                                <!-- Subject and Student Info -->
                                                <div class="student-info">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p class="mb-1">
                                                                <i class="fas fa-book me-2"></i>
                                                                <strong>Subject:</strong> <?php echo htmlspecialchars($evaluation['subject_name']); ?>
                                                            </p>
                                                            <p class="mb-1">
                                                                <i class="fas fa-building me-2"></i>
                                                                <strong>Department:</strong> <?php echo htmlspecialchars($evaluation['department']); ?>
                                                            </p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p class="mb-1">
                                                                <i class="fas fa-user me-2"></i>
                                                                <strong>Student:</strong> <?php echo htmlspecialchars($evaluation['student_name']); ?>
                                                            </p>
                                                            <p class="mb-1">
                                                                <i class="fas fa-envelope me-2"></i>
                                                                <strong>Email:</strong> <?php echo htmlspecialchars($evaluation['student_email']); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Dates -->
                                                <div class="mt-3">
                                                    <small class="text-muted me-4">
                                                        <i class="fas fa-calendar-plus me-1"></i>
                                                        Submitted: <?php echo date('M j, Y g:i A', strtotime($evaluation['submission_date'])); ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-tie me-1"></i>
                                                        Assigned by: <?php echo htmlspecialchars($evaluation['moderator_name']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 text-end">
                                                <!-- Deadline -->
                                                <div class="mb-3">
                                                    <div class="<?php echo $deadline_class; ?>">
                                                        <i class="fas fa-clock me-2"></i>
                                                        <?php echo $deadline_text; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y g:i A', $deadline); ?>
                                                    </small>
                                                </div>
                                                
                                                <!-- Action Buttons -->
                                                <div class="d-grid gap-2">
                                                    <a href="evaluate.php?id=<?php echo $evaluation['submission_id']; ?>" 
                                                       class="btn btn-evaluate-now">
                                                        <i class="fas fa-edit me-2"></i>Start Evaluation
                                                    </a>
                                                    <a href="preview_submission.php?id=<?php echo $evaluation['submission_id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye me-2"></i>Preview Submission
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
    
    <script>
        function filterEvaluations(filter) {
            // Update active tab
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            const evaluationCards = document.querySelectorAll('.evaluation-card');
            const today = new Date().toISOString().split('T')[0];
            
            evaluationCards.forEach(card => {
                const priority = card.getAttribute('data-priority');
                const dueDate = card.getAttribute('data-due-date');
                let show = true;
                
                switch(filter) {
                    case 'urgent':
                        show = priority === 'urgent';
                        break;
                    case 'due-today':
                        show = dueDate === today;
                        break;
                    case 'all':
                    default:
                        show = true;
                        break;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        // Auto-refresh the page every 5 minutes to get latest assignments
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>