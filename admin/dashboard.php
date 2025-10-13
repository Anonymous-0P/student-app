<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

checkLogin('admin');

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
?>

<style>
.admin-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
}

.admin-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.admin-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.stat-card::after {
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

.action-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: none;
    text-decoration: none;
    color: inherit;
    display: block;
    position: relative;
    overflow: hidden;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.2);
    text-decoration: none;
    color: inherit;
}

.action-icon {
    font-size: 2.5rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 1rem;
}

.progress-bar-custom {
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.table-admin {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.fade-in {
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4 fade-in">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">Admin Dashboard</h1>
                    <p class="text-muted mb-0">Comprehensive system overview and management</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="reports.php" class="btn btn-outline-primary">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <a href="export.php" class="btn btn-primary">
                        <i class="fas fa-download"></i> Export Data
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 fade-in" style="animation-delay: 0.1s;">
            <div class="stat-card text-center">
                <div class="h2 mb-1"><?= $stats['total_submissions'] ?></div>
                <div class="small opacity-75">Total Submissions</div>
                <div class="mt-2">
                    <small class="badge bg-light text-dark">
                        <?= $stats['pending_submissions'] ?> Pending
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in" style="animation-delay: 0.2s;">
            <div class="stat-card text-center">
                <div class="h2 mb-1"><?= $stats['total_students'] ?></div>
                <div class="small opacity-75">Active Students</div>
                <div class="mt-2">
                    <small class="badge bg-light text-dark">
                        <?= $stats['total_subjects'] ?> Subjects
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in" style="animation-delay: 0.3s;">
            <div class="stat-card text-center">
                <div class="h2 mb-1"><?= $stats['total_evaluators'] ?></div>
                <div class="small opacity-75">Evaluators</div>
                <div class="mt-2">
                    <small class="badge bg-light text-dark">
                        <?= $stats['total_moderators'] ?> Moderators
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in" style="animation-delay: 0.4s;">
            <div class="stat-card text-center">
                <div class="h2 mb-1">
                    <?= $stats['evaluated_submissions'] > 0 ? round(($stats['evaluated_submissions']/$stats['total_submissions'])*100) : 0 ?>%
                </div>
                <div class="small opacity-75">Evaluation Progress</div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-light" style="width: <?= $stats['evaluated_submissions'] > 0 ? round(($stats['evaluated_submissions']/$stats['total_submissions'])*100) : 0 ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 fade-in" style="animation-delay: 0.5s;">
            <a href="manage_users.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon">👥</div>
                    <h6 class="mb-2">User Management</h6>
                    <p class="small text-muted mb-0">Manage students, evaluators, moderators</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 fade-in" style="animation-delay: 0.6s;">
            <a href="manage_assignments.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon">📋</div>
                    <h6 class="mb-2">Assignment Logic</h6>
                    <p class="small text-muted mb-0">Configure moderator-evaluator mapping</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 fade-in" style="animation-delay: 0.7s;">
            <a href="answer_sheets.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon">📄</div>
                    <h6 class="mb-2">Answer Sheets</h6>
                    <p class="small text-muted mb-0">View, verify, and approve uploads</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 fade-in" style="animation-delay: 0.8s;">
            <a href="analytics.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon">📊</div>
                    <h6 class="mb-2">Analytics</h6>
                    <p class="small text-muted mb-0">Performance insights and trends</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Data Tables Row -->
    <div class="row g-4">
        <!-- Subject Performance -->
        <div class="col-md-6 fade-in" style="animation-delay: 0.9s;">
            <div class="admin-card">
                <h5 class="mb-3">📚 Subject Performance</h5>
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
        </div>

        <!-- Evaluator Performance -->
        <div class="col-md-6 fade-in" style="animation-delay: 1.0s;">
            <div class="admin-card">
                <h5 class="mb-3">👨‍🏫 Evaluator Performance</h5>
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
        </div>

        <!-- Recent Submissions -->
        <div class="col-12 fade-in" style="animation-delay: 1.1s;">
            <div class="admin-card">
                <h5 class="mb-3">🕒 Recent Submissions</h5>
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
                                                👁️
                                            </a>
                                            <a href="manage_assignments.php?submission=<?= $row['id'] ?>" class="btn btn-sm btn-outline-success" title="Assign">
                                                📝
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
        </div>
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

<?php include('../includes/footer.php'); ?>