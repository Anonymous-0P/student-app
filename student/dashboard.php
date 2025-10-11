<?php
include('../config/config.php');
include('../includes/header.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student'){
    header("Location: ../auth/login.php");
    exit;
}

// Get student statistics
$student_id = $_SESSION['user_id'];
$stats = $conn->query("SELECT 
    COUNT(*) as total_submissions,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_submissions,
    SUM(CASE WHEN status = 'evaluated' THEN 1 ELSE 0 END) as evaluated_submissions,
    AVG(CASE WHEN marks IS NOT NULL AND total_marks IS NOT NULL THEN (marks/total_marks)*100 END) as avg_percentage
    FROM submissions WHERE student_id = $student_id")->fetch_assoc();

// Get recent submissions
$recent_submissions = $conn->query("SELECT s.*, sub.code as subject_code, sub.name as subject_name 
    FROM submissions s 
    LEFT JOIN subjects sub ON s.subject_id = sub.id 
    WHERE s.student_id = $student_id 
    ORDER BY s.created_at DESC 
    LIMIT 3");

// Get available subjects count
$subjects_count = $conn->query("SELECT COUNT(*) as count FROM subjects WHERE is_active = 1")->fetch_assoc()['count'];

// Get user name for greeting
$user_name = $_SESSION['name'] ?? 'Student';
$stmt = $conn->prepare("SELECT name, roll_no, course, year, department FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
if($user_info) {
    $user_name = $user_info['name'];
}
?>

<style>
.dashboard-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.dashboard-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
    pointer-events: none;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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

.action-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.2);
    text-decoration: none;
    color: inherit;
}

.action-card:hover::after {
    opacity: 1;
}

.action-icon {
    font-size: 2.5rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 1rem;
}

.recent-item {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.recent-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-left-color: #667eea;
}

.status-pending { border-left-color: #ffc107 !important; }
.status-evaluated { border-left-color: #28a745 !important; }
.status-returned { border-left-color: #17a2b8 !important; }

.greeting-section {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(102, 126, 234, 0.2);
}

.pulse-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.bounce-in {
    animation: bounceIn 0.8s ease-out;
}

@keyframes bounceIn {
    0% { opacity: 0; transform: scale(0.3); }
    50% { opacity: 1; transform: scale(1.05); }
    70% { transform: scale(0.9); }
    100% { opacity: 1; transform: scale(1); }
}

.fade-in-up {
    animation: fadeInUp 0.6s ease-out forwards;
    opacity: 0;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.floating {
    animation: floating 3s ease-in-out infinite;
}

@keyframes floating {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}
</style>

<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="greeting-section bounce-in">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h3 mb-2">Welcome back, <span class="text-primary"><?= htmlspecialchars($user_name) ?>!</span></h1>
                <?php if($user_info && $user_info['roll_no']): ?>
                    <p class="text-muted mb-0">
                        Roll No: <?= htmlspecialchars($user_info['roll_no']) ?> 
                        <?php if($user_info['course']): ?>
                            • <?= htmlspecialchars($user_info['course']) ?>
                            <?php if($user_info['year']): ?>
                                (Year <?= $user_info['year'] ?>)
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="floating">
                    <span style="font-size: 3rem;">🎓</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 fade-in-up" style="animation-delay: 0.1s;">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="h4 mb-1 text-primary"><?= $stats['total_submissions'] ?></h3>
                        <p class="text-muted mb-0 small">Total Submissions</p>
                    </div>
                    <div class="text-primary" style="font-size: 2rem;">📄</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in-up" style="animation-delay: 0.2s;">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="h4 mb-1 text-warning"><?= $stats['pending_submissions'] ?></h3>
                        <p class="text-muted mb-0 small">Pending Review</p>
                    </div>
                    <div class="text-warning" style="font-size: 2rem;">⏳</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in-up" style="animation-delay: 0.3s;">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="h4 mb-1 text-success"><?= $stats['evaluated_submissions'] ?></h3>
                        <p class="text-muted mb-0 small">Evaluated</p>
                    </div>
                    <div class="text-success" style="font-size: 2rem;">✅</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in-up" style="animation-delay: 0.4s;">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="h4 mb-1 text-info">
                            <?= $stats['avg_percentage'] ? number_format($stats['avg_percentage'], 1) . '%' : '-' ?>
                        </h3>
                        <p class="text-muted mb-0 small">Average Score</p>
                    </div>
                    <div class="text-info" style="font-size: 2rem;">📊</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Cards -->
    <div class="row g-4 mb-4 justify-content-center">
        <div class="col-md-5 fade-in-up" style="animation-delay: 0.5s;">
            <a href="subjects.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon">📚</div>
                    <h5 class="mb-2">Browse Subjects</h5>
                    <p class="text-muted mb-0"><?= $subjects_count ?> subjects available for question papers</p>
                </div>
            </a>
        </div>
        <div class="col-md-5 fade-in-up" style="animation-delay: 0.6s;">
            <a href="view_submissions.php" class="action-card">
                <div class="text-center">
                    <div class="action-icon">📋</div>
                    <h5 class="mb-2">View Submissions</h5>
                    <p class="text-muted mb-0">Track your submission status and marks</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Recent Submissions -->
    <?php if($recent_submissions->num_rows > 0): ?>
    <div class="row">
        <div class="col-12 fade-in-up" style="animation-delay: 0.8s;">
            <div class="dashboard-card">
                <div class="card-body text-white">
                    <h5 class="card-title mb-4">📋 Recent Submissions</h5>
                    <div class="row">
                        <?php while($submission = $recent_submissions->fetch_assoc()): ?>
                            <div class="col-md-4 mb-3">
                                <div class="recent-item status-<?= $submission['status'] ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <?php if($submission['subject_code']): ?>
                                                <h6 class="mb-1 text-dark"><?= htmlspecialchars($submission['subject_code']) ?></h6>
                                                <p class="small text-muted mb-1"><?= htmlspecialchars($submission['subject_name']) ?></p>
                                            <?php else: ?>
                                                <h6 class="mb-1 text-muted">No Subject</h6>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-<?= $submission['status'] == 'pending' ? 'warning' : ($submission['status'] == 'evaluated' ? 'success' : 'info') ?>">
                                            <?= ucfirst($submission['status']) ?>
                                        </span>
                                    </div>
                                    <p class="small text-muted mb-2">
                                        <?= date('M j, Y • g:i A', strtotime($submission['created_at'])) ?>
                                    </p>
                                    <?php if($submission['marks'] !== null): ?>
                                        <div class="small">
                                            <strong class="text-success">
                                                <?= number_format($submission['marks'], 1) ?>
                                                <?php if($submission['total_marks']): ?>
                                                    / <?= number_format($submission['total_marks'], 1) ?>
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="view_submissions.php" class="btn btn-light btn-sm">
                            View All Submissions →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Add some interactive effects
document.addEventListener('DOMContentLoaded', function() {
    // Add hover sound effect (optional)
    const actionCards = document.querySelectorAll('.action-card');
    actionCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Animate statistics on scroll
    const statCards = document.querySelectorAll('.stat-card h3');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const number = parseInt(entry.target.textContent);
                animateNumber(entry.target, 0, number, 1000);
                observer.unobserve(entry.target);
            }
        });
    });

    statCards.forEach(card => observer.observe(card));

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
