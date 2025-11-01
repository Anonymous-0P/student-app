<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('evaluator');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$notification_id = (int)($_POST['notification_id'] ?? 0);

if ($notification_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid notification ID']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update notification']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>