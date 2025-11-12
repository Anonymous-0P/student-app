<?php
require_once('config/config.php');

echo "=== Adding per_question_marks column ===\n\n";

// Add the column
$sql = "ALTER TABLE `submissions` ADD COLUMN `per_question_marks` TEXT NULL AFTER `evaluator_remarks`";

if ($conn->query($sql)) {
    echo "✓ Successfully added per_question_marks column\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

// Verify the column was added
echo "\n=== Verifying column ===\n";
$result = $conn->query("SHOW COLUMNS FROM submissions LIKE 'per_question_marks'");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "✓ Column exists: " . $row['Field'] . " (" . $row['Type'] . ")\n";
} else {
    echo "✗ Column not found\n";
}
?>
