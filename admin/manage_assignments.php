<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Handle assignment operations
if(isset($_POST['create_assignment'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $moderator_id = (int)$_POST['moderator_id'];
        $evaluator_id = (int)$_POST['evaluator_id'];
        $subject_id = (int)$_POST['subject_id'];
        
        // Check if assignment already exists
        $check = $conn->prepare("SELECT id FROM evaluation_assignments WHERE moderator_id = ? AND evaluator_id = ? AND subject_id = ?");
        $check->bind_param("iii", $moderator_id, $evaluator_id, $subject_id);
        $check->execute();
        
        if($check->get_result()->num_rows > 0) {
            $error = "This assignment already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO evaluation_assignments (moderator_id, evaluator_id, subject_id, assigned_by, is_active) VALUES (?, ?, ?, ?, 1)");
            $admin_id = $_SESSION['user_id'];
            $stmt->bind_param("iiii", $moderator_id, $evaluator_id, $subject_id, $admin_id);
            
            if($stmt->execute()) {
                $success = "Assignment created successfully.";
            } else {
                $error = "Error creating assignment: " . $conn->error;
            }
        }
    }
}

// Handle bulk assignment
if(isset($_POST['auto_assign'])) {
    $moderator_id = (int)$_POST['bulk_moderator_id'];
    $subject_id = (int)$_POST['bulk_subject_id'];
    $max_assignments = 10; // 1 moderator : 10 evaluators
    
    // Get available evaluators (not assigned to this subject yet)
    $available = $conn->prepare("
        SELECT u.id FROM users u 
        WHERE u.role = 'evaluator' 
        AND u.is_active = 1 
        AND u.id NOT IN (
            SELECT evaluator_id FROM evaluation_assignments 
            WHERE moderator_id = ? AND subject_id = ? AND is_active = 1
        )
        LIMIT ?
    ");
    $available->bind_param("iii", $moderator_id, $subject_id, $max_assignments);
    $available->execute();
    $evaluators = $available->get_result();
    
    $assigned = 0;
    $admin_id = $_SESSION['user_id'];
    
    while($evaluator = $evaluators->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO evaluation_assignments (moderator_id, evaluator_id, subject_id, assigned_by, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("iiii", $moderator_id, $evaluator['id'], $subject_id, $admin_id);
        
        if($stmt->execute()) {
            $assigned++;
        }
    }
    
    $success = "Automatically assigned $assigned evaluators to the moderator.";
}

// Handle assignment toggle
if(isset($_POST['toggle_assignment'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    $new_status = (int)$_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE evaluation_assignments SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $assignment_id);
    
    if($stmt->execute()) {
        $success = "Assignment status updated successfully.";
    } else {
        $error = "Error updating assignment status.";
    }
}

// Get filter parameters
$moderator_filter = isset($_GET['moderator']) ? (int)$_GET['moderator'] : 0;
$subject_filter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get assignments with filters
$query = "
    SELECT 
        ea.*,
        m.name as moderator_name,
        e.name as evaluator_name,
        s.name as subject_name,
        s.code as subject_code,
        admin.name as assigned_by_name
    FROM evaluation_assignments ea
    JOIN users m ON ea.moderator_id = m.id
    JOIN users e ON ea.evaluator_id = e.id
    JOIN subjects s ON ea.subject_id = s.id
    JOIN users admin ON ea.assigned_by = admin.id
    WHERE 1=1
";

$params = [];
$types = '';

if($moderator_filter) {
    $query .= " AND ea.moderator_id = ?";
    $types .= 'i';
    $params[] = $moderator_filter;
}

if($subject_filter) {
    $query .= " AND ea.subject_id = ?";
    $types .= 'i';
    $params[] = $subject_filter;
}

if($status_filter !== '') {
    $query .= " AND ea.is_active = ?";
    $types .= 'i';
    $params[] = (int)$status_filter;
}

$query .= " ORDER BY ea.created_at DESC";

$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$assignments = $stmt->get_result();

// Get dropdowns data
$moderators = $conn->query("SELECT id, name FROM users WHERE role = 'moderator' AND is_active = 1 ORDER BY name");
$evaluators = $conn->query("SELECT id, name FROM users WHERE role = 'evaluator' AND is_active = 1 ORDER BY name");
$subjects = $conn->query("SELECT id, name, code FROM subjects ORDER BY name");

// Get assignment statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_assignments,
        SUM(is_active) as active_assignments,
        COUNT(DISTINCT moderator_id) as moderators_with_assignments,
        COUNT(DISTINCT evaluator_id) as evaluators_with_assignments
    FROM evaluation_assignments
")->fetch_assoc();

// Get moderator workload
$workload = $conn->query("
    SELECT 
        m.name as moderator_name,
        COUNT(ea.id) as total_evaluators,
        COUNT(CASE WHEN ea.is_active = 1 THEN 1 END) as active_evaluators
    FROM users m
    LEFT JOIN evaluation_assignments ea ON m.id = ea.moderator_id
    WHERE m.role = 'moderator' AND m.is_active = 1
    GROUP BY m.id, m.name
    ORDER BY active_evaluators DESC
");
?>

<style>
.assignment-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: none;
    margin-bottom: 1rem;
}

.assignment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.workload-meter {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.workload-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.workload-low { background: linear-gradient(90deg, #28a745, #20c997); }
.workload-medium { background: linear-gradient(90deg, #ffc107, #fd7e14); }
.workload-high { background: linear-gradient(90deg, #fd7e14, #dc3545); }
.workload-full { background: linear-gradient(90deg, #dc3545, #721c24); }

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.fade-in {
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4 fade-in">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">Assignment Management</h1>
                    <p class="text-muted mb-0">Manage moderator-evaluator assignments (1:10 ratio)</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#autoAssignModal">
                        🤖 Auto Assign
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                        <i class="fas fa-plus"></i> Manual Assignment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="row g-3 mb-4 fade-in">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="h4 mb-1"><?= $stats['total_assignments'] ?></div>
                <div>Total Assignments</div>
                <div class="small opacity-75"><?= $stats['active_assignments'] ?> active</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="h4 mb-1"><?= $stats['moderators_with_assignments'] ?></div>
                <div>Moderators Assigned</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="h4 mb-1"><?= $stats['evaluators_with_assignments'] ?></div>
                <div>Evaluators Assigned</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="h4 mb-1"><?= round($stats['evaluators_with_assignments'] / max($stats['moderators_with_assignments'], 1), 1) ?></div>
                <div>Avg. Ratio</div>
                <div class="small opacity-75">Evaluators per Moderator</div>
            </div>
        </div>
    </div>

    <!-- Moderator Workload -->
    <div class="assignment-card fade-in">
        <h5 class="mb-3">📊 Moderator Workload Distribution</h5>
        <div class="row g-3">
            <?php while($mod = $workload->fetch_assoc()): ?>
                <?php 
                $ratio = $mod['active_evaluators'] / 10; // 10 is max
                $widthPercent = min($ratio * 100, 100);
                
                if($ratio <= 0.5) $class = 'workload-low';
                elseif($ratio <= 0.8) $class = 'workload-medium';
                elseif($ratio < 1) $class = 'workload-high';
                else $class = 'workload-full';
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="p-3 border rounded">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-semibold"><?= htmlspecialchars($mod['moderator_name']) ?></div>
                            <div class="badge bg-primary"><?= $mod['active_evaluators'] ?>/10</div>
                        </div>
                        <div class="workload-meter">
                            <div class="workload-fill <?= $class ?>" style="width: <?= $widthPercent ?>%"></div>
                        </div>
                        <div class="small text-muted mt-1">
                            <?= $mod['total_evaluators'] ?> total assignments
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="assignment-card fade-in">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Filter by Moderator</label>
                <select name="moderator" class="form-select">
                    <option value="">All Moderators</option>
                    <?php 
                    $moderators->data_seek(0);
                    while($mod = $moderators->fetch_assoc()): 
                    ?>
                        <option value="<?= $mod['id'] ?>" <?= $moderator_filter == $mod['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mod['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter by Subject</label>
                <select name="subject" class="form-select">
                    <option value="">All Subjects</option>
                    <?php while($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?= $subject['id'] ?>" <?= $subject_filter == $subject['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subject['code'] . ' - ' . $subject['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter by Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">Filter</button>
                <a href="manage_assignments.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>

    <!-- Assignments Table -->
    <div class="assignment-card fade-in">
        <?php if($assignments->num_rows == 0): ?>
            <div class="text-center py-5">
                <div class="text-muted mb-3">📋</div>
                <h5 class="text-muted">No assignments found</h5>
                <p class="text-muted">No assignments match your current filters</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Moderator</th>
                            <th>Evaluator</th>
                            <th>Subject</th>
                            <th>Assigned By</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($assignment = $assignments->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($assignment['moderator_name']) ?></div>
                                <span class="badge bg-success">Moderator</span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($assignment['evaluator_name']) ?></div>
                                <span class="badge bg-primary">Evaluator</span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($assignment['subject_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($assignment['subject_code']) ?></div>
                            </td>
                            <td>
                                <div class="small"><?= htmlspecialchars($assignment['assigned_by_name']) ?></div>
                                <span class="badge bg-secondary">Admin</span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $assignment['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $assignment['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <div><?= date('M j, Y', strtotime($assignment['created_at'])) ?></div>
                                <div class="small text-muted"><?= date('g:i A', strtotime($assignment['created_at'])) ?></div>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $assignment['is_active'] ? 0 : 1 ?>">
                                    <button type="submit" name="toggle_assignment" class="btn btn-sm btn-outline-<?= $assignment['is_active'] ? 'danger' : 'success' ?>" 
                                            title="<?= $assignment['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <?= $assignment['is_active'] ? '🚫' : '✅' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Assignment Modal -->
<div class="modal fade" id="createAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">Create Manual Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Moderator <span class="text-danger">*</span></label>
                        <select name="moderator_id" class="form-select" required>
                            <option value="">Select Moderator</option>
                            <?php 
                            $moderators->data_seek(0);
                            while($mod = $moderators->fetch_assoc()): 
                            ?>
                                <option value="<?= $mod['id'] ?>"><?= htmlspecialchars($mod['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Evaluator <span class="text-danger">*</span></label>
                        <select name="evaluator_id" class="form-select" required>
                            <option value="">Select Evaluator</option>
                            <?php while($eval = $evaluators->fetch_assoc()): ?>
                                <option value="<?= $eval['id'] ?>"><?= htmlspecialchars($eval['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php 
                            $subjects->data_seek(0);
                            while($subject = $subjects->fetch_assoc()): 
                            ?>
                                <option value="<?= $subject['id'] ?>">
                                    <?= htmlspecialchars($subject['code'] . ' - ' . $subject['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_assignment" class="btn btn-primary">Create Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Auto Assignment Modal -->
<div class="modal fade" id="autoAssignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">🤖 Auto Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Auto Assignment:</strong> This will automatically assign up to 10 available evaluators to the selected moderator for the chosen subject.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Moderator <span class="text-danger">*</span></label>
                        <select name="bulk_moderator_id" class="form-select" required>
                            <option value="">Select Moderator</option>
                            <?php 
                            $moderators->data_seek(0);
                            while($mod = $moderators->fetch_assoc()): 
                            ?>
                                <option value="<?= $mod['id'] ?>"><?= htmlspecialchars($mod['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select name="bulk_subject_id" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php 
                            $subjects->data_seek(0);
                            while($subject = $subjects->fetch_assoc()): 
                            ?>
                                <option value="<?= $subject['id'] ?>">
                                    <?= htmlspecialchars($subject['code'] . ' - ' . $subject['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="auto_assign" class="btn btn-success">Auto Assign Evaluators</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>