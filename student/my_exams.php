<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];

// Get purchased subjects
$purchased_query = "SELECT ps.*, s.code, s.name, s.description, s.department, s.year, s.semester, s.duration_days,
                    DATEDIFF(ps.expiry_date, CURDATE()) as days_remaining,
                    CASE WHEN ps.expiry_date < CURDATE() THEN 1 ELSE 0 END as is_expired
                    FROM purchased_subjects ps
                    JOIN subjects s ON ps.subject_id = s.id
                    WHERE ps.student_id = ?
                    ORDER BY ps.purchase_date DESC";

$purchased_stmt = $conn->prepare($purchased_query);
$purchased_stmt->bind_param("i", $student_id);
$purchased_stmt->execute();
$purchased_subjects = $purchased_stmt->get_result();

// Calculate statistics
$total_purchased = 0;
$active_count = 0;
$expired_count = 0;
$total_spent = 0;

$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'expired' OR expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired,
                SUM(price_paid) as total_spent
                FROM purchased_subjects
                WHERE student_id = ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $student_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$pageTitle = "My Exams";
require_once('../includes/header.php');
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<style>
/* Additional styles for my exams */
.my-exams-content {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #ffffff;
    color: var(--text-dark);
    line-height: 1.6;
}

.exam-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    height: 100%;
    transition: all 0.2s;
    position: relative;
}

.exam-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.exam-card.expired {
    opacity: 0.6;
    background: #f9fafb;
}

.exam-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.exam-code {
    font-size: 0.875rem;
    color: var(--primary-color);
    margin-bottom: 0.75rem;
}

.exam-description {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.exam-meta {
    font-size: 0.8125rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.expiry-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: 6px;
    margin-bottom: 1rem;
}

.expiry-info.warning {
    background: #fff3cd;
    color: #856404;
}

.expiry-info.expired {
    background: #f8d7da;
    color: #721c24;
}

.expiry-info.active {
    background: #d4edda;
    color: #155724;
}

.exams-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.empty-exams {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-exams i {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .exams-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="my-exams-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-graduation-cap"></i> My Purchased Exams</h1>
                    <p>Access and manage your purchased exam subjects</p>
                </div>
                <div>
                    <a href="browse_exams.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Browse More Exams
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">

        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-icon" style="background-color: rgba(37, 99, 235, 0.1); color: #2563eb;">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?= $stats['total'] ?></div>
                        <div class="stat-label">Total Purchased</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?= $stats['active'] ?></div>
                        <div class="stat-label">Active Access</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-icon" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?= $stats['expired'] ?></div>
                        <div class="stat-label">Expired</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value">₹<?= number_format($stats['total_spent'], 0) ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchased Exams Grid -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fas fa-book-open"></i> My Exam Subjects</h5>
            </div>
            
            <?php if ($purchased_subjects->num_rows > 0): ?>
                <div class="exams-grid">
                    <?php while ($exam = $purchased_subjects->fetch_assoc()): ?>
                        <div class="exam-card <?= $exam['is_expired'] ? 'expired' : '' ?>">
                            <div class="d-flex flex-column h-100">
                                <div class="exam-code"><?= htmlspecialchars($exam['code']) ?></div>
                                <h6 class="exam-title"><?= htmlspecialchars($exam['name']) ?></h6>
                                
                                <?php if ($exam['description']): ?>
                                    <p class="exam-description">
                                        <?= htmlspecialchars($exam['description']) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="exam-meta">
                                    <span class="badge bg-secondary" style="background: #6b7280 !important; color: white !important;"><?= htmlspecialchars($exam['department']) ?></span>
                                    <?php if ($exam['year']): ?>
                                        <span class="me-2">Year <?= $exam['year'] ?></span>
                                    <?php endif; ?>
                                    <?php if ($exam['semester']): ?>
                                        <span>Sem <?= $exam['semester'] ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Expiry Information -->
                                <?php if ($exam['is_expired']): ?>
                                    <div class="expiry-info expired">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <span>Access expired on <?= date('M j, Y', strtotime($exam['expiry_date'])) ?></span>
                                    </div>
                                <?php elseif ($exam['days_remaining'] <= 7): ?>
                                    <div class="expiry-info warning">
                                        <i class="fas fa-clock"></i>
                                        <span>Expires in <?= $exam['days_remaining'] ?> day(s)</span>
                                    </div>
                                <?php else: ?>
                                    <div class="expiry-info active">
                                        <i class="fas fa-check-circle"></i>
                                        <span><?= $exam['days_remaining'] ?> days remaining</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Purchase Info -->
                                <div class="mb-3">
                                    <small class="text-muted">
                                        Purchased on <?= date('M j, Y', strtotime($exam['purchase_date'])) ?>
                                        <br>
                                        Payment: ₹<?= number_format($exam['price_paid'], 2) ?>
                                    </small>
                                </div>

                                <!-- Action Button -->
                                <div class="mt-auto">
                                    <?php if ($exam['is_expired']): ?>
                                        <button class="btn btn-outline-secondary w-100" disabled>
                                            <i class="fas fa-lock"></i> Access Expired
                                        </button>
                                    <?php else: ?>
                                        <a href="question_papers.php?subject_id=<?= $exam['subject_id'] ?>" class="btn btn-primary w-100">
                                            <i class="fas fa-file-alt"></i> View Question Papers
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-exams">
                    <i class="fas fa-graduation-cap"></i>
                    <h5>No exams purchased yet</h5>
                    <p class="text-muted mb-4">Start building your exam library by browsing and purchasing subjects.</p>
                    <a href="browse_exams.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-book-open"></i> Browse Exams
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>
