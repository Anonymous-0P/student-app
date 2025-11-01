<?php
session_start();

// Redirect to assignments.php - this is now the main evaluator dashboard
header("Location: assignments.php");
exit();
?>