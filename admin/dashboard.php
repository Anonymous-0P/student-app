<?php
include('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Don't include header.php as we'll create a custom integrated header
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ThetaExams</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Moderator Style CSS -->
    <link rel="stylesheet" href="../moderator/css/moderator-style.css">
</head>
<body style="background: #f9fafb;">

<!-- Modern Clean Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm" style="border-bottom: 1px solid #e5e7eb;">
    <div class="container-fluid">
        <!-- Hamburger Menu Button -->
        <button class="navbar-toggler border-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMenuOffcanvas" aria-controls="adminMenuOffcanvas">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="../index.php" style="color: var(--primary-color); font-weight: 600;">
            <i class="fas fa-graduation-cap me-2 fs-4"></i>
            <span>ThetaExams</span>
        </a>
        
        <!-- User Info & Logout -->
        <div class="d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" style="border-radius: 6px;">
                    <i class="fas fa-user-circle me-1"></i>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                    <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Clean Offcanvas Menu -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="adminMenuOffcanvas" aria-labelledby="adminMenuOffcanvasLabel" style="width: 280px; border-right: 1px solid #e5e7eb;">
    <div class="offcanvas-header" style="background: white; border-bottom: 1px solid #e5e7eb;">
        <h5 class="offcanvas-title" id="adminMenuOffcanvasLabel" style="color: var(--text-dark); font-weight: 600; font-size: 1.125rem;">
            <i class="fas fa-user-shield me-2" style="color: var(--primary-color);"></i>Admin Panel
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0" style="background: white;">
        <!-- User Info -->
        <div class="p-3 border-bottom" style="background: var(--bg-light);">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted" style="font-weight: 500;">Welcome back!</small>
                <span class="badge bg-success" style="background: #10b981 !important; color: white !important;">Online</span>
            </div>
            <h6 class="mb-0" style="color: var(--text-dark); font-weight: 600;"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></h6>
        </div>
        
        <div class="nav-section">
            <h6 class="nav-section-title">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </h6>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-home me-2"></i>Dashboard Home
                        <span class="badge bg-primary ms-auto">Active</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-line me-2"></i>Analytics
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-section">
            <h6 class="nav-section-title">
                <i class="fas fa-users me-2"></i>User Management
            </h6>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="manage_students.php">
                        <i class="fas fa-user-graduate me-2"></i>Students
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_moderators.php">
                        <i class="fas fa-user-tie me-2"></i>Moderators
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_evaluators.php">
                        <i class="fas fa-user-check me-2"></i>Evaluators
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-section">
            <h6 class="nav-section-title">
                <i class="fas fa-file-alt me-2"></i>Content Management
            </h6>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="answer_sheets.php">
                        <i class="fas fa-file-alt me-2"></i>Answer Sheets
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="subjects.php">
                        <i class="fas fa-book me-2"></i>Subjects
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_question_papers.php">
                        <i class="fas fa-file-upload me-2"></i>Question Papers
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-section">
            <h6 class="nav-section-title">
                <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
            </h6>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-line me-2"></i>Analytics Dashboard
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-file-chart-column me-2"></i>System Reports
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-section">
            <h6 class="nav-section-title">
                <i class="fas fa-cog me-2"></i>System & Tools
            </h6>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-tools me-2"></i>Settings
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="export.php">
                        <i class="fas fa-download me-2"></i>Export Data
                        <span class="nav-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Footer Section -->
        <div class="mt-auto p-3 border-top bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">Admin Panel v2.0</small>
                <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional admin-specific styles extending moderator-style.css */
.admin-content {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f9fafb;
    color: var(--text-dark);
    line-height: 1.6;
}

/* Evaluator Avatar Circle */
.evaluator-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
    margin-right: 10px;
    vertical-align: middle;
}

/* Offcanvas Menu Styles */
.offcanvas-body .nav-section {
    margin-bottom: 0.5rem;
    padding: 0 1rem;
}

.offcanvas-body .nav-section:first-of-type {
    margin-top: 1rem;
}

.offcanvas-body .nav-section-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    padding: 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border-color);
}

