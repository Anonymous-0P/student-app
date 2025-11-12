<?php
require_once('config/config.php');
// Accept id from CLI or GET
$submission_id = 0;
if (php_sapi_name() === 'cli') {
    global $argv;
    foreach ($argv as $arg) {
        if (preg_match('/^(--)?id=(\d+)$/', $arg, $m)) {
            $submission_id = intval($m[2]);
            break;
        }
    }
} else {
    $submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
}
if (!$submission_id) { die('No submission id'); }
$stmt = $conn->prepare("SELECT id, per_question_marks, marks_obtained, evaluator_remarks FROM submissions WHERE id = ?");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if (!$row) { die('Submission not found'); }
echo "Submission ID: " . $row['id'] . "\n";
echo "Marks Obtained: " . $row['marks_obtained'] . "\n";
echo "Evaluator Remarks: " . $row['evaluator_remarks'] . "\n";
echo "per_question_marks (raw):\n";
echo $row['per_question_marks'] . "\n";
if ($row['per_question_marks']) {
    $decoded = json_decode($row['per_question_marks'], true);
    echo "Decoded:\n";
    print_r($decoded);
} else {
    echo "No question-wise marks found.";
}
