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
<?php

// Get moderator info
$moderator_id = $_SESSION['user_id'];
$moderator_name = $_SESSION['name'];

// Dashboard statistics
$stats = [];

// Get assigned subjects count
$subjects_query = "SELECT COUNT(DISTINCT ms.subject_id) as subject_count 
                   FROM moderator_subjects ms 
                   WHERE ms.moderator_id = ? AND ms.is_active = 1";
$stmt = $conn->prepare($subjects_query);
if($stmt) {
    $stmt->bind_param("i", $moderator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['subjects'] = $result ? $result->fetch_assoc()['subject_count'] : 0;
} else {
    $stats['subjects'] = 0;
}

// Get evaluators under supervision
$evaluators_query = "SELECT COUNT(*) as evaluator_count 
                      FROM users 
                      WHERE moderator_id = ? AND role = 'evaluator' AND is_active = 1";
$stmt = $conn->prepare($evaluators_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$stats['evaluators'] = $stmt->get_result()->fetch_assoc()['evaluator_count'];

// Get submission statistics - join through evaluator's moderator_id
$submissions_query = "SELECT 
    COUNT(DISTINCT s.id) as total_submissions,
    SUM(CASE WHEN (s.status = 'pending' OR s.status = 'Submitted') 
        AND (s.evaluation_status IS NULL OR s.evaluation_status NOT IN ('evaluated', 'approved')) 
        THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN s.status IN ('assigned', 'evaluating', 'Under Evaluation') 
        OR s.evaluation_status = 'under_review' 
        THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN s.status = 'evaluated' OR s.evaluation_status = 'evaluated' 
        THEN 1 ELSE 0 END) as evaluated,
    SUM(CASE WHEN s.status = 'approved' OR s.evaluation_status = 'approved' 
        THEN 1 ELSE 0 END) as approved
    FROM submissions s
    LEFT JOIN users u ON s.evaluator_id = u.id
    WHERE u.moderator_id = ? OR s.moderator_id = ?";
$stmt = $conn->prepare($submissions_query);
$stmt->bind_param("ii", $moderator_id, $moderator_id);
$stmt->execute();
$submission_stats = $stmt->get_result()->fetch_assoc();
$stats = array_merge($stats, $submission_stats);

// Get assigned subjects with details
$subjects_detail_query = "SELECT 
    s.id,
    s.name,
    s.code,
    s.department,
    COUNT(DISTINCT sub.id) as total_submissions,
    COUNT(DISTINCT CASE WHEN sub.status = 'pending' THEN sub.id END) as pending_submissions,
    COUNT(DISTINCT u.id) as assigned_evaluators
    FROM subjects s
    JOIN moderator_subjects ms ON s.id = ms.subject_id
    LEFT JOIN assignments a ON s.id = a.subject_id
    LEFT JOIN submissions sub ON a.id = sub.assignment_id
    LEFT JOIN users u ON sub.evaluator_id = u.id AND u.role = 'evaluator'
    WHERE ms.moderator_id = ? AND ms.is_active = 1
    GROUP BY s.id, s.name, s.code, s.department
    ORDER BY s.name";
$stmt = $conn->prepare($subjects_detail_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$subjects_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get evaluator performance data
$evaluator_performance_query = "SELECT 
    u.id as evaluator_id,
    u.name as evaluator_name,
    u.email as evaluator_email,
    COUNT(DISTINCT s.id) as total_assignments,
    COUNT(DISTINCT CASE WHEN s.status = 'evaluated' OR s.evaluation_status = 'evaluated' 
        THEN s.id END) as completed_assignments,
    COUNT(DISTINCT CASE WHEN s.status IN ('assigned', 'evaluating', 'Under Evaluation') 
        OR s.evaluation_status = 'under_review'
        THEN s.id END) as in_progress_assignments,
    COUNT(DISTINCT CASE 
        WHEN (s.status = 'pending' OR s.status = 'Submitted') 
        AND (s.evaluation_status IS NULL OR s.evaluation_status NOT IN ('evaluated', 'approved', 'under_review'))
        THEN s.id END) as pending_assignments,
    AVG(CASE WHEN s.marks_obtained IS NOT NULL AND s.max_marks > 0 
        THEN (s.marks_obtained / s.max_marks) * 100 END) as avg_marks_percentage,
    COUNT(DISTINCT CASE WHEN (s.status = 'evaluated' OR s.evaluation_status = 'evaluated') 
        AND s.evaluated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        THEN s.id END) as evaluations_this_week
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluator_id
    WHERE u.role = 'evaluator' AND u.moderator_id = ? AND u.is_active = 1
    GROUP BY u.id, u.name, u.email
    ORDER BY completed_assignments DESC, u.name";

$stmt = $conn->prepare($evaluator_performance_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$evaluator_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if evaluator_ratings table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'evaluator_ratings'");
$moderator_ratingsTableExists = ($tableExists && $tableExists->num_rows > 0);

if ($moderator_ratingsTableExists) {
    // Get evaluator ratings
    $moderator_evaluator_ratings_query = "SELECT 
        u.name as evaluator_name,
        COUNT(er.id) as total_ratings,
        AVG(er.overall_rating) as avg_overall,
        COUNT(CASE WHEN er.evaluation_quality = 'excellent' THEN 1 END) as excellent_quality,
        COUNT(CASE WHEN er.evaluation_quality = 'good' THEN 1 END) as good_quality,
        COUNT(CASE WHEN er.feedback_helpfulness = 'very_helpful' THEN 1 END) as very_helpful,
        COUNT(CASE WHEN er.feedback_helpfulness = 'helpful' THEN 1 END) as helpful
        FROM users u
        LEFT JOIN evaluator_ratings er ON u.id = er.evaluator_id
        WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1
        GROUP BY u.id, u.name
        HAVING total_ratings > 0
        ORDER BY avg_overall DESC, total_ratings DESC
        LIMIT 10";
    $stmt = $conn->prepare($moderator_evaluator_ratings_query);
    $stmt->bind_param("i", $moderator_id);
    $stmt->execute();
    $moderator_evaluator_ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get rating statistics
    $moderator_rating_stats_query = "SELECT 
        COUNT(er.id) as total_ratings,
        AVG(er.overall_rating) as avg_overall_rating,
        COUNT(CASE WHEN er.overall_rating >= 4 THEN 1 END) as excellent_ratings,
        COUNT(CASE WHEN er.overall_rating < 3 THEN 1 END) as poor_ratings
        FROM evaluator_ratings er
        JOIN users u ON er.evaluator_id = u.id
        WHERE u.moderator_id = ?";
    $stmt = $conn->prepare($moderator_rating_stats_query);
    $stmt->bind_param("i", $moderator_id);
    $stmt->execute();
    $moderator_rating_stats_result = $stmt->get_result();
    $moderator_rating_stats = $moderator_rating_stats_result ? $moderator_rating_stats_result->fetch_assoc() : [
        'total_ratings' => 0,
        'avg_overall_rating' => 0,
        'excellent_ratings' => 0,
        'poor_ratings' => 0
    ];
} else {
    $moderator_evaluator_ratings = [];
    $moderator_rating_stats = [
        'total_ratings' => 0,
        'avg_overall_rating' => 0,
        'excellent_ratings' => 0,
        'poor_ratings' => 0
    ];
}
?>

<div class="moderator-content">

<style>
/* Minimal additional styles - main styles in moderator-style.css */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    transition: all 0.2s ease;
}

.stat-card:hover {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

:root {
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-light: #DBEAFE;
    --success: #10B981;
    --success-light: #D1FAE5;
    --warning: #F59E0B;
    --warning-light: #FEF3C7;
    --danger: #EF4444;
    --danger-light: #FEE2E2;
    --info: #06B6D4;
    --info-light: #CFFAFE;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
    --gray-900: #111827;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--gray-50);
    color: var(--gray-800);
    line-height: 1.6;
}

.dashboard-header {
    background: white;
    border-bottom: 1px solid var(--gray-200);
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.dashboard-header h1 {
    font-size: 1.875rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 0.25rem;
}

.dashboard-header p {
    color: var(--gray-500);
    font-size: 0.875rem;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.2s ease;
}

.stat-card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.stat-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-icon.blue { background: var(--primary-light); color: var(--primary); }
.stat-icon.green { background: var(--success-light); color: var(--success); }
.stat-icon.amber { background: var(--warning-light); color: var(--warning); }
.stat-icon.cyan { background: var(--info-light); color: var(--info); }

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-900);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--gray-500);
    font-weight: 500;
}

/* Cards */
.card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-100);
}

