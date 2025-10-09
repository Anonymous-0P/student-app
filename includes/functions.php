<?php
// Common helper functions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkLogin($role){
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != $role){
        // Determine correct path to auth based on current location
        $currentPath = $_SERVER['REQUEST_URI'];
        if (strpos($currentPath, '/student/') !== false || strpos($currentPath, '/faculty/') !== false) {
            header("Location: ../auth/login.php");
        } else {
            header("Location: auth/login.php");
        }
        exit;
    }
}

function redirect($url){
    header("Location: $url");
    exit;
}

function sanitize($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// CSRF token helpers (simple implementation)
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input() {
    $token = csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
}
?>
