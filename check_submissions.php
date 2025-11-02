<?php
require_once('config/config.php');

echo "Checking recent submissions for student...\n\n";

// Get the most recent submission
$result = $conn->query("
    SELECT s.*, 
           sub.name as subject_name, 
           sub.code as subject_code,
           u.name as evaluator_name
    FROM submissions s
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN users u ON s.evaluator_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 5
");

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "=================================\n";
        echo "Submission ID: " . $row['id'] . "\n";
        echo "Subject: " . $row['subject_name'] . " (" . $row['subject_code'] . ")\n";
        echo "Status: " . $row['status'] . "\n";
        echo "Evaluation Status: " . $row['evaluation_status'] . "\n";
        echo "Marks: " . $row['marks_obtained'] . " / " . $row['max_marks'] . "\n";
        echo "Evaluator: " . ($row['evaluator_name'] ?? 'Not assigned') . "\n";
        echo "Original PDF: " . ($row['pdf_url'] ?? 'None') . "\n";
        echo "Annotated PDF: " . ($row['annotated_pdf_url'] ?? 'None') . "\n";
        
        if (!empty($row['annotated_pdf_url'])) {
            if (file_exists($row['annotated_pdf_url'])) {
                echo "  ✅ Annotated file exists\n";
            } else {
                echo "  ❌ Annotated file NOT found at: " . $row['annotated_pdf_url'] . "\n";
            }
        }
        
        echo "Created: " . $row['created_at'] . "\n";
        echo "Evaluated: " . ($row['evaluated_at'] ?? 'Not yet') . "\n";
        echo "\n";
    }
} else {
    echo "No submissions found.\n";
}
?>
