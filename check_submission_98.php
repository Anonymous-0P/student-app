<?php
require_once 'config/config.php';

$result = $conn->query("SELECT * FROM submissions WHERE id = 98");
$row = $result->fetch_assoc();
echo json_encode($row, JSON_PRETTY_PRINT);
