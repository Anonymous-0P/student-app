<?php
require 'config/config.php';

echo "Submissions Table Columns:\n\n";
$result = $conn->query('DESCRIBE submissions');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
