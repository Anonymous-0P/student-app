<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Check if tables exist
$tables_check = $conn->query("SHOW TABLES LIKE 'subject_bundles'");
$tables_exist = ($tables_check && $tables_check->num_rows > 0);

// If tables don't exist, run migration
if(!$tables_exist && isset($_POST['run_migration'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $migration_file = '../db/add_subject_bundles.sql';
        
        if(!file_exists($migration_file)) {
            $error = "Migration file not found: $migration_file";
        } else {
            $sql = file_get_contents($migration_file);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            $conn->begin_transaction();
            try {
                foreach($statements as $statement) {
                    if(empty($statement) || strpos($statement, '--') === 0) continue;
                    if(!$conn->query($statement)) {
                        throw new Exception("Error: " . $conn->error);
                    }
                }
                $conn->commit();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Migration failed: " . $e->getMessage();
            }
        }
    }
    // Recheck after migration attempt
    $tables_check = $conn->query("SHOW TABLES LIKE 'subject_bundles'");
    $tables_exist = ($tables_check && $tables_check->num_rows > 0);
}

// Handle bundle creation
if(isset($_POST['create_bundle']) && $tables_exist) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $bundle_name = trim($_POST['bundle_name']);
        $description = trim($_POST['description']) ?: null;
        $bundle_price = floatval($_POST['bundle_price']);
        $discount_percentage = floatval($_POST['discount_percentage']) ?: 0;
        $duration_days = intval($_POST['duration_days']) ?: 365;
        $subject_ids = $_POST['subject_ids'] ?? [];
        
        if(empty($bundle_name)) {
            $error = "Bundle name is required.";
        } elseif($bundle_price <= 0) {
            $error = "Bundle price must be greater than 0.";
        } elseif(empty($subject_ids) || count($subject_ids) < 2) {
            $error = "Please select at least 2 subjects for the bundle.";
        } else {
            // Insert bundle
            $stmt = $conn->prepare("INSERT INTO subject_bundles (bundle_name, description, bundle_price, discount_percentage, duration_days, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssddi", $bundle_name, $description, $bundle_price, $discount_percentage, $duration_days);
            
            if($stmt->execute()) {
                $bundle_id = $conn->insert_id;
                
                // Insert bundle subjects
                $subject_stmt = $conn->prepare("INSERT INTO bundle_subjects (bundle_id, subject_id) VALUES (?, ?)");
                foreach($subject_ids as $subject_id) {
                    $subject_stmt->bind_param("ii", $bundle_id, $subject_id);
                    $subject_stmt->execute();
                }
                
                $success = "Subject bundle created successfully.";
            } else {
                $error = "Error creating bundle: " . $conn->error;
            }
        }
    }
}

// Handle bundle update
if(isset($_POST['update_bundle']) && $tables_exist) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $bundle_id = (int)$_POST['bundle_id'];
        $bundle_name = trim($_POST['bundle_name']);
        $description = trim($_POST['description']) ?: null;
        $bundle_price = floatval($_POST['bundle_price']);
        $discount_percentage = floatval($_POST['discount_percentage']) ?: 0;
        $duration_days = intval($_POST['duration_days']) ?: 365;
        $subject_ids = $_POST['subject_ids'] ?? [];
        
        if(empty($subject_ids) || count($subject_ids) < 2) {
            $error = "Please select at least 2 subjects for the bundle.";
        } else {
            $stmt = $conn->prepare("UPDATE subject_bundles SET bundle_name=?, description=?, bundle_price=?, discount_percentage=?, duration_days=? WHERE id=?");
            $stmt->bind_param("ssddii", $bundle_name, $description, $bundle_price, $discount_percentage, $duration_days, $bundle_id);
            
            if($stmt->execute()) {
                // Delete old bundle subjects
                $conn->query("DELETE FROM bundle_subjects WHERE bundle_id = $bundle_id");
                
                // Insert new bundle subjects
                $subject_stmt = $conn->prepare("INSERT INTO bundle_subjects (bundle_id, subject_id) VALUES (?, ?)");
                foreach($subject_ids as $subject_id) {
                    $subject_stmt->bind_param("ii", $bundle_id, $subject_id);
                    $subject_stmt->execute();
                }
                
                $success = "Bundle updated successfully.";
            } else {
                $error = "Error updating bundle: " . $conn->error;
            }
        }
    }
}

