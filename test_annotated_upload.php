<?php
require_once('config/config.php');

echo "<h2>Annotated PDF Upload Test</h2>";

// Check if annotated_pdfs directory exists
$upload_dir = __DIR__ . '/uploads/annotated_pdfs';
if (!is_dir($upload_dir)) {
    echo "<p style='color: orange;'>❌ Directory does not exist. Creating it...</p>";
    mkdir($upload_dir, 0775, true);
    echo "<p style='color: green;'>✅ Directory created successfully!</p>";
} else {
    echo "<p style='color: green;'>✅ Directory exists: " . htmlspecialchars($upload_dir) . "</p>";
}

// Check directory permissions
if (is_writable($upload_dir)) {
    echo "<p style='color: green;'>✅ Directory is writable</p>";
} else {
    echo "<p style='color: red;'>❌ Directory is NOT writable</p>";
}

// Check database column
$check_column = $conn->query("SHOW COLUMNS FROM submissions LIKE 'annotated_pdf_url'");
if ($check_column && $check_column->num_rows > 0) {
    echo "<p style='color: green;'>✅ Database column 'annotated_pdf_url' exists</p>";
} else {
    echo "<p style='color: red;'>❌ Database column 'annotated_pdf_url' does NOT exist</p>";
}

// List existing annotated PDFs
echo "<h3>Existing Annotated PDFs:</h3>";
$files = glob($upload_dir . '/*.pdf');
if (empty($files)) {
    echo "<p>No annotated PDFs found yet.</p>";
} else {
    echo "<ul>";
    foreach ($files as $file) {
        $size = filesize($file) / 1024; // KB
        echo "<li>" . basename($file) . " (" . number_format($size, 2) . " KB)</li>";
    }
    echo "</ul>";
}

// Test file upload form
?>
<hr>
<h3>Test File Upload</h3>
<form method="POST" enctype="multipart/form-data" style="max-width: 500px; border: 1px solid #ccc; padding: 20px;">
    <div style="margin-bottom: 15px;">
        <label><strong>Upload Test PDF:</strong></label>
        <input type="file" name="test_pdf" accept=".pdf" required style="display: block; margin-top: 5px;">
    </div>
    <button type="submit" name="test_upload" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;">
        Upload Test File
    </button>
</form>

<?php
if (isset($_POST['test_upload']) && isset($_FILES['test_pdf'])) {
    echo "<h3>Upload Test Result:</h3>";
    
    $file = $_FILES['test_pdf'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_type = mime_content_type($file['tmp_name']);
        
        if ($file_type === 'application/pdf') {
            $filename = 'test_' . time() . '.pdf';
            $destination = $upload_dir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                echo "<p style='color: green;'>✅ File uploaded successfully!</p>";
                echo "<p><strong>Filename:</strong> " . htmlspecialchars($filename) . "</p>";
                echo "<p><strong>Size:</strong> " . number_format($file['size'] / 1024, 2) . " KB</p>";
                echo "<p><strong>Path:</strong> " . htmlspecialchars($destination) . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to move uploaded file</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ File is not a PDF (type: " . htmlspecialchars($file_type) . ")</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Upload error: " . $file['error'] . "</p>";
    }
}
?>

<hr>
<p><a href="evaluator/pending_evaluations.php">← Back to Evaluations</a></p>
