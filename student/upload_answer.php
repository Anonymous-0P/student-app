<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in as student
checkLogin('student');

header('Content-Type: application/json');

// Configuration
$maxPdfSize = 25 * 1024 * 1024; // 25MB per submission
$allowedPdfMime = ['application/pdf'];

$respond = function($status, $message, $extra = []) {
    $payload = array_merge(['status' => $status, 'message' => $message], $extra);
    echo json_encode($payload);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond('error', 'Invalid request method.');
}

// Get form data
$subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
$paper_id = isset($_POST['paper_id']) ? (int)$_POST['paper_id'] : null;

if (empty($subject_id)) {
    $respond('error', 'Subject ID is required.');
}

if (empty($paper_id)) {
    $respond('error', 'Question paper ID is required.');
}

// Validate the question paper belongs to the subject
$paperCheckStmt = $conn->prepare("SELECT id, title FROM question_papers WHERE id = ? AND subject_id = ?");
$paperCheckStmt->bind_param("ii", $paper_id, $subject_id);
$paperCheckStmt->execute();
$paperResult = $paperCheckStmt->get_result();

if ($paperResult->num_rows === 0) {
    $respond('error', 'Invalid question paper or subject.');
}

$paperInfo = $paperResult->fetch_assoc();

// Validate uploaded file
if (!isset($_FILES['pdf_file'])) {
    $respond('error', 'No answer sheet received. Please try again.');
}

$pdfFile = $_FILES['pdf_file'];
if ($pdfFile['error'] !== UPLOAD_ERR_OK) {
    $respond('error', 'Upload failed. Please try again.');
}

if ($pdfFile['size'] > $maxPdfSize) {
    $respond('error', 'Answer sheet is too large. Please keep submissions under 25MB.');
}

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $pdfFile['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedPdfMime)) {
    $respond('error', 'Only PDF files are allowed.');
}

// Generate unique filename
$filename = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
$relativePath = 'uploads/pdfs/' . $filename;
$storePath = dirname(__DIR__) . '/' . $relativePath;

// Ensure upload directory exists
if (!is_dir(dirname($storePath))) {
    mkdir(dirname($storePath), 0775, true);
}

// Move uploaded file
if (!move_uploaded_file($pdfFile['tmp_name'], $storePath)) {
    $respond('error', 'Could not store uploaded answer sheet.');
}

$originalNames = trim($_POST['original_names'] ?? '');

// Start database transaction
$conn->begin_transaction();

try {
    // Check if question_paper_id column exists in submissions table
    $checkColumn = $conn->query("SHOW COLUMNS FROM submissions LIKE 'question_paper_id'");
    if ($checkColumn->num_rows === 0) {
        // Add the column if it doesn't exist
        $conn->query("ALTER TABLE submissions ADD COLUMN question_paper_id INT NULL AFTER subject_id");
        $conn->query("ALTER TABLE submissions ADD FOREIGN KEY (question_paper_id) REFERENCES question_papers(id) ON DELETE SET NULL");
    }
    
    // Insert submission
    $stmt = $conn->prepare("
        INSERT INTO submissions (student_id, subject_id, question_paper_id, pdf_url, original_filename, file_size, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $fileSize = (int)$pdfFile['size'];
    $stmt->bind_param("iiissi", $_SESSION['user_id'], $subject_id, $paper_id, $relativePath, $originalNames, $fileSize);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert submission');
    }
    
    $submission_id = $conn->insert_id;
    
    // Get all evaluators assigned to this subject
    $evaluatorStmt = $conn->prepare("
        SELECT DISTINCT u.id, u.name, u.email 
        FROM users u 
        INNER JOIN evaluator_subjects es ON u.id = es.evaluator_id 
        WHERE es.subject_id = ? AND u.role = 'evaluator' AND u.is_active = 1
    ");
    $evaluatorStmt->bind_param("i", $subject_id);
    $evaluatorStmt->execute();
    $evaluators = $evaluatorStmt->get_result();
    
    if ($evaluators->num_rows == 0) {
        throw new Exception('No evaluators found for this subject');
    }
    
    // Create assignment records for all evaluators
    $assignStmt = $conn->prepare("INSERT INTO submission_assignments (submission_id, evaluator_id, status, assigned_at) VALUES (?, ?, 'pending', NOW())");
    $notifyStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, created_at) VALUES (?, 'assignment_offered', ?, ?, ?, NOW())");
    
    while ($evaluator = $evaluators->fetch_assoc()) {
        // Create assignment record
        $assignStmt->bind_param("ii", $submission_id, $evaluator['id']);
        if (!$assignStmt->execute()) {
            throw new Exception('Failed to create assignment for evaluator ' . $evaluator['name']);
        }
        
        // Create notification
        $notifyTitle = "New Assignment Available";
        $notifyMessage = "A new answer sheet submission for '{$paperInfo['title']}' is available for evaluation.";
        $notifyStmt->bind_param("issi", $evaluator['id'], $notifyTitle, $notifyMessage, $submission_id);
        $notifyStmt->execute(); // Don't fail on notification errors
    }
    
    // Commit transaction
    $conn->commit();
    
    $respond('success', 'Answer sheet uploaded successfully and assigned to evaluators!', [
        'redirect' => 'view_submissions.php',
        'submission_id' => $submission_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Clean up uploaded file on database error
    if (file_exists($storePath)) {
        unlink($storePath);
    }
    
    $respond('error', 'Error: ' . $e->getMessage());
}
?>