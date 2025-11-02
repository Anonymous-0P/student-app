<?php
session_start();

// Check if user is logged in and is an evaluator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';

$evaluator_id = $_SESSION['user_id'];
$evaluator_name = $_SESSION['name'];

// Get assignment statistics for both legacy and new answer sheet system
$stats = [
    'pending_assignments' => 0,
    'accepted_assignments' => 0,
    'denied_assignments' => 0,
    'completed_evaluations' => 0
];

// Get pending assignments from answer sheet system
$pending_stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluator_answer_sheet_assignments WHERE evaluator_id = ? AND status = 'pending'");
$pending_stmt->execute([$evaluator_id]);
$stats['pending_assignments'] = $pending_stmt->fetchColumn();

// Get accepted assignments
$accepted_stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluator_answer_sheet_assignments WHERE evaluator_id = ? AND status = 'accepted'");
$accepted_stmt->execute([$evaluator_id]);
$stats['accepted_assignments'] = $accepted_stmt->fetchColumn();

// Get declined assignments
$declined_stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluator_answer_sheet_assignments WHERE evaluator_id = ? AND status = 'declined'");
$declined_stmt->execute([$evaluator_id]);
$stats['denied_assignments'] = $declined_stmt->fetchColumn();

// Get completed evaluations
$completed_stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluation_feedback WHERE evaluator_id = ?");
$completed_stmt->execute([$evaluator_id]);
$stats['completed_evaluations'] = $completed_stmt->fetchColumn();

// Get recent notifications
$notifications_query = "
    SELECT n.id, n.type, n.title, n.message, n.created_at, n.is_read
    FROM notifications n
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 5
";