// Handle bundle deletion
if(isset($_POST['delete_bundle']) && $tables_exist) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $bundle_id = (int)$_POST['bundle_id'];
        
        // Check if bundle has purchases
        $purchase_check = $conn->query("SELECT COUNT(*) as count FROM student_bundle_purchases WHERE bundle_id = $bundle_id");
        $purchases = $purchase_check->fetch_assoc();
        
        if($purchases['count'] > 0) {
            $error = "Cannot delete bundle: " . $purchases['count'] . " student(s) have purchased this bundle. Deactivate instead.";
        } else {
            $stmt = $conn->prepare("DELETE FROM subject_bundles WHERE id = ?");
            $stmt->bind_param("i", $bundle_id);
            
            if($stmt->execute()) {
                $success = "Bundle deleted successfully.";
            } else {
                $error = "Error deleting bundle.";
            }
        }
    }
}

// Handle status toggle
if(isset($_POST['toggle_bundle_status']) && $tables_exist) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $bundle_id = (int)$_POST['bundle_id'];
        $new_status = (int)$_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE subject_bundles SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $bundle_id);
        
        if($stmt->execute()) {
            $success = $new_status ? "Bundle activated successfully." : "Bundle deactivated successfully.";
        } else {
            $error = "Error updating bundle status.";
        }
    }
}

// Get all active subjects for dropdown
$subjects_query = $conn->query("SELECT id, code, name, price FROM subjects WHERE is_active = 1 ORDER BY code ASC");
$all_subjects = [];
if($subjects_query) {
    while($row = $subjects_query->fetch_assoc()) {
        $all_subjects[] = $row;
    }
}

// Handle AJAX request for bundle data (BEFORE header include)
if(isset($_GET['action']) && $_GET['action'] == 'get_bundle' && isset($_GET['id']) && $tables_exist) {
    $bundle_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM subject_bundles WHERE id = ?");
    $stmt->bind_param("i", $bundle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $bundle = $result->fetch_assoc();
        
        // Get subject IDs
        $subjects_query = $conn->query("SELECT subject_id FROM bundle_subjects WHERE bundle_id = $bundle_id");
        $subject_ids = [];
        while($row = $subjects_query->fetch_assoc()) {
            $subject_ids[] = $row['subject_id'];
        }
        $bundle['subject_ids'] = $subject_ids;
        
        header('Content-Type: application/json');
        echo json_encode($bundle);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Bundle not found']);
    }
    exit;
}

// Get all bundles with subject details
$bundles = null;
if($tables_exist) {
    $bundles = $conn->query("SELECT sb.*, 
    COUNT(DISTINCT bs.subject_id) as subject_count,
    COUNT(DISTINCT sbp.id) as purchase_count,
    GROUP_CONCAT(s.code SEPARATOR ', ') as subject_codes
    FROM subject_bundles sb
    LEFT JOIN bundle_subjects bs ON sb.id = bs.bundle_id
    LEFT JOIN subjects s ON bs.subject_id = s.id
    LEFT JOIN student_bundle_purchases sbp ON sb.id = sbp.bundle_id
    GROUP BY sb.id
    ORDER BY sb.created_at DESC");
}

include('../includes/header.php');
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<style>
.badge { color: white !important; }
.subject-checkbox-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
}
.subject-checkbox-item {
    padding: 0.5rem;
    border-bottom: 1px solid #f0f0f0;
}
.subject-checkbox-item:last-child {
    border-bottom: none;
}
</style>

