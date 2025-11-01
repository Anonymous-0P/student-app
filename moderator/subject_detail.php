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
$subject_id = (int)($_GET['id'] ?? 0);

if(!$subject_id) {
    header("Location: dashboard.php");
    exit();
}

// Get subject details
$subject_query = "SELECT s.*, ms.assigned_at
                  FROM subjects s
                  JOIN moderator_subjects ms ON s.id = ms.subject_id
                  WHERE s.id = ? AND ms.moderator_id = ? AND ms.is_active = 1";
$stmt = $conn->prepare($subject_query);
$stmt->bind_param("ii", $subject_id, $moderator_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();

if(!$subject) {
    header("Location: dashboard.php");
    exit();
}

// Get submissions for this subject
$submissions_query = "SELECT 
    s.id, s.submission_title, s.status, s.marks_obtained, s.max_marks,
    s.created_at, s.evaluated_at,
    u.name as student_name, u.roll_no,
    ev.name as evaluator_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON s.student_id = u.id
    LEFT JOIN users ev ON s.evaluator_id = ev.id
    WHERE a.subject_id = ? AND s.moderator_id = ?
    ORDER BY s.created_at DESC";
$stmt = $conn->prepare($submissions_query);
$stmt->bind_param("ii", $subject_id, $moderator_id);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get evaluators assigned to this subject
$evaluators_query = "SELECT DISTINCT u.id, u.name, u.email,
    COUNT(s.id) as assigned_count,
    AVG(s.marks_obtained / s.max_marks * 100) as avg_percentage
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluator_id
    LEFT JOIN assignments a ON s.assignment_id = a.id AND a.subject_id = ?
    WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1
    GROUP BY u.id, u.name, u.email
    ORDER BY assigned_count DESC, u.name";
$stmt = $conn->prepare($evaluators_query);
$stmt->bind_param("ii", $subject_id, $moderator_id);
$stmt->execute();
$evaluators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$stats = [
    'total_submissions' => count($submissions),
    'pending' => 0,
    'evaluating' => 0,
    'completed' => 0,
    'avg_score' => 0
];

$total_marks = 0;
$total_obtained = 0;
$score_count = 0;

foreach($submissions as $submission) {
    switch($submission['status']) {
        case 'pending':
            $stats['pending']++;
            break;
        case 'assigned':
        case 'evaluating':
            $stats['evaluating']++;
            break;
        case 'evaluated':
        case 'approved':
            $stats['completed']++;
            if($submission['marks_obtained'] !== null) {
                $total_obtained += $submission['marks_obtained'];
                $total_marks += $submission['max_marks'];
                $score_count++;
            }
            break;
    }
}

if($score_count > 0) {
    $stats['avg_score'] = ($total_obtained / $total_marks) * 100;
}
?>

<style>
.subject-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 15px;
}

.detail-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: none;
    margin-bottom: 1.5rem;
}

.stat-box {
    text-align: center;
    padding: 1.5rem;
    border-radius: 12px;
    color: white;
}

