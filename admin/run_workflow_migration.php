<?php
/**
 * Workflow System Migration Runner
 * Executes the workflow system database migration
 * Date: November 12, 2025
 */

require_once('../config/config.php');

// Check if user is admin (session already started in config.php)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admin privileges required.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow System Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 50px 0; }
        .migration-card { background: white; border-radius: 15px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .step { padding: 1rem; margin: 0.5rem 0; border-radius: 8px; background: #f8f9fa; }
        .step.success { background: #d4edda; border-left: 4px solid #28a745; }
        .step.error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .step.running { background: #d1ecf1; border-left: 4px solid #0dcaf0; }
        pre { background: #2d2d2d; color: #f8f8f8; padding: 1rem; border-radius: 5px; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="migration-card">
                    <h2 class="text-center mb-4">
                        <i class="fas fa-database text-primary"></i>
                        Workflow System Migration
                    </h2>
                    <p class="text-center text-muted mb-4">
                        This will update the database to support the new evaluation and moderation workflow
                    </p>

                    <div id="migration-steps">
                        <?php
                        // Migration files in order
                        $migration_files = [
                            'Main Migration' => __DIR__ . '/../db/workflow_migration_myisam.sql',
                            'Database Views' => __DIR__ . '/../db/workflow_views.sql',
                            'Triggers' => __DIR__ . '/../db/workflow_triggers.sql'
                        ];
                        
                        $total_success = 0;
                        $total_errors = 0;
                        $all_errors = [];
                        
                        // Helper function to check if column exists
                        function column_exists($conn, $table, $column) {
                            $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                            return $result && $result->num_rows > 0;
                        }
                        
                        // Helper function to check if index exists
                        function index_exists($conn, $table, $index) {
                            $result = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
                            return $result && $result->num_rows > 0;
                        }
                        
                        foreach ($migration_files as $file_name => $migration_file) {
                            echo '<div class="alert alert-info mt-3">';
                            echo '<h5><i class="fas fa-file-code"></i> Running: ' . $file_name . '</h5>';
                            echo '</div>';
                            
                            if (!file_exists($migration_file)) {
                                echo '<div class="alert alert-danger">Migration file not found: ' . basename($migration_file) . '</div>';
                                continue;
                            }
                            
                            // Read the SQL file
                            $sql_content = file_get_contents($migration_file);
                            
                            // Remove comments
                            $sql_content = preg_replace('/--.*$/m', '', $sql_content);
                            $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                            
                            // Split by semicolon, but be careful with DELIMITER statements
                            $statements = [];
                            $current_statement = '';
                            $lines = explode("\n", $sql_content);
                            $delimiter = ';';
                            
                            foreach ($lines as $line) {
                                $line = trim($line);
                                
                                if (empty($line)) continue;
                                
                                // Handle DELIMITER changes
                                if (stripos($line, 'DELIMITER') === 0) {
                                    $parts = explode(' ', $line);
                                    if (isset($parts[1])) {
                                        $delimiter = trim($parts[1]);
                                    }
                                    continue;
                                }
                                
                                $current_statement .= $line . "\n";
                                
                                // Check if statement ends
                                if (substr(rtrim($line), -strlen($delimiter)) === $delimiter) {
                                    // Remove the delimiter from the end
                                    $current_statement = substr(rtrim($current_statement), 0, -strlen($delimiter));
                                    if (!empty(trim($current_statement))) {
                                        $statements[] = trim($current_statement);
                                    }
                                    $current_statement = '';
                                }
                            }
                            
                            if (!empty(trim($current_statement))) {
                                $statements[] = trim($current_statement);
                            }
                            
                            echo '<div class="step running">';
                            echo '<strong>Processing ' . count($statements) . ' statements...</strong><br>';
                            echo '</div>';
                            
                            $success_count = 0;
                            $error_count = 0;
                            
                            // Execute each statement
                            foreach ($statements as $index => $statement) {
                                if (empty($statement)) continue;
                                
                                $step_num = $index + 1;
                                
                                // Get first few words for description
                                $words = str_word_count($statement, 1);
                                $description = implode(' ', array_slice($words, 0, 8)) . '...';
                                if (strlen($description) > 80) {
                                    $description = substr($description, 0, 77) . '...';
                                }
                                
                                echo '<div class="step running" id="step-' . $file_name . '-' . $step_num . '">';
                                echo "<strong>Step $step_num:</strong> " . htmlspecialchars($description) . "<br>";
                                
                                // Check if we should skip this statement
                                $should_skip = false;
                                $skip_reason = '';
                                
                                // Check for ALTER TABLE ADD COLUMN
                                if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+COLUMN\s+`?(\w+)`?/i', $statement, $matches)) {
                                    $table = $matches[1];
                                    $column = $matches[2];
                                    if (column_exists($conn, $table, $column)) {
                                        $should_skip = true;
                                        $skip_reason = "Column '$column' already exists in table '$table'";
                                    }
                                }
                                
                                // Check for CREATE INDEX
                                if (preg_match('/CREATE\s+INDEX\s+`?(\w+)`?\s+ON\s+`?(\w+)`?/i', $statement, $matches)) {
                                    $index = $matches[1];
                                    $table = $matches[2];
                                    if (index_exists($conn, $table, $index)) {
                                        $should_skip = true;
                                        $skip_reason = "Index '$index' already exists on table '$table'";
                                    }
                                }
                                
                                if ($should_skip) {
                                    echo '<span class="text-info"><i class="fas fa-info-circle"></i> Skipped: ' . htmlspecialchars($skip_reason) . '</span>';
                                    $success_count++;
                                    $total_success++;
                                    echo '</div>';
                                    echo '<script>document.getElementById("step-' . $file_name . '-' . $step_num . '").classList.remove("running"); document.getElementById("step-' . $file_name . '-' . $step_num . '").classList.add("success");</script>';
                                    flush();
                                    if (ob_get_level() > 0) ob_flush();
                                    continue;
                                }
                                
                                // Execute statement
                                $result = $conn->query($statement);
                                
                                if ($result) {
                                    echo '<span class="text-success"><i class="fas fa-check-circle"></i> Success</span>';
                                    $success_count++;
                                    $total_success++;
                                    echo '</div>';
                                    echo '<script>document.getElementById("step-' . $file_name . '-' . $step_num . '").classList.remove("running"); document.getElementById("step-' . $file_name . '-' . $step_num . '").classList.add("success");</script>';
                                } else {
                                    $error_msg = $conn->error;
                                    
                                    // Some errors can be ignored
                                    $ignorable_errors = ['already exists', 'Duplicate', 'Can\'t DROP', 'doesn\'t exist'];
                                    $is_ignorable = false;
                                    
                                    foreach ($ignorable_errors as $ignore) {
                                        if (stripos($error_msg, $ignore) !== false) {
                                            $is_ignorable = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($is_ignorable) {
                                        echo '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Skipped (already exists)</span>';
                                        $success_count++;
                                        $total_success++;
                                        echo '</div>';
                                        echo '<script>document.getElementById("step-' . $file_name . '-' . $step_num . '").classList.remove("running"); document.getElementById("step-' . $file_name . '-' . $step_num . '").classList.add("success");</script>';
                                    } else {
                                        echo '<span class="text-danger"><i class="fas fa-times-circle"></i> Error: ' . htmlspecialchars($error_msg) . '</span>';
                                        $error_count++;
                                        $total_errors++;
                                        $all_errors[] = [
                                            'file' => $file_name,
                                            'step' => $step_num, 
                                            'error' => $error_msg, 
                                            'statement' => $statement
                                        ];
                                        echo '</div>';
                                        echo '<script>document.getElementById("step-' . $file_name . '-' . $step_num . '").classList.remove("running"); document.getElementById("step-' . $file_name . '-' . $step_num . '").classList.add("error");</script>';
                                    }
                                }
                                
                                flush();
                                if (ob_get_level() > 0) ob_flush();
                            }
                            
                            echo '<div class="alert ' . ($error_count > 0 ? 'alert-warning' : 'alert-success') . ' mt-2">';
                            echo '<strong>' . $file_name . ':</strong> ';
                            echo $success_count . ' successful, ' . $error_count . ' errors';
                            echo '</div>';
                        }
                        
                        echo '<hr>';
                        
                        if ($total_errors === 0) {
                            echo '<div class="alert alert-success">';
                            echo '<h4><i class="fas fa-check-circle"></i> Migration Completed Successfully!</h4>';
                            echo '<p>All ' . $total_success . ' steps executed successfully.</p>';
                            echo '<p class="mb-0">The workflow system is now ready to use.</p>';
                            echo '</div>';
                            
                            // Verify installation
                            echo '<div class="step success">';
                            echo '<strong>Verification:</strong><br>';
                            
                            // Check new tables
                            $tables_to_check = ['workflow_logs', 'workflow_notifications', 'workflow_settings', 'moderation_history', 'evaluation_locks'];
                            $tables_found = 0;
                            
                            foreach ($tables_to_check as $table) {
                                $result = $conn->query("SHOW TABLES LIKE '$table'");
                                if ($result && $result->num_rows > 0) {
                                    echo "<i class='fas fa-check text-success'></i> Table '$table' created<br>";
                                    $tables_found++;
                                } else {
                                    echo "<i class='fas fa-times text-danger'></i> Table '$table' missing<br>";
                                }
                            }
                            
                            // Check workflow settings
                            $settings_result = $conn->query("SELECT COUNT(*) as count FROM workflow_settings");
                            if ($settings_result) {
                                $settings_row = $settings_result->fetch_assoc();
                                echo "<i class='fas fa-check text-success'></i> " . $settings_row['count'] . " workflow settings configured<br>";
                            }
                            
                            // Check views
                            $views_result = $conn->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_" . $conn->query("SELECT DATABASE()")->fetch_row()[0] . " IN ('v_pending_evaluations', 'v_pending_moderations', 'v_published_results')");
                            if ($views_result) {
                                echo "<i class='fas fa-check text-success'></i> " . $views_result->num_rows . "/3 views created<br>";
                            }
                            
                            // Check new columns in submissions
                            $columns_result = $conn->query("SHOW COLUMNS FROM submissions LIKE '%visible%'");
                            if ($columns_result) {
                                echo "<i class='fas fa-check text-success'></i> " . $columns_result->num_rows . " visibility columns added<br>";
                            }
                            
                            echo '</div>';
                            
                            echo '<div class="mt-4 text-center">';
                            echo '<a href="dashboard.php" class="btn btn-primary btn-lg"><i class="fas fa-home"></i> Go to Dashboard</a>';
                            echo '<a href="../moderator/dashboard.php" class="btn btn-success btn-lg ms-2"><i class="fas fa-clipboard-check"></i> Moderator Dashboard</a>';
                            echo '</div>';
                            
                        } else {
                            echo '<div class="alert alert-warning">';
                            echo '<h4><i class="fas fa-exclamation-triangle"></i> Migration Completed with Errors</h4>';
                            echo '<p>Successfully executed: ' . $total_success . ' steps</p>';
                            echo '<p>Errors encountered: ' . $total_errors . ' steps</p>';
                            echo '</div>';
                            
                            if (!empty($all_errors)) {
                                echo '<div class="step error">';
                                echo '<strong>Error Details:</strong><br>';
                                foreach ($all_errors as $error) {
                                    echo '<div class="mt-2 p-2 border-start border-danger border-3">';
                                    echo '<strong>' . htmlspecialchars($error['file']) . ' - Step ' . $error['step'] . ':</strong><br>';
                                    echo '<span class="text-danger">' . htmlspecialchars($error['error']) . '</span><br>';
                                    echo '<pre class="mt-1">' . htmlspecialchars(substr($error['statement'], 0, 300)) . '...</pre>';
                                    echo '</div>';
                                }
                                echo '</div>';
                            }
                            
                            echo '<div class="mt-4 text-center">';
                            echo '<button onclick="location.reload()" class="btn btn-warning"><i class="fas fa-redo"></i> Retry Migration</button>';
                            echo '<a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-home"></i> Go to Dashboard Anyway</a>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>
