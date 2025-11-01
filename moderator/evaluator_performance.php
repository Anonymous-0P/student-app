<?php
require_once('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

$moderator_id = $_SESSION['user_id'];

// Get evaluators under this moderator's supervision
$evaluators_query = "SELECT u.id, u.name, u.email, u.created_at, u.last_login, u.is_active,
    COUNT(DISTINCT e.id) as total_evaluations,
    COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.id END) as completed_evaluations,
    AVG(CASE WHEN e.status = 'completed' THEN e.marks END) as avg_marks_given,
    AVG(CASE WHEN e.status = 'completed' THEN e.max_marks END) as avg_max_marks,
    COUNT(DISTINCT ef.id) as answer_sheet_evaluations,
    AVG(ef.percentage) as avg_percentage_given,
    AVG(ef.evaluation_time_minutes) as avg_evaluation_time,
    MAX(e.completed_at) as last_evaluation_date
    FROM users u
    LEFT JOIN evaluations e ON u.id = e.evaluator_id
    LEFT JOIN evaluation_feedback ef ON u.id = ef.evaluator_id
    WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1
    GROUP BY u.id, u.name, u.email, u.created_at, u.last_login, u.is_active
    ORDER BY u.name";

$stmt = $conn->prepare($evaluators_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$evaluators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get overall performance metrics
$overall_stats_query = "SELECT 
    COUNT(DISTINCT e.evaluator_id) as active_evaluators,
    COUNT(DISTINCT e.id) as total_evaluations,
    COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.id END) as completed_evaluations,
    AVG(CASE WHEN e.status = 'completed' THEN e.marks END) as avg_marks,
    AVG(CASE WHEN e.status = 'completed' AND e.max_marks > 0 THEN (e.marks/e.max_marks)*100 END) as avg_percentage,
    COUNT(DISTINCT ef.id) as total_answer_sheet_evaluations,
    AVG(ef.evaluation_time_minutes) as avg_evaluation_time
    FROM users u
    LEFT JOIN evaluations e ON u.id = e.evaluator_id
    LEFT JOIN evaluation_feedback ef ON u.id = ef.evaluator_id
    WHERE u.moderator_id = ? AND u.role = 'evaluator'";

