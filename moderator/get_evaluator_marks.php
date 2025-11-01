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
$evaluator_id = isset($_POST['evaluator_id']) ? (int)$_POST['evaluator_id'] : 0;
$subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;

if (!$evaluator_id) {
    echo '<div class="alert alert-warning">Please select an evaluator</div>';
    exit();
}

// Verify evaluator belongs to this moderator
$verify_query = "SELECT name FROM users WHERE id = ? AND moderator_id = ? AND role = 'evaluator'";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ii", $evaluator_id, $moderator_id);
$verify_stmt->execute();
$evaluator_info = $verify_stmt->get_result()->fetch_assoc();

if (!$evaluator_info) {
    echo '<div class="alert alert-danger">Invalid evaluator selected</div>';
    exit();
}

// Build query with optional subject filter
$subject_filter = "";
$params = [$evaluator_id];
$types = "i";

if ($subject_id) {
    $subject_filter = "AND s.subject_id = ?";
    $params[] = $subject_id;
    $types .= "i";
}

// Get marks given by this evaluator
$marks_query = "SELECT 
    s.id as submission_id,
    s.submission_title,
    s.marks_obtained,
    s.max_marks,
    s.evaluator_remarks,
    s.evaluated_at,
    s.created_at as submitted_at,
    u.name as student_name,
    u.email as student_email,
    sub.name as subject_name,
    sub.code as subject_code,
    ROUND((s.marks_obtained / s.max_marks) * 100, 2) as percentage
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    WHERE s.evaluator_id = ? AND s.status = 'evaluated' $subject_filter
    ORDER BY s.evaluated_at DESC";

$marks_stmt = $conn->prepare($marks_query);
$marks_stmt->bind_param($types, ...$params);
$marks_stmt->execute();
$marks_results = $marks_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_evaluated = count($marks_results);
$total_marks_given = array_sum(array_column($marks_results, 'marks_obtained'));
$total_max_marks = array_sum(array_column($marks_results, 'max_marks'));
$avg_percentage = $total_max_marks > 0 ? round(($total_marks_given / $total_max_marks) * 100, 2) : 0;

?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center bg-primary text-white">
            <div class="card-body">
                <h4><?= $total_evaluated ?></h4>
                <small>Total Evaluated</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h4><?= $total_marks_given ?></h4>
                <small>Total Marks Given</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-info text-white">
            <div class="card-body">
                <h4><?= $total_max_marks ?></h4>
                <small>Total Max Marks</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-warning text-white">
            <div class="card-body">
                <h4><?= $avg_percentage ?>%</h4>
                <small>Average %</small>
            </div>
        </div>
    </div>
</div>

<h6 class="mb-3">Marking History for <?= htmlspecialchars($evaluator_info['name']) ?></h6>

<?php if (empty($marks_results)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        No evaluated submissions found for this evaluator<?= $subject_id ? ' in the selected subject' : '' ?>.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Submission</th>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Marks</th>
                    <th>Percentage</th>
                    <th>Evaluated Date</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($marks_results as $mark): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($mark['submission_title'] ?: 'Submission #' . $mark['submission_id']) ?></strong>
                        <br>
                        <small class="text-muted">ID: <?= $mark['submission_id'] ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars($mark['student_name']) ?>
                        <br>
                        <small class="text-muted"><?= htmlspecialchars($mark['student_email']) ?></small>
                    </td>
                    <td>
                        <span class="badge bg-secondary"><?= htmlspecialchars($mark['subject_code']) ?></span>
                        <br>
                        <small><?= htmlspecialchars($mark['subject_name']) ?></small>
                    </td>
                    <td>
                        <strong><?= $mark['marks_obtained'] ?>/<?= $mark['max_marks'] ?></strong>
                    </td>
                    <td>
                        <span class="badge bg-<?= $mark['percentage'] >= 80 ? 'success' : ($mark['percentage'] >= 60 ? 'warning' : 'danger') ?>">
                            <?= $mark['percentage'] ?>%
                        </span>
                    </td>
                    <td>
                        <?= date('M j, Y', strtotime($mark['evaluated_at'])) ?>
                        <br>
                        <small class="text-muted"><?= date('g:i A', strtotime($mark['evaluated_at'])) ?></small>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($mark['evaluator_remarks']) ?>">
                            <?= htmlspecialchars(substr($mark['evaluator_remarks'], 0, 50)) ?>
                            <?= strlen($mark['evaluator_remarks']) > 50 ? '...' : '' ?>
                        </div>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewSubmissionDetails(<?= $mark['submission_id'] ?>)" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="overrideMarks(<?= $mark['submission_id'] ?>, <?= $mark['marks_obtained'] ?>, <?= $mark['max_marks'] ?>)" title="Override Marks">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Marking Pattern Analysis -->
    <div class="mt-4">
        <h6>Marking Pattern Analysis</h6>
        <div class="row">
            <div class="col-md-6">
                <canvas id="marksDistributionChart" width="400" height="200"></canvas>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Statistics</h6>
                        <?php
                        $percentages = array_column($marks_results, 'percentage');
                        $grade_a = count(array_filter($percentages, function($p) { return $p >= 80; }));
                        $grade_b = count(array_filter($percentages, function($p) { return $p >= 60 && $p < 80; }));
                        $grade_c = count(array_filter($percentages, function($p) { return $p < 60; }));
                        ?>
                        <p class="mb-2">Grade A (80%+): <span class="badge bg-success"><?= $grade_a ?></span></p>
                        <p class="mb-2">Grade B (60-79%): <span class="badge bg-warning"><?= $grade_b ?></span></p>
                        <p class="mb-2">Grade C (<60%): <span class="badge bg-danger"><?= $grade_c ?></span></p>
                        <hr>
                        <p class="mb-1"><strong>Strictness Level:</strong> 
                            <span class="badge bg-<?= $avg_percentage > 75 ? 'success' : ($avg_percentage > 60 ? 'warning' : 'danger') ?>">
                                <?= $avg_percentage > 75 ? 'Lenient' : ($avg_percentage > 60 ? 'Moderate' : 'Strict') ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function viewSubmissionDetails(submissionId) {
    window.open('../student/submission_status.php?id=' + submissionId, '_blank');
}
</script>