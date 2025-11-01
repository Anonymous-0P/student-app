<?php
// Include config first to set headers before any output
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

// Include header after authentication check
include('../includes/header.php');

$moderator_id = $_SESSION['user_id'];
$success = $error = '';

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['assign_submission'])) {
        $submission_id = (int)$_POST['submission_id'];
        $evaluator_id = (int)$_POST['evaluator_id'];
        $deadline = $_POST['deadline'];
        
        // Update submission with evaluator assignment
        $stmt = $conn->prepare("UPDATE submissions SET evaluator_id = ?, status = 'assigned', moderator_id = ? WHERE id = ?");
        $stmt->bind_param("iii", $evaluator_id, $moderator_id, $submission_id);
        
        if($stmt->execute()) {
            // Create evaluator assignment record
            $stmt2 = $conn->prepare("INSERT INTO evaluator_assignments (evaluator_id, moderator_id, submission_id, evaluation_deadline, status) VALUES (?, ?, ?, ?, 'assigned')");
            $stmt2->bind_param("iiss", $evaluator_id, $moderator_id, $submission_id, $deadline);
            $stmt2->execute();
            
            $success = "Submission assigned successfully to evaluator.";
        } else {
            $error = "Failed to assign submission.";
        }
    }
    
    if(isset($_POST['auto_assign'])) {
        $subject_id = (int)$_POST['subject_id'];
        
        // Get all pending submissions for this subject
        $pending_query = "SELECT s.id FROM submissions s 
                         JOIN assignments a ON s.assignment_id = a.id 
                         WHERE a.subject_id = ? AND s.status = 'pending' AND s.moderator_id = ?";
        $stmt = $conn->prepare($pending_query);
        $stmt->bind_param("ii", $subject_id, $moderator_id);
        $stmt->execute();
        $pending_submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get available evaluators for this subject
        $evaluators_query = "SELECT u.id, COUNT(ea.id) as current_workload 
                            FROM users u 
                            LEFT JOIN evaluator_assignments ea ON u.id = ea.evaluator_id 
                            WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1 
                            GROUP BY u.id 
                            ORDER BY current_workload ASC";
        $stmt = $conn->prepare($evaluators_query);
        $stmt->bind_param("i", $moderator_id);
        $stmt->execute();
        $evaluators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $assigned_count = 0;
        $evaluator_index = 0;
        
        foreach($pending_submissions as $submission) {
            if(empty($evaluators)) break;
            
            $evaluator_id = $evaluators[$evaluator_index]['id'];
            $deadline = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            // Assign submission
            $stmt = $conn->prepare("UPDATE submissions SET evaluator_id = ?, status = 'assigned', moderator_id = ? WHERE id = ?");
            $stmt->bind_param("iii", $evaluator_id, $moderator_id, $submission['id']);
            
            if($stmt->execute()) {
                // Create assignment record
                $stmt2 = $conn->prepare("INSERT INTO evaluator_assignments (evaluator_id, moderator_id, submission_id, evaluation_deadline, status) VALUES (?, ?, ?, ?, 'assigned')");
                $stmt2->bind_param("iiss", $evaluator_id, $moderator_id, $submission['id'], $deadline);
                $stmt2->execute();
                
                $assigned_count++;
            }
            
            // Round-robin assignment
            $evaluator_index = ($evaluator_index + 1) % count($evaluators);
        }
        
        $success = "Auto-assigned {$assigned_count} submissions successfully.";
    }
}

// Get available evaluators
$evaluators_query = "SELECT u.id, u.name, u.email, COUNT(ea.id) as current_assignments,
                     AVG(CASE WHEN mh.action_type = 'evaluated' THEN 1 ELSE 0 END) as efficiency_score
                     FROM users u 
                     LEFT JOIN evaluator_assignments ea ON u.id = ea.evaluator_id AND ea.status IN ('assigned', 'in_progress')
                     LEFT JOIN marks_history mh ON u.id = mh.evaluator_id AND mh.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1 
                     GROUP BY u.id, u.name, u.email
                     ORDER BY current_assignments ASC, u.name";
