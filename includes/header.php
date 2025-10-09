<?php
if(!isset($_SESSION)) session_start();

// Determine base URL based on current directory level
$currentPath = $_SERVER['REQUEST_URI'];
$pathParts = explode('/', trim($currentPath, '/'));
$baseUrl = '';

// If we're in a subdirectory (auth, student, faculty), go up one level
if (isset($pathParts[1]) && in_array($pathParts[1], ['auth', 'student', 'faculty'])) {
    $baseUrl = '../';
} elseif (strpos($currentPath, '/student-app/') !== false) {
    // If we're directly in the app root
    $baseUrl = './';
} else {
    $baseUrl = '/student-app/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Photo App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/style.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<header class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background: var(--brand-primary-gradient);">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= $baseUrl ?>index.php">Student Photo App</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl ?>index.php">Home</a></li>
                <?php if(isset($_SESSION['role'])): ?>
                    <?php if($_SESSION['role'] == 'student'): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= $baseUrl ?>student/dashboard.php">Dashboard</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= $baseUrl ?>faculty/dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $baseUrl ?>auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $baseUrl ?>auth/login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $baseUrl ?>auth/signup.php">Signup</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</header>
<main class="flex-grow-1 py-4">
    <div class="container">