.offcanvas-body .nav-link {
    color: var(--text-dark) !important;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    margin: 0.125rem 0;
    transition: all 0.2s ease;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.offcanvas-body .nav-link:hover {
    background: var(--primary-color);
    color: white !important;
    transform: translateX(4px);
}

.offcanvas-body .nav-link.active {
    background: var(--primary-color);
    color: white !important;
    font-weight: 600;
}

.offcanvas-body .nav-link i {
    width: 20px;
    text-align: center;
    font-size: 0.875rem;
}

.offcanvas-body .nav-arrow {
    opacity: 0.5;
    font-size: 0.75rem;
}

.offcanvas-body .nav-link:hover .nav-arrow {
    opacity: 1;
    transform: translateX(2px);
}

/* Navbar Styles */
.navbar {
    padding: 0.75rem 0;
}

.navbar-brand {
    font-size: 1.25rem;
    font-weight: 600;
}

.navbar-toggler:focus {
    box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
}

.dropdown-item {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    transition: all 0.15s ease;
}

.dropdown-item:hover {
    background: var(--bg-light);
}

/* Footer in Offcanvas */
.offcanvas-body .mt-auto {
    margin-top: auto !important;
}
</style>

<?php

// Get comprehensive statistics
$stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM submissions) as total_submissions,
    (SELECT COUNT(*) FROM submissions WHERE status = 'pending') as pending_submissions,
    (SELECT COUNT(*) FROM submissions WHERE status = 'evaluated') as evaluated_submissions,
    (SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1) as total_students,
    (SELECT COUNT(*) FROM users WHERE role = 'evaluator' AND is_active = 1) as total_evaluators,
    (SELECT COUNT(*) FROM users WHERE role = 'moderator' AND is_active = 1) as total_moderators,
    (SELECT COUNT(*) FROM subjects WHERE is_active = 1) as total_subjects
")->fetch_assoc();

