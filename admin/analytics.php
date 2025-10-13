<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Submission Analytics
$submissionStats = $conn->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_submissions,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM submissions 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$submissionStats->bind_param("ss", $start_date, $end_date);
$submissionStats->execute();
$dailyStats = $submissionStats->get_result()->fetch_all(MYSQLI_ASSOC);

// Subject Performance
$subjectPerformance = $conn->prepare("
    SELECT 
        s.name as subject_name,
        s.code as subject_code,
        COUNT(sub.id) as total_submissions,
        SUM(CASE WHEN sub.status = 'approved' THEN 1 ELSE 0 END) as approved,
        ROUND(SUM(CASE WHEN sub.status = 'approved' THEN 1 ELSE 0 END) * 100.0 / COUNT(sub.id), 1) as approval_rate
    FROM subjects s
    LEFT JOIN submissions sub ON s.id = sub.subject_id 
        AND DATE(sub.created_at) BETWEEN ? AND ?
    GROUP BY s.id, s.name, s.code
    HAVING total_submissions > 0
    ORDER BY approval_rate DESC
");
$subjectPerformance->bind_param("ss", $start_date, $end_date);
$subjectPerformance->execute();
$subjectStats = $subjectPerformance->get_result()->fetch_all(MYSQLI_ASSOC);

// Evaluator Performance
$evaluatorPerformance = $conn->query("
    SELECT 
        u.name as evaluator_name,
        COUNT(DISTINCT ea.subject_id) as subjects_assigned,
        COUNT(DISTINCT ea.moderator_id) as moderators_working_with,
        (SELECT COUNT(*) FROM submissions s 
         JOIN subjects sub ON s.subject_id = sub.id 
         JOIN evaluation_assignments ea2 ON sub.id = ea2.subject_id 
         WHERE ea2.evaluator_id = u.id AND s.status != 'pending') as evaluations_completed
    FROM users u
    LEFT JOIN evaluation_assignments ea ON u.id = ea.evaluator_id AND ea.is_active = 1
    WHERE u.role = 'evaluator' AND u.is_active = 1
    GROUP BY u.id, u.name
    ORDER BY evaluations_completed DESC
");

// Student Activity
$studentActivity = $conn->prepare("
    SELECT 
        u.name as student_name,
        u.roll_no,
        u.course,
        COUNT(s.id) as total_submissions,
        SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) as approved,
        MAX(s.created_at) as last_submission
    FROM users u
    LEFT JOIN submissions s ON u.id = s.student_id 
        AND DATE(s.created_at) BETWEEN ? AND ?
    WHERE u.role = 'student' AND u.is_active = 1
    GROUP BY u.id, u.name, u.roll_no, u.course
    HAVING total_submissions > 0
    ORDER BY total_submissions DESC
    LIMIT 20
");
$studentActivity->bind_param("ss", $start_date, $end_date);
$studentActivity->execute();
$studentStats = $studentActivity->get_result()->fetch_all(MYSQLI_ASSOC);

// Overall Statistics
$overallStats = $conn->prepare("
    SELECT 
        COUNT(*) as total_submissions,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        COUNT(DISTINCT student_id) as unique_students,
        COUNT(DISTINCT subject_id) as subjects_used
    FROM submissions 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$overallStats->bind_param("ss", $start_date, $end_date);
$overallStats->execute();
$overall = $overallStats->get_result()->fetch_assoc();

// System Health
$systemHealth = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1) as active_students,
        (SELECT COUNT(*) FROM users WHERE role = 'evaluator' AND is_active = 1) as active_evaluators,
        (SELECT COUNT(*) FROM users WHERE role = 'moderator' AND is_active = 1) as active_moderators,
        (SELECT COUNT(*) FROM evaluation_assignments WHERE is_active = 1) as active_assignments,
        (SELECT COUNT(*) FROM subjects) as total_subjects
")->fetch_assoc();
?>

<style>
.analytics-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: none;
    margin-bottom: 1rem;
}

.analytics-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.metric-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: transform 0.3s ease;
    height: 100%;
}

.metric-card:hover {
    transform: translateY(-5px);
}

.chart-container {
    position: relative;
    height: 300px;
    margin: 1rem 0;
}

.progress-ring {
    transform: rotate(-90deg);
}

.progress-ring-circle {
    fill: transparent;
    stroke: #e9ecef;
    stroke-width: 4;
}

