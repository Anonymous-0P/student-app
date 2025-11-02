<?php
require_once('config/config.php');

echo "<h2>Moderator Marks Access - Query Test</h2>";

// Test query with a sample evaluator
$test_query = "SELECT 
    s.id as submission_id,
    s.status,
    s.evaluation_status,
    s.marks_obtained,
    s.max_marks,
    s.evaluator_id,
    u.name as student_name
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.evaluator_id IS NOT NULL 
    AND (s.status = 'evaluated' OR s.evaluation_status = 'evaluated')
    LIMIT 10";

$result = $conn->query($test_query);

if ($result) {
    echo "<p style='color: green;'>✅ Query executed successfully!</p>";
    echo "<p>Found <strong>" . $result->num_rows . "</strong> evaluated submissions</p>";
    
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Submission ID</th>";
        echo "<th>Status</th>";
        echo "<th>Evaluation Status</th>";
        echo "<th>Evaluator ID</th>";
        echo "<th>Student</th>";
        echo "<th>Marks</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['submission_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['evaluation_status'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['evaluator_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
            echo "<td>" . $row['marks_obtained'] . "/" . $row['max_marks'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<hr>";
        echo "<h3>Test with Specific Evaluator</h3>";
        
        // Get first evaluator with evaluated submissions
        $eval_query = "SELECT DISTINCT s.evaluator_id, u.name as evaluator_name 
                       FROM submissions s 
                       JOIN users u ON s.evaluator_id = u.id 
                       WHERE s.evaluator_id IS NOT NULL 
                       AND (s.status = 'evaluated' OR s.evaluation_status = 'evaluated')
                       LIMIT 1";
        $eval_result = $conn->query($eval_query);
        
        if ($eval_result && $eval_result->num_rows > 0) {
            $evaluator = $eval_result->fetch_assoc();
            echo "<p><strong>Testing with Evaluator:</strong> " . htmlspecialchars($evaluator['evaluator_name']) . " (ID: " . $evaluator['evaluator_id'] . ")</p>";
            
            $specific_query = "SELECT COUNT(*) as count 
                              FROM submissions s
                              WHERE s.evaluator_id = " . $evaluator['evaluator_id'] . " 
                              AND (s.status = 'evaluated' OR s.evaluation_status = 'evaluated')";
            $count_result = $conn->query($specific_query);
            $count = $count_result->fetch_assoc()['count'];
            
            echo "<p style='color: green;'>✅ This evaluator has <strong>$count</strong> evaluated submissions</p>";
            echo "<p><a href='moderator/marks_access.php?evaluator_id=" . $evaluator['evaluator_id'] . "&evaluator_name=" . urlencode($evaluator['evaluator_name']) . "'>View in Moderator Dashboard</a></p>";
        }
        
    } else {
        echo "<p style='color: orange;'>⚠️ No evaluated submissions found in database</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Query failed: " . $conn->error . "</p>";
}

echo "<hr>";
echo "<h3>Column Check</h3>";

$columns_query = "SHOW COLUMNS FROM submissions WHERE Field IN ('status', 'evaluation_status')";
$columns_result = $conn->query($columns_query);

if ($columns_result) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th>Column Name</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($col = $columns_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $col['Field'] . "</strong></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
