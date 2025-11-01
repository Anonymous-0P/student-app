<?php
require_once('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

?>


<style>
/* Hamburger Menu Styles */
.hamburger-menu {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1050;
}

.hamburger-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
    cursor: pointer;
}

.hamburger-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.hamburger-line {
    width: 20px;
    height: 2px;
    background: white;
    margin: 2px 0;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.hamburger-btn:hover .hamburger-line {
    width: 24px;
}

/* Offcanvas Menu Styles */
.offcanvas {
    background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
    border-right: 1px solid #dee2e6;
}

.offcanvas-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.offcanvas-title {
    font-weight: 600;
}

.btn-close {
    filter: invert(1);
}

.nav-section {
    margin-bottom: 1.5rem;
}

.nav-section-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.nav-link {
    color: #495057 !important;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin: 0.25rem 0;
    transition: all 0.3s ease;
    text-decoration: none;
}

.nav-link:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
    transform: translateX(5px);
}

.nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
    font-weight: 600;
}

.nav-link i {
    width: 20px;
    margin-right: 10px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .hamburger-menu {
        top: 15px;
        left: 15px;
    }
    
    .hamburger-btn {
        width: 45px;
        height: 45px;
    }
}
</style>

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

// Get submission statistics
$submissions_query = "SELECT 
    COUNT(*) as total_submissions,
    SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN s.status = 'assigned' OR s.status = 'evaluating' THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN s.status = 'evaluated' THEN 1 ELSE 0 END) as evaluated,
    SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM submissions s
    WHERE s.moderator_id = ?";