.progress-ring-progress {
    fill: transparent;
    stroke: #007bff;
    stroke-width: 4;
    stroke-linecap: round;
    transition: stroke-dasharray 0.3s ease;
}

.performance-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.performance-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.performance-excellent { background: linear-gradient(90deg, #28a745, #20c997); }
.performance-good { background: linear-gradient(90deg, #20c997, #17a2b8); }
.performance-average { background: linear-gradient(90deg, #ffc107, #fd7e14); }
.performance-poor { background: linear-gradient(90deg, #fd7e14, #dc3545); }

.fade-in {
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.export-btn {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: transform 0.3s ease;
}

.export-btn:hover {
    transform: translateY(-2px);
    color: white;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4 fade-in">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">Analytics & Reports</h1>
                    <p class="text-muted mb-0">Comprehensive insights and performance analytics</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="export-btn" onclick="exportReport('pdf')">
                        📄 Export PDF
                    </button>
                    <button class="export-btn" onclick="exportReport('csv')">
                        📊 Export CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="analytics-card fade-in">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">📈 Update Analytics</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('week')">Last Week</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('month')">Last Month</button>
            </div>
        </form>
    </div>

    <!-- Overall Metrics -->
    <div class="row g-3 mb-4 fade-in">
        <div class="col-md-2">
            <div class="metric-card">
                <div class="h4 mb-1"><?= $overall['total_submissions'] ?></div>
                <div class="small">Total Submissions</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card">
                <div class="h4 mb-1"><?= $overall['approved'] ?></div>
                <div class="small">Approved</div>
                <div class="small opacity-75"><?= $overall['total_submissions'] > 0 ? round(($overall['approved'] / $overall['total_submissions']) * 100, 1) : 0 ?>%</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card">
                <div class="h4 mb-1"><?= $overall['rejected'] ?></div>
                <div class="small">Rejected</div>
                <div class="small opacity-75"><?= $overall['total_submissions'] > 0 ? round(($overall['rejected'] / $overall['total_submissions']) * 100, 1) : 0 ?>%</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card">
                <div class="h4 mb-1"><?= $overall['pending'] ?></div>
                <div class="small">Pending</div>
                <div class="small opacity-75"><?= $overall['total_submissions'] > 0 ? round(($overall['pending'] / $overall['total_submissions']) * 100, 1) : 0 ?>%</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card">
                <div class="h4 mb-1"><?= $overall['unique_students'] ?></div>
                <div class="small">Active Students</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card">
                <div class="h4 mb-1"><?= $overall['subjects_used'] ?></div>
                <div class="small">Subjects Used</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Daily Submissions Chart -->
            <div class="analytics-card fade-in">
                <h5 class="mb-3">📈 Daily Submission Trends</h5>
                <div class="chart-container">
                    <canvas id="submissionChart"></canvas>
                </div>
            </div>

            <!-- Subject Performance -->
            <div class="analytics-card fade-in">
                <h5 class="mb-3">📚 Subject Performance</h5>
                <?php if(empty($subjectStats)): ?>
                    <div class="text-center py-4">
                        <div class="text-muted">No subject data available for selected period</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Submissions</th>
                                    <th>Approved</th>
                                    <th>Approval Rate</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($subjectStats as $subject): ?>
                                    <?php 
                                    $rate = (float)$subject['approval_rate'];
                                    if($rate >= 85) $perfClass = 'performance-excellent';
                                    elseif($rate >= 70) $perfClass = 'performance-good';
                                    elseif($rate >= 50) $perfClass = 'performance-average';
                                    else $perfClass = 'performance-poor';
                                    ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($subject['subject_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($subject['subject_code']) ?></div>
                                    </td>
                                    <td><span class="badge bg-primary"><?= $subject['total_submissions'] ?></span></td>
                                    <td><span class="badge bg-success"><?= $subject['approved'] ?></span></td>
                                    <td><strong><?= $subject['approval_rate'] ?>%</strong></td>
                                    <td>
                                        <div class="performance-bar">
                                            <div class="performance-fill <?= $perfClass ?>" style="width: <?= $subject['approval_rate'] ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Student Activity -->
            <div class="analytics-card fade-in">
                <h5 class="mb-3">👨‍🎓 Top Student Activity</h5>
                <?php if(empty($studentStats)): ?>
                    <div class="text-center py-4">
                        <div class="text-muted">No student activity data available for selected period</div>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach(array_slice($studentStats, 0, 12) as $student): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="p-3 border rounded">
                                <div class="fw-semibold"><?= htmlspecialchars($student['student_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($student['roll_no']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($student['course']) ?></div>
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="badge bg-primary"><?= $student['total_submissions'] ?> submissions</span>
                                        <span class="badge bg-success"><?= $student['approved'] ?> approved</span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Last: <?= date('M j', strtotime($student['last_submission'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- System Health -->
            <div class="analytics-card fade-in">
                <h5 class="mb-3">💚 System Health</h5>
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <div>
                            <div class="fw-semibold">Active Students</div>
                            <div class="small text-muted">Registered and active</div>
                        </div>
                        <span class="badge bg-primary rounded-pill"><?= $systemHealth['active_students'] ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <div>
                            <div class="fw-semibold">Active Evaluators</div>
                            <div class="small text-muted">Available for assignments</div>
                        </div>
                        <span class="badge bg-success rounded-pill"><?= $systemHealth['active_evaluators'] ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <div>
                            <div class="fw-semibold">Active Moderators</div>
                            <div class="small text-muted">Managing evaluations</div>
                        </div>
                        <span class="badge bg-info rounded-pill"><?= $systemHealth['active_moderators'] ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <div>
                            <div class="fw-semibold">Active Assignments</div>
                            <div class="small text-muted">Evaluator assignments</div>
                        </div>
                        <span class="badge bg-warning rounded-pill"><?= $systemHealth['active_assignments'] ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <div>
                            <div class="fw-semibold">Total Subjects</div>
                            <div class="small text-muted">Available for submissions</div>
                        </div>
                        <span class="badge bg-secondary rounded-pill"><?= $systemHealth['total_subjects'] ?></span>
                    </div>
                </div>
            </div>

            <!-- Evaluator Performance -->
            <div class="analytics-card fade-in">
                <h5 class="mb-3">⭐ Evaluator Performance</h5>
                <div class="list-group list-group-flush">
                    <?php while($evaluator = $evaluatorPerformance->fetch_assoc()): ?>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= htmlspecialchars($evaluator['evaluator_name']) ?></div>
                                <div class="small text-muted">
                                    <?= $evaluator['subjects_assigned'] ?> subjects • 
                                    <?= $evaluator['moderators_working_with'] ?> moderators
                                </div>
                            </div>
                            <span class="badge bg-primary"><?= $evaluator['evaluations_completed'] ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="analytics-card fade-in">
                <h5 class="mb-3">⚡ Quick Actions</h5>
                <div class="d-grid gap-2">
                    <a href="manage_users.php" class="btn btn-outline-primary">
                        👥 Manage Users
                    </a>
                    <a href="manage_assignments.php" class="btn btn-outline-success">
                        📋 Manage Assignments
                    </a>
                    <a href="answer_sheets.php" class="btn btn-outline-info">
                        📄 Review Submissions
                    </a>
                    <button class="btn btn-outline-warning" onclick="generateReport()">
                        📊 Generate Full Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily submissions chart
const ctx = document.getElementById('submissionChart').getContext('2d');
const chartData = <?= json_encode($dailyStats) ?>;

const dates = chartData.map(d => d.date);
const totalSubmissions = chartData.map(d => parseInt(d.total_submissions));
const approved = chartData.map(d => parseInt(d.approved));
const rejected = chartData.map(d => parseInt(d.rejected));
const pending = chartData.map(d => parseInt(d.pending));

new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates.map(date => new Date(date).toLocaleDateString()),
        datasets: [
            {
                label: 'Total Submissions',
                data: totalSubmissions,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            },
            {
                label: 'Approved',
                data: approved,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            },
            {
                label: 'Rejected',
                data: rejected,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            },
            {
                label: 'Pending',
                data: pending,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                position: 'top',
            }
        }
    }
});

function setDateRange(period) {
    const today = new Date();
    let startDate;
    
    if (period === 'week') {
        startDate = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
    } else if (period === 'month') {
        startDate = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
    }
    
    document.querySelector('input[name="start_date"]').value = startDate.toISOString().split('T')[0];
    document.querySelector('input[name="end_date"]').value = today.toISOString().split('T')[0];
}

function exportReport(format) {
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    
    alert(`${format.toUpperCase()} export functionality would be implemented here.\nDate range: ${startDate} to ${endDate}`);
}

function generateReport() {
    alert('Full report generation functionality would be implemented here.');
}
</script>

<?php include('../includes/footer.php'); ?>