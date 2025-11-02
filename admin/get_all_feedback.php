<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

try {
    // Check if evaluator_ratings table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'evaluator_ratings'");
    if (!$tableExists || $tableExists->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'feedback' => [],
            'stats' => [
                'total_feedback' => 0,
                'avg_rating' => '0.0',
                'with_comments' => 0
            ]
        ]);
        exit;
    }

    // Get all feedback with comments
    $allFeedback = $conn->query("
        SELECT 
            er.*,
            evaluator.name as evaluator_name,
            student.name as student_name
        FROM evaluator_ratings er
        JOIN users evaluator ON er.evaluator_id = evaluator.id
        JOIN users student ON er.student_id = student.id
        WHERE er.comments IS NOT NULL AND er.comments != ''
        ORDER BY er.created_at DESC
    ");
    
    $feedback = $allFeedback ? $allFeedback->fetch_all(MYSQLI_ASSOC) : [];
    
    // Get feedback statistics
    $stats = $conn->query("SELECT 
        COUNT(*) as total_feedback,
        ROUND(AVG(overall_rating), 1) as avg_rating,
        COUNT(CASE WHEN comments IS NOT NULL AND comments != '' THEN 1 END) as with_comments
        FROM evaluator_ratings")->fetch_assoc();
    
    // Format feedback for JSON response
    $formattedFeedback = [];
    foreach ($feedback as $item) {
        $formattedFeedback[] = [
            'id' => $item['id'],
            'evaluator_name' => $item['evaluator_name'],
            'student_name' => $item['student_name'],
            'overall_rating' => (int)$item['overall_rating'],
            'evaluation_quality' => $item['evaluation_quality'],
            'feedback_helpfulness' => $item['feedback_helpfulness'],
            'comments' => $item['comments'],
            'created_at' => $item['created_at']
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'feedback' => $formattedFeedback,
        'stats' => [
            'total_feedback' => (int)$stats['total_feedback'],
            'avg_rating' => $stats['avg_rating'] ?? '0.0',
            'with_comments' => (int)$stats['with_comments']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>