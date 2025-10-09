<?php
// Central configuration & database connection
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Basic security headers (can also be set at server level)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "student_photo_app";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Optionally control error reporting (show minimal in production)
if (!defined('APP_DEBUG')) {
  define('APP_DEBUG', true); // set to false in production
}
if (APP_DEBUG) {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
} else {
  error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
  ini_set('display_errors', 0);
}

// Define paths
if (!defined('UPLOAD_PDF_DIR')) {
  define('UPLOAD_PDF_DIR', dirname(__DIR__) . '/uploads/pdfs/');
}
if (!defined('UPLOAD_TMP_DIR')) {
  define('UPLOAD_TMP_DIR', dirname(__DIR__) . '/uploads/temp/');
}
?>
