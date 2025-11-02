<?php
require_once('../config/config.php');
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$evaluator_id = intval($input['evaluator_id'] ?? 0);
$submission_id = intval($input['submission_id'] ?? 0);
$overall_rating = intval($input['overall_rating'] ?? 0);
$evaluation_quality = trim($input['evaluation_quality'] ?? '');
$feedback_helpfulness = trim($input['feedback_helpfulness'] ?? '');
$comments = trim($input['comments'] ?? '');
$student_id = $_SESSION['user_id'];

// Validation
if (!$evaluator_id || !$submission_id || !$overall_rating || !$evaluation_quality || !$feedback_helpfulness) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if ($overall_rating < 1 || $overall_rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit;
}

// Verify that the submission belongs to the student and was evaluated by the evaluator
$verify_query = "SELECT id FROM submissions 
                 WHERE id = ? AND student_id = ? AND evaluator_id = ? AND evaluation_status = 'evaluated'";
$verify_stmt = $conn->prepare($verify_query);
if (!$verify_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$verify_stmt->bind_param("iii", $submission_id, $student_id, $evaluator_id);
$verify_stmt->execute();
$result = $verify_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission or evaluator']);
    exit;
}

try {
    // Check if rating already exists for this submission
    $check_query = "SELECT id FROM evaluator_ratings 
                    WHERE student_id = ? AND evaluator_id = ? AND submission_id = ?";
    $check_stmt = $conn->prepare($check_query);
    
    if (!$check_stmt) {
        // Create the table if it doesn't exist
        $create_table_query = "
        CREATE TABLE IF NOT EXISTS evaluator_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            evaluator_id INT NOT NULL,
            submission_id INT NOT NULL,
            overall_rating TINYINT NOT NULL CHECK (overall_rating BETWEEN 1 AND 5),
            evaluation_quality ENUM('excellent', 'good', 'average', 'poor') NOT NULL,
            feedback_helpfulness ENUM('very_helpful', 'helpful', 'somewhat_helpful', 'not_helpful') NOT NULL,
            comments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_rating (student_id, evaluator_id, submission_id),
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
        )";
        
        if (!$conn->query($create_table_query)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create ratings table']);
            exit;
        }
        
        // Retry preparing the check statement
        $check_stmt = $conn->prepare($check_query);
    }
    
    if ($check_stmt) {
        $check_stmt->bind_param("iii", $student_id, $evaluator_id, $submission_id);
        $check_stmt->execute();
        $existing_result = $check_stmt->get_result();
        
        if ($existing_result->num_rows > 0) {
            // Update existing rating
            $update_query = "UPDATE evaluator_ratings 
                            SET overall_rating = ?, evaluation_quality = ?, feedback_helpfulness = ?, comments = ?, updated_at = NOW()
                            WHERE student_id = ? AND evaluator_id = ? AND submission_id = ?";
            $update_stmt = $conn->prepare($update_query);
            
            if (!$update_stmt) {
                echo json_encode(['success' => false, 'message' => 'Failed to prepare update statement']);
                exit;
            }
            
            $update_stmt->bind_param("isssiil", $overall_rating, $evaluation_quality, $feedback_helpfulness, $comments, $student_id, $evaluator_id, $submission_id);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Rating updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update rating']);
            }
        } else {
            // Insert new rating
            $insert_query = "INSERT INTO evaluator_ratings 
                            (student_id, evaluator_id, submission_id, overall_rating, evaluation_quality, feedback_helpfulness, comments)
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            
            if (!$insert_stmt) {
                echo json_encode(['success' => false, 'message' => 'Failed to prepare insert statement']);
                exit;
            }
            
            $insert_stmt->bind_param("iiiisss", $student_id, $evaluator_id, $submission_id, $overall_rating, $evaluation_quality, $feedback_helpfulness, $comments);
            
            if ($insert_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to submit rating']);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database preparation error']);
    }
    
} catch (Exception $e) {
    error_log("Rating submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your rating']);
}
?>