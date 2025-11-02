<?php
session_start();
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    echo 'Unauthorized access';
    exit();
}

$submission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$submission_id) {
    echo 'Invalid submission ID';
    exit();
}

// Get submission remarks
$remarks_query = "SELECT evaluator_remarks FROM submissions WHERE id = ?";
$remarks_stmt = $conn->prepare($remarks_query);
$remarks_stmt->bind_param("i", $submission_id);
$remarks_stmt->execute();
$result = $remarks_stmt->get_result()->fetch_assoc();

if ($result && $result['evaluator_remarks']) {
    echo htmlspecialchars($result['evaluator_remarks']);
} else {
    echo 'No remarks available';
}
?>