.card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-900);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-title i {
    color: var(--primary);
}

/* Tables */
.table-wrapper {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.table thead {
    background: var(--gray-50);
}

.table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--gray-200);
}

.table td {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.table tbody tr {
    transition: background 0.15s ease;
}

.table tbody tr:hover {
    background: var(--gray-50);
}

/* User Info */
.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}

.user-details h6 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 0.125rem;
}

.user-details p {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin: 0;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 6px;
    line-height: 1;
}

.badge-primary { background: var(--primary-light); color: var(--primary); }
.badge-success { background: var(--success-light); color: var(--success); }
.badge-warning { background: var(--warning-light); color: var(--warning); }
.badge-danger { background: var(--danger-light); color: var(--danger); }
.badge-info { background: var(--info-light); color: var(--info); }
.badge-gray { background: var(--gray-100); color: var(--gray-600); }

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-outline {
    background: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
}

.btn-outline:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 6px;
}

.btn-group {
    display: flex;
    gap: 0.5rem;
}

/* Progress Bar */
.progress {
    width: 100%;
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: var(--primary);
    transition: width 0.3s ease;
}

/* Metrics List */
.metrics-list {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.metric-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8125rem;
}

.metric-label {
    color: var(--gray-600);
}

.metric-value {
    font-weight: 600;
}

