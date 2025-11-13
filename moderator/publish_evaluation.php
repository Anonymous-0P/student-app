<?php
// Always return clean JSON and guard against warnings
header('Content-Type: application/json');
ob_start();

require_once('../config/config.php');
require_once('../includes/functions.php');
session_start();

// Clear any buffered output (warnings/notices) before sending JSON
if (ob_get_length()) { ob_clean(); }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    // Auth: moderator only
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'moderator') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
    if (!$submission_id) {
        echo json_encode(['success' => false, 'message' => 'Missing submission_id']);
        exit;
    }

    // Only set publication flags; leave existing status/evaluation_status unchanged (enum safe)
    $stmt = $conn->prepare("UPDATE submissions 
        SET is_published = 1, published_at = NOW(), updated_at = NOW() 
        WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $submission_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
            exit;
        } else {
            // If column missing, instruct migration
            echo json_encode([
                'success' => false,
                'message' => 'Publish failed: ' . $stmt->error . ' (Run db/add_publish_gating_columns.sql)'
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Prepare failed: ' . $conn->error . ' (Run db/add_publish_gating_columns.sql)'
        ]);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