// Get submissions by subject
$subjectStats = $conn->query("SELECT s.code, s.name, COUNT(sub.id) as submission_count,
    AVG(CASE WHEN sub.marks IS NOT NULL AND sub.total_marks IS NOT NULL THEN (sub.marks/sub.total_marks)*100 END) as avg_score
    FROM subjects s 
    LEFT JOIN submissions sub ON s.id = sub.subject_id 
    WHERE s.is_active = 1 
    GROUP BY s.id 
    ORDER BY submission_count DESC 
    LIMIT 5");

// Get recent submissions
$recentSubmissions = $conn->query("SELECT sub.*, s.code as subject_code, u.name as student_name, u.roll_no
    FROM submissions sub
    LEFT JOIN subjects s ON sub.subject_id = s.id
    LEFT JOIN users u ON sub.student_id = u.id
    ORDER BY sub.created_at DESC 
    LIMIT 8");

// Get evaluator performance
$evaluatorPerf = $conn->query("SELECT u.name, COUNT(s.id) as evaluations,
    AVG(CASE WHEN s.marks IS NOT NULL AND s.total_marks IS NOT NULL THEN (s.marks/s.total_marks)*100 END) as avg_score_given
    FROM users u
    LEFT JOIN submissions s ON u.id = s.evaluated_by
    WHERE u.role = 'evaluator' AND u.is_active = 1
    GROUP BY u.id
    ORDER BY evaluations DESC
    LIMIT 5");

// Check if evaluator_ratings table exists first
$tableExists = $conn->query("SHOW TABLES LIKE 'evaluator_ratings'");
$ratingsTableExists = ($tableExists && $tableExists->num_rows > 0);

if ($ratingsTableExists) {
    // Pagination for evaluator ratings
    $ratings_per_page = 5;
    $ratings_page = isset($_GET['ratings_page']) ? max(1, intval($_GET['ratings_page'])) : 1;
    $ratings_offset = ($ratings_page - 1) * $ratings_per_page;
    
    // Get total count of evaluators with ratings
    $totalRatingsCount = $conn->query("SELECT COUNT(DISTINCT u.id) as total
        FROM users u
        LEFT JOIN evaluator_ratings er ON u.id = er.evaluator_id
        WHERE u.role = 'evaluator' AND u.is_active = 1
        GROUP BY u.id
        HAVING COUNT(er.id) > 0");
    $total_evaluators_with_ratings = $totalRatingsCount ? $totalRatingsCount->num_rows : 0;
    $total_ratings_pages = ceil($total_evaluators_with_ratings / $ratings_per_page);
    
    // Get evaluator ratings summary with pagination
    $evaluatorRatings = $conn->query("SELECT 
        u.id as evaluator_id,
        u.name as evaluator_name,
        COUNT(er.id) as total_ratings,
        AVG(er.overall_rating) as avg_overall,
        COUNT(CASE WHEN er.evaluation_quality = 'excellent' THEN 1 END) as excellent_quality,
        COUNT(CASE WHEN er.evaluation_quality = 'good' THEN 1 END) as good_quality,
        COUNT(CASE WHEN er.feedback_helpfulness = 'very_helpful' THEN 1 END) as very_helpful,
        COUNT(CASE WHEN er.feedback_helpfulness = 'helpful' THEN 1 END) as helpful
        FROM users u
        LEFT JOIN evaluator_ratings er ON u.id = er.evaluator_id
        WHERE u.role = 'evaluator' AND u.is_active = 1
        GROUP BY u.id, u.name
        HAVING total_ratings > 0
        ORDER BY avg_overall DESC, total_ratings DESC
        LIMIT $ratings_per_page OFFSET $ratings_offset");

    // Get rating statistics
    $ratingStatsResult = $conn->query("SELECT 
        COUNT(*) as total_ratings,
        AVG(overall_rating) as avg_overall_rating,
        COUNT(CASE WHEN overall_rating >= 4 THEN 1 END) as excellent_ratings,
        COUNT(CASE WHEN overall_rating < 3 THEN 1 END) as poor_ratings
        FROM evaluator_ratings");
    
    $ratingStats = $ratingStatsResult ? $ratingStatsResult->fetch_assoc() : [
        'total_ratings' => 0,
        'avg_overall_rating' => 0,
        'excellent_ratings' => 0,
        'poor_ratings' => 0
    ];
    
    // Get recent evaluator feedback with comments
    $recentFeedback = $conn->query("SELECT 
        er.comments,
        er.overall_rating,
        er.evaluation_quality,
        er.feedback_helpfulness,
        er.created_at,
        evaluator.name as evaluator_name,
        student.name as student_name
        FROM evaluator_ratings er
        JOIN users evaluator ON er.evaluator_id = evaluator.id
        JOIN users student ON er.student_id = student.id
        WHERE er.comments IS NOT NULL AND er.comments != ''
        ORDER BY er.created_at DESC
        LIMIT 5");
} else {
    // Create empty result sets if table doesn't exist
    $evaluatorRatings = false;
    $recentFeedback = false;
    $ratingStats = [
        'total_ratings' => 0,
        'avg_overall_rating' => 0,
        'excellent_ratings' => 0,
        'poor_ratings' => 0
    ];
}
?>

<style>
/* Action Cards - Modernstyle matching moderator panel */
.action-card {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    transition: all 0.2s ease;
    text-decoration: none;
    color: inherit;
    display: block;
    height: 100%;
    min-height: 140px;
}

.action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    text-decoration: none;
    color: inherit;
}

.action-icon {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 0.75rem;
    display: block;
}

.action-icon i {
    font-size: 2rem;
    color: var(--primary-color);
}

.action-card h6 {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.action-card p {
    font-size: 0.8125rem;
    color: var(--text-muted);
    margin-bottom: 0;
}

.fade-in {
    animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Ensure all badges have white text */
.badge {
    color: white !important;
}
</style>

<div class="container-fluid" style="padding-left: 50px; padding-right: 50px;">
    <!-- Header -->
    <div class="row mb-4 mt-4 fade-in">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">Admin Dashboard</h1>
                    <!-- <p class="text-muted mb-0">Comprehensive system overview and management</p> -->
                </div>
               
            </div>
        </div>
    </div>
   
    <!-- Quick Actions -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 fade-in" style="animation-delay: 0.5s;">
            <a href="manage_students.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon"><i class="fas fa-user-graduate"></i></div>
                    <h6 class="mb-2">Student Management</h6>
                    <p class="small text-muted mb-0">Manage student accounts and profiles</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 fade-in" style="animation-delay: 0.6s;">
            <a href="manage_moderators.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon"><i class="fas fa-user-tie"></i></div>
                    <h6 class="mb-2">Moderator Management</h6>
                    <p class="small text-muted mb-0">Manage moderator accounts and assignments</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 fade-in" style="animation-delay: 0.7s;">
            <a href="manage_evaluators.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <h6 class="mb-2">Evaluator Management</h6>
                    <p class="small text-muted mb-0">Manage evaluator accounts and assignments</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 fade-in" style="animation-delay: 0.8s;">
            <a href="manage_evaluation_schemes.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon"><i class="fas fa-file-alt"></i></div>
                    <h6 class="mb-2">Scheme of Evaluation</h6>
                    <p class="small text-muted mb-0">View, verify, and approve uploads</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Secondary Actions -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 fade-in" style="animation-delay: 0.9s;">
            <a href="analytics.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                    <h6 class="mb-2">Analytics</h6>
                    <p class="small text-muted mb-0">Performance insights and trends</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 fade-in" style="animation-delay: 1.0s;">
            <a href="subjects.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon"><i class="fas fa-book"></i></div>
                    <h6 class="mb-2">Subject Management</h6>
                    <p class="small text-muted mb-0">Manage subjects and courses</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 fade-in" style="animation-delay: 1.1s;">
            <a href="manage_question_papers.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon"><i class="fas fa-file-upload"></i></div>
                    <h6 class="mb-2">Question Papers</h6>
                    <p class="small text-muted mb-0">Upload and manage question papers</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 fade-in" style="animation-delay: 1.2s;">
            <a href="smtp_settings.php" class="text-decoration-none">
                <div class="action-card" style="cursor: pointer;">
                    <div class="text-center">
                        <div class="action-icon"><i class="fas fa-envelope-open-text"></i></div>
                        <h6 class="mb-2">Email Configuration</h6>
                        <p class="small text-muted mb-0">Manage SMTP server settings</p>
                    </div>
                </div>
            </a>
        </div>
      
    </div>

    <!-- Data Tables Row -->
    <div class="row g-4">
        <!-- Subject Performance -->
        <!-- <div class="col-md-6 fade-in" style="animation-delay: 0.9s;">
            <div class="dashboard-card">
                <h5 class="mb-3"><i class="fas fa-book text-primary"></i> Subject Performance</h5>
                <?php if($subjectStats->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Submissions</th>
                                    <th>Avg Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $subjectStats->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['code']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['name']) ?></div>
                                    </td>
                                    <td><span class="badge bg-primary"><?= $row['submission_count'] ?></span></td>
                                    <td>
                                        <?php if($row['avg_score']): ?>
                                            <span class="text-success fw-semibold"><?= number_format($row['avg_score'], 1) ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">No subject data available</p>
                <?php endif; ?>
            </div>
        </div> -->

        <!-- Evaluator Performance -->
        <!-- <div class="col-md-6 fade-in" style="animation-delay: 1.0s;">
            <div class="dashboard-card">
                <h5 class="mb-3"><i class="fas fa-chalkboard-teacher text-info"></i> Evaluator Performance</h5>
                <?php if($evaluatorPerf->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Evaluator</th>
                                    <th>Evaluations</th>
                                    <th>Avg Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $evaluatorPerf->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                                    </td>
                                    <td><span class="badge bg-info"><?= $row['evaluations'] ?: 0 ?></span></td>
                                    <td>
                                        <?php if($row['avg_score_given']): ?>
                                            <span class="text-info fw-semibold"><?= number_format($row['avg_score_given'], 1) ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">No evaluator data available</p>
                <?php endif; ?>
            </div>
        </div> -->

        <!-- Evaluator Feedback -->
       

        <!-- Evaluator Ratings -->
        <div class="col-12 fade-in" style="animation-delay: 1.2s;">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-star text-warning"></i> Evaluator Ratings</h5>
                    <div class="d-flex gap-3">
                        <?php if($ratingStats['total_ratings'] > 0): ?>
                            <small class="text-muted">
                                <i class="fas fa-chart-line"></i> Avg: <?= number_format($ratingStats['avg_overall_rating'], 1) ?>/5
                            </small>
                            <small class="text-success">
                                <i class="fas fa-thumbs-up"></i> <?= $ratingStats['excellent_ratings'] ?> Excellent
                            </small>
                            <?php if($ratingStats['poor_ratings'] > 0): ?>
                                <small class="text-danger">
                                    <i class="fas fa-thumbs-down"></i> <?= $ratingStats['poor_ratings'] ?> Poor
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($evaluatorRatings && $evaluatorRatings->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th>Evaluator Name</th>
                                    <th>Total Reviews</th>
                                    <th>Overall Rating</th>
                                    <th>Quality</th>
                                    <th>Helpfulness</th>
                                   
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $evaluatorRatings->data_seek(0);
                                while($rating = $evaluatorRatings->fetch_assoc()): 
                                    $overallRating = round($rating['avg_overall']);
                                    $ratingClass = $rating['avg_overall'] >= 4 ? 'success' : ($rating['avg_overall'] >= 3 ? 'warning' : 'danger');
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="evaluator-avatar">
                                                    <?= strtoupper(substr($rating['evaluator_name'], 0, 1)) ?>
                                                </div>
                                                <strong><?= htmlspecialchars($rating['evaluator_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= $rating['total_ratings'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <span class="fw-bold text-<?= $ratingClass ?> me-2"><?= number_format($rating['avg_overall'], 1) ?></span>
                                                <div class="stars-small">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $overallRating ? 'text-warning' : 'text-muted' ?>" style="font-size: 0.85rem;"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column small">
                                                <span class="text-success"><i class="fas fa-check-circle"></i> <?= $rating['excellent_quality'] ?> Excellent</span>
                                                <span class="text-primary"><i class="fas fa-thumbs-up"></i> <?= $rating['good_quality'] ?> Good</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column small">
                                                <span class="text-success"><i class="fas fa-star"></i> <?= $rating['very_helpful'] ?> Very Helpful</span>
                                                <span class="text-info"><i class="fas fa-info-circle"></i> <?= $rating['helpful'] ?> Helpful</span>
                                            </div>
                                        </td>
                                        
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if($total_ratings_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted small">
                                Showing page <?= $ratings_page ?> of <?= $total_ratings_pages ?> (<?= $total_evaluators_with_ratings ?> evaluators)
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if($ratings_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?ratings_page=<?= $ratings_page - 1 ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">&laquo;</span>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for($i = 1; $i <= $total_ratings_pages; $i++): ?>
                                        <?php if($i == $ratings_page): ?>
                                            <li class="page-item active"><span class="page-link"><?= $i ?></span></li>
                                        <?php elseif($i == 1 || $i == $total_ratings_pages || abs($i - $ratings_page) <= 2): ?>
                                            <li class="page-item"><a class="page-link" href="?ratings_page=<?= $i ?>"><?= $i ?></a></li>
                                        <?php elseif(abs($i - $ratings_page) == 3): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if($ratings_page < $total_ratings_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?ratings_page=<?= $ratings_page + 1 ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">&raquo;</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailedRatingsModal">
                            <i class="fas fa-chart-bar me-2"></i> View Complete Analytics
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-star-half-alt text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                        <?php if (!$ratingsTableExists): ?>
                            <p class="text-muted mt-3">Evaluator Rating System Not Set Up</p>
                            <small class="text-muted">The evaluator_ratings table needs to be created to display rating data</small>
                        <?php else: ?>
                            <p class="text-muted mt-3">No evaluator ratings available yet</p>
                            <small class="text-muted">Ratings will appear here once students start evaluating their assigned evaluators</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Submissions -->
        <!-- <div class="col-12 fade-in" style="animation-delay: 1.1s;">
            <div class="admin-card">
                <h5 class="mb-3"><i class="fas fa-clock text-warning"></i> Recent Submissions</h5>
                <?php if($recentSubmissions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Marks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $recentSubmissions->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['student_name']) ?></div>
                                        <?php if($row['roll_no']): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($row['roll_no']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($row['subject_code']): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($row['subject_code']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">No subject</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = 'bg-warning';
                                        if($row['status'] == 'evaluated') $statusClass = 'bg-success';
                                        if($row['status'] == 'returned') $statusClass = 'bg-info';
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span>
                                    </td>
                                    <td>
                                        <div><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                                        <div class="small text-muted"><?= date('g:i A', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <?php if($row['marks'] !== null): ?>
                                            <span class="text-success fw-semibold">
                                                <?= number_format($row['marks'], 1) ?>
                                                <?php if($row['total_marks']): ?>
                                                    / <?= number_format($row['total_marks'], 1) ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="answer_sheets.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="answer_sheets.php" class="btn btn-outline-primary">View All Submissions</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">No submissions yet</p>
                <?php endif; ?>
            </div>
        </div> -->
    </div>
</div>

<script>
// Add some interactive effects
document.addEventListener('DOMContentLoaded', function() {
    // Animate numbers on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = entry.target;
                const finalNumber = parseInt(target.textContent);
                animateNumber(target, 0, finalNumber, 1000);
                observer.unobserve(target);
            }
        });
    });

    document.querySelectorAll('.stat-card .h2').forEach(el => {
        observer.observe(el);
    });

    function animateNumber(element, start, end, duration) {
        const startTime = performance.now();
        const update = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const current = Math.floor(progress * (end - start) + start);
            
            if (element.textContent.includes('%')) {
                element.textContent = current + '%';
            } else {
                element.textContent = current;
            }
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        };
        requestAnimationFrame(update);
    }
});
</script>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Additional JavaScript for enhanced interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-close offcanvas when clicking on nav links (mobile UX)
    const navLinks = document.querySelectorAll('#adminMenuOffcanvas .nav-link');
    const offcanvasElement = document.getElementById('adminMenuOffcanvas');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Don't close if it's the logout link (let the confirmation work)
            if (!this.href.includes('logout.php')) {
                // Small delay to allow the link to register
                setTimeout(() => {
                    const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasElement);
                    if (offcanvas) {
                        offcanvas.hide();
                    }
                }, 100);
            }
        });
    });
    
    // Add ripple effect to navbar toggler
    const navbarToggler = document.querySelector('.navbar-toggler');
    if (navbarToggler) {
        navbarToggler.addEventListener('click', function(e) {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            }, 100);
        });
    }
    
    // Smooth scroll enhancement for offcanvas
    const offcanvas = document.getElementById('adminMenuOffcanvas');
    if (offcanvas) {
        offcanvas.addEventListener('show.bs.offcanvas', function() {
            document.body.style.overflow = 'hidden';
        });
        
        offcanvas.addEventListener('hidden.bs.offcanvas', function() {
            document.body.style.overflow = '';
        });
    }
});
</script>

<!-- All Feedback Modal -->
<div class="modal fade" id="allFeedbackModal" tabindex="-1" aria-labelledby="allFeedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allFeedbackModalLabel">
                    <i class="fas fa-comments text-success"></i> All Evaluator Feedback
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="allFeedbackContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading feedback...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load all feedback when modal is opened
document.getElementById('allFeedbackModal').addEventListener('show.bs.modal', function() {
    fetch('get_all_feedback.php')
        .then(response => response.json())
        .then(data => {
            let content = '';
            
            if (data.success && data.feedback.length > 0) {
                content = `
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3>${data.stats.total_feedback}</h3>
                                    <small>Total Feedback</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h3>${data.stats.avg_rating}</h3>
                                    <small>Average Rating</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h3>${data.stats.with_comments}</h3>
                                    <small>With Comments</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                `;
                
                data.feedback.forEach(feedback => {
                    const stars = (num) => {
                        let stars = '';
                        for(let i = 1; i <= 5; i++) {
                            stars += `<i class="fas fa-star ${i <= num ? 'text-warning' : 'text-muted'}"></i>`;
                        }
                        return stars;
                    };
                    
                    content += `
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0">${feedback.evaluator_name}</h6>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-warning me-2">${feedback.overall_rating}/5</span>
                                            <div class="stars-small">${stars(feedback.overall_rating)}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <span class="badge bg-${feedback.evaluation_quality === 'excellent' ? 'success' : feedback.evaluation_quality === 'good' ? 'primary' : 'secondary'} me-1">
                                            ${feedback.evaluation_quality}
                                        </span>
                                        <span class="badge bg-${feedback.feedback_helpfulness === 'very_helpful' ? 'success' : feedback.feedback_helpfulness === 'helpful' ? 'primary' : 'secondary'}">
                                            ${feedback.feedback_helpfulness.replace('_', ' ')}
                                        </span>
                                    </div>
                                    
                                    <blockquote class="blockquote">
                                        <p class="mb-2 small">"${feedback.comments}"</p>
                                        <footer class="blockquote-footer">
                                            <small>by <cite title="Student">${feedback.student_name}</cite> on ${new Date(feedback.created_at).toLocaleDateString()}</small>
                                        </footer>
                                    </blockquote>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                content += `</div>`;
            } else {
                content = `
                    <div class="text-center py-5">
                        <i class="fas fa-comment-slash text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h5 class="mt-3 text-muted">No feedback available</h5>
                        <p class="text-muted">Student feedback comments will appear here once they start rating evaluators</p>
                    </div>
                `;
            }
            
            document.getElementById('allFeedbackContent').innerHTML = content;
        })
        .catch(error => {
            console.error('Error loading feedback:', error);
            document.getElementById('allFeedbackContent').innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 text-warning">Error Loading Feedback</h5>
                    <p class="text-muted">Please try again later</p>
                </div>
            `;
        });
});
</script>

<style>
.blockquote-sm {
    border-left: 3px solid #dee2e6;
    padding-left: 1rem;
    margin-left: 0;
}

.modal-header {
    background-color: var(--bg-light);
    border-bottom: 1px solid var(--border-color);
}

.modal-header .btn-close {
    filter: none;
}
</style>

<!-- Detailed Ratings Modal -->
<div class="modal fade" id="detailedRatingsModal" tabindex="-1" aria-labelledby="detailedRatingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailedRatingsModalLabel">
                    <i class="fas fa-star text-warning"></i> Detailed Evaluator Ratings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
document.getElementById('detailedRatingsModal').addEventListener('show.bs.modal', function() {
    fetch('get_detailed_ratings.php')
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
                        <p class="text-muted">Detailed ratings will appear here once students start evaluating evaluators</p>
                    </div>
                `;
            }
            
            document.getElementById('ratingsContent').innerHTML = content;
        })
        .catch(error => {
            console.error('Error loading ratings:', error);
            document.getElementById('ratingsContent').innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 text-warning">Error Loading Ratings</h5>
                    <p class="text-muted">Please try again later</p>
                </div>
            `;
        });
});

// Function to scroll to ratings section
function scrollToRatings() {
    // Find the evaluator ratings section
    const ratingsElements = document.querySelectorAll('h5');
    let ratingsSection = null;
    
    ratingsElements.forEach(element => {
        if (element.textContent.includes('Evaluator Ratings')) {
            ratingsSection = element.closest('.dashboard-card, .col-12');
        }
    });
    
    if (ratingsSection) {
        ratingsSection.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
        // Add a subtle highlight effect
        ratingsSection.style.transition = 'box-shadow 0.3s ease';
        ratingsSection.style.boxShadow = '0 0 20px rgba(102, 126, 234, 0.3)';
        setTimeout(() => {
            ratingsSection.style.boxShadow = '';
        }, 2000);
    }
}
</script>

<style>
.stars-small .fas {
    font-size: 0.8rem;
}

.badge.bg-outline-secondary {
    background: transparent !important;
    border: 1px solid #6c757d;
    color: #6c757d;
}
</style>

</body>
</html>