$stmt = $conn->prepare($evaluators_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$evaluators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unassigned submissions
$unassigned_query = "SELECT s.id, s.submission_title, s.created_at, s.max_marks,
                     u.name as student_name, u.roll_no,
                     subj.name as subject_name, subj.code as subject_code
                     FROM submissions s
                     JOIN users u ON s.student_id = u.id
                     LEFT JOIN assignments a ON s.assignment_id = a.id
                     LEFT JOIN subjects subj ON a.subject_id = subj.id
                     WHERE s.status = 'pending' AND s.moderator_id IS NULL
                     ORDER BY s.created_at ASC";
$stmt = $conn->prepare($unassigned_query);
$stmt->execute();
$unassigned_submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get subjects for auto-assignment
$subjects_query = "SELECT s.id, s.name, s.code, 
                   COUNT(sub.id) as pending_count
                   FROM subjects s
                   JOIN moderator_subjects ms ON s.id = ms.subject_id
                   LEFT JOIN assignments a ON s.id = a.subject_id
                   LEFT JOIN submissions sub ON a.id = sub.assignment_id AND sub.status = 'pending'
                   WHERE ms.moderator_id = ? AND ms.is_active = 1
                   GROUP BY s.id, s.name, s.code
                   HAVING pending_count > 0
                   ORDER BY s.name";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$subjects_with_pending = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<style>
.assignment-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: none;
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
}