$stmt = $conn->prepare($overall_stats_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$overall_stats = $stmt->get_result()->fetch_assoc();

// Get recent evaluation activities
$recent_activities_query = "SELECT 
    e.id, e.marks, e.max_marks, e.completed_at, e.status,
    u.name as evaluator_name,
    sub.code as subject_code, sub.name as subject_name,
    st.name as student_name,
    s.id as submission_id
    FROM evaluations e
    INNER JOIN users u ON e.evaluator_id = u.id
    INNER JOIN submissions s ON e.submission_id = s.id
    INNER JOIN subjects sub ON s.subject_id = sub.id
    INNER JOIN users st ON s.student_id = st.id
    WHERE u.moderator_id = ? AND e.status = 'completed'
    ORDER BY e.completed_at DESC
    LIMIT 20";

$stmt = $conn->prepare($recent_activities_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<style>
.evaluator-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: none;
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
}

.evaluator-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.performance-badge {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.performance-badge.excellent {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.performance-badge.good {
    background: linear-gradient(135deg, #17a2b8, #6f42c1);
}

.performance-badge.average {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
}

.performance-badge.needs-improvement {
    background: linear-gradient(135deg, #dc3545, #e83e8c);
}

.metric-box {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
    border-left: 4px solid #667eea;
}

.metric-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #667eea;
}

.metric-label {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 0.5rem;
}

.activity-item {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    border-left: 4px solid #28a745;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2"><i class="fas fa-users-cog text-primary"></i> Evaluator Performance Monitor</h1>
                    <p class="text-muted mb-0">Track and analyze your evaluators' performance, marking patterns, and evaluation quality</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Performance Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="metric-box">
                <div class="metric-number"><?= $overall_stats['active_evaluators'] ?: 0 ?></div>
                <div class="metric-label">Active Evaluators</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-box" style="border-left-color: #28a745;">
                <div class="metric-number" style="color: #28a745;"><?= $overall_stats['completed_evaluations'] ?: 0 ?></div>
                <div class="metric-label">Completed Evaluations</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-box" style="border-left-color: #17a2b8;">
                <div class="metric-number" style="color: #17a2b8;"><?= number_format($overall_stats['avg_percentage'] ?: 0, 1) ?>%</div>
                <div class="metric-label">Average Score Given</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-box" style="border-left-color: #ffc107;">
                <div class="metric-number" style="color: #ffc107;"><?= round($overall_stats['avg_evaluation_time'] ?: 0) ?> min</div>
                <div class="metric-label">Avg Evaluation Time</div>
            </div>
        </div>
    </div>

    <!-- Evaluators Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3"><i class="fas fa-users text-primary"></i> Evaluators Overview</h4>
            <?php if (!empty($evaluators)): ?>
                <div class="row">
                    <?php foreach ($evaluators as $evaluator): ?>
                        <?php
                        // Calculate performance rating
                        $completion_rate = $evaluator['total_evaluations'] > 0 ? 
                            ($evaluator['completed_evaluations'] / $evaluator['total_evaluations']) * 100 : 0;
                        $avg_score = $evaluator['avg_max_marks'] > 0 ? 
                            ($evaluator['avg_marks_given'] / $evaluator['avg_max_marks']) * 100 : 0;
                        
                        $performance_class = 'excellent';
                        $performance_text = 'Excellent';
                        if ($completion_rate < 70 || $avg_score < 50) {
                            $performance_class = 'needs-improvement';
                            $performance_text = 'Needs Improvement';
                        } elseif ($completion_rate < 85 || $avg_score < 70) {
                            $performance_class = 'average';
                            $performance_text = 'Average';
                        } elseif ($completion_rate < 95 || $avg_score < 85) {
                            $performance_class = 'good';
                            $performance_text = 'Good';
                        }
                        ?>
                        <div class="col-lg-6">
                            <div class="evaluator-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($evaluator['name']) ?></h5>
                                        <p class="text-muted mb-0"><?= htmlspecialchars($evaluator['email']) ?></p>
                                    </div>
                                    <span class="performance-badge <?= $performance_class ?>"><?= $performance_text ?></span>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-4 text-center">
                                        <div class="fw-bold text-primary"><?= $evaluator['total_evaluations'] ?></div>
                                        <small class="text-muted">Total</small>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="fw-bold text-success"><?= $evaluator['completed_evaluations'] ?></div>
                                        <small class="text-muted">Completed</small>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="fw-bold text-info"><?= number_format($completion_rate, 1) ?>%</div>
                                        <small class="text-muted">Rate</small>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <strong>Avg Score Given:</strong><br>
                                        <span class="text-primary"><?= number_format($avg_score, 1) ?>%</span>
                                    </div>
                                    <div class="col-6">
                                        <strong>Avg Time:</strong><br>
                                        <span class="text-info"><?= round($evaluator['avg_evaluation_time'] ?: 0) ?> min</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Last Evaluation:</strong> 
                                    <span class="text-muted">
                                        <?= $evaluator['last_evaluation_date'] ? 
                                            date('M j, Y', strtotime($evaluator['last_evaluation_date'])) : 'Never' ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary btn-sm flex-fill" onclick="viewEvaluatorDetails(<?= $evaluator['id'] ?>, '<?= htmlspecialchars($evaluator['name']) ?>')">
                                        <i class="fas fa-chart-line"></i> View Details
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="viewEvaluatorEvaluations(<?= $evaluator['id'] ?>)">
                                        <i class="fas fa-list"></i> Evaluations
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="evaluator-card text-center">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Evaluators Found</h5>
                    <p class="text-muted">No evaluators are currently assigned under your supervision.</p>
                    <p class="text-info">Your moderator ID: <?= $moderator_id ?></p>
                    <button class="btn btn-primary" onclick="location.href='assign_evaluator.php'">
                        <i class="fas fa-user-plus"></i> Assign Evaluators
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-12">
            <h4 class="mb-3"><i class="fas fa-history text-primary"></i> Recent Evaluation Activities</h4>
            <?php if (!empty($recent_activities)): ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <strong><?= htmlspecialchars($activity['evaluator_name']) ?></strong><br>
                                <small class="text-muted"><?= date('M j, Y g:i A', strtotime($activity['completed_at'])) ?></small>
                            </div>
                            <div class="col-md-3">
                                <strong><?= htmlspecialchars($activity['subject_code']) ?></strong><br>
                                <small><?= htmlspecialchars($activity['subject_name']) ?></small>
                            </div>
                            <div class="col-md-3">
                                <strong>Student:</strong> <?= htmlspecialchars($activity['student_name']) ?><br>
                                <small>Submission ID: <?= $activity['submission_id'] ?></small>
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="fw-bold text-primary">
                                    <?= $activity['marks'] ?>/<?= $activity['max_marks'] ?>
                                </div>
                                <div class="text-muted">
                                    <?= number_format(($activity['marks']/$activity['max_marks'])*100, 1) ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="evaluator-card text-center">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Recent Activities</h5>
                    <p class="text-muted">No recent evaluation activities found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewEvaluatorDetails(evaluatorId, evaluatorName) {
    alert('Detailed view for ' + evaluatorName + ' (ID: ' + evaluatorId + ') - Feature coming soon!');
}

function viewEvaluatorEvaluations(evaluatorId) {
    alert('Evaluations list for evaluator ID: ' + evaluatorId + ' - Feature coming soon!');
}
</script>

<?php include('../includes/footer.php'); ?>