/* Rating Card */
.rating-card {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 1.25rem;
    height: 100%;
}

.rating-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.rating-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-900);
}

.rating-score {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.rating-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
}

.stars {
    display: flex;
    gap: 0.125rem;
}

.stars i {
    font-size: 0.875rem;
}

.rating-details {
    display: grid;
    gap: 0.5rem;
}

.rating-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
}

.rating-item-label {
    color: var(--gray-600);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
}

.empty-state h6 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
}

.empty-state p {
    font-size: 0.875rem;
    color: var(--gray-500);
}

/* Grid Layouts */
.grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

/* Summary Stats */
.summary-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    padding: 1rem;
    background: var(--gray-50);
    border-radius: 10px;
}

.summary-stat {
    text-align: center;
}

.summary-stat h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 0.25rem;
}

.summary-stat p {
    font-size: 0.75rem;
    color: var(--gray-600);
    margin: 0;
}

/* Responsive */
@media (max-width: 1024px) {
    .grid-2 {
        grid-template-columns: 1fr;
    }
    
    .summary-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .dashboard-header h1 {
        font-size: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .grid-3 {
        grid-template-columns: 1fr;
    }
    
    .summary-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="container">
        <h1>Moderator Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($moderator_name) ?></p>
    </div>
</div>

<div class="container">
    <!-- Statistics Cards -->

    <!-- Evaluator Performance -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                <i class="fas fa-chart-line"></i>
                Evaluator Performance
            </h5>
            
        </div>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Evaluator</th>
                        <th>Status</th>
                        <th>Assignments</th>
                        <th>Progress</th>
                        <th>This Week</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($evaluator_performance)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-user-slash"></i>
                                <h6>No Evaluators Assigned</h6>
                                <p>Start by assigning evaluators to your subjects</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($evaluator_performance as $evaluator): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($evaluator['evaluator_name'], 0, 2)) ?>
                                    </div>
                                    <div class="user-details">
                                        <h6><?= htmlspecialchars($evaluator['evaluator_name']) ?></h6>
                                        <p><?= htmlspecialchars($evaluator['evaluator_email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                // Determine status based on priority: completed > in progress > pending
                                if($evaluator['completed_assignments'] > 0 && $evaluator['in_progress_assignments'] == 0 && $evaluator['pending_assignments'] == 0) {
                                    $status = 'Completed';
                                    $badge_class = 'badge-success';
                                } elseif($evaluator['in_progress_assignments'] > 0) {
                                    $status = 'Working';
                                    $badge_class = 'badge-info';
                                } elseif($evaluator['pending_assignments'] > 0) {
                                    $status = 'Pending';
                                    $badge_class = 'badge-warning';
                                } elseif($evaluator['total_assignments'] > 0) {
                                    $status = 'Active';
                                    $badge_class = 'badge-success';
                                } else {
                                    $status = 'Idle';
                                    $badge_class = 'badge-gray';
                                }
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $status ?></span>
                            </td>
                            <td>
                                <div class="metrics-list">
                                    <div class="metric-row">
                                        <span class="metric-label">Total:</span>
                                        <span class="metric-value"><?= $evaluator['total_assignments'] ?></span>
                                    </div>
                                    <div class="metric-row">
                                        <span class="metric-label">Completed:</span>
                                        <span class="metric-value" style="color: var(--success);"><?= $evaluator['completed_assignments'] ?></span>
                                    </div>
                                </div>
                            </td>
                            <td style="min-width: 120px;">
                                <?php if($evaluator['avg_marks_percentage']): ?>
                                    <div class="progress" style="margin-bottom: 0.25rem;">
                                        <div class="progress-bar" style="width: <?= min(100, $evaluator['avg_marks_percentage']) ?>%"></div>
                                    </div>
                                    <small style="color: var(--gray-500); font-size: 0.75rem;">
                                        <?= number_format($evaluator['avg_marks_percentage'], 1) ?>% avg
                                    </small>
                                <?php else: ?>
                                    <small style="color: var(--gray-400);">No data</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $evaluator['evaluations_this_week'] > 0 ? 'badge-success' : 'badge-gray' ?>">
                                    <?= $evaluator['evaluations_this_week'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="submissions.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-list-alt"></i> View Submissions
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if(!empty($evaluator_performance)): ?>
        <div class="summary-stats" style="margin-top: 1.5rem;">
            <div class="summary-stat">
                <h4><?= count($evaluator_performance) ?></h4>
                <p>Total Evaluators</p>
            </div>
            <div class="summary-stat">
                <h4 style="color: var(--success);"><?= array_sum(array_column($evaluator_performance, 'completed_assignments')) ?></h4>
                <p>Completed</p>
            </div>
            <div class="summary-stat">
                <h4 style="color: var(--info);"><?= array_sum(array_column($evaluator_performance, 'in_progress_assignments')) ?></h4>
                <p>In Progress</p>
            </div>
            <div class="summary-stat">
                <h4 style="color: var(--warning);"><?= array_sum(array_column($evaluator_performance, 'pending_assignments')) ?></h4>
                <p>Pending</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Evaluator Ratings -->
    <?php if(!empty($moderator_evaluator_ratings)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                <i class="fas fa-star"></i>
                Evaluator Ratings
            </h5>
            <button class="btn btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#detailedRatingsModal">
                View All Ratings
            </button>
        </div>

        <div class="grid-3">
            <?php foreach(array_slice($moderator_evaluator_ratings, 0, 6) as $rating): ?>
            <div class="rating-card">
                <div class="rating-header">
                    <h6 class="rating-name"><?= htmlspecialchars($rating['evaluator_name']) ?></h6>
                    <span class="badge badge-primary"><?= $rating['total_ratings'] ?></span>
                </div>
                <div class="rating-score">
                    <span class="rating-number"><?= number_format($rating['avg_overall'], 1) ?></span>
                    <div class="stars">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color: <?= $i <= round($rating['avg_overall']) ? 'var(--warning)' : 'var(--gray-300)' ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="rating-details">
                    <div class="rating-item">
                        <span class="rating-item-label">Excellent Quality</span>
                        <span class="badge badge-success"><?= $rating['excellent_quality'] ?></span>
                    </div>
                    <div class="rating-item">
                        <span class="rating-item-label">Good Quality</span>
                        <span class="badge badge-primary"><?= $rating['good_quality'] ?></span>
                    </div>
                    <div class="rating-item">
                        <span class="rating-item-label">Very Helpful</span>
                        <span class="badge badge-info"><?= $rating['very_helpful'] ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Assigned Subjects -->
    <!-- <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                <i class="fas fa-book-open"></i>
                Assigned Subjects
            </h5>
            <a href="subjects.php" class="btn btn-outline btn-sm">
                Manage Subjects
            </a>
        </div>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Department</th>
                        <th>Submissions</th>
                        <th>Evaluators</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($subjects_details)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-book"></i>
                                <h6>No Subjects Assigned</h6>
                                <p>You don't have any subjects assigned yet</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($subjects_details as $subject): ?>
                        <tr>
                            <td>
                                <div>
                                    <h6 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.125rem;">
                                        <?= htmlspecialchars($subject['name']) ?>
                                    </h6>
                                    <p style="font-size: 0.75rem; color: var(--gray-500); margin: 0;">
                                        <?= htmlspecialchars($subject['code']) ?>
                                    </p>
                                </div>
                            </td>
                            <td>
                                <span style="color: var(--gray-600); font-size: 0.875rem;">
                                    <?= htmlspecialchars($subject['department']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <span class="badge badge-primary"><?= $subject['total_submissions'] ?></span>
                                    <?php if($subject['pending_submissions'] > 0): ?>
                                    <span class="badge badge-warning"><?= $subject['pending_submissions'] ?> pending</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= $subject['assigned_evaluators'] ?></span>
                            </td>
                            <td>
                                <a href="subject_detail.php?id=<?= $subject['id'] ?>" class="btn btn-outline btn-icon">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div> -->
</div>

<!-- Detailed Ratings Modal -->
<div class="modal fade" id="detailedRatingsModal" tabindex="-1" aria-labelledby="detailedRatingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary); color: white;">
                <h5 class="modal-title" id="detailedRatingsModalLabel">
                    <i class="fas fa-star"></i> Detailed Evaluator Ratings
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ratingsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading detailed ratings...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load detailed ratings when modal is opened
document.getElementById('detailedRatingsModal')?.addEventListener('show.bs.modal', function() {
    fetch('get_moderator_detailed_ratings.php')
        .then(response => response.json())
        .then(data => {
            let content = '';
            
            if (data.success && data.ratings.length > 0) {
                content = `
                    <div class="summary-stats" style="margin-bottom: 2rem;">
                        <div class="summary-stat">
                            <h4 style="color: var(--primary);">${data.stats.total_ratings}</h4>
                            <p>Total Ratings</p>
                        </div>
                        <div class="summary-stat">
                            <h4 style="color: var(--success);">${data.stats.avg_overall_rating}</h4>
                            <p>Average Rating</p>
                        </div>
                        <div class="summary-stat">
                            <h4 style="color: var(--warning);">${data.stats.excellent_ratings}</h4>
                            <p>Excellent (4+)</p>
                        </div>
                        <div class="summary-stat">
                            <h4 style="color: var(--danger);">${data.stats.poor_ratings}</h4>
                            <p>Poor (< 3)</p>
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Evaluator</th>
                                    <th>Student</th>
                                    <th>Overall</th>
                                    <th>Quality</th>
                                    <th>Helpfulness</th>
                                    <th>Comments</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.ratings.forEach(rating => {
                    const stars = (num) => {
                        let starsHtml = '';
                        for(let i = 1; i <= 5; i++) {
                            starsHtml += `<i class="fas fa-star" style="font-size: 0.75rem; color: ${i <= num ? 'var(--warning)' : 'var(--gray-300)'}"></i>`;
                        }
                        return starsHtml;
                    };
                    
                    const qualityClass = rating.evaluation_quality === 'excellent' ? 'badge-success' : 
                                        rating.evaluation_quality === 'good' ? 'badge-primary' : 'badge-gray';
                    const helpfulClass = rating.feedback_helpfulness === 'very_helpful' ? 'badge-success' : 
                                        rating.feedback_helpfulness === 'helpful' ? 'badge-primary' : 'badge-gray';
                    
                    content += `
                        <tr>
                            <td><strong>${rating.evaluator_name}</strong></td>
                            <td>${rating.student_name}</td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-weight: 600;">${rating.overall_rating}</span>
                                    <div>${stars(rating.overall_rating)}</div>
                                </div>
                            </td>
                            <td>
                                <span class="badge ${qualityClass}">${rating.evaluation_quality}</span>
                            </td>
                            <td>
                                <span class="badge ${helpfulClass}">${rating.feedback_helpfulness.replace('_', ' ')}</span>
                            </td>
                            <td>
                                <small style="color: var(--gray-600);">${rating.comments || 'No comments'}</small>
                            </td>
                            <td>
                                <small style="color: var(--gray-500);">${new Date(rating.created_at).toLocaleDateString()}</small>
                            </td>
                        </tr>
                    `;
                });
                
                content += `
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                content = `
                    <div class="empty-state">
                        <i class="fas fa-star-half-alt"></i>
                        <h6>No Ratings Available</h6>
                        <p>Detailed ratings will appear here once students start evaluating your assigned evaluators</p>
                    </div>
                `;
            }
            
            document.getElementById('ratingsContent').innerHTML = content;
        })
        .catch(error => {
            console.error('Error loading ratings:', error);
            document.getElementById('ratingsContent').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                    <h6>Error Loading Ratings</h6>
                    <p>Please try again later</p>
                </div>
            `;
        });
});
</script>

</div><!-- Close moderator-content -->

<?php include('../includes/footer.php'); ?>