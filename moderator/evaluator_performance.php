<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

include('../includes/header.php');
?>
<link rel="stylesheet" href="css/moderator-style.css">

<div class="moderator-content">
<?php

$moderator_id = $_SESSION['user_id'];

// Get evaluators under this moderator's supervision
$evaluators_query = "SELECT u.id, u.name, u.email, u.created_at, u.is_active,
    COUNT(DISTINCT s.id) as total_evaluations,
    COUNT(DISTINCT CASE WHEN s.evaluation_status = 'evaluated' THEN s.id END) as completed_evaluations,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' THEN s.marks_obtained END) as avg_marks_given,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' THEN s.max_marks END) as avg_max_marks,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' AND s.max_marks > 0 
        THEN (s.marks_obtained / s.max_marks) * 100 END) as avg_percentage_given,
    MAX(s.evaluated_at) as last_evaluation_date
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluator_id
    WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1
    GROUP BY u.id, u.name, u.email, u.created_at, u.is_active
    ORDER BY u.name";

$stmt = $conn->prepare($evaluators_query);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$evaluators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get overall performance metrics
$overall_stats_query = "SELECT 
    COUNT(DISTINCT s.evaluator_id) as active_evaluators,
    COUNT(DISTINCT s.id) as total_evaluations,
    COUNT(DISTINCT CASE WHEN s.evaluation_status = 'evaluated' THEN s.id END) as completed_evaluations,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' THEN s.marks_obtained END) as avg_marks,
    AVG(CASE WHEN s.evaluation_status = 'evaluated' AND s.max_marks > 0 
        THEN (s.marks_obtained/s.max_marks)*100 END) as avg_percentage,
    COUNT(DISTINCT CASE WHEN s.evaluation_status = 'evaluated' THEN s.id END) as total_answer_sheet_evaluations
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluator_id
    WHERE u.moderator_id = ? AND u.role = 'evaluator'";

$stmt = $conn->prepare($overall_stats_query);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$overall_stats = $stmt->get_result()->fetch_assoc();

// Get recent evaluation activities
$recent_activities_query = "SELECT 
    s.id as submission_id,
    s.marks_obtained as marks,
    s.max_marks,
    s.evaluated_at as completed_at,
    s.evaluation_status as status,
    u.name as evaluator_name,
    sub.code as subject_code,
    sub.name as subject_name,
    st.name as student_name
    FROM submissions s
    INNER JOIN users u ON s.evaluator_id = u.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    INNER JOIN users st ON s.student_id = st.id
    WHERE u.moderator_id = ? AND s.evaluation_status = 'evaluated'
    ORDER BY s.evaluated_at DESC
    LIMIT 20";

$stmt = $conn->prepare($recent_activities_query);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<style>
.evaluator-card {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: box-shadow 0.2s;
}

.evaluator-card:hover {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.performance-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.performance-badge.excellent {
    background: #dcfce7;
    color: #166534;
}

.performance-badge.good {
    background: #dbeafe;
    color: #1e40af;
}

.performance-badge.average {
    background: #fef3c7;
    color: #92400e;
}

.performance-badge.needs-improvement {
    background: #fee2e2;
    color: #991b1b;
}

.metric-box {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.25rem;
    text-align: center;
}

.metric-number {
    font-size: 1.875rem;
    font-weight: 600;
    color: var(--text-dark);
}

.metric-label {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.activity-item {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}
</style>

<div class="page-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Evaluator Performance</h1>
                <p>Track and analyze your evaluators' performance and evaluation quality</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<div class="container">
    <!-- Overall Performance Statistics -->
    <div class="card-stats">
        <div class="stat-box">
            <h4><?= $overall_stats['active_evaluators'] ?: 0 ?></h4>
            <small>Active Evaluators</small>
        </div>
        <div class="stat-box">
            <h4><?= $overall_stats['completed_evaluations'] ?: 0 ?></h4>
            <small>Completed Evaluations</small>
        </div>
        <div class="stat-box">
            <h4><?= number_format($overall_stats['avg_percentage'] ?: 0, 1) ?>%</h4>
            <small>Average Score Given</small>
        </div>
        <div class="stat-box">
            <h4><?= $overall_stats['total_evaluations'] ?: 0 ?></h4>
            <small>Total Evaluations</small>
        </div>
    </div>

    <!-- Evaluators Overview -->
    <div class="dashboard-card">
        <h5 class="mb-3">Evaluators Overview</h5>
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
                                    <div class="col-4">
                                        <div class="small text-muted">Total</div>
                                        <div class="fw-semibold"><?= $evaluator['total_evaluations'] ?></div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-muted">Completed</div>
                                        <div class="fw-semibold text-success"><?= $evaluator['completed_evaluations'] ?></div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-muted">Rate</div>
                                        <div class="fw-semibold"><?= number_format($completion_rate, 1) ?>%</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="small text-muted">Avg Score</div>
                                        <div class="fw-semibold"><?= number_format($avg_score, 1) ?>%</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="small text-muted">Last Evaluation</div>
                                        <div class="small">
                                            <?= $evaluator['last_evaluation_date'] ? 
                                                date('M j, Y', strtotime($evaluator['last_evaluation_date'])) : 'Never' ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-primary" onclick="viewEvaluatorDetails(<?= $evaluator['id'] ?>, '<?= htmlspecialchars($evaluator['name']) ?>')">
                                        View Details
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewEvaluatorEvaluations(<?= $evaluator['id'] ?>)">
                                        Evaluations
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-muted">
                <p>No evaluators are currently assigned under your supervision.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activities -->
    <div class="dashboard-card">
        <h5 class="mb-3">Recent Evaluation Activities</h5>
        <?php if (!empty($recent_activities)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Evaluator</th>
                            <th>Subject</th>
                            <th>Student</th>
                            <th>Marks</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activities as $activity): ?>
                        <tr>
                            <td><?= htmlspecialchars($activity['evaluator_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($activity['subject_code']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($activity['subject_name']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($activity['student_name']) ?><br>
                                <small class="text-muted">ID: <?= $activity['submission_id'] ?></small>
                            </td>
                            <td>
                                <strong><?= $activity['marks'] ?>/<?= $activity['max_marks'] ?></strong>
                                <span class="text-muted">(<?= number_format(($activity['marks']/$activity['max_marks'])*100, 1) ?>%)</span>
                            </td>
                            <td>
                                <small><?= date('M j, Y', strtotime($activity['completed_at'])) ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-muted">
                <p>No recent evaluation activities found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewEvaluatorDetails(evaluatorId, evaluatorName) {
    // Redirect to marks access page for this evaluator
    window.location.href = 'marks_access.php?evaluator_id=' + evaluatorId + '&evaluator_name=' + encodeURIComponent(evaluatorName);
}

function viewEvaluatorEvaluations(evaluatorId) {
    // Redirect to marks access page for this evaluator
    window.location.href = 'marks_access.php?evaluator_id=' + evaluatorId;
}
</script>

</div><!-- Close moderator-content -->

<?php include('../includes/footer.php'); ?>