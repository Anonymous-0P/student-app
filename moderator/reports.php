<?php
// Include config first to set headers before any output
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

// Include header after authentication check
include('../includes/header.php');

$moderator_id = $_SESSION['user_id'];

// Get report parameters
$report_type = $_GET['type'] ?? 'subject_performance';
$subject_id = $_GET['subject_id'] ?? '';
$evaluator_id = $_GET['evaluator_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today

// Get available subjects
$subjects_query = "SELECT s.id, s.name, s.code FROM subjects s
                   JOIN moderator_subjects ms ON s.id = ms.subject_id
                   WHERE ms.moderator_id = ? AND ms.is_active = 1
                   ORDER BY s.name";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available evaluators
$evaluators_query = "SELECT id, name FROM users
                     WHERE moderator_id = ? AND role = 'evaluator' AND is_active = 1
                     ORDER BY name";
$stmt = $conn->prepare($evaluators_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$evaluators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Generate report data based on type
$report_data = [];

if($report_type === 'subject_performance') {
    // Subject-wise performance report
    $query = "SELECT 
        s.name as subject_name, 
        s.code as subject_code,
        COUNT(sub.id) as total_submissions,
        COUNT(CASE WHEN sub.status = 'approved' THEN 1 END) as completed_submissions,
        AVG(sub.marks_obtained) as avg_marks,
        AVG(sub.max_marks) as avg_max_marks,
        AVG(sub.marks_obtained / sub.max_marks * 100) as avg_percentage,
        MIN(sub.marks_obtained / sub.max_marks * 100) as min_percentage,
        MAX(sub.marks_obtained / sub.max_marks * 100) as max_percentage,
        COUNT(DISTINCT sub.evaluator_id) as evaluators_involved
        FROM subjects s
        JOIN moderator_subjects ms ON s.id = ms.subject_id
        LEFT JOIN assignments a ON s.id = a.subject_id
        LEFT JOIN submissions sub ON a.id = sub.assignment_id 
            AND sub.created_at BETWEEN ? AND ?
        WHERE ms.moderator_id = ? AND ms.is_active = 1";
    
    $params = [$date_from, $date_to, $moderator_id];
    $types = "ssi";
    
    if($subject_id) {
        $query .= " AND s.id = ?";
        $params[] = $subject_id;
        $types .= "i";
    }
    
    $query .= " GROUP BY s.id, s.name, s.code ORDER BY s.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif($report_type === 'evaluator_efficiency') {
    // Evaluator efficiency tracking
    $query = "SELECT 
        u.name as evaluator_name,
        u.email as evaluator_email,
        COUNT(sub.id) as total_assigned,
        COUNT(CASE WHEN sub.status IN ('evaluated', 'approved') THEN 1 END) as completed,
        COUNT(CASE WHEN sub.status = 'assigned' THEN 1 END) as pending,
        COUNT(CASE WHEN ea.status = 'overdue' THEN 1 END) as overdue,
        AVG(DATEDIFF(sub.evaluated_at, ea.assigned_at)) as avg_evaluation_days,
        AVG(sub.marks_obtained / sub.max_marks * 100) as avg_marks_given,
        STDDEV(sub.marks_obtained / sub.max_marks * 100) as marks_consistency,
        COUNT(CASE WHEN mh.action_type = 'revised' THEN 1 END) as overridden_count
        FROM users u
        LEFT JOIN submissions sub ON u.id = sub.evaluator_id 
            AND sub.created_at BETWEEN ? AND ?
        LEFT JOIN evaluator_assignments ea ON u.id = ea.evaluator_id 
            AND ea.assigned_at BETWEEN ? AND ?
        LEFT JOIN marks_history mh ON sub.id = mh.submission_id 
            AND mh.action_type = 'revised' AND mh.created_by_role = 'moderator'
        WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1";
    
    $params = [$date_from, $date_to, $date_from, $date_to, $moderator_id];
    $types = "ssssi";
    
    if($evaluator_id) {
        $query .= " AND u.id = ?";
        $params[] = $evaluator_id;
        $types .= "i";
    }
    
    $query .= " GROUP BY u.id, u.name, u.email ORDER BY completed DESC, avg_evaluation_days ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif($report_type === 'workload_distribution') {
    // Workload distribution analysis
    $query = "SELECT 
        u.name as evaluator_name,
        COUNT(CASE WHEN sub.status = 'assigned' THEN 1 END) as current_assigned,
        COUNT(CASE WHEN sub.status IN ('evaluating', 'assigned') THEN 1 END) as current_workload,
        COUNT(sub.id) as total_handled,
        s.name as subject_name,
        COUNT(DISTINCT s.id) as subjects_count
        FROM users u
        LEFT JOIN submissions sub ON u.id = sub.evaluator_id
        LEFT JOIN assignments a ON sub.assignment_id = a.id
        LEFT JOIN subjects s ON a.subject_id = s.id
        WHERE u.moderator_id = ? AND u.role = 'evaluator' AND u.is_active = 1
        GROUP BY u.id, u.name
        ORDER BY current_workload DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $moderator_id);
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif($report_type === 'marks_analysis') {
    // Marks distribution and consistency analysis
    $query = "SELECT 
        CASE 
            WHEN (sub.marks_obtained / sub.max_marks * 100) >= 90 THEN 'A+ (90-100%)'
            WHEN (sub.marks_obtained / sub.max_marks * 100) >= 80 THEN 'A (80-89%)'
            WHEN (sub.marks_obtained / sub.max_marks * 100) >= 70 THEN 'B (70-79%)'
            WHEN (sub.marks_obtained / sub.max_marks * 100) >= 60 THEN 'C (60-69%)'
            WHEN (sub.marks_obtained / sub.max_marks * 100) >= 50 THEN 'D (50-59%)'
            ELSE 'F (<50%)'
        END as grade_range,
        COUNT(*) as submission_count,
        AVG(sub.marks_obtained) as avg_marks_in_range,
        COUNT(DISTINCT sub.evaluator_id) as evaluators_in_range,
        COUNT(CASE WHEN mh.action_type = 'revised' THEN 1 END) as overrides_in_range
        FROM submissions sub
        LEFT JOIN marks_history mh ON sub.id = mh.submission_id 
            AND mh.action_type = 'revised' AND mh.created_by_role = 'moderator'
        WHERE sub.moderator_id = ? AND sub.created_at BETWEEN ? AND ?
        AND sub.status IN ('evaluated', 'approved')
        GROUP BY 
            CASE 
                WHEN (sub.marks_obtained / sub.max_marks * 100) >= 90 THEN 'A+ (90-100%)'
                WHEN (sub.marks_obtained / sub.max_marks * 100) >= 80 THEN 'A (80-89%)'
                WHEN (sub.marks_obtained / sub.max_marks * 100) >= 70 THEN 'B (70-79%)'
                WHEN (sub.marks_obtained / sub.max_marks * 100) >= 60 THEN 'C (60-69%)'
                WHEN (sub.marks_obtained / sub.max_marks * 100) >= 50 THEN 'D (50-59%)'
                ELSE 'F (<50%)'
            END
        ORDER BY AVG(sub.marks_obtained / sub.max_marks * 100) DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $moderator_id, $date_from, $date_to);
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Calculate summary statistics
$summary_stats = [
    'total_submissions' => 0,
    'completed_evaluations' => 0,
    'pending_evaluations' => 0,
    'avg_turnaround_time' => 0,
    'evaluator_count' => count($evaluators)
];

$summary_query = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status IN ('evaluated', 'approved') THEN 1 END) as completed,
    COUNT(CASE WHEN status IN ('assigned', 'evaluating') THEN 1 END) as pending,
    AVG(CASE WHEN evaluated_at IS NOT NULL THEN DATEDIFF(evaluated_at, created_at) END) as avg_days
    FROM submissions 
    WHERE moderator_id = ? AND created_at BETWEEN ? AND ?";
    
$stmt = $conn->prepare($summary_query);
$stmt->bind_param("iss", $moderator_id, $date_from, $date_to);
$stmt->execute();
$summary_result = $stmt->get_result()->fetch_assoc();

$summary_stats['total_submissions'] = $summary_result['total'];
$summary_stats['completed_evaluations'] = $summary_result['completed'];
$summary_stats['pending_evaluations'] = $summary_result['pending'];
$summary_stats['avg_turnaround_time'] = round($summary_result['avg_days'] ?? 0, 1);
?>

<style>
.report-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: none;
    margin-bottom: 1.5rem;
}

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
}

