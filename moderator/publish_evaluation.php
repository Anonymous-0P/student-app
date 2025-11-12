<?php
require_once('../config/config.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
$submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
if (!$submission_id) {
    echo json_encode(['success' => false, 'message' => 'No submission id']);
    exit;
}
$stmt = $conn->prepare("UPDATE submissions SET is_published = 1, status = 'published', evaluation_status = 'published', updated_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $submission_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
