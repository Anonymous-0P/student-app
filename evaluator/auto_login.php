<?php
session_start();
require_once('../config/config.php');

// First, let's check available evaluators
echo "<h3>Available Users:</h3>";
$stmt = $pdo->query("SELECT id, name, email, role FROM users WHERE role = 'evaluator'");
while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<p>ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}, Role: {$user['role']}</p>";
}

// Auto-login as evaluator for testing
$evaluator_id = 28; // From the debug output, this is Evaluator1
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'evaluator'");
$stmt->execute([$evaluator_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    echo "<h3>Auto-logged in as:</h3>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Name: " . $_SESSION['name'] . "</p>";
    echo "<p>Role: " . $_SESSION['role'] . "</p>";
    
    echo "<p><a href='assignments.php'>Go to Assignments</a></p>";
} else {
    echo "<p>Could not find evaluator with ID $evaluator_id</p>";
}
?>