.report-nav {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.report-nav .nav-link {
    color: #495057;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.report-nav .nav-link.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.summary-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
}

.chart-container {
    position: relative;
    height: 400px;
    margin: 2rem 0;
}

.data-table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.performance-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.performance-excellent { background-color: #00b894; }
.performance-good { background-color: #6c5ce7; }
.performance-average { background-color: #fdcb6e; }
.performance-poor { background-color: #e17055; }

.export-buttons {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
</style>

<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-0">
                    <i class="fas fa-chart-bar"></i> Reports & Analytics
                </h1>
                <p class="mb-0 mt-2 opacity-75">Comprehensive performance tracking and evaluation insights</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="dashboard.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Report Navigation -->
    <div class="report-nav">
        <ul class="nav nav-pills justify-content-center">
            <li class="nav-item">
                <a class="nav-link <?= $report_type === 'subject_performance' ? 'active' : '' ?>" 
                   href="?type=subject_performance">
                    <i class="fas fa-book"></i> Subject Performance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $report_type === 'evaluator_efficiency' ? 'active' : '' ?>" 
                   href="?type=evaluator_efficiency">
                    <i class="fas fa-user-clock"></i> Evaluator Efficiency
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $report_type === 'workload_distribution' ? 'active' : '' ?>" 
                   href="?type=workload_distribution">
                    <i class="fas fa-balance-scale"></i> Workload Distribution
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $report_type === 'marks_analysis' ? 'active' : '' ?>" 
                   href="?type=marks_analysis">
                    <i class="fas fa-chart-pie"></i> Marks Analysis
                </a>
            </li>
        </ul>
    </div>

    <!-- Filters -->
    <div class="report-card">
        <form method="GET" class="row g-3">
            <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">
            
            <div class="col-md-2">
                <label class="form-label">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            </div>
            
            <?php if(in_array($report_type, ['subject_performance'])): ?>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach($subjects as $subject): ?>
                    <option value="<?= $subject['id'] ?>" <?= $subject_id == $subject['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($subject['name']) ?> (<?= $subject['code'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if(in_array($report_type, ['evaluator_efficiency'])): ?>
            <div class="col-md-3">
                <label class="form-label">Evaluator</label>
                <select name="evaluator_id" class="form-select">
                    <option value="">All Evaluators</option>
                    <?php foreach($evaluators as $evaluator): ?>
                    <option value="<?= $evaluator['id'] ?>" <?= $evaluator_id == $evaluator['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($evaluator['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Generate Report</button>
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
                <div class="export-buttons w-100">
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="exportToCSV()">
                        <i class="fas fa-download"></i> CSV
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="summary-card">
                <h3><?= $summary_stats['total_submissions'] ?></h3>
                <small>Total Submissions</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="summary-card" style="background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);">
                <h3><?= $summary_stats['completed_evaluations'] ?></h3>
                <small>Completed Evaluations</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="summary-card" style="background: linear-gradient(135deg, #fdcb6e 0%, #f39c12 100%);">
                <h3><?= $summary_stats['pending_evaluations'] ?></h3>
                <small>Pending Evaluations</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="summary-card" style="background: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%);">
                <h3><?= $summary_stats['avg_turnaround_time'] ?> days</h3>
                <small>Avg. Turnaround Time</small>
            </div>
        </div>
    </div>

    <!-- Report Content -->
    <div class="report-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">
                <?php 
                $report_titles = [
                    'subject_performance' => 'Subject-wise Performance Report',
                    'evaluator_efficiency' => 'Evaluator Efficiency Tracking',
                    'workload_distribution' => 'Workload Distribution Analysis',
                    'marks_analysis' => 'Marks Distribution Analysis'
                ];
                echo $report_titles[$report_type] ?? 'Report';
                ?>
            </h5>
            <span class="badge bg-info"><?= count($report_data) ?> records</span>
        </div>

        <?php if(empty($report_data)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-chart-line fa-3x mb-3"></i>
                <p>No data available for the selected criteria</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover data-table" id="reportTable">
                    <thead class="table-light">
                        <tr>
                            <?php if($report_type === 'subject_performance'): ?>
                                <th>Subject</th>
                                <th>Total Submissions</th>
                                <th>Completed</th>
                                <th>Avg. Score</th>
                                <th>Score Range</th>
                                <th>Evaluators</th>
                                <th>Performance</th>
                            <?php elseif($report_type === 'evaluator_efficiency'): ?>
                                <th>Evaluator</th>
                                <th>Assigned</th>
                                <th>Completed</th>
                                <th>Pending</th>
                                <th>Avg. Days</th>
                                <th>Avg. Marks</th>
                                <th>Consistency</th>
                                <th>Overrides</th>
                                <th>Efficiency</th>
                            <?php elseif($report_type === 'workload_distribution'): ?>
                                <th>Evaluator</th>
                                <th>Current Assigned</th>
                                <th>Current Workload</th>
                                <th>Total Handled</th>
                                <th>Subjects</th>
                                <th>Load Status</th>
                            <?php elseif($report_type === 'marks_analysis'): ?>
                                <th>Grade Range</th>
                                <th>Submissions</th>
                                <th>Avg. Marks</th>
                                <th>Evaluators</th>
                                <th>Overrides</th>
                                <th>Distribution</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($report_data as $row): ?>
                        <tr>
                            <?php if($report_type === 'subject_performance'): ?>
                                <td>
                                    <strong><?= htmlspecialchars($row['subject_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['subject_code']) ?></small>
                                </td>
                                <td><?= $row['total_submissions'] ?></td>
                                <td><?= $row['completed_submissions'] ?></td>
                                <td><?= number_format($row['avg_percentage'] ?? 0, 1) ?>%</td>
                                <td>
                                    <small><?= number_format($row['min_percentage'] ?? 0, 1) ?>% - <?= number_format($row['max_percentage'] ?? 0, 1) ?>%</small>
                                </td>
                                <td><?= $row['evaluators_involved'] ?></td>
                                <td>
                                    <?php 
                                    $perf = $row['avg_percentage'] ?? 0;
                                    $perf_class = $perf >= 75 ? 'excellent' : ($perf >= 60 ? 'good' : ($perf >= 45 ? 'average' : 'poor'));
                                    $perf_text = $perf >= 75 ? 'Excellent' : ($perf >= 60 ? 'Good' : ($perf >= 45 ? 'Average' : 'Needs Improvement'));
                                    ?>
                                    <span class="performance-indicator performance-<?= $perf_class ?>"></span>
                                    <?= $perf_text ?>
                                </td>
                            <?php elseif($report_type === 'evaluator_efficiency'): ?>
                                <td>
                                    <strong><?= htmlspecialchars($row['evaluator_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['evaluator_email']) ?></small>
                                </td>
                                <td><?= $row['total_assigned'] ?></td>
                                <td><?= $row['completed'] ?></td>
                                <td><?= $row['pending'] ?></td>
                                <td><?= number_format($row['avg_evaluation_days'] ?? 0, 1) ?></td>
                                <td><?= number_format($row['avg_marks_given'] ?? 0, 1) ?>%</td>
                                <td><?= number_format($row['marks_consistency'] ?? 0, 1) ?></td>
                                <td>
                                    <?php if($row['overridden_count'] > 0): ?>
                                    <span class="badge bg-warning"><?= $row['overridden_count'] ?></span>
                                    <?php else: ?>
                                    <span class="text-success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $efficiency = ($row['completed'] / max($row['total_assigned'], 1)) * 100;
                                    $eff_class = $efficiency >= 90 ? 'excellent' : ($efficiency >= 75 ? 'good' : ($efficiency >= 60 ? 'average' : 'poor'));
                                    ?>
                                    <span class="performance-indicator performance-<?= $eff_class ?>"></span>
                                    <?= number_format($efficiency, 1) ?>%
                                </td>
                            <?php elseif($report_type === 'workload_distribution'): ?>
                                <td><?= htmlspecialchars($row['evaluator_name']) ?></td>
                                <td><?= $row['current_assigned'] ?></td>
                                <td><?= $row['current_workload'] ?></td>
                                <td><?= $row['total_handled'] ?></td>
                                <td><?= $row['subjects_count'] ?></td>
                                <td>
                                    <?php 
                                    $load = $row['current_workload'];
                                    if($load <= 5) {
                                        echo '<span class="badge bg-success">Light</span>';
                                    } elseif($load <= 10) {
                                        echo '<span class="badge bg-warning">Moderate</span>';
                                    } else {
                                        echo '<span class="badge bg-danger">Heavy</span>';
                                    }
                                    ?>
                                </td>
                            <?php elseif($report_type === 'marks_analysis'): ?>
                                <td><?= htmlspecialchars($row['grade_range']) ?></td>
                                <td><?= $row['submission_count'] ?></td>
                                <td><?= number_format($row['avg_marks_in_range'], 1) ?></td>
                                <td><?= $row['evaluators_in_range'] ?></td>
                                <td><?= $row['overrides_in_range'] ?></td>
                                <td>
                                    <?php 
                                    $total_submissions = array_sum(array_column($report_data, 'submission_count'));
                                    $percentage = ($row['submission_count'] / max($total_submissions, 1)) * 100;
                                    ?>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <small><?= number_format($percentage, 1) ?>%</small>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportToCSV() {
    const table = document.getElementById('reportTable');
    const csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let cellText = cols[j].innerText.trim();
            // Remove extra whitespace and newlines
            cellText = cellText.replace(/\s+/g, ' ');
            row.push('"' + cellText + '"');
        }
        csv.push(row.join(','));
    }
    
    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = '<?= $report_type ?>_report_<?= date("Y-m-d") ?>.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

function exportToPDF() {
    window.print();
}

// Enhanced table styling and interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to table rows
    const rows = document.querySelectorAll('#reportTable tbody tr');
    rows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9ff';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Auto-refresh every 5 minutes
    setTimeout(function() {
        location.reload();
    }, 300000);
});

// Print styles
window.addEventListener('beforeprint', function() {
    document.body.classList.add('printing');
});

window.addEventListener('afterprint', function() {
    document.body.classList.remove('printing');
});
</script>

<style media="print">
.page-header, .report-nav, .export-buttons, .btn {
    display: none !important;
}

.report-card {
    box-shadow: none !important;
    border: 1px solid #ddd !important;
}

.container {
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

table {
    font-size: 12px !important;
}
</style>

<?php include('../includes/footer.php'); ?>