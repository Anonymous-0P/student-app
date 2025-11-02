<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Handle submission status updates
if(isset($_POST['update_status'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $submission_id = (int)$_POST['submission_id'];
        $new_status = $_POST['new_status'];
        $remarks = trim($_POST['remarks'] ?? '');
        
        $stmt = $conn->prepare("UPDATE submissions SET status = ?, admin_remarks = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $remarks, $submission_id);
        
        if($stmt->execute()) {
            $success = "Submission status updated successfully.";
        } else {
            $error = "Error updating submission status.";
        }
    }
}

// Handle bulk operations
if(isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_ids = $_POST['selected_submissions'] ?? [];
    
    if(empty($selected_ids)) {
        $error = "Please select at least one submission.";
    } else {
        $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
        
        if($action == 'approve') {
            $stmt = $conn->prepare("UPDATE submissions SET status = 'approved' WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        } elseif($action == 'reject') {
            $stmt = $conn->prepare("UPDATE submissions SET status = 'rejected' WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        }
        
        if($stmt && $stmt->execute()) {
            $success = "Bulk operation completed successfully.";
        } else {
            $error = "Error performing bulk operation.";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$subject_filter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build submissions query with filters
$query = "
    SELECT 
        s.*,
        u.name as student_name,
        u.roll_no,
        sub.name as subject_name,
        sub.code as subject_code
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    JOIN subjects sub ON s.subject_id = sub.id
    WHERE 1=1
";

$params = [];
$types = '';

if($status_filter) {
    $query .= " AND s.status = ?";
    $types .= 's';
    $params[] = $status_filter;
}

if($subject_filter) {
    $query .= " AND s.subject_id = ?";
    $types .= 'i';
    $params[] = $subject_filter;
}

if($date_filter) {
    $query .= " AND DATE(s.created_at) = ?";
    $types .= 's';
    $params[] = $date_filter;
}

if($search) {
    $query .= " AND (u.name LIKE ? OR u.roll_no LIKE ? OR sub.name LIKE ?)";
    $types .= 'sss';
    $searchPattern = "%$search%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}

if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}

$submissions = $stmt->get_result();

// Get subjects for filter
$subjects = $conn->query("SELECT id, name, code FROM subjects ORDER BY name");

// Get submission statistics
$stats = $conn->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM submissions
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

$statusCounts = [];
foreach($stats as $stat) {
    $statusCounts[$stat['status']] = $stat['count'];
}

// Get recent activity
$recentActivity = $conn->query("
    SELECT 
        s.created_at,
        u.name as student_name,
        sub.name as subject_name,
        s.status
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    JOIN subjects sub ON s.subject_id = sub.id
    ORDER BY s.created_at DESC
    LIMIT 10
");
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<style>
.status-badge {
    font-size: 0.75rem;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-weight: 600;
}

.file-preview {
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    transition: border-color 0.3s ease;
}

.file-preview:hover {
    border-color: var(--primary-color);
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
                    <h1 class="h3 mb-2">Answer Sheet Management</h1>
                    <!-- <p class="text-muted mb-0">Review, verify, and manage student submissions</p> -->
                </div>
                
            </div>
        </div>
    </div>

    <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <!-- <div class="row g-3 mb-4 fade-in">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="h4 mb-1"><?= $statusCounts['pending'] ?? 0 ?></div>
                <div>Pending Review</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="h4 mb-1"><?= $statusCounts['approved'] ?? 0 ?></div>
                <div>Approved</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="h4 mb-1"><?= $statusCounts['rejected'] ?? 0 ?></div>
                <div>Rejected</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="h4 mb-1"><?= $statusCounts['under_review'] ?? 0 ?></div>
                <div>Under Review</div>
            </div>
        </div>
    </div> -->

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Filters -->
            <div class="answer-card fade-in">
                <form method="GET" class="row g-3">
                    
                    <div class="col-md-3">
                        <label class="form-label">Subject</label>
                        <select name="subject" class="form-select">
                            <option value="">All Subjects</option>
                            <?php while($subject = $subjects->fetch_assoc()): ?>
                                <option value="<?= $subject['id'] ?>" <?= $subject_filter == $subject['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['code'] . ' - ' . $subject['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, roll no..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="answer_sheets.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Submissions -->
            <div class="answer-card fade-in">
                <?php if($submissions->num_rows == 0): ?>
                    <div class="text-center py-5">
                        <div class="text-muted mb-3">📄</div>
                        <h5 class="text-muted">No submissions found</h5>
                        <p class="text-muted">No submissions match your current filters</p>
                    </div>
                <?php else: ?>
                    <form id="bulkForm" method="POST">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <input type="checkbox" id="selectAll" class="form-check-input me-2">
                                <label for="selectAll" class="form-check-label">Select All</label>
                            </div>
                            <div class="text-muted">
                                <?= $submissions->num_rows ?> submission(s) found
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40"></th>
                                        <th>Student</th>
                                        <th>Subject</th>
                                        <th>Submition Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($submission = $submissions->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_submissions[]" value="<?= $submission['id'] ?>" class="form-check-input submission-checkbox">
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($submission['student_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($submission['roll_no']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($submission['subject_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($submission['subject_code']) ?></div>
                                        
                                            
                                        </td>
                                        
                                        <td>
                                            <div><?= date('M j, Y', strtotime($submission['created_at'])) ?></div>
                                            <div class="small text-muted"><?= date('g:i A', strtotime($submission['created_at'])) ?></div>
                                        </td>
                                      
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Recent Activity -->
            <div class="answer-card fade-in">
                <h5 class="mb-3">📈 Recent Activity</h5>
                <div class="list-group list-group-flush">
                    <?php while($activity = $recentActivity->fetch_assoc()): ?>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= htmlspecialchars($activity['student_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($activity['subject_name']) ?></div>
                            </div>
                            <div class="text-end">
                                <span class="status-badge status-<?= $activity['status'] ?> small">
                                    <?= ucfirst($activity['status']) ?>
                                </span>
                                <div class="text-muted small mt-1"><?= date('M j, g:i A', strtotime($activity['created_at'])) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="answer-card fade-in">
                <h5 class="mb-3">📊 Quick Stats</h5>
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-2 bg-light rounded">
                            <div class="h6 mb-0"><?= array_sum($statusCounts) ?></div>
                            <div class="small text-muted">Total</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-light rounded">
                            <div class="h6 mb-0"><?= round((($statusCounts['approved'] ?? 0) / max(array_sum($statusCounts), 1)) * 100) ?>%</div>
                            <div class="small text-muted">Approved</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">📝 Review Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="reviewSubmissionId">
                    
                    <div id="submissionDetails"></div>
                    
                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="new_status" class="form-select" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="under_review">Under Review</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Admin Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Add any remarks or feedback..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="hiddenBulkForm" method="POST" style="display: none;">
    <input type="hidden" name="bulk_action" id="hiddenBulkAction">
</form>

<script>
// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.submission-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateBulkButtons();
});

// Individual checkbox change
document.addEventListener('change', function(e) {
    if(e.target.classList.contains('submission-checkbox')) {
        updateBulkButtons();
    }
});

function updateBulkButtons() {
    const selected = document.querySelectorAll('.submission-checkbox:checked');
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    const bulkRejectBtn = document.getElementById('bulkRejectBtn');
    
    if(selected.length > 0) {
        bulkApproveBtn.disabled = false;
        bulkRejectBtn.disabled = false;
    } else {
        bulkApproveBtn.disabled = true;
        bulkRejectBtn.disabled = true;
    }
}

function bulkAction(action) {
    const selected = document.querySelectorAll('.submission-checkbox:checked');
    if(selected.length === 0) {
        alert('Please select at least one submission.');
        return;
    }
    
    const confirmMsg = action === 'approve' ? 
        `Are you sure you want to approve ${selected.length} submission(s)?` :
        `Are you sure you want to reject ${selected.length} submission(s)?`;
    
    if(confirm(confirmMsg)) {
        const form = document.getElementById('hiddenBulkForm');
        document.getElementById('hiddenBulkAction').value = action;
        
        // Add selected IDs to form
        selected.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_submissions[]';
            input.value = checkbox.value;
            form.appendChild(input);
        });
        
        form.submit();
    }
}

function reviewSubmission(submission) {
    document.getElementById('reviewSubmissionId').value = submission.id;
    
    const details = `
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Student:</strong> ${submission.student_name}<br>
                        <strong>Roll No:</strong> ${submission.roll_no}<br>
                        <strong>Subject:</strong> ${submission.subject_name} (${submission.subject_code})
                    </div>
                    <div class="col-md-6">
                        <strong>Current Status:</strong> 
                        <span class="status-badge status-${submission.status}">
                            ${submission.status.charAt(0).toUpperCase() + submission.status.slice(1).replace('_', ' ')}
                        </span><br>
                        <strong>Uploaded:</strong> ${new Date(submission.created_at).toLocaleString()}<br>
                        <strong>File:</strong> <a href="../uploads/pdfs/${submission.pdf_path}" target="_blank" class="btn btn-sm btn-outline-primary">📄 View PDF</a>
                    </div>
                </div>
                ${submission.admin_remarks ? `<div class="mt-3"><strong>Previous Remarks:</strong><br><em>${submission.admin_remarks}</em></div>` : ''}
            </div>
        </div>
    `;
    
    document.getElementById('submissionDetails').innerHTML = details;
    
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}
</script>

<?php include('../includes/footer.php'); ?>