<?php
require_once('config/config.php');

echo "Creating test annotated PDF for submission ID 88...\n\n";

// Create the annotated_pdfs directory if it doesn't exist
$dir = 'uploads/annotated_pdfs';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
    echo "✅ Created directory: $dir\n";
}

// Copy the original PDF and rename it as an "annotated" version for testing
$originalPdf = 'uploads/pdfs/1761992397_d55c66b27a96.pdf';
$annotatedPdf = 'uploads/annotated_pdfs/annotated_88_' . time() . '.pdf';

if (file_exists($originalPdf)) {
    if (copy($originalPdf, $annotatedPdf)) {
        echo "✅ Created test annotated PDF: $annotatedPdf\n\n";
        
        // Update the database
        $stmt = $conn->prepare("UPDATE submissions SET annotated_pdf_url = ? WHERE id = 88");
        $stmt->bind_param("s", $annotatedPdf);
        
        if ($stmt->execute()) {
            echo "✅ Database updated successfully!\n\n";
            echo "Now reload the student dashboard and you should see:\n";
            echo "  - '✓ Evaluated' button in desktop view\n";
            echo "  - 'Download Annotated Answer Sheet' button in mobile view\n\n";
            echo "The button will open: $annotatedPdf\n";
        } else {
            echo "❌ Failed to update database: " . $conn->error . "\n";
        }
    } else {
        echo "❌ Failed to copy file\n";
    }
} else {
    echo "❌ Original PDF not found: $originalPdf\n";
}
?>
