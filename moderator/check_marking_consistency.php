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

// Find submissions that might have inconsistencies
// This could be submissions evaluated by different evaluators for similar content,
// or submissions with unusual marking patterns

// 1. Find submissions with similar content but different marking patterns
$inconsistency_query = "SELECT 
    s1.id as submission1_id,
    s1.submission_title as title1,
    s1.marks_obtained as marks1,
    s1.max_marks as max_marks1,
    ROUND((s1.marks_obtained / s1.max_marks) * 100, 2) as percentage1,
    u1.name as student1,
    e1.name as evaluator1,
    s2.id as submission2_id,
    s2.submission_title as title2,
    s2.marks_obtained as marks2,
    s2.max_marks as max_marks2,
    ROUND((s2.marks_obtained / s2.max_marks) * 100, 2) as percentage2,
    u2.name as student2,
    e2.name as evaluator2,
    sub.name as subject_name,
    sub.code as subject_code,
    ABS(ROUND((s1.marks_obtained / s1.max_marks) * 100, 2) - ROUND((s2.marks_obtained / s2.max_marks) * 100, 2)) as percentage_diff
    FROM submissions s1
    JOIN submissions s2 ON s1.subject_id = s2.subject_id 
        AND s1.id < s2.id 
        AND s1.evaluator_id != s2.evaluator_id
        AND s1.status = 'evaluated' 
        AND s2.status = 'evaluated'
    JOIN users u1 ON s1.student_id = u1.id
    JOIN users u2 ON s2.student_id = u2.id
    JOIN users e1 ON s1.evaluator_id = e1.id
    JOIN users e2 ON s2.evaluator_id = e2.id
    JOIN subjects sub ON s1.subject_id = sub.id
    JOIN moderator_subjects ms ON sub.id = ms.subject_id
    WHERE ms.moderator_id = ? 
        AND ms.is_active = 1
        AND ABS(ROUND((s1.marks_obtained / s1.max_marks) * 100, 2) - ROUND((s2.marks_obtained / s2.max_marks) * 100, 2)) > 20
    ORDER BY percentage_diff DESC
    LIMIT 20";

$inconsistency_stmt = $conn->prepare($inconsistency_query);
$inconsistency_stmt->bind_param("i", $moderator_id);
$inconsistency_stmt->execute();
$inconsistencies = $inconsistency_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Find evaluators with unusual marking patterns
$evaluator_patterns_query = "SELECT 
    e.id as evaluator_id,
    e.name as evaluator_name,
    COUNT(s.id) as total_evaluations,
    AVG(ROUND((s.marks_obtained / s.max_marks) * 100, 2)) as avg_percentage,
    MIN(ROUND((s.marks_obtained / s.max_marks) * 100, 2)) as min_percentage,
    MAX(ROUND((s.marks_obtained / s.max_marks) * 100, 2)) as max_percentage,
    STDDEV(ROUND((s.marks_obtained / s.max_marks) * 100, 2)) as std_deviation
    FROM users e
    JOIN submissions s ON e.id = s.evaluator_id
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN moderator_subjects ms ON sub.id = ms.subject_id
    WHERE e.role = 'evaluator' 
        AND e.moderator_id = ?
        AND s.status = 'evaluated'
        AND ms.moderator_id = ?
        AND ms.is_active = 1
    GROUP BY e.id, e.name
    HAVING total_evaluations >= 3
    ORDER BY std_deviation DESC";

