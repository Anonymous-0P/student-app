<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

checkLogin('faculty');

// Handle evaluation form submission
if(isset($_POST['evaluate'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $submission_id = (int)$_POST['submission_id'];
        $marks = !empty($_POST['marks']) ? (float)$_POST['marks'] : null;
        $total_marks = !empty($_POST['total_marks']) ? (float)$_POST['total_marks'] : null;
        $evaluation_notes = trim($_POST['evaluation_notes']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE submissions SET marks=?, total_marks=?, evaluation_notes=?, status=?, evaluated_at=NOW(), evaluated_by=? WHERE id=?");
        $stmt->bind_param("ddssii", $marks, $total_marks, $evaluation_notes, $status, $_SESSION['user_id'], $submission_id);
        
        if($stmt->execute()) {
            $success = "Submission evaluated successfully.";
        } else {
            $error = "Error updating submission: " . $conn->error;
        }
    }
}

// Get filter parameters
$subject_filter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query with filters
$query = "SELECT s.*, u.name as student_name, u.roll_no, sub.code as subject_code, sub.name as subject_name,
          e.name as evaluator_name
          FROM submissions s 
          JOIN users u ON s.student_id = u.id 
          LEFT JOIN subjects sub ON s.subject_id = sub.id 
          LEFT JOIN users e ON s.evaluated_by = e.id
          WHERE 1=1";
$params = [];
$types = '';

if($subject_filter > 0) {
    $query .= " AND s.subject_id = ?";
    $types .= 'i';
    $params[] = $subject_filter;
}

if($status_filter) {
    $query .= " AND s.status = ?";
    $types .= 's';
    $params[] = $status_filter;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get subjects for filter dropdown
$subjectsQuery = "SELECT id, code, name FROM subjects WHERE is_active=1 ORDER BY code";
$subjectsResult = $conn->query($subjectsQuery);
?>

<div class="row">
    <div class="col-12">
        <div class="page-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Student Submissions</h2>
                <div class="d-flex gap-2">
                    <span class="badge bg-warning">Pending: <?php 
                        $pendingQuery = $conn->query("SELECT COUNT(*) as count FROM submissions WHERE status='pending'");
                        echo $pendingQuery->fetch_assoc()['count'];
                    ?></span>
                    <span class="badge bg-success">Evaluated: <?php 
                        $evaluatedQuery = $conn->query("SELECT COUNT(*) as count FROM submissions WHERE status='evaluated'");
                        echo $evaluatedQuery->fetch_assoc()['count'];
                    ?></span>
                </div>
            </div>

            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Filter by Subject</label>
                    <select name="subject" class="form-select">
                        <option value="">All Subjects</option>
                        <?php while($subject = $subjectsResult->fetch_assoc()): ?>
                            <option value="<?= $subject['id'] ?>" <?= $subject_filter == $subject['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject['code'] . ' - ' . $subject['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Filter by Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="evaluated" <?= $status_filter == 'evaluated' ? 'selected' : '' ?>>Evaluated</option>
                        <option value="returned" <?= $status_filter == 'returned' ? 'selected' : '' ?>>Returned</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?" class="btn btn-outline-secondary ms-2">Clear</a>
                </div>
            </form>

            <!-- Submissions Table -->
            <?php if($result->num_rows == 0): ?>
                <div class="text-center py-5">
                    <div class="text-muted mb-3">📄</div>
                    <h5 class="text-muted">No submissions found</h5>
                    <p class="text-muted">No submissions match your current filters</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Marks</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <?php 
                            $statusClass = 'bg-warning';
                            if($row['status'] == 'evaluated') $statusClass = 'bg-success';
                            if($row['status'] == 'returned') $statusClass = 'bg-info';
                            
                            $pdfUrl = $row['pdf_url'];
                            if (strpos($pdfUrl, '/uploads/') === 0) {
                                $pdfUrl = '..' . $pdfUrl;
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['student_name']) ?></div>
                                    <?php if($row['roll_no']): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($row['roll_no']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['subject_code']): ?>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['subject_code']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['subject_name']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">No subject</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?> text-white">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                    <?php if($row['evaluated_at']): ?>
                                        <div class="small text-muted">
                                            <?= date('M j, Y', strtotime($row['evaluated_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['marks'] !== null): ?>
                                        <div class="fw-bold text-success">
                                            <?= number_format((float)$row['marks'], 1) ?>
                                            <?php if($row['total_marks']): ?>
                                                / <?= number_format((float)$row['total_marks'], 1) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not evaluated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                                    <div class="small text-muted"><?= date('g:i A', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" 
                                           class="btn btn-sm btn-outline-primary" title="View PDF">
                                            📄
                                        </a>
                                        <button class="btn btn-sm btn-outline-success" 
                                                onclick="openEvaluationModal(<?= htmlspecialchars(json_encode($row)) ?>)"
                                                title="Evaluate">
                                            ✏️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Evaluation Modal -->
<div class="modal fade" id="evaluationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_input(); ?>
                <input type="hidden" name="submission_id" id="modal_submission_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Evaluate Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div id="modal_student_info" class="bg-light p-3 rounded mb-3"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Marks Obtained</label>
                            <input type="number" step="0.5" min="0" name="marks" id="modal_marks" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Marks</label>
                            <input type="number" step="0.5" min="0" name="total_marks" id="modal_total_marks" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" id="modal_status" class="form-select" required>
                                <option value="pending">Pending</option>
                                <option value="evaluated">Evaluated</option>
                                <option value="returned">Returned</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Evaluation Notes</label>
                            <textarea name="evaluation_notes" id="modal_notes" class="form-control" rows="4" 
                                      placeholder="Add feedback, comments, or notes for the student..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="evaluate" class="btn btn-success">Save Evaluation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEvaluationModal(submission) {
    document.getElementById('modal_submission_id').value = submission.id;
    document.getElementById('modal_marks').value = submission.marks || '';
    document.getElementById('modal_total_marks').value = submission.total_marks || '';
    document.getElementById('modal_status').value = submission.status;
    document.getElementById('modal_notes').value = submission.evaluation_notes || '';
    
    const studentInfo = `
        <strong>Student:</strong> ${submission.student_name} ${submission.roll_no ? '(' + submission.roll_no + ')' : ''}<br>
        <strong>Subject:</strong> ${submission.subject_code ? submission.subject_code + ' - ' + submission.subject_name : 'No subject'}<br>
        <strong>Submitted:</strong> ${new Date(submission.created_at).toLocaleDateString()}
    `;
    document.getElementById('modal_student_info').innerHTML = studentInfo;
    
    new bootstrap.Modal(document.getElementById('evaluationModal')).show();
}
</script>

<?php include('../includes/footer.php'); ?>