<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

$pageTitle = "Run Evaluation Schemes Migration";

$migrationStatus = '';
$errorDetails = '';

// Run migration if requested
if (isset($_POST['run_migration'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $migrationStatus = 'error';
        $errorDetails = 'Invalid CSRF token.';
    } else {
        // Check if table already exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'evaluation_schemes'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $migrationStatus = 'warning';
            $errorDetails = 'Table already exists. Migration skipped.';
        } else {
            // Read and execute migration SQL
            $sqlFile = '../db/add_evaluation_schemes_table.sql';
            
            if (!file_exists($sqlFile)) {
                $migrationStatus = 'error';
                $errorDetails = 'Migration file not found: ' . $sqlFile;
            } else {
                $sql = file_get_contents($sqlFile);
                
                // Remove comments and split into individual statements
                $sql = preg_replace('/--.*$/m', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                
                // Execute the SQL
                if ($conn->multi_query($sql)) {
                    // Clear all results
                    do {
                        if ($result = $conn->store_result()) {
                            $result->free();
                        }
                    } while ($conn->more_results() && $conn->next_result());
                    
                    // Verify table was created
                    $verifyTable = $conn->query("SHOW TABLES LIKE 'evaluation_schemes'");
                    
                    if ($verifyTable && $verifyTable->num_rows > 0) {
                        $migrationStatus = 'success';
                        
                        // Create upload directory
                        $uploadDir = '../uploads/evaluation_schemes/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                    } else {
                        $migrationStatus = 'error';
                        $errorDetails = 'Table creation verification failed.';
                    }
                } else {
                    $migrationStatus = 'error';
                    $errorDetails = 'SQL Error: ' . $conn->error;
                }
            }
        }
    }
}

// Check current status
$tableExists = false;
$uploadDirExists = false;

$checkTable = $conn->query("SHOW TABLES LIKE 'evaluation_schemes'");
if ($checkTable && $checkTable->num_rows > 0) {
    $tableExists = true;
}

$uploadDir = '../uploads/evaluation_schemes/';
if (is_dir($uploadDir)) {
    $uploadDirExists = true;
}

require_once('../includes/header.php');
?>

<style>
.status-card {
    border-left: 4px solid #ccc;
    transition: all 0.3s ease;
}

.status-card.success {
    border-left-color: #28a745;
    background-color: #d4edda;
}

.status-card.pending {
    border-left-color: #ffc107;
    background-color: #fff3cd;
}

.status-card.error {
    border-left-color: #dc3545;
    background-color: #f8d7da;
}

.migration-info {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
}

.code-block {
    background-color: #f4f4f4;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 1rem;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    overflow-x: auto;
}
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-database me-2"></i>Evaluation Schemes Migration</h2>
            <p class="text-muted mb-0">Set up the evaluation schemes management system</p>
        </div>
        <a href="manage_evaluation_schemes.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Schemes
        </a>
    </div>

    <!-- Migration Status Messages -->
    <?php if ($migrationStatus === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Migration completed successfully!</strong>
            <ul class="mb-0 mt-2">
                <li>Evaluation schemes table created</li>
                <li>Upload directory created</li>
                <li>Indexes and foreign keys configured</li>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($migrationStatus === 'warning'): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Migration skipped:</strong> <?= htmlspecialchars($errorDetails) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($migrationStatus === 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-times-circle me-2"></i>
            <strong>Migration failed:</strong> <?= htmlspecialchars($errorDetails) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Current Status -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card status-card <?= $tableExists ? 'success' : 'pending' ?> h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-1">
                                <i class="fas fa-table me-2"></i>Database Table
                            </h5>
                            <p class="card-text mb-0">
                                <?= $tableExists ? 'Table exists and ready' : 'Table not found - migration needed' ?>
                            </p>
                        </div>
                        <div>
                            <?php if ($tableExists): ?>
                                <i class="fas fa-check-circle fa-3x text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle fa-3x text-warning"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card status-card <?= $uploadDirExists ? 'success' : 'pending' ?> h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-1">
                                <i class="fas fa-folder me-2"></i>Upload Directory
                            </h5>
                            <p class="card-text mb-0">
                                <?= $uploadDirExists ? 'Directory exists and writable' : 'Directory will be created' ?>
                            </p>
                        </div>
                        <div>
                            <?php if ($uploadDirExists): ?>
                                <i class="fas fa-check-circle fa-3x text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle fa-3x text-warning"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Migration Information -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Migration Details</h5>
        </div>
        <div class="card-body">
            <div class="migration-info">
                <h6>This migration will:</h6>
                <ul>
                    <li>Create the <code>evaluation_schemes</code> table in your database</li>
                    <li>Set up proper indexes for performance optimization</li>
                    <li>Configure foreign key relationships with subjects and users</li>
                    <li>Create the upload directory at <code>uploads/evaluation_schemes/</code></li>
                </ul>

                <h6 class="mt-3">Table Structure:</h6>
                <div class="code-block">
CREATE TABLE evaluation_schemes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);
                </div>
            </div>
        </div>
    </div>

    <!-- Migration Action -->
    <?php if (!$tableExists): ?>
        <div class="card border-warning">
            <div class="card-body">
                <h5 class="card-title text-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>Action Required
                </h5>
                <p class="card-text mb-3">
                    The evaluation schemes table has not been created yet. Click the button below to run the migration.
                </p>
                
                <form method="POST" onsubmit="return confirm('Are you sure you want to run this migration?');">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button type="submit" name="run_migration" class="btn btn-primary btn-lg">
                        <i class="fas fa-play me-2"></i>Run Migration Now
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <strong>System Ready!</strong> The evaluation schemes system is fully set up and ready to use.
            <a href="manage_evaluation_schemes.php" class="alert-link">Go to Evaluation Schemes →</a>
        </div>
    <?php endif; ?>

    <!-- Additional Information -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h5>
        </div>
        <div class="card-body">
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            What happens if I run this migration multiple times?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The migration is safe to run multiple times. It uses <code>IF NOT EXISTS</code> clauses to prevent duplicate table creation.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            What file types can be uploaded?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The system supports PDF, DOC, DOCX, JPG, and PNG files up to 10MB in size.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            Who can access evaluation schemes?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <ul class="mb-0">
                                <li><strong>Admin:</strong> Full access - upload, view, edit, delete all schemes</li>
                                <li><strong>Moderator:</strong> Read-only access to schemes for their assigned subjects</li>
                                <li><strong>Evaluator:</strong> Read-only access to schemes for subjects they evaluate</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            Can I undo this migration?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes, you can drop the table using SQL: <code>DROP TABLE evaluation_schemes;</code>
                            However, this will delete all uploaded schemes and their metadata.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>