$evaluator_patterns_stmt = $conn->prepare($evaluator_patterns_query);
$evaluator_patterns_stmt->bind_param("ii", $moderator_id, $moderator_id);
$evaluator_patterns_stmt->execute();
$evaluator_patterns = $evaluator_patterns_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Find submissions that need second opinion
$second_opinion_query = "SELECT 
    s.id as submission_id,
    s.submission_title,
    s.marks_obtained,
    s.max_marks,
    ROUND((s.marks_obtained / s.max_marks) * 100, 2) as percentage,
    u.name as student_name,
    e.name as evaluator_name,
    sub.name as subject_name,
    sub.code as subject_code,
    s.evaluated_at
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    JOIN users e ON s.evaluator_id = e.id
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN moderator_subjects ms ON sub.id = ms.subject_id
    WHERE s.status = 'evaluated'
        AND ms.moderator_id = ?
        AND ms.is_active = 1
        AND (
            ROUND((s.marks_obtained / s.max_marks) * 100, 2) < 40 OR
            ROUND((s.marks_obtained / s.max_marks) * 100, 2) > 95 OR
            LENGTH(s.evaluator_remarks) < 20
        )
    ORDER BY s.evaluated_at DESC
    LIMIT 10";

$second_opinion_stmt = $conn->prepare($second_opinion_query);
$second_opinion_stmt->bind_param("i", $moderator_id);
$second_opinion_stmt->execute();
$second_opinion = $second_opinion_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center bg-warning text-white">
            <div class="card-body">
                <h4><?= count($inconsistencies) ?></h4>
                <small>Potential Inconsistencies</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center bg-info text-white">
            <div class="card-body">
                <h4><?= count($evaluator_patterns) ?></h4>
                <small>Evaluators Analyzed</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center bg-danger text-white">
            <div class="card-body">
                <h4><?= count($second_opinion) ?></h4>
                <small>Need Review</small>
            </div>
        </div>
    </div>
</div>

<!-- Inconsistencies Found -->
<div class="mb-4">
    <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i>Large Marking Differences (>20%)</h6>
    <?php if (empty($inconsistencies)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>No significant marking inconsistencies found.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Subject</th>
                        <th>Submission 1</th>
                        <th>Submission 2</th>
                        <th>Evaluators</th>
                        <th>Marks</th>
                        <th>Difference</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inconsistencies as $inc): ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($inc['subject_code']) ?></span>
                            <br><small><?= htmlspecialchars($inc['subject_name']) ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($inc['student1']) ?></strong>
                            <br><small>ID: <?= $inc['submission1_id'] ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($inc['student2']) ?></strong>
                            <br><small>ID: <?= $inc['submission2_id'] ?></small>
                        </td>
                        <td>
                            <div class="mb-1"><?= htmlspecialchars($inc['evaluator1']) ?></div>
                            <div><?= htmlspecialchars($inc['evaluator2']) ?></div>
                        </td>
                        <td>
                            <div class="mb-1"><?= $inc['marks1'] ?>/<?= $inc['max_marks1'] ?> (<?= $inc['percentage1'] ?>%)</div>
                            <div><?= $inc['marks2'] ?>/<?= $inc['max_marks2'] ?> (<?= $inc['percentage2'] ?>%)</div>
                        </td>
                        <td>
                            <span class="badge bg-danger"><?= $inc['percentage_diff'] ?>%</span>
                        </td>
                        <td>
                            <div class="btn-group-vertical" role="group">
                                <button class="btn btn-sm btn-outline-primary" onclick="compareSubmissions(<?= $inc['submission1_id'] ?>, <?= $inc['submission2_id'] ?>)">
                                    <i class="fas fa-balance-scale"></i> Compare
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="reviewInconsistency(<?= $inc['submission1_id'] ?>, <?= $inc['submission2_id'] ?>)">
                                    <i class="fas fa-gavel"></i> Review
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Evaluator Marking Patterns -->
<div class="mb-4">
    <h6><i class="fas fa-chart-line text-info me-2"></i>Evaluator Marking Patterns</h6>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Evaluator</th>
                    <th>Evaluations</th>
                    <th>Average %</th>
                    <th>Range</th>
                    <th>Consistency</th>
                    <th>Pattern</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($evaluator_patterns as $pattern): ?>
                <tr>
                    <td><?= htmlspecialchars($pattern['evaluator_name']) ?></td>
                    <td><?= $pattern['total_evaluations'] ?></td>
                    <td>
                        <span class="badge bg-<?= $pattern['avg_percentage'] > 75 ? 'success' : ($pattern['avg_percentage'] > 60 ? 'warning' : 'danger') ?>">
                            <?= round($pattern['avg_percentage'], 1) ?>%
                        </span>
                    </td>
                    <td><?= round($pattern['min_percentage'], 1) ?>% - <?= round($pattern['max_percentage'], 1) ?>%</td>
                    <td>
                        <?php $consistency = $pattern['std_deviation']; ?>
                        <span class="badge bg-<?= $consistency < 10 ? 'success' : ($consistency < 20 ? 'warning' : 'danger') ?>">
                            <?= $consistency < 10 ? 'High' : ($consistency < 20 ? 'Medium' : 'Low') ?>
                        </span>
                        <br><small>(σ = <?= round($consistency, 1) ?>)</small>
                    </td>
                    <td>
                        <?php 
                        $avg = $pattern['avg_percentage'];
                        if ($avg > 80) echo '<span class="badge bg-success">Lenient</span>';
                        elseif ($avg > 60) echo '<span class="badge bg-warning">Moderate</span>';
                        else echo '<span class="badge bg-danger">Strict</span>';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Submissions Needing Review -->
