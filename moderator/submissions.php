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

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$subject_filter = $_GET['subject_id'] ?? '';
$evaluator_filter = $_GET['evaluator_id'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build query
$query = "SELECT s.id, s.submission_title, s.status, s.marks_obtained, s.max_marks,
          s.created_at, s.evaluated_at, s.evaluator_remarks,
          u.name as student_name, u.roll_no,
          ev.name as evaluator_name,
          subj.name as subject_name, subj.code as subject_code
          FROM submissions s
          JOIN users u ON s.student_id = u.id
          LEFT JOIN users ev ON s.evaluator_id = ev.id
          LEFT JOIN assignments a ON s.assignment_id = a.id
          LEFT JOIN subjects subj ON a.subject_id = subj.id
          WHERE s.moderator_id = ?";

$params = [$moderator_id];
$types = "i";

if($status_filter) {
    $query .= " AND s.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if($subject_filter) {
    $query .= " AND subj.id = ?";
    $params[] = $subject_filter;
    $types .= "i";
}

if($evaluator_filter) {
    $query .= " AND s.evaluator_id = ?";
    $params[] = $evaluator_filter;
    $types .= "i";
}

if($search) {
    $query .= " AND (u.name LIKE ? OR u.roll_no LIKE ? OR s.submission_title LIKE ?)";
    $searchPattern = "%$search%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types .= "sss";
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filter options
$subjects_query = "SELECT DISTINCT s.id, s.name, s.code FROM subjects s
                   JOIN moderator_subjects ms ON s.id = ms.subject_id
                   WHERE ms.moderator_id = ? AND ms.is_active = 1
                   ORDER BY s.name";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$evaluators_query = "SELECT id, name FROM users
                     WHERE moderator_id = ? AND role = 'evaluator' AND is_active = 1
                     ORDER BY name";
$stmt = $conn->prepare($evaluators_query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$evaluators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<style>
.submissions-card {
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

.submission-row {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.submission-row:hover {
    border-color: #667eea;
    background-color: #f8f9ff;
    transform: translateY(-2px);
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
.status-rejected { background-color: #e17055; color: white; }
</style>

<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-0">
                    <i class="fas fa-file-alt"></i> All Submissions
                </h1>
                <p class="mb-0 mt-2 opacity-75">Manage and track all submission evaluations</p>
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
    <!-- Filters -->
    <div class="submissions-card">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="assigned" <?= $status_filter === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                    <option value="evaluating" <?= $status_filter === 'evaluating' ? 'selected' : '' ?>>Evaluating</option>
                    <option value="evaluated" <?= $status_filter === 'evaluated' ? 'selected' : '' ?>>Evaluated</option>
                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach($subjects as $subject): ?>
                    <option value="<?= $subject['id'] ?>" <?= $subject_filter == $subject['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($subject['name']) ?> (<?= $subject['code'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Evaluator</label>
                <select name="evaluator_id" class="form-select">
                    <option value="">All Evaluators</option>
                    <?php foreach($evaluators as $evaluator): ?>
                    <option value="<?= $evaluator['id'] ?>" <?= $evaluator_filter == $evaluator['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($evaluator['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Student name..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Submissions List -->
    <div class="submissions-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="fas fa-list text-primary"></i> Submissions List
            </h5>
            <span class="badge bg-info"><?= count($submissions) ?> submissions</span>
        </div>

        <?php if(empty($submissions)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-search fa-3x mb-3"></i>
                <p>No submissions found matching your filters</p>
            </div>
        <?php else: ?>
            <div class="submissions-list">
                <?php foreach($submissions as $submission): ?>
                <div class="submission-row">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h6 class="mb-1"><?= htmlspecialchars($submission['submission_title'] ?: 'Submission #' . $submission['id']) ?></h6>
                            <div class="text-muted small">
                                <div><strong>Student:</strong> <?= htmlspecialchars($submission['student_name']) ?>
                                    <?php if($submission['roll_no']): ?>
                                    (<?= htmlspecialchars($submission['roll_no']) ?>)
                                    <?php endif; ?>
                                </div>
                                <div><strong>Subject:</strong> <?= htmlspecialchars($submission['subject_name'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <?php if($submission['evaluator_name']): ?>
                                <strong><?= htmlspecialchars($submission['evaluator_name']) ?></strong>
                                <br>
                                <small class="text-muted">Evaluator</small>
                            <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-2">
                            <?php if($submission['marks_obtained'] !== null): ?>
                                <div class="text-center">
                                    <h5 class="mb-0"><?= $submission['marks_obtained'] ?>/<?= $submission['max_marks'] ?></h5>
                                    <small class="text-muted"><?= number_format(($submission['marks_obtained']/$submission['max_marks'])*100, 1) ?>%</small>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted">
                                    <span>Not graded</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-2">
                            <span class="status-badge status-<?= $submission['status'] ?>">
                                <?= ucfirst($submission['status']) ?>
                            </span>
                            <br>
                            <small class="text-muted"><?= date('M j, g:i A', strtotime($submission['created_at'])) ?></small>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-info" onclick="viewSubmission(<?= $submission['id'] ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if($submission['status'] === 'evaluated'): ?>
                                <a href="marks_overview.php?submission_id=<?= $submission['id'] ?>" class="btn btn-sm btn-outline-primary" title="Review Marks">
                                    <i class="fas fa-check-double"></i>
                                </a>
                                <?php elseif($submission['status'] === 'pending'): ?>
                                <a href="assign_evaluator.php?submission_id=<?= $submission['id'] ?>" class="btn btn-sm btn-outline-warning" title="Assign Evaluator">
                                    <i class="fas fa-user-plus"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if($submission['evaluator_remarks']): ?>
                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <strong>Evaluator Remarks:</strong> <?= htmlspecialchars($submission['evaluator_remarks']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function viewSubmission(submissionId) {
    // Open submission in new window/tab
    window.open(`../submissions/view.php?id=${submissionId}`, '_blank');
}

// Auto-refresh every 3 minutes
setTimeout(function() {
    location.reload();
}, 180000);

// Add smooth animations
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.submission-row');
    rows.forEach((row, index) => {
        row.style.animationDelay = (index * 0.05) + 's';
        row.classList.add('fadeInUp');
    });
});
</script>

<?php include('../includes/footer.php'); ?>