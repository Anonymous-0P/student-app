<?php
/**
 * Database Migration: Add Password Reset Columns
 * Run this script once to add password reset functionality
 */

require_once('config/config.php');

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Migration - Password Reset</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'>
</head>
<body class='bg-light'>
    <div class='container mt-5'>
        <div class='row justify-content-center'>
            <div class='col-md-8'>
                <div class='card shadow'>
                    <div class='card-header bg-primary text-white'>
                        <h4 class='mb-0'><i class='fas fa-database me-2'></i>Database Migration</h4>
                    </div>
                    <div class='card-body'>";

try {
    // Check if columns already exist
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
    
    if ($check->num_rows > 0) {
        echo "<div class='alert alert-info'>
                <i class='fas fa-info-circle me-2'></i>
                <strong>Already Migrated:</strong> Password reset columns already exist in the database.
              </div>";
    } else {
        // Add reset_token column
        $sql1 = "ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL AFTER password";
        if ($conn->query($sql1)) {
            echo "<div class='alert alert-success'>
                    <i class='fas fa-check-circle me-2'></i>
                    Successfully added <code>reset_token</code> column
                  </div>";
        } else {
            throw new Exception("Error adding reset_token column: " . $conn->error);
        }
        
        // Add reset_token_expiry column
        $sql2 = "ALTER TABLE users ADD COLUMN reset_token_expiry DATETIME NULL DEFAULT NULL AFTER reset_token";
        if ($conn->query($sql2)) {
            echo "<div class='alert alert-success'>
                    <i class='fas fa-check-circle me-2'></i>
                    Successfully added <code>reset_token_expiry</code> column
                  </div>";
        } else {
            throw new Exception("Error adding reset_token_expiry column: " . $conn->error);
        }
        
        // Add index for better performance
        $sql3 = "ALTER TABLE users ADD INDEX idx_reset_token (reset_token)";
        if ($conn->query($sql3)) {
            echo "<div class='alert alert-success'>
                    <i class='fas fa-check-circle me-2'></i>
                    Successfully added index on <code>reset_token</code>
                  </div>";
        } else {
            throw new Exception("Error adding index: " . $conn->error);
        }
        
        echo "<div class='alert alert-success mt-3'>
                <i class='fas fa-check-circle me-2'></i>
                <strong>Migration Completed Successfully!</strong><br>
                Password reset functionality is now available.
              </div>";
    }
    
    // Display current table structure
    echo "<div class='mt-4'>
            <h5>Current Users Table Structure:</h5>
            <div class='table-responsive'>
                <table class='table table-bordered table-sm'>";
    
    $columns = $conn->query("SHOW COLUMNS FROM users");
    echo "<thead class='table-light'>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Default</th>
            </tr>
          </thead>
          <tbody>";
    
    while ($col = $columns->fetch_assoc()) {
        $highlight = ($col['Field'] == 'reset_token' || $col['Field'] == 'reset_token_expiry') ? 'table-success' : '';
        echo "<tr class='$highlight'>
                <td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>
                <td>" . htmlspecialchars($col['Type']) . "</td>
                <td>" . htmlspecialchars($col['Null']) . "</td>
                <td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>
              </tr>";
    }
    
    echo "</tbody></table>
          </div>
          </div>";
    
    echo "<div class='mt-4'>
            <a href='../auth/forgot_password.php' class='btn btn-primary'>
                <i class='fas fa-key me-2'></i>Test Password Reset
            </a>
            <a href='../auth/login.php' class='btn btn-secondary ms-2'>
                <i class='fas fa-sign-in-alt me-2'></i>Go to Login
            </a>
          </div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
            <i class='fas fa-exclamation-triangle me-2'></i>
            <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
          </div>";
}

echo "          </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";

$conn->close();
?>
