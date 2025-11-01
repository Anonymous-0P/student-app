<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];
$subject_id = (int)($_GET['id'] ?? 0);

// Verify student has purchased this subject
$check_stmt = $conn->prepare("
    SELECT ps.*, s.code, s.name, s.description, s.department, s.year, s.semester, s.duration_days,
           DATEDIFF(ps.expiry_date, CURDATE()) as days_remaining
    FROM purchased_subjects ps
    JOIN subjects s ON ps.subject_id = s.id
    WHERE ps.student_id = ? AND ps.subject_id = ? AND ps.status = 'active'
");
$check_stmt->bind_param("ii", $student_id, $subject_id);
$check_stmt->execute();
$subject = $check_stmt->get_result()->fetch_assoc();

if (!$subject) {
    header("Location: browse_exams.php");
    exit();
}

$pageTitle = $subject['name'] . " - Subject Details";
require_once('../includes/header.php');
?>

<div class="container">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="browse_exams.php">Browse Exams</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($subject['code']) ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Subject Info -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-book me-2"></i>
                        <?= htmlspecialchars($subject['code']) ?> - <?= htmlspecialchars($subject['name']) ?>
                    </h4>
                </div>
                <div class="card-body">
                    <p class="lead"><?= htmlspecialchars($subject['description'] ?: 'No description available.') ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-building text-muted me-2"></i>
                                <span><strong>Department:</strong> <?= htmlspecialchars($subject['department']) ?></span>
                            </div>
                            <?php if ($subject['year']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-calendar text-muted me-2"></i>
                                    <span><strong>Year:</strong> <?= $subject['year'] ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($subject['semester']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-calendar-alt text-muted me-2"></i>
                                    <span><strong>Semester:</strong> <?= $subject['semester'] ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-clock text-muted me-2"></i>
                                <span><strong>Access Remaining:</strong> 
                                    <?php if ($subject['days_remaining'] > 0): ?>
                                        <span class="text-success"><?= $subject['days_remaining'] ?> days</span>
                                    <?php else: ?>
                                        <span class="text-danger">Expired</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-calendar-check text-muted me-2"></i>
                                <span><strong>Purchased:</strong> <?= date('M j, Y', strtotime($subject['purchase_date'])) ?></span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-dollar-sign text-muted me-2"></i>
                                <span><strong>Price Paid:</strong> $<?= number_format($subject['price_paid'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Content (Placeholder) -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Course Content</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Coming Soon!</strong> The full course content with interactive lessons, practice tests, and detailed explanations will be available here.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center p-3 border rounded">
                                <i class="fas fa-play-circle fa-2x text-primary me-3"></i>
                                <div>
                                    <h6 class="mb-1">Video Lectures</h6>
                                    <small class="text-muted">Interactive video content</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center p-3 border rounded">
                                <i class="fas fa-file-alt fa-2x text-success me-3"></i>
                                <div>
                                    <h6 class="mb-1">Study Materials</h6>
                                    <small class="text-muted">PDFs and reading materials</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center p-3 border rounded">
                                <i class="fas fa-tasks fa-2x text-warning me-3"></i>
                                <div>
                                    <h6 class="mb-1">Practice Tests</h6>
                                    <small class="text-muted">Mock exams and quizzes</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center p-3 border rounded">
                                <i class="fas fa-chart-line fa-2x text-info me-3"></i>
                                <div>
                                    <h6 class="mb-1">Progress Tracking</h6>
                                    <small class="text-muted">Monitor your learning</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" disabled>
                            <i class="fas fa-play"></i> Start Learning
                        </button>
                        <button class="btn btn-outline-secondary" disabled>
                            <i class="fas fa-tasks"></i> Take Practice Test
                        </button>
                        <button class="btn btn-outline-info" disabled>
                            <i class="fas fa-chart-line"></i> View Progress
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                    
                    <div class="mt-3 p-3 bg-light rounded">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            These features are currently under development and will be available soon.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Access Information -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-key me-2"></i>Access Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php if ($subject['days_remaining'] > 30): ?>
                            <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                            <h6 class="text-success">Full Access</h6>
                        <?php elseif ($subject['days_remaining'] > 0): ?>
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-2"></i>
                            <h6 class="text-warning">Expiring Soon</h6>
                        <?php else: ?>
                            <i class="fas fa-times-circle fa-3x text-danger mb-2"></i>
                            <h6 class="text-danger">Access Expired</h6>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-1">
                            <strong>
                                <?php if ($subject['days_remaining'] > 0): ?>
                                    <?= $subject['days_remaining'] ?> days remaining
                                <?php else: ?>
                                    Access has expired
                                <?php endif; ?>
                            </strong>
                        </p>
                        <small class="text-muted">
                            Expires on <?= date('M j, Y', strtotime($subject['expiry_date'])) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>