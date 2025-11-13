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
?>
<link rel="stylesheet" href="css/moderator-style.css">
<?php

$moderator_id = $_SESSION['user_id'];

// Build query - Get submissions through evaluator relationship
$query = "SELECT s.id, s.submission_title, s.status, s.marks_obtained, s.max_marks,
          s.created_at, s.evaluated_at, s.evaluator_remarks, s.evaluation_notes,
          s.pdf_url, s.annotated_pdf_url, s.marks, s.total_marks,
          s.evaluator_id, s.student_id, s.final_marks, s.final_feedback,
          s.assignment_id,
          u.name as student_name, u.roll_no, u.email as student_email,
          ev.name as evaluator_name, ev.email as evaluator_email,
          subj.name as subject_name, subj.code as subject_code
          FROM submissions s
          JOIN users u ON s.student_id = u.id
          LEFT JOIN users ev ON s.evaluator_id = ev.id
          LEFT JOIN assignments a ON s.assignment_id = a.id
          LEFT JOIN subjects subj ON a.subject_id = subj.id
          WHERE (ev.moderator_id = ? OR s.moderator_id = ? OR 
                 (s.moderator_id IS NULL AND ev.moderator_id = ?))
          ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
if(!$stmt) {
    die("Query preparation failed: " . $conn->error . "<br>Query: " . $query);
}
$stmt->bind_param("iii", $moderator_id, $moderator_id, $moderator_id);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="moderator-content">

<style>
:root {
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-light: #DBEAFE;
    --success: #10B981;
    --success-light: #D1FAE5;
    --warning: #F59E0B;
    --warning-light: #FEF3C7;
    --danger: #EF4444;
    --danger-light: #FEE2E2;
    --info: #06B6D4;
    --info-light: #CFFAFE;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
    --gray-900: #111827;
}

.dashboard-header {
    background: white;
    border-bottom: 1px solid var(--gray-200);
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.dashboard-header h1 {
    font-size: 1.875rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 0.25rem;
}

.dashboard-header p {
    color: var(--gray-500);
    font-size: 0.875rem;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-100);
}

.card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-900);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-title i {
    color: var(--primary);
}

.table-wrapper {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.table thead {
    background: var(--gray-50);
}

.table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--gray-200);
}

.table td {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.table tbody tr {
    transition: background 0.15s ease;
}

.table tbody tr:hover {
    background: var(--gray-50);
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 6px;
    line-height: 1;
}

.badge-primary { background: var(--primary-light); color: var(--primary); }
.badge-success { background: var(--success-light); color: var(--success); }
.badge-warning { background: var(--warning-light); color: var(--warning); }
.badge-danger { background: var(--danger-light); color: var(--danger); }
.badge-info { background: var(--info-light); color: var(--info); }
.badge-gray { background: var(--gray-100); color: var(--gray-600); }

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-outline {
    background: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
}

.btn-outline:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 6px;
}

.btn-group {
    display: flex;
    gap: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
}

.empty-state h6 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
}

.empty-state p {
    font-size: 0.875rem;
    color: var(--gray-500);
}

.submission-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.submission-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 0.25rem;
}

.submission-detail {
    font-size: 0.75rem;
    color: var(--gray-600);
}

.marks-display {
    text-align: center;
}

.marks-value {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 0.125rem;
}

.marks-percentage {
    font-size: 0.75rem;
    color: var(--gray-500);
}

