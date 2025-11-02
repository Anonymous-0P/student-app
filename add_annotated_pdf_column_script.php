<?php
require_once('config/config.php');

echo "<h2>Adding annotated_pdf_url Column to Submissions Table</h2>";
echo "<hr>";

// Check if column already exists
$check_query = "SHOW COLUMNS FROM submissions LIKE 'annotated_pdf_url'";
$result = $conn->query($check_query);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Column 'annotated_pdf_url' already exists.</p>";
} else {
    // Add the column
    $alter_query = "ALTER TABLE submissions ADD COLUMN annotated_pdf_url VARCHAR(255) NULL AFTER pdf_url";
    
    if ($conn->query($alter_query)) {
        echo "<p style='color: green;'>✓ Successfully added 'annotated_pdf_url' column to submissions table.</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding column: " . $conn->error . "</p>";
        exit;
    }
}

echo "<hr>";
echo "<h3>Verification:</h3>";

// Display table structure
$describe_query = "DESCRIBE submissions";
$columns = $conn->query($describe_query);

if ($columns->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>";
    
    while ($row = $columns->fetch_assoc()) {
        $highlight = ($row['Field'] === 'annotated_pdf_url') ? "style='background-color: #d4edda;'" : "";
        echo "<tr $highlight>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<hr>";
echo "<p style='color: green; font-weight: bold;'>✓ Migration completed successfully!</p>";
echo "<p><strong>Important:</strong> Please delete this file (add_annotated_pdf_column_script.php) after verifying the migration.</p>";
echo "<p><a href='student/dashboard.php' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a></p>";

$conn->close();
?>
