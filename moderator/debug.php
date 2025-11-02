<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Information</h1>";

require_once('../config/config.php');
echo "<p>✓ Config loaded</p>";

require_once('../includes/functions.php');
echo "<p>✓ Functions loaded</p>";

echo "<h2>Session Check</h2>";
if(isset($_SESSION['user_id'])) {
    echo "<p>✓ User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>✓ Role: " . $_SESSION['role'] . "</p>";
    echo "<p>✓ Name: " . $_SESSION['name'] . "</p>";
} else {
    echo "<p style='color:red'>✗ No session found - you need to login first</p>";
    echo "<p><a href='../auth/login.php'>Go to Login Page</a></p>";
    exit;
}

if($_SESSION['role'] != 'moderator'){
    echo "<p style='color:red'>✗ You are not a moderator. Your role is: " . $_SESSION['role'] . "</p>";
    echo "<p><a href='../auth/login.php'>Login as moderator</a></p>";
    exit;
}

echo "<h2>Database Check</h2>";
$moderator_id = $_SESSION['user_id'];
$query = "SELECT COUNT(*) as count FROM users WHERE id = ? AND role = 'moderator'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if($result['count'] > 0) {
    echo "<p>✓ Moderator found in database</p>";
} else {
    echo "<p style='color:red'>✗ Moderator not found in database</p>";
}

echo "<h2>All checks passed!</h2>";
echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
?>