@media (max-width: 768px) {
    .dashboard-header h1 {
        font-size: 1.5rem;
    }
}
</style>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>All Submissions</h1>
                <p>Manage and track all submission evaluations</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<div class="container">
    <!-- Submissions List -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                <i class="fas fa-list"></i>
                Submissions List
            </h5>
            <span class="badge badge-info"><?= count($submissions) ?> submissions</span>
        </div>

        <?php if(empty($submissions)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h6>No Submissions Found</h6>
                <p>No submissions are available at this time</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Submission</th>
                            <th>Evaluator</th>
                            <th>Marks</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($submissions as $submission): ?>
                        <tr>
                            <td>
                                <div class="submission-info">
                                    <div class="submission-title">
                                        <?= htmlspecialchars($submission['submission_title'] ?: 'Submission #' . $submission['id']) ?>
                                    </div>
                                    <div class="submission-detail">
                                        <strong>Student:</strong> <?= htmlspecialchars($submission['student_name']) ?>
                                        <?php if($submission['roll_no']): ?>
                                        (<?= htmlspecialchars($submission['roll_no']) ?>)
                                        <?php endif; ?>
                                    </div>
                                    <div class="submission-detail">
                                        <strong>Subject:</strong> <?= htmlspecialchars($submission['subject_name'] ?? 'N/A') ?>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <?php if($submission['evaluator_name']): ?>
                                    <div>
                                        <div style="font-weight: 600; color: var(--gray-900); font-size: 0.875rem;">
                                            <?= htmlspecialchars($submission['evaluator_name']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">
                                            <?= htmlspecialchars($submission['evaluator_email']) ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--gray-400); font-size: 0.875rem;">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if($submission['marks_obtained'] !== null): ?>
                                    <div class="marks-display">
                                        <div class="marks-value">
                                            <?= $submission['marks_obtained'] ?>/<?= $submission['max_marks'] ?>
                                        </div>
                                        <div class="marks-percentage">
                                            <?= number_format(($submission['marks_obtained']/$submission['max_marks'])*100, 1) ?>%
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="text-align: center; color: var(--gray-400); font-size: 0.875rem;">
                                        Not graded
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php
                                $status = $submission['status'];
                                $badge_class = 'badge-gray';
                                
                                if($status === 'evaluated' || (isset($submission['evaluation_status']) && $submission['evaluation_status'] === 'evaluated')) {
                                    $badge_class = 'badge-success';
                                    $status = 'Evaluated';
                                } elseif($status === 'Submitted' || $status === 'pending') {
                                    $badge_class = 'badge-warning';
                                    $status = 'Pending';
                                } elseif($status === 'assigned' || $status === 'evaluating' || $status === 'Under Evaluation') {
                                    $badge_class = 'badge-info';
                                    $status = 'In Progress';
                                } elseif($status === 'approved') {
                                    $badge_class = 'badge-primary';
                                    $status = 'Approved';
                                }
                                ?>
                                <span class="badge <?= $badge_class ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                                <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                                    <?= date('M j, g:i A', strtotime($submission['created_at'])) ?>
                                </div>
                            </td>
                            
                            <td>
                                <?= date('M j, Y', strtotime($submission['created_at'])) ?>
                            </td>
                            
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-outline btn-icon" 
                                            onclick='viewDetails(<?= json_encode($submission, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                                            title="View Details"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#detailsModal">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if($submission['status'] === 'evaluated' || (isset($submission['evaluation_status']) && $submission['evaluation_status'] === 'evaluated')): ?>
                                    <a href="review_evaluation.php?id=<?= $submission['id'] ?>" 
                                       class="btn btn-primary btn-icon" 
                                       title="Review Evaluation">
                                        <i class="fas fa-clipboard-check"></i>
                                    </a>
                                    <?php elseif($submission['status'] === 'pending'): ?>
                                    <a href="assign_evaluator.php?submission_id=<?= $submission['id'] ?>" 
                                       class="btn btn-outline btn-icon" 
                                       title="Assign Evaluator">
                                        <i class="fas fa-user-plus"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Submission Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary); color: white;">
                <h5 class="modal-title" id="detailsModalLabel">
                    <i class="fas fa-file-alt"></i> Submission Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
