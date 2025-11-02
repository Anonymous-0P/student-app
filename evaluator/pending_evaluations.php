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
                        <h1><i class="fas fa-clipboard-list me-2"></i>Pending Evaluations</h1>
                        <p>Review and evaluate assigned submissions</p>
                    </div>
                </div>
            </div>


            <!-- Evaluations Container -->
            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h5><i class="fas fa-clipboard-list me-2"></i>Pending Evaluations (<?php echo count($pending_evaluations); ?>)</h5>
                        <?php if (empty($pending_evaluations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No pending evaluations. Great job!</p>
                            </div>
                        <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Submission</th>
                                            <th>Student</th>
                                            <th>Subject</th>
                                            <th>Deadline</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
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
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($evaluation['submission_title']); ?></strong>
                                            <br><small class="text-muted">Submitted: <?php echo date('M j, Y', strtotime($evaluation['submission_date'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($evaluation['student_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($evaluation['student_email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($evaluation['subject_code']); ?></span>
                                            <br><small><?php echo htmlspecialchars($evaluation['subject_name']); ?></small>
                                        </td>
                                        <td>
                                            <span class="<?php echo $deadline_class; ?>">
                                                <?php echo $deadline_text; ?>
                                            </span>
                                            <br><small class="text-muted"><?php echo date('M j, Y', $deadline); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="evaluate.php?id=<?php echo $evaluation['submission_id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i> Evaluate
                                                </a>
                                            </div>
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