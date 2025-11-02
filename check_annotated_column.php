<?php
require_once('config/config.php');

echo "Checking submissions table structure...\n\n";

$result = $conn->query('DESCRIBE submissions');
$columns = [];
while($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n\n";

if (in_array('annotated_pdf_url', $columns)) {
    echo "✅ annotated_pdf_url column EXISTS\n\n";
    
    // Check if there are any submissions with annotated PDFs
    $checkData = $conn->query("SELECT id, annotated_pdf_url FROM submissions WHERE annotated_pdf_url IS NOT NULL AND annotated_pdf_url != ''");
    if ($checkData->num_rows > 0) {
        echo "Found " . $checkData->num_rows . " submission(s) with annotated PDFs:\n";
        while($row = $checkData->fetch_assoc()) {
            echo "  - Submission ID: " . $row['id'] . " -> " . $row['annotated_pdf_url'] . "\n";
            $filePath = $row['annotated_pdf_url'];
            if (file_exists($filePath)) {
                echo "    ✅ File exists\n";
            } else {
                echo "    ❌ File NOT found\n";
            }
        }
    } else {
        echo "No submissions have annotated PDFs yet.\n";
    }
} else {
    echo "❌ annotated_pdf_url column DOES NOT EXIST\n";
    echo "\nYou need to run the migration first!\n";
    echo "Open: http://localhost/student-app/add_annotated_pdf_column_script.php\n";
}
?>