$stmt = $conn->prepare($submissions_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$submission_stats = $stmt->get_result()->fetch_assoc();
$stats = array_merge($stats, $submission_stats);

// Get recent activities
$recent_activities_query = "SELECT 
    s.id,
    s.submission_title,
    s.status,
    s.created_at,
    s.updated_at,
    u.name as student_name,
    subj.name as subject_name,
    ev.name as evaluator_name
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    LEFT JOIN assignments a ON s.assignment_id = a.id
    LEFT JOIN subjects subj ON a.subject_id = subj.id
    LEFT JOIN users ev ON s.evaluator_id = ev.id
    WHERE s.moderator_id = ?
    ORDER BY s.updated_at DESC
    LIMIT 10";
$stmt = $conn->prepare($recent_activities_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

// Check if evaluator_ratings table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'evaluator_ratings'");
$moderator_ratingsTableExists = ($tableExists && $tableExists->num_rows > 0);

if ($moderator_ratingsTableExists) {
    // Get evaluator ratings for moderator's assigned evaluators
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

    // Get rating statistics for moderator's evaluators
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
    // Set empty defaults if table doesn't exist
    $moderator_evaluator_ratings = [];
    $moderator_rating_stats = [
        'total_ratings' => 0,
        'avg_overall_rating' => 0,
        'excellent_ratings' => 0,
        'poor_ratings' => 0
    ];
}
?>

<style>
.dashboard-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: none;
    transition: all 0.3s ease;
    height: 100%;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.activity-item {
    padding: 1rem;
    border-left: 4px solid #667eea;
    margin-bottom: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-pending { background-color: #ffeaa7; color: #d63031; }
.status-assigned { background-color: #74b9ff; color: white; }
.status-evaluating { background-color: #fd79a8; color: white; }
.status-evaluated { background-color: #00b894; color: white; }
.status-approved { background-color: #6c5ce7; color: white; }

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
}

.quick-action-btn {
    background: linear-gradient(135deg, #00b894, #00cec9);
    border: none;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,184,148,0.3);
    color: white;
}
</style>

<div class="page-header py-2 rounded">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-0">
                    <i class="fas fa-user-cog"></i> Moderator Dashboard
                </h1>
                <p class="mb-0 mt-2 opacity-75">Welcome back, <?= htmlspecialchars($moderator_name) ?></p>
            </div>
            <div class="col-md-4 text-md-end">
                
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Statistics Cards -->
    <!-- <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['subjects'] ?></div>
                <div class="stat-label">Assigned Subjects</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%);">
                <div class="stat-number"><?= $stats['evaluators'] ?></div>
                <div class="stat-label">Supervised Evaluators</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);">
                <div class="stat-number"><?= $stats['evaluated'] + $stats['approved'] ?></div>
                <div class="stat-label">Completed Reviews</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #e17055 0%, #f39c12 100%);">
                <div class="stat-number"><?= $stats['pending'] + $stats['under_review'] ?></div>
                <div class="stat-label">Pending Reviews</div>
            </div>
        </div>
    </div> -->

    
     <!-- Evaluator Management Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-users text-primary"></i> Evaluator Performance Overview
                    </h5>
                    <a href="evaluator_performance.php" class="btn btn-outline-primary btn-sm">View Detailed Performance</a>
                </div>

                <?php
                // Get evaluator performance data
                $evaluator_performance_query = "SELECT 
                    u.id as evaluator_id,
                    u.name as evaluator_name,
                    u.email as evaluator_email,
                    COUNT(DISTINCT sa.submission_id) as total_assignments,
                    COUNT(DISTINCT CASE WHEN sa.status = 'completed' THEN sa.submission_id END) as completed_assignments,
                    COUNT(DISTINCT CASE WHEN sa.status = 'accepted' THEN sa.submission_id END) as in_progress_assignments,
                    COUNT(DISTINCT CASE WHEN sa.status = 'pending' THEN sa.submission_id END) as pending_assignments,
                    AVG(CASE WHEN s.marks_obtained IS NOT NULL AND s.max_marks > 0 
                        THEN (s.marks_obtained / s.max_marks) * 100 END) as avg_marks_percentage,
                    COUNT(DISTINCT CASE WHEN s.evaluation_status = 'evaluated' AND s.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                        THEN s.id END) as evaluations_this_week
                    FROM users u
                    LEFT JOIN submission_assignments sa ON u.id = sa.evaluator_id
                    LEFT JOIN submissions s ON sa.submission_id = s.id
                    WHERE u.role = 'evaluator' AND u.moderator_id = ? AND u.is_active = 1
                    GROUP BY u.id, u.name, u.email
                    ORDER BY completed_assignments DESC, u.name";
                
                $stmt = $conn->prepare($evaluator_performance_query);
                $stmt->bind_param("i", $moderator_id);
                $stmt->execute();
                $evaluator_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Evaluator</th>
                                <th>Status</th>
                                <th>Assignments</th>
                                <th>Performance</th>
                                <th>This Week</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($evaluator_performance)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-user-slash fa-2x mb-2"></i>
                                    <p class="mb-0">No evaluators assigned yet</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($evaluator_performance as $evaluator): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($evaluator['evaluator_name']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($evaluator['evaluator_email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_color = 'success';
                                        $status_text = 'Active';
                                        if($evaluator['pending_assignments'] > 0) {
                                            $status_color = 'warning';
                                            $status_text = 'Has Pending';
                                        }
                                        if($evaluator['in_progress_assignments'] > 0) {
                                            $status_color = 'info';
                                            $status_text = 'Working';
                                        }
                                        ?>
                                        <span class="badge bg-<?= $status_color ?>"><?= $status_text ?></span>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <div><strong><?= $evaluator['total_assignments'] ?></strong> Total</div>
                                            <div class="text-success"><?= $evaluator['completed_assignments'] ?> Completed</div>
                                            <div class="text-info"><?= $evaluator['in_progress_assignments'] ?> In Progress</div>
                                            <div class="text-warning"><?= $evaluator['pending_assignments'] ?> Pending</div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($evaluator['avg_marks_percentage']): ?>
                                            <div class="progress mb-1" style="height: 8px;">
                                                <div class="progress-bar bg-primary" 
                                                     style="width: <?= min(100, $evaluator['avg_marks_percentage']) ?>%"></div>
                                            </div>
                                            <small class="text-muted">Avg: <?= number_format($evaluator['avg_marks_percentage'], 1) ?>%</small>
                                        <?php else: ?>
                                            <small class="text-muted">No evaluations yet</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $evaluator['evaluations_this_week'] > 0 ? 'success' : 'secondary' ?>">
                                            <?= $evaluator['evaluations_this_week'] ?> evaluations
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="assign_evaluator.php?evaluator_id=<?= $evaluator['evaluator_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Assign Work">
                                                <i class="fas fa-plus"></i>
                                            </a>
                                            <a href="marks_access.php?evaluator_id=<?= $evaluator['evaluator_id'] ?>&evaluator_name=<?= urlencode($evaluator['evaluator_name']) ?>" 
                                               class="btn btn-sm btn-outline-warning" title="Marks Access">
                                                <i class="fas fa-check-double"></i>
                                            </a>
                                            <a href="evaluator_performance.php?id=<?= $evaluator['evaluator_id'] ?>" 
                                               class="btn btn-sm btn-outline-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../evaluator/assignments.php?evaluator_id=<?= $evaluator['evaluator_id'] ?>" target="_blank"
                                               class="btn btn-sm btn-outline-secondary" title="View Evaluator Dashboard">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Quick Stats for Evaluators -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-primary"><?= count($evaluator_performance) ?></h4>
                            <small class="text-muted">Total Evaluators</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-success"><?= array_sum(array_column($evaluator_performance, 'completed_assignments')) ?></h4>
                            <small class="text-muted">Completed Assignments</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-info"><?= array_sum(array_column($evaluator_performance, 'in_progress_assignments')) ?></h4>
                            <small class="text-muted">In Progress</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-warning"><?= array_sum(array_column($evaluator_performance, 'pending_assignments')) ?></h4>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Evaluator Ratings Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-star text-warning"></i> Evaluator Ratings</h5>
                    <div class="d-flex gap-3">
                        <?php if($moderator_rating_stats['total_ratings'] > 0): ?>
                            <small class="text-muted">
                                <i class="fas fa-chart-line"></i> Avg: <?= number_format($moderator_rating_stats['avg_overall_rating'], 1) ?>/5
                            </small>
                            <small class="text-success">
                                <i class="fas fa-thumbs-up"></i> <?= $moderator_rating_stats['excellent_ratings'] ?> Excellent
                            </small>
                            <?php if($moderator_rating_stats['poor_ratings'] > 0): ?>
                                <small class="text-danger">
                                    <i class="fas fa-thumbs-down"></i> <?= $moderator_rating_stats['poor_ratings'] ?> Poor
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if(!empty($moderator_evaluator_ratings)): ?>
                    <div class="row">
                        <?php foreach($moderator_evaluator_ratings as $rating): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-1 text-truncate" title="<?= htmlspecialchars($rating['evaluator_name']) ?>">
                                            <?= htmlspecialchars($rating['evaluator_name']) ?>
                                        </h6>
                                        <span class="badge bg-primary"><?= $rating['total_ratings'] ?> reviews</span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="fw-bold text-warning me-2"><?= number_format($rating['avg_overall'], 1) ?></span>
                                            <div class="stars-small">
                                                <?php 
                                                $overall = round($rating['avg_overall']);
                                                for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $overall ? 'text-warning' : 'text-muted' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rating-breakdown">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Excellent Quality:</small>
                                            <span class="badge bg-success"><?= $rating['excellent_quality'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Good Quality:</small>
                                            <span class="badge bg-primary"><?= $rating['good_quality'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Very Helpful:</small>
                                            <span class="badge bg-info"><?= $rating['very_helpful'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Helpful:</small>
                                            <span class="badge bg-outline-secondary"><?= $rating['helpful'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-3">
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#moderatorDetailedRatingsModal">
                            <i class="fas fa-eye"></i> View Detailed Ratings
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-star-half-alt text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                        <?php if (!$moderator_ratingsTableExists): ?>
                            <p class="text-muted mt-3">Evaluator Rating System Not Set Up</p>
                            <small class="text-muted">The evaluator_ratings table needs to be created to display rating data</small>
                        <?php else: ?>
                            <p class="text-muted mt-3">No evaluator ratings available yet</p>
                            <small class="text-muted">Ratings will appear here once students start evaluating your assigned evaluators</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Assigned Subjects -->
    <div class="row mb-4">
        <div class="col-lg-7">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-book text-success"></i> Assigned Subjects
                    </h5>
                    <a href="subjects.php" class="btn btn-outline-success btn-sm">Manage All</a>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Department</th>
                                <th>Submissions</th>
                                <th>Evaluators</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($subjects_details as $subject): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($subject['name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($subject['code']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($subject['department']) ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= $subject['total_submissions'] ?></span>
                                    <?php if($subject['pending_submissions'] > 0): ?>
                                    <span class="badge bg-warning"><?= $subject['pending_submissions'] ?> pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= $subject['assigned_evaluators'] ?> evaluators</span>
                                </td>
                                <td>
                                    <a href="subject_detail.php?id=<?= $subject['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
    </div>

   
</div>



<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.table th {
    border-top: none;
    font-weight: 600;
    background: #f8f9fa;
}

.progress {
    border-radius: 10px;
}

.table-responsive {
    border-radius: 10px;
    border: 1px solid #dee2e6;
}
</style>

<!-- Moderator Detailed Ratings Modal -->
<div class="modal fade" id="moderatorDetailedRatingsModal" tabindex="-1" aria-labelledby="moderatorDetailedRatingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title" id="moderatorDetailedRatingsModalLabel">
                    <i class="fas fa-star"></i> Detailed Evaluator Ratings
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="moderatorRatingsContent">
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

<style>
.stars-small .fas {
    font-size: 0.8rem;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.badge.bg-outline-secondary {
    background: transparent !important;
    border: 1px solid #6c757d;
    color: #6c757d;
}
</style>

<script>
// Load detailed ratings when modal is opened
document.getElementById('moderatorDetailedRatingsModal').addEventListener('show.bs.modal', function() {
    fetch('get_moderator_detailed_ratings.php')
        .then(response => response.json())
        .then(data => {
            let content = '';
            
            if (data.success && data.ratings.length > 0) {
                content = `
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h3>${data.stats.total_ratings}</h3>
                                    <small>Total Ratings</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3>${data.stats.avg_overall_rating}</h3>
                                    <small>Average Rating</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h3>${data.stats.excellent_ratings}</h3>
                                    <small>Excellent (4+ stars)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h3>${data.stats.poor_ratings}</h3>
                                    <small>Poor (< 3 stars)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Evaluator</th>
                                    <th>Student</th>
                                    <th>Overall</th>
                                    <th>Evaluation Quality</th>
                                    <th>Feedback Helpfulness</th>
                                    <th>Comments</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.ratings.forEach(rating => {
                    const stars = (num) => {
                        let stars = '';
                        for(let i = 1; i <= 5; i++) {
                            stars += `<i class="fas fa-star ${i <= num ? 'text-warning' : 'text-muted'}"></i>`;
                        }
                        return stars;
                    };
                    
                    content += `
                        <tr>
                            <td><strong>${rating.evaluator_name}</strong></td>
                            <td>${rating.student_name}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="me-2">${rating.overall_rating}</span>
                                    <div class="stars-small">${stars(rating.overall_rating)}</div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-${rating.evaluation_quality === 'excellent' ? 'success' : rating.evaluation_quality === 'good' ? 'primary' : 'secondary'}">${rating.evaluation_quality}</span>
                            </td>
                            <td>
                                <span class="badge bg-${rating.feedback_helpfulness === 'very_helpful' ? 'success' : rating.feedback_helpfulness === 'helpful' ? 'primary' : 'secondary'}">${rating.feedback_helpfulness.replace('_', ' ')}</span>
                            </td>
                            <td>
                                <small class="text-muted">${rating.comments || 'No comments'}</small>
                            </td>
                            <td>
                                <small>${new Date(rating.created_at).toLocaleDateString()}</small>
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
                    <div class="text-center py-5">
                        <i class="fas fa-star-half-alt text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h5 class="mt-3 text-muted">No ratings available</h5>
                        <p class="text-muted">Detailed ratings will appear here once students start evaluating your assigned evaluators</p>
                    </div>
                `;
            }
            
            document.getElementById('moderatorRatingsContent').innerHTML = content;
        })
        .catch(error => {
            console.error('Error loading ratings:', error);
            document.getElementById('moderatorRatingsContent').innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 text-warning">Error Loading Ratings</h5>
                    <p class="text-muted">Please try again later</p>
                </div>
            `;
        });
});

// Auto-refresh dashboard every 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);



// Add smooth animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.dashboard-card, .stat-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
        card.classList.add('fadeInUp');
    });
});
</script>

<?php include('../includes/footer.php'); ?>