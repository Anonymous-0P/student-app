<?php
require_once('../config/config.php');
require_once('../includes/functions.php');
checkLogin('admin');

header('Content-Type: text/plain');
echo "Backfill start\n";

$sql = "UPDATE submissions SET 
  percentage = CASE WHEN max_marks > 0 AND marks_obtained IS NOT NULL THEN ROUND((marks_obtained / max_marks) * 100, 2) ELSE percentage END,
  grade = CASE 
    WHEN marks_obtained IS NULL OR max_marks <= 0 THEN grade
    WHEN (marks_obtained / max_marks) * 100 >= 90 THEN 'A+'
    WHEN (marks_obtained / max_marks) * 100 >= 85 THEN 'A'
    WHEN (marks_obtained / max_marks) * 100 >= 80 THEN 'A-'
    WHEN (marks_obtained / max_marks) * 100 >= 75 THEN 'B+'
    WHEN (marks_obtained / max_marks) * 100 >= 70 THEN 'B'
    WHEN (marks_obtained / max_marks) * 100 >= 65 THEN 'B-'
    WHEN (marks_obtained / max_marks) * 100 >= 60 THEN 'C+'
    WHEN (marks_obtained / max_marks) * 100 >= 55 THEN 'C'
    WHEN (marks_obtained / max_marks) * 100 >= 50 THEN 'C-'
    WHEN (marks_obtained / max_marks) * 100 >= 35 THEN 'D'
    ELSE 'F' END
WHERE (percentage IS NULL OR grade IS NULL) AND marks_obtained IS NOT NULL";

if ($conn->query($sql)) {
    echo "Backfill completed successfully. Rows affected: " . $conn->affected_rows . "\n";
} else {
    echo "Backfill failed: " . $conn->error . "\n";
}

echo "Done\n";
?>