<div class="mb-4">
    <h6><i class="fas fa-flag text-danger me-2"></i>Submissions Needing Review</h6>
    <small class="text-muted">Submissions with very high/low marks or insufficient feedback</small>
    
    <?php if (empty($second_opinion)): ?>
        <div class="alert alert-success mt-2">
            <i class="fas fa-check-circle me-2"></i>No submissions flagged for review.
        </div>
    <?php else: ?>
        <div class="table-responsive mt-2">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Submission</th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Evaluator</th>
                        <th>Marks</th>
                        <th>Flag Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($second_opinion as $review): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($review['submission_title'] ?: 'Submission #' . $review['submission_id']) ?>
                            <br><small class="text-muted">ID: <?= $review['submission_id'] ?></small>
                        </td>
                        <td><?= htmlspecialchars($review['student_name']) ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($review['subject_code']) ?></span>
                            <br><small><?= htmlspecialchars($review['subject_name']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($review['evaluator_name']) ?></td>
                        <td>
                            <span class="badge bg-<?= $review['percentage'] < 40 ? 'danger' : ($review['percentage'] > 95 ? 'success' : 'warning') ?>">
                                <?= $review['marks_obtained'] ?>/<?= $review['max_marks'] ?> (<?= $review['percentage'] ?>%)
                            </span>
                        </td>
                        <td>
                            <?php
                            $reasons = [];
                            if ($review['percentage'] < 40) $reasons[] = 'Very Low Score';
                            if ($review['percentage'] > 95) $reasons[] = 'Very High Score';
                            if (strlen($review['evaluator_remarks'] ?? '') < 20) $reasons[] = 'Insufficient Feedback';
                            echo implode(', ', $reasons);
                            ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-primary" onclick="reviewSubmission(<?= $review['submission_id'] ?>)">
                                    <i class="fas fa-eye"></i> Review
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="overrideMarks(<?= $review['submission_id'] ?>, <?= $review['marks_obtained'] ?>, <?= $review['max_marks'] ?>)">
                                    <i class="fas fa-edit"></i> Override
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function compareSubmissions(id1, id2) {
    window.open(`compare_submissions.php?s1=${id1}&s2=${id2}`, '_blank', 'width=1200,height=800');
}

function reviewInconsistency(id1, id2) {
    if (confirm('This will open both submissions for detailed comparison. Continue?')) {
        window.open(`../evaluator/evaluate.php?id=${id1}`, '_blank');
        setTimeout(() => {
            window.open(`../evaluator/evaluate.php?id=${id2}`, '_blank');
        }, 1000);
    }
}

function reviewSubmission(submissionId) {
    window.open(`../evaluator/evaluate.php?id=${submissionId}`, '_blank');
}
</script>