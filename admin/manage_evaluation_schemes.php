<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

$pageTitle = "Manage Evaluation Schemes";

// Handle file upload
if (isset($_POST['upload_scheme'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $subject_id = (int)$_POST['subject_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if (empty($subject_id) || empty($title)) {
            $error = "Subject and title are required.";
        } elseif (empty($_FILES['scheme_file']['name'])) {
            $error = "Please select a file to upload.";
        } else {
            $file = $_FILES['scheme_file'];
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
            $max_size = 10 * 1024 * 1024; // 10MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = "Invalid file type. Only PDF, DOC, DOCX, JPG, and PNG files are allowed.";
            } elseif ($file['size'] > $max_size) {
                $error = "File size exceeds 10MB limit.";
            } else {
                $upload_dir = '../uploads/evaluation_schemes/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'scheme_' . $subject_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $stmt = $conn->prepare("INSERT INTO evaluation_schemes (subject_id, title, description, file_path, original_filename, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssii", $subject_id, $title, $description, $file_path, $file['name'], $file['size'], $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $success = "Evaluation scheme uploaded successfully.";
                    } else {
                        $error = "Database error: " . $stmt->error;
                        unlink($file_path);
                    }
                } else {
                    $error = "Failed to upload file.";
                }
            }
        }
    }
}

// Handle delete
if (isset($_POST['delete_scheme'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $scheme_id = (int)$_POST['scheme_id'];
        
        // Get file path before deleting
        $stmt = $conn->prepare("SELECT file_path FROM evaluation_schemes WHERE id = ?");
        $stmt->bind_param("i", $scheme_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Delete from database
            $deleteStmt = $conn->prepare("DELETE FROM evaluation_schemes WHERE id = ?");
            $deleteStmt->bind_param("i", $scheme_id);
            
            if ($deleteStmt->execute()) {
                // Delete file
                if (file_exists($row['file_path'])) {
                    unlink($row['file_path']);
                }
                $success = "Evaluation scheme deleted successfully.";
            } else {
                $error = "Error deleting scheme.";
            }
        }
    }
}

// Handle toggle active status
if (isset($_POST['toggle_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $scheme_id = (int)$_POST['scheme_id'];
        $new_status = (int)$_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE evaluation_schemes SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $scheme_id);
        
        if ($stmt->execute()) {
            $success = "Status updated successfully.";
        } else {
            $error = "Error updating status.";
        }
    }
}

// Get all subjects
$subjects = $conn->query("SELECT id, name, code FROM subjects ORDER BY name");

// Check if evaluation_schemes table exists
$tableExists = false;
$checkTable = $conn->query("SHOW TABLES LIKE 'evaluation_schemes'");
if ($checkTable && $checkTable->num_rows > 0) {
    $tableExists = true;
}

// Get all evaluation schemes with subject info
if ($tableExists) {
    $schemes = $conn->query("
        SELECT 
            es.*,
            s.name as subject_name,
            s.code as subject_code,
            u.name as uploaded_by_name
        FROM evaluation_schemes es
        JOIN subjects s ON es.subject_id = s.id
        JOIN users u ON es.uploaded_by = u.id
        ORDER BY es.created_at DESC
    ");
} else {
    $schemes = null;
    $error = "Evaluation schemes table not found. Please run the migration SQL: db/add_evaluation_schemes_table.sql";
}

require_once('../includes/header.php');
?>

<style>
.scheme-card {
    transition: all 0.3s ease;
    border-left: 4px solid #667eea;
}

.scheme-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.file-icon {
    font-size: 2rem;
    color: #667eea;
}

.badge-active {
    background-color: #28a745;
}

.badge-inactive {
    background-color: #6c757d;
}
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-file-alt me-2"></i>Evaluation Schemes</h2>
            <p class="text-muted mb-0">Upload and manage evaluation schemes for subjects</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="fas fa-upload me-2"></i>Upload Scheme
        </button>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Schemes Grid -->
    <div class="row g-3">
        <?php if ($schemes && $schemes->num_rows > 0): ?>
            <?php while ($scheme = $schemes->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card scheme-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="file-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <span class="badge <?= $scheme['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $scheme['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            
                            <h5 class="card-title mb-2"><?= htmlspecialchars($scheme['title']) ?></h5>
                            
                            <div class="mb-3">
                                <span class="badge bg-primary"><?= htmlspecialchars($scheme['subject_code']) ?></span>
                                <span class="text-muted ms-2"><?= htmlspecialchars($scheme['subject_name']) ?></span>
                            </div>
                            
                            <?php if ($scheme['description']): ?>
                                <p class="card-text text-muted small mb-3">
                                    <?= htmlspecialchars(substr($scheme['description'], 0, 100)) ?>
                                    <?= strlen($scheme['description']) > 100 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="small text-muted mb-3">
                                <div><i class="fas fa-user me-2"></i><?= htmlspecialchars($scheme['uploaded_by_name']) ?></div>
                                <div><i class="fas fa-calendar me-2"></i><?= date('M d, Y', strtotime($scheme['created_at'])) ?></div>
                                <div><i class="fas fa-file me-2"></i><?= number_format($scheme['file_size'] / 1024, 2) ?> KB</div>
                            </div>
                            
                            <div class="btn-group w-100">
                                <a href="<?= htmlspecialchars($scheme['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <!-- <a href="<?= htmlspecialchars($scheme['file_path']) ?>" download class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-download"></i> Download
                                </a> -->
                                <!-- <button class="btn btn-sm btn-outline-warning" onclick="toggleStatus(<?= $scheme['id'] ?>, <?= $scheme['is_active'] ? 0 : 1 ?>)">
                                    <i class="fas fa-<?= $scheme['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                </button> -->
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteScheme(<?= $scheme['id'] ?>, '<?= htmlspecialchars($scheme['title']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No evaluation schemes uploaded yet. Click "Upload Scheme" to add one.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Upload Evaluation Scheme</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php 
                            $subjects->data_seek(0);
                            while ($subject = $subjects->fetch_assoc()): 
                            ?>
                                <option value="<?= $subject['id'] ?>">
                                    <?= htmlspecialchars($subject['code']) ?> - <?= htmlspecialchars($subject['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g., Marking Scheme 2024" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Scheme File <span class="text-danger">*</span></label>
                        <input type="file" name="scheme_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <div class="form-text">Allowed: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_scheme" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Forms for Actions -->
<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="scheme_id" id="toggle_scheme_id">
    <input type="hidden" name="new_status" id="toggle_new_status">
    <input type="hidden" name="toggle_status" value="1">
</form>

<form id="deleteSchemeForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="scheme_id" id="delete_scheme_id">
    <input type="hidden" name="delete_scheme" value="1">
</form>

<script>
function toggleStatus(schemeId, newStatus) {
    if (confirm('Are you sure you want to ' + (newStatus ? 'activate' : 'deactivate') + ' this scheme?')) {
        document.getElementById('toggle_scheme_id').value = schemeId;
        document.getElementById('toggle_new_status').value = newStatus;
        document.getElementById('toggleStatusForm').submit();
    }
}

function deleteScheme(schemeId, title) {
    if (confirm('Are you sure you want to delete "' + title + '"? This action cannot be undone.')) {
        document.getElementById('delete_scheme_id').value = schemeId;
        document.getElementById('deleteSchemeForm').submit();
    }
}
</script>

<?php require_once('../includes/footer.php'); ?>
