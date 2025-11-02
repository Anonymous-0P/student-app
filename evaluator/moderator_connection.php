<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';
require_once '../includes/functions.php';

$evaluator_id = $_SESSION['user_id'];

// Get moderator information
$moderator_query = "SELECT u.id, u.name as moderator_name, u.email as moderator_email 
                   FROM users u 
                   WHERE u.id = (SELECT moderator_id FROM users WHERE id = ?)";
$moderator_stmt = $pdo->prepare($moderator_query);
$moderator_stmt->execute([$evaluator_id]);
$moderator_info = $moderator_stmt->fetch(PDO::FETCH_ASSOC);

// Get evaluation statistics for report to moderator
$stats_query = "SELECT 
    COUNT(*) as total_assignments,
    COUNT(CASE WHEN sa.status = 'pending' THEN 1 END) as pending_assignments,
    COUNT(CASE WHEN sa.status = 'accepted' THEN 1 END) as active_assignments,
    COUNT(CASE WHEN sa.status = 'completed' THEN 1 END) as completed_assignments,
    AVG(CASE WHEN s.marks_obtained IS NOT NULL AND s.max_marks > 0 
        THEN (s.marks_obtained / s.max_marks) * 100 END) as avg_percentage,
    COUNT(CASE WHEN s.evaluation_status = 'evaluated' AND s.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        THEN 1 END) as evaluations_this_week
    FROM submission_assignments sa 
    LEFT JOIN submissions s ON sa.submission_id = s.id
    WHERE sa.evaluator_id = ?";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$evaluator_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity for moderator visibility
$activity_query = "SELECT 
    s.id as submission_id,
    s.submission_title,
    s.status,
    s.created_at,
    s.updated_at,
    s.marks_obtained,
    s.max_marks,
    sa.status as assignment_status,
    sa.assigned_at,
    sa.responded_at,
    u.name as student_name,
    sub.name as subject_name,
    sub.code as subject_code
    FROM submission_assignments sa
    JOIN submissions s ON sa.submission_id = s.id
    JOIN users u ON s.student_id = u.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    WHERE sa.evaluator_id = ?
    ORDER BY s.updated_at DESC
    LIMIT 20";
$activity_stmt = $pdo->prepare($activity_query);
$activity_stmt->execute([$evaluator_id]);
$recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
.connection-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border: none;
    margin-bottom: 2rem;
}

.moderator-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 2rem;
    position: relative;
    overflow: hidden;
}

.moderator-info::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.stat-box {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 1rem;
}

.activity-item {
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.activity-item:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-pending { background-color: #ffeaa7; color: #d63031; }
.status-accepted { background-color: #74b9ff; color: white; }
.status-completed { background-color: #00b894; color: white; }
.status-evaluated { background-color: #6c5ce7; color: white; }

.performance-indicator {
    background: linear-gradient(135deg, #00b894, #00cec9);
    border-radius: 10px;
    padding: 1.5rem;
    color: white;
    text-align: center;
}
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="fas fa-link text-primary"></i> Moderator Connection
                    </h1>
                    <p class="text-muted mb-0">Your connection and performance overview for moderator visibility</p>
                </div>
                <div>
                    <a href="assignments.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Assignments
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Moderator Information -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="moderator-info">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <div class="mb-3">
                            <i class="fas fa-user-tie fa-4x"></i>
                        </div>
                        <h5>Your Moderator</h5>
                    </div>
                    <div class="col-md-6">
                        <?php if($moderator_info): ?>
                        <h3 class="mb-3"><?= htmlspecialchars($moderator_info['moderator_name']) ?></h3>
                        <p class="mb-2">
                            <i class="fas fa-envelope me-2"></i>
                            <?= htmlspecialchars($moderator_info['moderator_email']) ?>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-id-badge me-2"></i>
                            Moderator ID: <?= $moderator_info['id'] ?>
                        </p>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-info-circle me-2"></i>
                            Your assignments and performance are monitored by this moderator
                        </p>
                        <?php else: ?>
                        <h3 class="mb-3">No Moderator Assigned</h3>
                        <p class="mb-0">You are not currently assigned to any moderator. Please contact administration.</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4><?= $stats['total_assignments'] ?></h4>
                            <small>Total Assignments</small>
                        </div>
                        <div class="stat-box">
                            <h4><?= $stats['evaluations_this_week'] ?></h4>
                            <small>This Week</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="performance-indicator">
                <h3 class="mb-3">
                    <i class="fas fa-chart-line me-2"></i>
                    Performance Score
                </h3>
                <div class="mb-3">
                    <h1 class="display-4">
                        <?= $stats['avg_percentage'] ? number_format($stats['avg_percentage'], 1) . '%' : 'N/A' ?>
                    </h1>
                    <p class="mb-0">Average Marking</p>
                </div>
                <div class="row text-center">
                    <div class="col-6">
                        <strong><?= $stats['completed_assignments'] ?></strong>
                        <br><small>Completed</small>
                    </div>
                    <div class="col-6">
                        <strong><?= $stats['pending_assignments'] ?></strong>
                        <br><small>Pending</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Status Dashboard -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="connection-card text-center">
                <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                <h4 class="text-warning"><?= $stats['pending_assignments'] ?></h4>
                <p class="mb-0">Pending Assignments</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="connection-card text-center">
                <i class="fas fa-play fa-2x text-info mb-3"></i>
                <h4 class="text-info"><?= $stats['active_assignments'] ?></h4>
                <p class="mb-0">Active Evaluations</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="connection-card text-center">
                <i class="fas fa-check fa-2x text-success mb-3"></i>
                <h4 class="text-success"><?= $stats['completed_assignments'] ?></h4>
                <p class="mb-0">Completed</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="connection-card text-center">
                <i class="fas fa-calendar-week fa-2x text-primary mb-3"></i>
                <h4 class="text-primary"><?= $stats['evaluations_this_week'] ?></h4>
                <p class="mb-0">This Week</p>
            </div>
        </div>
    </div>

    <!-- Recent Activity Log -->
    <div class="row">
        <div class="col-12">
            <div class="connection-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-info"></i> Recent Activity Log
                        <small class="text-muted">(Visible to your moderator)</small>
                    </h5>
                    <span class="badge bg-primary"><?= count($recent_activities) ?> activities</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Submission</th>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Marks</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_activities)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p class="mb-0">No recent activities</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($recent_activities as $activity): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($activity['submission_title'] ?: 'Submission #' . $activity['submission_id']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($activity['student_name']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($activity['subject_code']) ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($activity['subject_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $activity['assignment_status'] ?>">
                                            <?= ucfirst($activity['assignment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($activity['marks_obtained'] !== null): ?>
                                            <strong><?= $activity['marks_obtained'] ?>/<?= $activity['max_marks'] ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?= number_format(($activity['marks_obtained'] / $activity['max_marks']) * 100, 1) ?>%
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Not evaluated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M j, Y', strtotime($activity['updated_at'])) ?>
                                            <br>
                                            <?= date('g:i A', strtotime($activity['updated_at'])) ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 30 seconds to keep data current
setInterval(function() {
    location.reload();
}, 30000);

// Add real-time status indicators
document.addEventListener('DOMContentLoaded', function() {
    // Add pulse animation to active status
    const activeElements = document.querySelectorAll('.status-accepted');
    activeElements.forEach(element => {
        element.style.animation = 'pulse 2s infinite';
    });
});
</script>

<?php include('../includes/footer.php'); ?>