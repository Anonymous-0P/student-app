<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

if (!isset($_GET['paper_id'])) {
    die('No paper ID provided');
}

$paper_id = (int)$_GET['paper_id'];
echo "<h3>PDF Debug Test for Paper ID: $paper_id</h3>";

// Get paper details
$paperStmt = $conn->prepare("SELECT qp.*, s.code as subject_code, s.name as subject_name 
                            FROM question_papers qp 
                            LEFT JOIN subjects s ON qp.subject_id = s.id 
                            WHERE qp.id = ? AND qp.is_active = 1");
$paperStmt->bind_param("i", $paper_id);
$paperStmt->execute();
$paper = $paperStmt->get_result()->fetch_assoc();

if (!$paper) {
    echo "<p style='color: red;'>❌ Paper not found in database</p>";
    exit;
}

echo "<p>✅ Paper found: " . htmlspecialchars($paper['title']) . "</p>";
echo "<p>📁 File path: " . htmlspecialchars($paper['file_path']) . "</p>";

if (!file_exists($paper['file_path'])) {
    echo "<p style='color: red;'>❌ PDF file does not exist on server</p>";
    echo "<p>Looking for: " . htmlspecialchars($paper['file_path']) . "</p>";
} else {
    echo "<p>✅ PDF file exists on server</p>";
    echo "<p>📊 File size: " . number_format(filesize($paper['file_path'])) . " bytes</p>";
    echo "<p>🔗 MIME type: " . htmlspecialchars($paper['mime_type']) . "</p>";
}

echo "<hr>";
echo "<h4>Test Links:</h4>";
echo "<p><a href='pdf_viewer.php?serve_pdf=1&paper_id=$paper_id' target='_blank'>🔗 Direct PDF Serve Test</a></p>";
echo "<p><a href='pdf_viewer.php?paper_id=$paper_id'>🔗 Full PDF Viewer Test</a></p>";

echo "<hr>";
echo "<h4>Database Info:</h4>";
echo "<pre>";
print_r($paper);
echo "</pre>";
?>