function viewDetails(submission) {
    const marks = submission.marks_obtained || submission.marks || 'N/A';
    const maxMarks = submission.max_marks || submission.total_marks || 'N/A';
    const percentage = (submission.marks_obtained && submission.max_marks) 
        ? ((submission.marks_obtained / submission.max_marks) * 100).toFixed(1) 
        : 'N/A';
    
    const content = `
        <div class="row g-3">
            <!-- Student Information -->
            <div class="col-md-6">
                <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 1rem;">
                    <h6 style="color: var(--gray-900); font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-user" style="color: var(--primary);"></i> Student Information
                    </h6>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                        <div><strong style="color: var(--gray-700);">Name:</strong> <span style="color: var(--gray-900);">${submission.student_name || 'N/A'}</span></div>
                        <div><strong style="color: var(--gray-700);">Roll No:</strong> <span style="color: var(--gray-900);">${submission.roll_no || 'N/A'}</span></div>
                        <div><strong style="color: var(--gray-700);">Email:</strong> <span style="color: var(--gray-900);">${submission.student_email || 'N/A'}</span></div>
                        <div><strong style="color: var(--gray-700);">Subject:</strong> <span style="color: var(--gray-900);">${submission.subject_name || 'N/A'} ${submission.subject_code ? '(' + submission.subject_code + ')' : ''}</span></div>
                    </div>
                </div>
            </div>
            
            <!-- Evaluator Information -->
            <div class="col-md-6">
                <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 1rem;">
                    <h6 style="color: var(--gray-900); font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-user-tie" style="color: var(--primary);"></i> Evaluator Information
                    </h6>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                        <div><strong style="color: var(--gray-700);">Name:</strong> <span style="color: var(--gray-900);">${submission.evaluator_name || 'Not assigned'}</span></div>
                        <div><strong style="color: var(--gray-700);">Email:</strong> <span style="color: var(--gray-900);">${submission.evaluator_email || 'N/A'}</span></div>
                        <div><strong style="color: var(--gray-700);">Status:</strong> 
                            <span class="badge badge-${getStatusClass(submission.status)}">${submission.status || 'N/A'}</span>
                        </div>
                        <div><strong style="color: var(--gray-700);">Evaluated:</strong> <span style="color: var(--gray-900);">${submission.evaluated_at ? new Date(submission.evaluated_at).toLocaleString() : 'Not yet evaluated'}</span></div>
                    </div>
                </div>
            </div>
            
            <!-- Marks & Grading -->
            <div class="col-12">
                <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 1rem;">
                    <h6 style="color: var(--gray-900); font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-star" style="color: var(--primary);"></i> Marks & Grading
                    </h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <div style="font-size: 2rem; font-weight: 700; color: var(--primary);">${marks}</div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);">Marks Obtained</div>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 2rem; font-weight: 700; color: var(--info);">${maxMarks}</div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);">Total Marks</div>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 2rem; font-weight: 700; color: var(--success);">${percentage}%</div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);">Percentage</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Evaluation Feedback -->
            ${submission.evaluator_remarks || submission.evaluation_notes || submission.final_feedback ? `
            <div class="col-12">
                <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 1rem;">
                    <h6 style="color: var(--gray-900); font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-comment-alt" style="color: var(--primary);"></i> Evaluation Feedback
                    </h6>
                    ${submission.evaluator_remarks ? `
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--gray-700); font-size: 0.875rem;">Evaluator Remarks:</strong>
                        <div style="border: 1px solid var(--gray-200); border-radius: 6px; padding: 0.75rem; margin-top: 0.5rem; background: var(--gray-50); font-size: 0.875rem; color: var(--gray-800);">
                            ${submission.evaluator_remarks}
                        </div>
                    </div>
                    ` : ''}
                    ${submission.evaluation_notes ? `
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--gray-700); font-size: 0.875rem;">Evaluation Notes:</strong>
                        <div style="border: 1px solid var(--gray-200); border-radius: 6px; padding: 0.75rem; margin-top: 0.5rem; background: var(--gray-50); font-size: 0.875rem; color: var(--gray-800);">
                            ${submission.evaluation_notes}
                        </div>
                    </div>
                    ` : ''}
                    ${submission.final_feedback ? `
                    <div>
                        <strong style="color: var(--gray-700); font-size: 0.875rem;">Final Feedback:</strong>
                        <div style="border: 1px solid var(--gray-200); border-radius: 6px; padding: 0.75rem; margin-top: 0.5rem; background: var(--gray-50); font-size: 0.875rem; color: var(--gray-800);">
                            ${submission.final_feedback}
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}
            
            <!-- Submission Files -->
            <div class="col-12">
                <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 1rem;">
                    <h6 style="color: var(--gray-900); font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-file-pdf" style="color: var(--primary);"></i> Submission Files
                    </h6>
                    <div class="row g-2">
                        <div class="col-12">
                            ${submission.annotated_pdf_url ? `
                            <a href="../${submission.annotated_pdf_url}" target="_blank" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-file-alt"></i> View Annotated PDF
                            </a>
                            ` : '<p style="color: var(--gray-400); font-size: 0.875rem;">No annotated PDF available</p>'}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="col-12">
                <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 1rem;">
                    <h6 style="color: var(--gray-900); font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-clock" style="color: var(--primary);"></i> Timeline
                    </h6>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                        <div><strong style="color: var(--gray-700);">Submitted:</strong> <span style="color: var(--gray-900);">${submission.created_at ? new Date(submission.created_at).toLocaleString() : 'N/A'}</span></div>
                        ${submission.evaluated_at ? `
                        <div><strong style="color: var(--gray-700);">Evaluated:</strong> <span style="color: var(--gray-900);">${new Date(submission.evaluated_at).toLocaleString()}</span></div>
                        ` : '<div style="color: var(--gray-500);">Not yet evaluated</div>'}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('modalContent').innerHTML = content;
}

function getStatusClass(status) {
    if(status === 'evaluated' || status === 'Evaluated') return 'success';
    if(status === 'pending' || status === 'Submitted') return 'warning';
    if(status === 'assigned' || status === 'evaluating' || status === 'Under Evaluation') return 'info';
    if(status === 'approved') return 'primary';
    if(status === 'rejected') return 'danger';
    return 'gray';
}

function viewSubmission(submissionId) {
    // Open submission in new window/tab
    window.open(`../submissions/view.php?id=${submissionId}`, '_blank');
}
</script>

</div><!-- Close moderator-content -->

<?php include('../includes/footer.php'); ?>