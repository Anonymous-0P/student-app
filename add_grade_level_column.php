<?php
require_once('config/config.php');

// Set headers for proper display
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Adding grade_level Column to Subjects Table</h2>";
echo "<p>This script will add a grade_level column and populate it based on year values and subject codes.</p>";
echo "<hr>";

// Step 1: Check if column already exists
$check_query = "SHOW COLUMNS FROM subjects LIKE 'grade_level'";
$result = $conn->query($check_query);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Column 'grade_level' already exists. Skipping creation.</p>";
} else {
    // Add the grade_level column
    $alter_query = "ALTER TABLE subjects ADD COLUMN grade_level ENUM('10th', '12th') NULL AFTER department";
    if ($conn->query($alter_query)) {
        echo "<p style='color: green;'>✓ Successfully added 'grade_level' column to subjects table.</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding column: " . $conn->error . "</p>";
        exit;
    }
}

echo "<hr>";

// Step 2: Update grade_level based on year values
echo "<h3>Updating grade_level based on year values...</h3>";

$update1 = "UPDATE subjects SET grade_level = '10th' WHERE year = 1";
$result1 = $conn->query($update1);
echo "<p>Updated " . $conn->affected_rows . " subjects with year=1 to grade_level='10th'</p>";

$update2 = "UPDATE subjects SET grade_level = '12th' WHERE year = 2";
$result2 = $conn->query($update2);
echo "<p>Updated " . $conn->affected_rows . " subjects with year=2 to grade_level='12th'</p>";

echo "<hr>";

// Step 3: Update based on subject code patterns
echo "<h3>Updating grade_level based on subject code patterns...</h3>";

$update3 = "UPDATE subjects SET grade_level = '10th' WHERE grade_level IS NULL AND (code LIKE '%10TH%' OR code LIKE '%_10_%')";
$result3 = $conn->query($update3);
echo "<p>Updated " . $conn->affected_rows . " subjects with '10TH' in code to grade_level='10th'</p>";

$update4 = "UPDATE subjects SET grade_level = '12th' WHERE grade_level IS NULL AND (code LIKE '%12TH%' OR code LIKE '%_12_%')";
$result4 = $conn->query($update4);
echo "<p>Updated " . $conn->affected_rows . " subjects with '12TH' in code to grade_level='12th'</p>";

echo "<hr>";

// Step 4: Set default for any remaining NULL values
echo "<h3>Setting default values for remaining subjects...</h3>";

$update5 = "UPDATE subjects SET grade_level = '10th' WHERE grade_level IS NULL";
$result5 = $conn->query($update5);
echo "<p>Set " . $conn->affected_rows . " remaining subjects to default grade_level='10th'</p>";

echo "<hr>";

// Step 5: Display results
echo "<h3>Final Verification - All Subjects:</h3>";

$verify_query = "SELECT code, name, department, year, grade_level FROM subjects ORDER BY grade_level, code";
$subjects = $conn->query($verify_query);

if ($subjects->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Code</th>";
    echo "<th>Name</th>";
    echo "<th>Department</th>";
    echo "<th>Year (Old)</th>";
    echo "<th>Grade Level (New)</th>";
    echo "</tr>";
    
    while ($row = $subjects->fetch_assoc()) {
        $badge_color = $row['grade_level'] === '10th' ? '#28a745' : '#007bff';
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['department']) . "</td>";
        echo "<td>" . ($row['year'] ?: 'NULL') . "</td>";
        echo "<td style='background-color: " . $badge_color . "; color: white; font-weight: bold; text-align: center;'>" 
             . htmlspecialchars($row['grade_level']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Summary statistics
    echo "<hr>";
    echo "<h3>Summary Statistics:</h3>";
    
    $stats_query = "SELECT grade_level, COUNT(*) as count FROM subjects GROUP BY grade_level";
    $stats = $conn->query($stats_query);
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f2f2f2;'><th>Grade Level</th><th>Count</th></tr>";
    while ($row = $stats->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['grade_level']) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    echo "</table>";
    
} else {
    echo "<p>No subjects found in database.</p>";
}

echo "<hr>";
echo "<p style='color: green; font-weight: bold;'>✓ Migration completed successfully!</p>";
echo "<p><a href='student/browse_exams.php' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to Browse Exams</a></p>";
echo "<p style='color: red;'><strong>Important:</strong> Please delete this file (add_grade_level_column.php) after verifying the migration.</p>";

$conn->close();
?>
