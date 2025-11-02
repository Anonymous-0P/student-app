<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test...<br>";

require_once('./config/config.php');
echo "Config loaded<br>";

session_start();
echo "Session started<br>";

// Check database connection
if ($conn) {
    echo "Database connected<br>";
} else {
    echo "Database connection failed: " . mysqli_connect_error() . "<br>";
}

// Check session
if (isset($_SESSION['user_id'])) {
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Role: " . $_SESSION['role'] . "<br>";
} else {
    echo "No session found. Please login first.<br>";
}

echo "<br><a href='auth/login.php'>Go to Login</a>";
?>
