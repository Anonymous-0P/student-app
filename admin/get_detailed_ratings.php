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
    // Get detailed ratings with evaluator and student names
    $detailedRatings = $conn->prepare("
        SELECT 
            er.*,
            evaluator.name as evaluator_name,
            student.name as student_name,
            s.name as subject_name
        FROM evaluator_ratings er
        JOIN users evaluator ON er.evaluator_id = evaluator.id
        JOIN users student ON er.student_id = student.id
        LEFT JOIN submissions sub ON er.submission_id = sub.id
        LEFT JOIN subjects s ON sub.subject_id = s.id
        ORDER BY er.created_at DESC
    ");
    
    $detailedRatings->execute();
    $ratings = $detailedRatings->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get statistics
    $stats = $conn->query("SELECT 
        COUNT(*) as total_ratings,
        ROUND(AVG(overall_rating), 1) as avg_overall_rating,
        COUNT(CASE WHEN overall_rating >= 4 THEN 1 END) as excellent_ratings,
        COUNT(CASE WHEN overall_rating < 3 THEN 1 END) as poor_ratings,
        COUNT(CASE WHEN evaluation_quality = 'excellent' THEN 1 END) as excellent_quality,
        COUNT(CASE WHEN feedback_helpfulness = 'very_helpful' THEN 1 END) as very_helpful_feedback
        FROM evaluator_ratings")->fetch_assoc();
    
    // Format ratings for JSON response
    $formattedRatings = [];
    foreach ($ratings as $rating) {
        $formattedRatings[] = [
            'id' => $rating['id'],
            'evaluator_name' => $rating['evaluator_name'],
            'student_name' => $rating['student_name'],
            'subject_name' => $rating['subject_name'] ?? 'N/A',
            'overall_rating' => (int)$rating['overall_rating'],
            'evaluation_quality' => $rating['evaluation_quality'],
            'feedback_helpfulness' => $rating['feedback_helpfulness'],
            'comments' => $rating['comments'],
            'created_at' => $rating['created_at']
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'ratings' => $formattedRatings,
        'stats' => [
            'total_ratings' => (int)$stats['total_ratings'],
            'avg_overall_rating' => $stats['avg_overall_rating'] ?? '0.0',
            'excellent_ratings' => (int)$stats['excellent_ratings'],
            'poor_ratings' => (int)$stats['poor_ratings']
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