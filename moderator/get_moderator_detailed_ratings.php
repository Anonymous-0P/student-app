<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is moderator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'moderator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$moderator_id = $_SESSION['user_id'];

try {
    // Get detailed ratings for moderator's assigned evaluators only
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
        WHERE evaluator.moderator_id = ?
        ORDER BY er.created_at DESC
    ");
    
    $detailedRatings->bind_param("i", $moderator_id);
    $detailedRatings->execute();
    $ratings = $detailedRatings->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get statistics for moderator's evaluators
    $stats = $conn->prepare("SELECT 
        COUNT(er.id) as total_ratings,
        ROUND(AVG(er.overall_rating), 1) as avg_overall_rating,
        COUNT(CASE WHEN er.overall_rating >= 4 THEN 1 END) as excellent_ratings,
        COUNT(CASE WHEN er.overall_rating < 3 THEN 1 END) as poor_ratings,
        COUNT(CASE WHEN er.evaluation_quality = 'excellent' THEN 1 END) as excellent_quality,
        COUNT(CASE WHEN er.feedback_helpfulness = 'very_helpful' THEN 1 END) as very_helpful_feedback
        FROM evaluator_ratings er
        JOIN users u ON er.evaluator_id = u.id
        WHERE u.moderator_id = ?");
    
    $stats->bind_param("i", $moderator_id);
    $stats->execute();
    $statsResult = $stats->get_result()->fetch_assoc();
    
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
            'total_ratings' => (int)$statsResult['total_ratings'],
            'avg_overall_rating' => $statsResult['avg_overall_rating'] ?? '0.0',
            'excellent_ratings' => (int)$statsResult['excellent_ratings'],
            'poor_ratings' => (int)$statsResult['poor_ratings']
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