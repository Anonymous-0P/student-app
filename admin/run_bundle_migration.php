<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

$migration_file = '../db/add_subject_bundles.sql';
$migration_executed = false;
$errors = [];
$success_messages = [];

// Check if tables already exist
$tables_check = $conn->query("SHOW TABLES LIKE 'subject_bundles'");
$tables_exist = ($tables_check && $tables_check->num_rows > 0);

if(isset($_POST['run_migration'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        if(!file_exists($migration_file)) {
            $errors[] = "Migration file not found: $migration_file";
        } else {
            $sql = file_get_contents($migration_file);
            
            // Split by semicolon to execute individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            $conn->begin_transaction();
            
            try {
                foreach($statements as $statement) {
                    if(empty($statement) || strpos($statement, '--') === 0) continue;
                    
                    if(!$conn->query($statement)) {
                        throw new Exception("Error executing statement: " . $conn->error);
                    }
                }
                
                $conn->commit();
                $migration_executed = true;
                $success_messages[] = "Migration executed successfully!";
                $success_messages[] = "Created tables: subject_bundles, bundle_subjects, student_bundle_purchases";
                
                // Refresh check
                $tables_check = $conn->query("SHOW TABLES LIKE 'subject_bundles'");
                $tables_exist = ($tables_check && $tables_check->num_rows > 0);
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}

include('../includes/header.php');
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<div class="container-fluid" style="padding-left: 50px; padding-right: 50px;">
    <div class="row">
        <div class="col-12">
            <div class="mb-4 mt-4">
                <h1 class="h3 mb-1"><i class="fas fa-database text-primary"></i> Bundle Feature Migration</h1>
                <p class="text-muted mb-0">Run database migration to enable subject bundle pricing</p>
            </div>

            <?php if(!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> Migration Failed</h5>
                    <ul class="mb-0">
                        <?php foreach($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if(!empty($success_messages)): ?>
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> Migration Successful</h5>
                    <ul class="mb-0">
                        <?php foreach($success_messages as $msg): ?>
                            <li><?= htmlspecialchars($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <hr>
                    <p class="mb-0">
                        <a href="subject_bundles.php" class="btn btn-success">
                            <i class="fas fa-layer-group"></i> Go to Bundle Management
                        </a>
                        <a href="subjects.php" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-book"></i> Back to Subjects
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="dashboard-card">
                <h5 class="mb-3"><i class="fas fa-info-circle"></i> Migration Status</h5>
                
                <?php if($tables_exist): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Migration Already Complete</strong>
                        <p class="mb-0 mt-2">The bundle feature tables already exist in your database.</p>
                    </div>
                    <div class="mt-3">
                        <a href="subject_bundles.php" class="btn btn-success">
                            <i class="fas fa-layer-group"></i> Go to Bundle Management
                        </a>
                        <a href="subjects.php" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-book"></i> Back to Subjects
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Migration Required</strong>
                        <p class="mb-0 mt-2">The bundle feature requires additional database tables.</p>
                    </div>

                    <h6 class="mt-4 mb-3">What will be created:</h6>
                    <ul>
                        <li><strong>subject_bundles</strong> - Stores bundle definitions with pricing and discounts</li>
                        <li><strong>bundle_subjects</strong> - Links subjects to bundles (many-to-many)</li>
                        <li><strong>student_bundle_purchases</strong> - Tracks student bundle purchases</li>
                    </ul>

                    <form method="post" class="mt-4">
                        <?php csrf_input(); ?>
                        <button type="submit" name="run_migration" class="btn btn-primary btn-lg">
                            <i class="fas fa-play-circle"></i> Run Migration Now
                        </button>
                        <a href="subjects.php" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                    </form>

                    <div class="alert alert-info mt-4">
                        <h6><i class="fas fa-lightbulb"></i> Alternative Method</h6>
                        <p>You can also run the migration manually:</p>
                        <ol>
                            <li>Open phpMyAdmin</li>
                            <li>Select your database</li>
                            <li>Go to SQL tab</li>
                            <li>Copy and paste contents from: <code>db/add_subject_bundles.sql</code></li>
                            <li>Click "Go"</li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
