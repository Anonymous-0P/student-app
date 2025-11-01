<?php
session_start();
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

$moderator_id = $_SESSION['user_id'];
$search_term = isset($_POST['search_term']) ? trim($_POST['search_term']) : '';

if (!$search_term) {
    echo '<div class="alert alert-warning">Please enter a search term</div>';
    exit();
}

// Search for submissions by ID or student name
$search_query = "SELECT 
    s.id as submission_id,
    s.submission_title,
    s.marks_obtained,
    s.max_marks,
    s.evaluator_remarks,
    s.status,
    s.evaluated_at,
    s.created_at as submitted_at,
    ROUND((s.marks_obtained / s.max_marks) * 100, 2) as percentage,
    u.name as student_name,
    u.email as student_email,
    e.name as evaluator_name,
    e.email as evaluator_email,
    sub.name as subject_name,
    sub.code as subject_code
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    LEFT JOIN users e ON s.evaluator_id = e.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN moderator_subjects ms ON sub.id = ms.subject_id
    WHERE (s.id = ? OR u.name LIKE ? OR u.email LIKE ?)
        AND ms.moderator_id = ?
        AND ms.is_active = 1
        AND s.status IN ('evaluated', 'approved')
    ORDER BY s.updated_at DESC
    LIMIT 20";

$search_like = "%{$search_term}%";
$search_stmt = $conn->prepare($search_query);
$search_stmt->bind_param("issi", $search_term, $search_like, $search_like, $moderator_id);
$search_stmt->execute();
$search_results = $search_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<?php if (empty($search_results)): ?>
    <div class="alert alert-info">
        <i class="fas fa-search me-2"></i>
        No submissions found matching "<?= htmlspecialchars($search_term) ?>".
        <br><small>Try searching by submission ID, student name, or email address.</small>
    </div>
<?php else: ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        Found <?= count($search_results) ?> submission(s) matching "<?= htmlspecialchars($search_term) ?>".
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Submission Details</th>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Current Marks</th>
                    <th>Evaluator</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($search_results as $submission): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($submission['submission_title'] ?: 'Submission #' . $submission['submission_id']) ?></strong>
                        <br>
                        <small class="text-muted">
                            ID: <?= $submission['submission_id'] ?> | 
                            Submitted: <?= date('M j, Y', strtotime($submission['submitted_at'])) ?>
                        </small>
                        <?php if ($submission['evaluated_at']): ?>
                        <br>
                        <small class="text-info">
                            Evaluated: <?= date('M j, Y g:i A', strtotime($submission['evaluated_at'])) ?>
                        </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($submission['student_name']) ?></strong>
                        <br>
                        <small class="text-muted"><?= htmlspecialchars($submission['student_email']) ?></small>
                    </td>
                    <td>
                        <span class="badge bg-secondary"><?= htmlspecialchars($submission['subject_code']) ?></span>
                        <br>
                        <small><?= htmlspecialchars($submission['subject_name']) ?></small>
                    </td>
                    <td>
                        <?php if ($submission['marks_obtained'] !== null): ?>
                            <div class="text-center">
                                <h5 class="mb-1"><?= $submission['marks_obtained'] ?>/<?= $submission['max_marks'] ?></h5>
                                <span class="badge bg-<?= $submission['percentage'] >= 80 ? 'success' : ($submission['percentage'] >= 60 ? 'warning' : 'danger') ?>">
                                    <?= $submission['percentage'] ?>%
                                </span>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">Not evaluated</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($submission['evaluator_name']): ?>
                            <strong><?= htmlspecialchars($submission['evaluator_name']) ?></strong>
                            <br>
                            <small class="text-muted"><?= htmlspecialchars($submission['evaluator_email']) ?></small>
                        <?php else: ?>
                            <span class="text-muted">Not assigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $submission['status'] === 'evaluated' ? 'success' : ($submission['status'] === 'approved' ? 'primary' : 'secondary') ?>">
                            <?= ucfirst($submission['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group-vertical" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewSubmissionDetails(<?= $submission['submission_id'] ?>)" title="View Full Details">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <?php if ($submission['marks_obtained'] !== null): ?>
                            <button class="btn btn-sm btn-outline-warning" onclick="overrideMarks(<?= $submission['submission_id'] ?>, <?= $submission['marks_obtained'] ?>, <?= $submission['max_marks'] ?>)" title="Override Marks">
                                <i class="fas fa-edit"></i> Override
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-info" onclick="viewEvaluatorRemarks(<?= $submission['submission_id'] ?>)" title="View Evaluator Remarks">
                                <i class="fas fa-comment"></i> Remarks
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Override History -->
    <div class="mt-4">
        <h6><i class="fas fa-history me-2"></i>Recent Override History</h6>
        <?php
        // Get recent override history for this moderator
        $history_query = "SELECT 
            mh.submission_id,
            mh.old_marks,
            mh.new_marks,
            mh.max_marks,
            mh.reason,
            mh.created_at,
            s.submission_title,
            u.name as student_name
            FROM marks_history mh
            JOIN submissions s ON mh.submission_id = s.id
            JOIN users u ON s.student_id = u.id
            WHERE mh.moderator_id = ? AND mh.action_type = 'override'
            ORDER BY mh.created_at DESC
            LIMIT 10";
        
        try {
            $history_stmt = $conn->prepare($history_query);
            $history_stmt->bind_param("i", $moderator_id);
            $history_stmt->execute();
            $override_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            if (!empty($override_history)): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Submission</th>
                                <th>Student</th>
                                <th>Override</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($override_history as $history): ?>
                            <tr>
                                <td>
                                    <small><?= date('M j, g:i A', strtotime($history['created_at'])) ?></small>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars($history['submission_title'] ?: 'Submission #' . $history['submission_id']) ?></small>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars($history['student_name']) ?></small>
                                </td>
                                <td>
                                    <small>
                                        <span class="text-danger"><?= $history['old_marks'] ?></span> → 
                                        <span class="text-success"><?= $history['new_marks'] ?></span>
                                        /<?= $history['max_marks'] ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($history['reason']) ?>">
                                        <?= htmlspecialchars(substr($history['reason'], 0, 50)) ?>
                                        <?= strlen($history['reason']) > 50 ? '...' : '' ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <small>No recent override history found.</small>
                </div>
            <?php endif;
        } catch (Exception $e) {
            echo '<div class="alert alert-info"><small>Override history not available.</small></div>';
        }
        ?>
    </div>
<?php endif; ?>

<script>
function viewSubmissionDetails(submissionId) {
    window.open(`../evaluator/evaluate.php?id=${submissionId}`, '_blank');
}

function viewEvaluatorRemarks(submissionId) {
    // Show remarks in a modal or alert
    fetch(`get_submission_remarks.php?id=${submissionId}`)
        .then(response => response.text())
        .then(data => {
            if (data.trim()) {
                alert('Evaluator Remarks:\n\n' + data);
            } else {
                alert('No evaluator remarks available for this submission.');
            }
        })
        .catch(error => {
            alert('Error loading remarks: ' + error.message);
        });
}
</script>