.submission-item {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.submission-item:hover {
    border-color: #667eea;
    background-color: #f8f9ff;
}

.evaluator-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.evaluator-card:hover {
    border-color: #667eea;
    background-color: #f8f9ff;
}

.progress-ring {
    width: 80px;
    height: 80px;
}

.progress-ring-circle {
    fill: transparent;
    stroke-width: 8;
    stroke-dasharray: 251;
    stroke-dashoffset: 251;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.5s ease;
}
</style>

<div class="container">
    <!-- Subject Header -->
    <div class="subject-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0"><?= htmlspecialchars($subject['name']) ?></h1>
                    <p class="mb-1 opacity-75">Subject Code: <?= htmlspecialchars($subject['code']) ?></p>
                    <p class="mb-0 opacity-75">
                        Department: <?= htmlspecialchars($subject['department']) ?> | 
                        Year: <?= $subject['year'] ?> | 
                        Semester: <?= $subject['semester'] ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="dashboard.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                    <a href="assign_evaluator.php?subject_id=<?= $subject_id ?>" class="btn btn-light">
                        <i class="fas fa-user-plus"></i> Assign Evaluators
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h3><?= $stats['total_submissions'] ?></h3>
                <small>Total Submissions</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%);">
                <h3><?= $stats['pending'] ?></h3>
                <small>Pending Assignment</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #fdcb6e 0%, #f39c12 100%);">
                <h3><?= $stats['evaluating'] ?></h3>
                <small>Under Evaluation</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);">
                <h3><?= number_format($stats['avg_score'], 1) ?>%</h3>
                <small>Average Score</small>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Submissions List -->
        <div class="col-lg-8">
            <div class="detail-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt text-primary"></i> Recent Submissions
                    </h5>
                    <span class="badge bg-primary"><?= count($submissions) ?> submissions</span>
                </div>

                <?php if(empty($submissions)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No submissions found for this subject</p>
                    </div>
                <?php else: ?>
                    <div style="max-height: 500px; overflow-y: auto;">
                        <?php foreach(array_slice($submissions, 0, 10) as $submission): ?>
                        <div class="submission-item">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="mb-1"><?= htmlspecialchars($submission['submission_title'] ?: 'Submission #' . $submission['id']) ?></h6>
                                    <small class="text-muted">
                                        Student: <?= htmlspecialchars($submission['student_name']) ?>
                                        <?php if($submission['roll_no']): ?>
                                        (<?= htmlspecialchars($submission['roll_no']) ?>)
                                        <?php endif; ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        Submitted: <?= date('M j, Y g:i A', strtotime($submission['created_at'])) ?>
                                    </small>
                                </div>
                                
                                <div class="col-md-3">
                                    <?php if($submission['evaluator_name']): ?>
                                        <small class="text-info d-block">Evaluator:</small>
                                        <strong><?= htmlspecialchars($submission['evaluator_name']) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-2">
                                    <?php if($submission['marks_obtained'] !== null): ?>
                                        <div class="text-center">
                                            <strong><?= $submission['marks_obtained'] ?>/<?= $submission['max_marks'] ?></strong>
                                            <br>
                                            <small class="text-muted"><?= number_format(($submission['marks_obtained']/$submission['max_marks'])*100, 1) ?>%</small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not graded</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-1">
                                    <span class="badge bg-<?= $submission['status'] === 'pending' ? 'warning' : 
                                                               ($submission['status'] === 'assigned' || $submission['status'] === 'evaluating' ? 'info' :
                                                               ($submission['status'] === 'approved' ? 'success' : 'secondary')) ?>">
                                        <?= ucfirst($submission['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if(count($submissions) > 10): ?>
                        <div class="text-center mt-3">
                            <a href="submissions.php?subject_id=<?= $subject_id ?>" class="btn btn-outline-primary">
                                View All <?= count($submissions) ?> Submissions
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Evaluators Panel -->
        <div class="col-lg-4">
            <div class="detail-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-users text-success"></i> Assigned Evaluators
                    </h5>
                    <span class="badge bg-success"><?= count($evaluators) ?> evaluators</span>
                </div>

                <?php if(empty($evaluators)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-user-slash fa-2x mb-3"></i>
                        <p>No evaluators assigned</p>
                        <a href="assign_evaluator.php?subject_id=<?= $subject_id ?>" class="btn btn-outline-success btn-sm">
                            Assign Evaluators
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach($evaluators as $evaluator): ?>
                    <div class="evaluator-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= htmlspecialchars($evaluator['name']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($evaluator['email']) ?></small>
                                <br>
                                <small class="text-info">
                                    <?= $evaluator['assigned_count'] ?> submissions assigned
                                </small>
                            </div>
                            <div class="text-end">
                                <?php if($evaluator['avg_percentage']): ?>
                                    <div class="text-center">
                                        <strong><?= number_format($evaluator['avg_percentage'], 1) ?>%</strong>
                                        <br>
                                        <small class="text-muted">Avg. Score</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Performance Overview -->
            <div class="detail-card">
                <h5 class="mb-3">
                    <i class="fas fa-chart-line text-warning"></i> Performance Overview
                </h5>
                
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-success"><?= $stats['completed'] ?></h4>
                        <small class="text-muted">Completed</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-warning"><?= $stats['pending'] + $stats['evaluating'] ?></h4>
                        <small class="text-muted">Pending</small>
                    </div>
                </div>
                
                <div class="mt-3">
                    <?php 
                    $completion_rate = $stats['total_submissions'] > 0 ? 
                                     ($stats['completed'] / $stats['total_submissions']) * 100 : 0;
                    ?>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?= $completion_rate ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Completion Rate</span>
                        <span><?= number_format($completion_rate, 1) ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="detail-card">
                <h5 class="mb-3">
                    <i class="fas fa-bolt text-info"></i> Quick Actions
                </h5>
                
                <div class="d-grid gap-2">
                    <a href="assign_evaluator.php?subject_id=<?= $subject_id ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-user-plus"></i> Manage Evaluators
                    </a>
                    <a href="submissions.php?subject_id=<?= $subject_id ?>&status=pending" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-clock"></i> Review Pending (<?= $stats['pending'] ?>)
                    </a>
                    <a href="marks_overview.php?subject=<?= $subject_id ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-check-double"></i> Check Marks
                    </a>
                    <a href="reports.php?type=subject_performance&subject_id=<?= $subject_id ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-chart-bar"></i> Subject Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 2 minutes
setTimeout(function() {
    location.reload();
}, 120000);

// Add smooth animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.detail-card, .submission-item, .evaluator-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
        card.classList.add('fadeInUp');
    });
});
</script>

<?php include('../includes/footer.php'); ?>