<div class="container-fluid" style="padding-left: 50px; padding-right: 50px;">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
                <div>
                    <h1 class="h3 mb-1"><i class="fas fa-layer-group text-primary"></i> Subject Bundle Management</h1>
                    <p class="text-muted mb-0">Create combo packages with discounted pricing</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="subjects.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Subjects
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bundleModal" onclick="resetForm()">
                        <i class="fas fa-plus"></i> Create Bundle
                    </button>
                </div>
            </div>

            <!-- Migration Notice -->
            <?php if(!$tables_exist): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Database Tables Not Found</h5>
                    <p class="mb-3">The bundle feature requires database tables. Click below to create them automatically.</p>
                    <form method="post">
                        <?php csrf_input(); ?>
                        <button type="submit" name="run_migration" class="btn btn-primary">
                            <i class="fas fa-database"></i> Create Database Tables
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Alerts -->
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Bundles Table -->
            <?php if($tables_exist): ?>
            <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Bundle List
                            <small class="text-muted">(<?= $bundles ? $bundles->num_rows : 0 ?> bundles)</small>
                        </h5>
                    </div>

                    <?php if($bundles && $bundles->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th>Bundle Name</th>
                                        <th>Subjects Included</th>
                                        <th>Bundle Price</th>
                                        <th>Discount</th>
                                        <th>Duration</th>
                                        <th>Purchases</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($bundle = $bundles->fetch_assoc()): ?>
                                        <?php
                                        // Calculate total individual price
                                        $bundle_subjects_query = $conn->query("
                                            SELECT s.price 
                                            FROM bundle_subjects bs 
                                            JOIN subjects s ON bs.subject_id = s.id 
                                            WHERE bs.bundle_id = " . $bundle['id']);
                                        $total_individual_price = 0;
                                        while($bs = $bundle_subjects_query->fetch_assoc()) {
                                            $total_individual_price += $bs['price'];
                                        }
                                        $savings = $total_individual_price - $bundle['bundle_price'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($bundle['bundle_name']) ?></div>
                                                <?php if($bundle['description']): ?>
                                                    <small class="text-muted"><?= htmlspecialchars($bundle['description']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?= $bundle['subject_count'] ?> Subjects</span>
                                                <?php if($bundle['subject_codes']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($bundle['subject_codes']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="fw-semibold text-success">₹<?= number_format($bundle['bundle_price'], 2) ?></div>
                                                <?php if($total_individual_price > 0): ?>
                                                    <small class="text-muted"><s>₹<?= number_format($total_individual_price, 2) ?></s></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if($savings > 0): ?>
                                                    <span class="badge bg-danger"><?= $bundle['discount_percentage'] ?>% OFF</span>
                                                    <br><small class="text-success">Save ₹<?= number_format($savings, 2) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?= $bundle['duration_days'] ?> days</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?= $bundle['purchase_count'] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if($bundle['is_active']): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editBundle(<?= $bundle['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Toggle bundle status?')">
                                                        <?php csrf_input(); ?>
                                                        <input type="hidden" name="bundle_id" value="<?= $bundle['id'] ?>">
                                                        <input type="hidden" name="new_status" value="<?= $bundle['is_active'] ? 0 : 1 ?>">
                                                        <button type="submit" name="toggle_bundle_status" class="btn btn-sm btn-outline-warning">
                                                            <i class="fas fa-<?= $bundle['is_active'] ? 'pause' : 'play' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this bundle?')">
                                                        <?php csrf_input(); ?>
                                                        <input type="hidden" name="bundle_id" value="<?= $bundle['id'] ?>">
                                                        <button type="submit" name="delete_bundle" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No bundles created yet</h5>
                            <p class="text-muted">Create your first subject bundle to offer combo pricing</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bundleModal" onclick="resetForm()">
                                <i class="fas fa-plus"></i> Create Bundle
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bundle Modal -->
<div class="modal fade" id="bundleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bundleModalLabel">Create Subject Bundle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="bundleForm">
                <div class="modal-body">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="bundle_id" id="bundle_id">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="bundle_name" class="form-label">Bundle Name *</label>
                            <input type="text" class="form-control" name="bundle_name" id="bundle_name" required
                                   placeholder="e.g., Science Combo, Complete 12th Package">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="2"
                                      placeholder="Brief description of what's included in this bundle"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="bundle_price" class="form-label">Bundle Price (₹) *</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control" name="bundle_price" 
                                       id="bundle_price" required placeholder="999.00" oninput="calculateBundleSummary()">
                                <button type="button" class="btn btn-outline-secondary" onclick="suggestPrice()" 
                                        title="Auto-suggest 20% discount">
                                    <i class="fas fa-magic"></i>
                                </button>
                            </div>
                            <small class="text-muted">Or click <i class="fas fa-magic"></i> for suggested price</small>
                        </div>
                        <div class="col-md-4">
                            <label for="discount_percentage" class="form-label">Discount % *</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" 
                                   name="discount_percentage" id="discount_percentage" value="10" required>
                        </div>
                        <div class="col-md-4">
                            <label for="duration_days" class="form-label">Duration (Days) *</label>
                            <input type="number" min="1" class="form-control" name="duration_days" 
                                   id="duration_days" value="365" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Select Subjects (minimum 2) *</label>
                            <div class="subject-checkbox-list" id="subjectCheckboxList">
                                <?php foreach($all_subjects as $subject): ?>
                                    <div class="subject-checkbox-item form-check">
                                        <input class="form-check-input subject-checkbox" type="checkbox" name="subject_ids[]" 
                                               value="<?= $subject['id'] ?>" id="subject_<?= $subject['id'] ?>"
                                               data-price="<?= $subject['price'] ?>" onchange="calculateBundleSummary()">
                                        <label class="form-check-label" for="subject_<?= $subject['id'] ?>">
                                            <strong><?= htmlspecialchars($subject['code']) ?></strong> - 
                                            <?= htmlspecialchars($subject['name']) ?>
                                            <span class="text-success">(₹<?= number_format($subject['price'], 2) ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Select at least 2 subjects to create a bundle</small>
                        </div>
                        <div class="col-12" id="pricingSummary" style="display: none;">
                            <div class="alert alert-info mb-0">
                                <h6 class="mb-2"><i class="fas fa-calculator"></i> Pricing Summary</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted">Total Individual Price:</small>
                                        <div class="fw-bold" id="totalIndividualPrice">₹0.00</div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Bundle Price:</small>
                                        <div class="fw-bold text-success" id="displayBundlePrice">₹0.00</div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">You Save:</small>
                                        <div class="fw-bold text-danger" id="savingsAmount">₹0.00 (0%)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_bundle" id="submitBtn" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Bundle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('bundleForm').reset();
    document.getElementById('bundle_id').value = '';
    document.getElementById('bundleModalLabel').textContent = 'Create Subject Bundle';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Create Bundle';
    document.getElementById('submitBtn').name = 'create_bundle';
    document.querySelectorAll('input[name="subject_ids[]"]').forEach(cb => cb.checked = false);
    document.getElementById('pricingSummary').style.display = 'none';
}

function calculateBundleSummary() {
    const checkboxes = document.querySelectorAll('.subject-checkbox:checked');
    let totalIndividual = 0;
    
    checkboxes.forEach(cb => {
        totalIndividual += parseFloat(cb.getAttribute('data-price')) || 0;
    });
    
    const bundlePrice = parseFloat(document.getElementById('bundle_price').value) || 0;
    const savings = totalIndividual - bundlePrice;
    const discountPercent = totalIndividual > 0 ? ((savings / totalIndividual) * 100).toFixed(2) : 0;
    
    if(checkboxes.length >= 2) {
        document.getElementById('pricingSummary').style.display = 'block';
        document.getElementById('totalIndividualPrice').textContent = '₹' + totalIndividual.toFixed(2);
        document.getElementById('displayBundlePrice').textContent = '₹' + bundlePrice.toFixed(2);
        
        if(savings > 0) {
            document.getElementById('savingsAmount').textContent = '₹' + savings.toFixed(2) + ' (' + discountPercent + '%)';
            document.getElementById('savingsAmount').className = 'fw-bold text-danger';
        } else {
            document.getElementById('savingsAmount').textContent = '₹0.00 (0%)';
            document.getElementById('savingsAmount').className = 'fw-bold text-muted';
        }
        
        // Auto-update discount percentage field
        if(bundlePrice > 0 && totalIndividual > 0) {
            document.getElementById('discount_percentage').value = discountPercent;
        }
    } else {
        document.getElementById('pricingSummary').style.display = 'none';
    }
}

function suggestPrice() {
    const checkboxes = document.querySelectorAll('.subject-checkbox:checked');
    let totalIndividual = 0;
    
    checkboxes.forEach(cb => {
        totalIndividual += parseFloat(cb.getAttribute('data-price')) || 0;
    });
    
    if(checkboxes.length < 2) {
        alert('Please select at least 2 subjects first');
        return;
    }
    
    // Suggest 20% discount
    const suggestedPrice = (totalIndividual * 0.8).toFixed(2);
    document.getElementById('bundle_price').value = suggestedPrice;
    document.getElementById('discount_percentage').value = '20';
    calculateBundleSummary();
}

// Add event listener to bundle price input
document.addEventListener('DOMContentLoaded', function() {
    const bundlePriceInput = document.getElementById('bundle_price');
    if(bundlePriceInput) {
        bundlePriceInput.addEventListener('input', calculateBundleSummary);
    }
});

function editBundle(bundleId) {
    fetch(`?action=get_bundle&id=${bundleId}`)
        .then(response => response.json())
        .then(data => {
            if(data.error) {
                alert('Error: ' + data.error);
                return;
            }
            
            document.getElementById('bundle_id').value = data.id;
            document.getElementById('bundle_name').value = data.bundle_name || '';
            document.getElementById('description').value = data.description || '';
            document.getElementById('bundle_price').value = data.bundle_price || '';
            document.getElementById('discount_percentage').value = data.discount_percentage || '10';
            document.getElementById('duration_days').value = data.duration_days || '365';
            
            // Uncheck all first
            document.querySelectorAll('input[name="subject_ids[]"]').forEach(cb => cb.checked = false);
            
            // Check selected subjects
            if(data.subject_ids) {
                data.subject_ids.forEach(subjectId => {
                    const checkbox = document.getElementById('subject_' + subjectId);
                    if(checkbox) checkbox.checked = true;
                });
            }
            
            // Calculate summary after loading
            setTimeout(calculateBundleSummary, 100);
            
            document.getElementById('bundleModalLabel').textContent = 'Edit Subject Bundle';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update Bundle';
            document.getElementById('submitBtn').name = 'update_bundle';
            
            new bootstrap.Modal(document.getElementById('bundleModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading bundle data');
        });
}
</script>

<?php include('../includes/footer.php'); ?>
