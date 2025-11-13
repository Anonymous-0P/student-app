<?php
/**
 * Migration Script: Remove Department from Moderators
 * This script removes department associations from moderator accounts
 * Run this once to update the database
 */

require_once('../config/config.php');

// Check if user is admin (session already started in config.php)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admin privileges required.");
}

echo "<h2>Migration: Remove Department from Moderators</h2>";
echo "<p>Starting migration...</p>";

try {
    // Step 1: Set all moderator departments to NULL
    echo "<p>Step 1: Clearing department data for moderators...</p>";
    $result = $conn->query("UPDATE users SET department = NULL WHERE role = 'moderator'");
    
    if ($result) {
        $affected_rows = $conn->affected_rows;
        echo "<p style='color: green;'>✓ Successfully cleared department for {$affected_rows} moderator(s)</p>";
    } else {
        throw new Exception("Failed to clear departments: " . $conn->error);
    }
    
    // Step 2: Add index to improve moderator queries
    echo "<p>Step 2: Adding performance indexes...</p>";
    
    // Check if index exists
    $index_check = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_role_active'");
    if ($index_check->num_rows == 0) {
        $conn->query("CREATE INDEX idx_users_role_active ON users(role, is_active)");
        echo "<p style='color: green;'>✓ Added index: idx_users_role_active</p>";
    } else {
        echo "<p style='color: blue;'>ℹ Index idx_users_role_active already exists</p>";
    }
    
    // Check if moderator_id index exists
    $mod_index_check = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_moderator_id'");
    if ($mod_index_check->num_rows == 0) {
        $conn->query("CREATE INDEX idx_users_moderator_id ON users(moderator_id)");
        echo "<p style='color: green;'>✓ Added index: idx_users_moderator_id</p>";
    } else {
        echo "<p style='color: blue;'>ℹ Index idx_users_moderator_id already exists</p>";
    }
    
    // Step 3: Verification
    echo "<p>Step 3: Verifying migration...</p>";
    $verify = $conn->query("
        SELECT 
            COUNT(*) as total_moderators,
            SUM(CASE WHEN department IS NULL THEN 1 ELSE 0 END) as moderators_without_dept,
            SUM(CASE WHEN department IS NOT NULL THEN 1 ELSE 0 END) as moderators_with_dept
        FROM users 
        WHERE role = 'moderator'
    ")->fetch_assoc();
    
    echo "<table border='1' cellpadding='10' style='margin: 20px 0; border-collapse: collapse;'>";
    echo "<tr><th>Metric</th><th>Count</th></tr>";
    echo "<tr><td>Total Moderators</td><td>{$verify['total_moderators']}</td></tr>";
    echo "<tr><td>Without Department</td><td>{$verify['moderators_without_dept']}</td></tr>";
    echo "<tr><td>With Department</td><td>{$verify['moderators_with_dept']}</td></tr>";
    echo "</table>";
    
    if ($verify['moderators_with_dept'] == 0) {
        echo "<p style='color: green; font-weight: bold;'>✓ Migration completed successfully!</p>";
        echo "<p>All moderators have been updated. The department field is no longer used for moderator accounts.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Warning: {$verify['moderators_with_dept']} moderator(s) still have department data.</p>";
    }
    
    echo "<hr>";
    echo "<h3>Summary of Changes:</h3>";
    echo "<ul>";
    echo "<li>Removed department associations from all moderator accounts</li>";
    echo "<li>Added performance indexes for faster queries</li>";
    echo "<li>Department field preserved in database for backward compatibility</li>";
    echo "<li>Updated manage_moderators.php to not use department field</li>";
    echo "</ul>";
    
    echo "<p><a href='manage_moderators.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to Moderator Management</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>Migration failed. Please check the error and try again.</p>";
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Migration Complete</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h2 { color: #333; }
        p { line-height: 1.6; }
        table { width: 100%; }
        th { background: #f0f0f0; text-align: left; }
    </style>
</head>
<body>
</body>
</html>
