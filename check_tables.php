<?php
require_once 'config/config.php';

echo "=== SUBMISSIONS TABLE ===\n";
$result = $conn->query("SHOW TABLES LIKE 'submissions'");
if ($result && $result->num_rows > 0) {
    echo "Table exists\n\n";
    
    echo "Columns:\n";
    $cols = $conn->query("DESCRIBE submissions");
    while ($row = $cols->fetch_assoc()) {
        echo "  {$row['Field']} - {$row['Type']}\n";
    }
    
    echo "\nIndexes:\n";
    $indexes = $conn->query("SHOW INDEX FROM submissions");
    while ($row = $indexes->fetch_assoc()) {
        echo "  {$row['Key_name']} on {$row['Column_name']}\n";
    }
} else {
    echo "Table does NOT exist\n";
}

echo "\n\n=== USERS TABLE ===\n";
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result && $result->num_rows > 0) {
    echo "Table exists\n\n";
    
    echo "Columns:\n";
    $cols = $conn->query("DESCRIBE users");
    while ($row = $cols->fetch_assoc()) {
        echo "  {$row['Field']} - {$row['Type']}\n";
    }
    
    echo "\nIndexes:\n";
    $indexes = $conn->query("SHOW INDEX FROM users");
    while ($row = $indexes->fetch_assoc()) {
        echo "  {$row['Key_name']} on {$row['Column_name']}\n";
    }
} else {
    echo "Table does NOT exist\n";
}

echo "\n\n=== FOREIGN KEY SUPPORT ===\n";
$result = $conn->query("SELECT @@FOREIGN_KEY_CHECKS");
$row = $result->fetch_row();
echo "Foreign Key Checks: " . ($row[0] ? 'ENABLED' : 'DISABLED') . "\n";

$result = $conn->query("SHOW VARIABLES LIKE 'default_storage_engine'");
$row = $result->fetch_assoc();
echo "Default Storage Engine: {$row['Value']}\n";