.assignment-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.evaluator-card {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.evaluator-card:hover {
    border-color: #667eea;
    background-color: #f8f9ff;
}

.workload-indicator {
    width: 100%;
    height: 6px;
    background-color: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.workload-bar {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.workload-low { background-color: #00b894; }
.workload-medium { background-color: #fdcb6e; }
.workload-high { background-color: #e17055; }

.auto-assign-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
}

.submission-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.submission-item:hover {
    background: #e3f2fd;
    border-color: #2196f3;
}
</style>

<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-0">
                    <i class="fas fa-user-plus"></i> Evaluator Assignment
                </h1>
                <p class="mb-0 mt-2 opacity-75">Assign submissions to evaluators and manage workload distribution</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="dashboard.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Auto Assignment Section -->
    <div class="auto-assign-section">
        <h3 class="mb-3">
            <i class="fas fa-magic"></i> Auto Assignment
        </h3>
        <p class="mb-4">Automatically distribute submissions evenly among available evaluators</p>
        
        <form method="POST" class="row align-items-end">
            <div class="col-md-8">
                <label class="form-label">Select Subject for Auto Assignment</label>
                <select name="subject_id" class="form-select" required>
                    <option value="">Choose a subject...</option>
                    <?php foreach($subjects_with_pending as $subject): ?>
                    <option value="<?= $subject['id'] ?>">
                        <?= htmlspecialchars($subject['name']) ?> (<?= $subject['code'] ?>) 
                        - <?= $subject['pending_count'] ?> pending submissions
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" name="auto_assign" class="btn btn-light btn-lg w-100">
                    <i class="fas fa-bolt"></i> Auto Assign
                </button>
            </div>
        </form>
    </div>

    <div class="row">
        <!-- Evaluators Panel -->
        <div class="col-lg-4">
            <div class="assignment-card">
                <h5 class="mb-3">
                    <i class="fas fa-users text-primary"></i> Available Evaluators
                </h5>
                
                <?php if(empty($evaluators)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-user-slash fa-3x mb-3"></i>
                        <p>No evaluators available</p>
                        <a href="#" class="btn btn-outline-primary btn-sm">Add Evaluator</a>
                    </div>
                <?php else: ?>
                    <?php foreach($evaluators as $evaluator): ?>
                    <div class="evaluator-card" data-evaluator-id="<?= $evaluator['id'] ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong><?= htmlspecialchars($evaluator['name']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($evaluator['email']) ?></small>
                            </div>
                            <span class="badge bg-<?= $evaluator['current_assignments'] <= 5 ? 'success' : ($evaluator['current_assignments'] <= 10 ? 'warning' : 'danger') ?>">
                                <?= $evaluator['current_assignments'] ?> assignments
                            </span>
                        </div>
                        
                        <div class="workload-indicator mb-2">
                            <div class="workload-bar workload-<?= $evaluator['current_assignments'] <= 5 ? 'low' : ($evaluator['current_assignments'] <= 10 ? 'medium' : 'high') ?>" 
                                 style="width: <?= min(($evaluator['current_assignments'] / 15) * 100, 100) ?>%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between text-small">
                            <span class="text-muted">Workload: <?= $evaluator['current_assignments'] ?>/15</span>
                            <span class="text-muted">Efficiency: <?= number_format(($evaluator['efficiency_score'] ?? 0) * 100, 1) ?>%</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Unassigned Submissions -->
        <div class="col-lg-8">
            <div class="assignment-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt text-warning"></i> Unassigned Submissions
                    </h5>
                    <span class="badge bg-warning"><?= count($unassigned_submissions) ?> pending</span>
                </div>
                
                <?php if(empty($unassigned_submissions)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                        <p>All submissions have been assigned!</p>
                    </div>
                <?php else: ?>
                    <div style="max-height: 600px; overflow-y: auto;">
                        <?php foreach($unassigned_submissions as $submission): ?>
                        <div class="submission-item">
                            <form method="POST" class="row align-items-center">
                                <input type="hidden" name="submission_id" value="<?= $submission['id'] ?>">
                                
                                <div class="col-md-5">
                                    <strong><?= htmlspecialchars($submission['submission_title'] ?: 'Submission #' . $submission['id']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        Student: <?= htmlspecialchars($submission['student_name']) ?>
                                        <?php if($submission['roll_no']): ?>
                                        (<?= htmlspecialchars($submission['roll_no']) ?>)
                                        <?php endif; ?>
                                    </small>
                                    <br>
                                    <small class="text-info">
                                        Subject: <?= htmlspecialchars($submission['subject_name'] ?? 'N/A') ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> <?= date('M j, Y g:i A', strtotime($submission['created_at'])) ?>
                                    </small>
                                </div>
                                
                                <div class="col-md-3">
                                    <select name="evaluator_id" class="form-select form-select-sm" required>
                                        <option value="">Select Evaluator</option>
                                        <?php foreach($evaluators as $evaluator): ?>
                                        <option value="<?= $evaluator['id'] ?>">
                                            <?= htmlspecialchars($evaluator['name']) ?> (<?= $evaluator['current_assignments'] ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <input type="datetime-local" name="deadline" class="form-control form-control-sm" 
                                           value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>" required>
                                </div>
                                
                                <div class="col-md-2">
                                    <button type="submit" name="assign_submission" class="btn btn-primary btn-sm w-100">
                                        <i class="fas fa-check"></i> Assign
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Assignment Statistics -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="assignment-card">
                <h5 class="mb-3">
                    <i class="fas fa-chart-pie text-info"></i> Assignment Statistics
                </h5>
                
                <div class="row text-center">
                    <?php 
                    $total_evaluators = count($evaluators);
                    $overloaded = array_filter($evaluators, function($e) { return $e['current_assignments'] > 10; });
                    $balanced = array_filter($evaluators, function($e) { return $e['current_assignments'] >= 5 && $e['current_assignments'] <= 10; });
                    $underutilized = array_filter($evaluators, function($e) { return $e['current_assignments'] < 5; });
                    ?>
                    
                    <div class="col-md-3">
                        <h4 class="text-success"><?= count($underutilized) ?></h4>
                        <small class="text-muted">Available Evaluators</small>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-warning"><?= count($balanced) ?></h4>
                        <small class="text-muted">Balanced Workload</small>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-danger"><?= count($overloaded) ?></h4>
                        <small class="text-muted">Overloaded</small>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-primary"><?= count($unassigned_submissions) ?></h4>
                        <small class="text-muted">Pending Assignment</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time workload visualization
function updateWorkloadIndicators() {
    const evaluators = document.querySelectorAll('.evaluator-card');
    evaluators.forEach(card => {
        const badge = card.querySelector('.badge');
        const workloadBar = card.querySelector('.workload-bar');
        const assignments = parseInt(badge.textContent);
        
        // Update bar width
        const percentage = Math.min((assignments / 15) * 100, 100);
        workloadBar.style.width = percentage + '%';
        
        // Update colors based on workload
        if (assignments <= 5) {
            badge.className = 'badge bg-success';
            workloadBar.className = 'workload-bar workload-low';
        } else if (assignments <= 10) {
            badge.className = 'badge bg-warning';
            workloadBar.className = 'workload-bar workload-medium';
        } else {
            badge.className = 'badge bg-danger';
            workloadBar.className = 'workload-bar workload-high';
        }
    });
}

// Auto-refresh every 2 minutes
setInterval(function() {
    updateWorkloadIndicators();
}, 120000);

// Enhanced form interactions
document.addEventListener('DOMContentLoaded', function() {
    // Highlight evaluator when selected
    const selects = document.querySelectorAll('select[name="evaluator_id"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            // Remove previous highlights
            document.querySelectorAll('.evaluator-card').forEach(card => {
                card.classList.remove('border-primary', 'bg-primary', 'text-white');
            });
            
            // Highlight selected evaluator
            if (this.value) {
                const evaluatorCard = document.querySelector(`[data-evaluator-id="${this.value}"]`);
                if (evaluatorCard) {
                    evaluatorCard.classList.add('border-primary');
                    setTimeout(() => {
                        evaluatorCard.classList.remove('border-primary');
                    }, 2000);
                }
            }
        });
    });
    
    // Smooth animations
    const cards = document.querySelectorAll('.assignment-card, .evaluator-card, .submission-item');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.05) + 's';
        card.classList.add('fadeInUp');
    });
});
</script>

<?php include('../includes/footer.php'); ?>