$stmt = $pdo->prepare($notifications_query);
$stmt->execute([$evaluator_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent pending assignments
$pending_assignments_query = "
    SELECT easa.id as assignment_id, easa.assigned_at,
           as_main.id as submission_id, as_main.exam_title as title, as_main.submitted_at,
           s.code as subject_code, s.name as subject_name,
           u.name as student_name
    FROM evaluator_answer_sheet_assignments easa
    JOIN answer_sheets as_main ON easa.answer_sheet_id = as_main.id
    JOIN subjects s ON as_main.subject_id = s.id
    JOIN users u ON as_main.student_id = u.id
    WHERE easa.evaluator_id = ? AND easa.status = 'pending'
    ORDER BY easa.assigned_at DESC
    LIMIT 5
";

$stmt = $pdo->prepare($pending_assignments_query);
$stmt->execute([$evaluator_id]);
$pending_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent accepted assignments (currently evaluating)
$accepted_assignments_query = "
    SELECT easa.id as assignment_id, easa.accepted_at as responded_at,
           as_main.id as submission_id, as_main.exam_title as title, as_main.status, as_main.submitted_at,
           s.code as subject_code, s.name as subject_name,
           u.name as student_name
    FROM evaluator_answer_sheet_assignments easa
    JOIN answer_sheets as_main ON easa.answer_sheet_id = as_main.id
    JOIN subjects s ON as_main.subject_id = s.id
    JOIN users u ON as_main.student_id = u.id
    WHERE easa.evaluator_id = ? AND easa.status = 'accepted'
    ORDER BY easa.accepted_at DESC
    LIMIT 5
";

$stmt = $pdo->prepare($accepted_assignments_query);
$stmt->execute([$evaluator_id]);
$accepted_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assigned subjects
$subjects_query = "
    SELECT DISTINCT s.id, s.code, s.name, 
           (SELECT COUNT(*) FROM evaluator_answer_sheet_assignments easa 
            JOIN answer_sheets as_main ON easa.answer_sheet_id = as_main.id 
            WHERE easa.evaluator_id = ? AND as_main.subject_id = s.id) as total_assignments
    FROM subjects s
    JOIN evaluator_subjects es ON s.id = es.subject_id
    WHERE es.evaluator_id = ?
    ORDER BY s.code
";

$stmt = $pdo->prepare($subjects_query);
$stmt->execute([$evaluator_id, $evaluator_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* Modern Minimal Dashboard Styles */
:root {
    --primary-color: #2563eb;
    --primary-dark: #1d4ed8;
    --secondary-color: #64748b;
    --success-color: #059669;
    --warning-color: #d97706;
    --danger-color: #dc2626;
    --light-bg: #f8fafc;
    --border-color: #e2e8f0;
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: var(--light-bg);
    color: var(--text-primary);
}

/* Dashboard Cards */
.dashboard-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
    height: 100%;
}

.dashboard-card:hover {
    box-shadow: var(--shadow-md);
    border-color: #cbd5e1;
}

/* Statistics Cards */
.stat-widget {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-align: center;
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.stat-widget:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}

.stat-widget::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--primary-color);
}

.stat-widget .h3 {
    color: var(--text-primary);
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-widget .small {
    color: var(--text-secondary);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    font-size: 0.75rem;
}

/* Notification and Assignment Items */
.notification-item, .assignment-item {
    background: white;
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-bottom: 0.75rem;
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
}

.notification-item:hover, .assignment-item:hover {
    box-shadow: var(--shadow-sm);
    border-color: #cbd5e1;
}

.notification-item.unread {
    border-left: 3px solid var(--warning-color);
    background: #fffbeb;
}

.assignment-item.pending {
    border-left: 3px solid var(--warning-color);
}

.assignment-item.accepted {
    border-left: 3px solid var(--success-color);
}

/* Button Styles */
.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.btn-outline-primary {
    color: var(--primary-color);
    border-color: var(--border-color);
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-outline-primary:hover {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    transform: translateY(-1px);
}

.btn-outline-warning {
    color: var(--warning-color);
    border-color: var(--border-color);
    font-weight: 500;
    position: relative;
}

.btn-outline-warning:hover {
    background-color: var(--warning-color);
    border-color: var(--warning-color);
}

/* Quick Action Buttons */
.quick-action-btn {
    background: var(--primary-color);
    border: none;
    color: white;
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius-md);
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
}

.quick-action-btn:hover {
    background: var(--primary-dark);
    color: white;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.quick-action-btn.btn-danger {
    background: var(--danger-color);
}

.quick-action-btn.btn-danger:hover {
    background: #b91c1c;
}

/* Header Styles */
.page-header {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    margin-bottom: 2rem;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.page-subtitle {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Card Headers */
.card-header-custom {
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    margin: -1.5rem -1.5rem 1.5rem -1.5rem;
}

.card-title-custom {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

/* Badge Styles */
.badge {
    font-weight: 500;
    padding: 0.375rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
}

.badge.bg-primary {
    background-color: var(--primary-color) !important;
}

.badge.bg-success {
    background-color: var(--success-color) !important;
}

.badge.bg-warning {
    background-color: var(--warning-color) !important;
}

.badge.bg-danger {
    background-color: var(--danger-color) !important;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.empty-state h6 {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

/* Animations */
.fade-in {
    animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-card {
        padding: 1rem;
    }
    
    .stat-widget {
        padding: 1rem;
    }
    
    .quick-action-btn {
        width: 100%;
        margin-right: 0;
        margin-bottom: 0.5rem;
    }
}

/* Utility Classes */
.text-primary-custom {
    color: var(--primary-color) !important;
}

.text-secondary-custom {
    color: var(--text-secondary) !important;
}

.border-light-custom {
    border-color: var(--border-color) !important;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4 fade-in">
        <div class="col-12">
            <div class="page-header p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h1 class="page-title">
                            Welcome, <?= htmlspecialchars($evaluator_name) ?>
                        </h1>
                        <p class="page-subtitle mb-0">Manage your assignments and evaluations</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="assignments.php" class="btn btn-primary">
                            <i class="fas fa-tasks me-1"></i> Assignments
                        </a>
                        <a href="evaluation_notifications.php" class="btn btn-outline-warning position-relative">
                            <i class="fas fa-bell me-1"></i> 
                            Notifications
                            <?php if ($stats['pending_assignments'] > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $stats['pending_assignments'] ?>
                                    <span class="visually-hidden">unread notifications</span>
                                </span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->

    <div class="row g-3">
        <!-- Pending Assignments -->
        <!-- <div class="col-lg-6 fade-in">
            <div class="dashboard-card">
                <div class="card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title-custom">Pending Assignments</h6>
                        <a href="evaluation_notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                
                <?php if (!empty($pending_assignments)): ?>
                    <?php foreach ($pending_assignments as $assignment): ?>
                        <div class="assignment-item pending">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge bg-warning text-dark"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                        <h6 class="mb-0"><?= htmlspecialchars($assignment['title']) ?></h6>
                                    </div>
                                    <p class="mb-1 text-secondary-custom"><?= htmlspecialchars($assignment['student_name']) ?></p>
                                    <small class="text-secondary-custom">
                                        <?= date('M j, g:i A', strtotime($assignment['assigned_at'])) ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <a href="evaluation_notifications.php" class="btn btn-sm btn-outline-primary">
                                        Review
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h6>No Pending Assignments</h6>
                        <p class="mb-0">All caught up! No evaluation requests at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div> -->

        <!-- Active Evaluations -->
        <!-- <div class="col-lg-6 fade-in">
            <div class="dashboard-card">
                <div class="card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title-custom">Active Evaluations</h6>
                        <a href="assignments.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                
                <?php if (!empty($accepted_assignments)): ?>
                    <?php foreach ($accepted_assignments as $assignment): ?>
                        <div class="assignment-item accepted">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge bg-success"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                        <h6 class="mb-0"><?= htmlspecialchars($assignment['title']) ?></h6>
                                    </div>
                                    <p class="mb-1 text-secondary-custom"><?= htmlspecialchars($assignment['student_name']) ?></p>
                                    <small class="text-secondary-custom">
                                        Status: <span class="badge bg-primary"><?= strtoupper($assignment['status']) ?></span>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <a href="evaluate_answer_sheet.php?id=<?= $assignment['submission_id'] ?>" class="btn btn-sm btn-primary">
                                        Evaluate
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <h6>No Active Evaluations</h6>
                        <p class="mb-0">No assignments currently under evaluation.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div> -->

        <!-- Recent Notifications -->
        <div class="col-lg-6 fade-in">
            <div class="dashboard-card">
                <div class="card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title-custom">Recent Notifications</h6>
                        <a href="evaluation_notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-2"><?= htmlspecialchars($notification['title']) ?></h6>
                                    <p class="mb-2 text-secondary-custom"><?= htmlspecialchars(substr($notification['message'], 0, 80)) ?>...</p>
                                    <small class="text-secondary-custom"><?= date('M j, g:i A', strtotime($notification['created_at'])) ?></small>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <span class="badge bg-warning text-dark">New</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h6>No Recent Notifications</h6>
                        <p class="mb-0">You're all caught up!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assigned Subjects -->
        <div class="col-lg-6 fade-in">
            <div class="dashboard-card">
                <div class="card-header-custom">
                    <h6 class="card-title-custom">Assigned Subjects</h6>
                </div>
                
                <?php if (!empty($subjects)): ?>
                    <?php foreach ($subjects as $subject): ?>
                        <div class="assignment-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-primary"><?= htmlspecialchars($subject['code']) ?></span>
                                        <h6 class="mb-0"><?= htmlspecialchars($subject['name']) ?></h6>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-secondary"><?= $subject['total_assignments'] ?> assignments</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h6>No Subjects Assigned</h6>
                        <p class="mb-0">Contact administrator for subject assignments.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4 fade-in">
        <div class="col-12">
            <div class="dashboard-card">
                <div class="card-header-custom">
                    <h6 class="card-title-custom">Quick Actions</h6>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="evaluation_notifications.php" class="quick-action-btn">
                        <i class="fas fa-bell me-1"></i> View Notifications
                    </a>
                    <a href="assignments.php" class="quick-action-btn">
                        <i class="fas fa-tasks me-1"></i> All Assignments
                    </a>
                    <a href="evaluation_history.php" class="quick-action-btn">
                        <i class="fas fa-history me-1"></i> History
                    </a>
                    <a href="../auth/logout.php" class="quick-action-btn btn-danger" onclick="return confirm('Are you sure you want to logout?')">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh notifications every 60 seconds
setInterval(() => {
    // Only refresh if there are no pending actions
    if (!document.querySelector('button:disabled')) {
        // Could implement AJAX refresh for notifications here
    }
}, 60000);
</script>

<?php include '../includes/footer.php'; ?>