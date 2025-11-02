<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Handle AJAX request for evaluator data
if(isset($_GET['action']) && $_GET['action'] == 'get_evaluator' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'evaluator'");
    if (!$stmt) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Get assigned subjects
        $subjects = [];
        $subject_check = $conn->query("SHOW TABLES LIKE 'evaluator_subjects'");
        if ($subject_check && $subject_check->num_rows > 0) {
            $subject_stmt = $conn->prepare("SELECT subject_id FROM evaluator_subjects WHERE evaluator_id = ?");
            if ($subject_stmt) {
                $subject_stmt->bind_param("i", $user_id);
                $subject_stmt->execute();
                $subject_result = $subject_stmt->get_result();
                while($row = $subject_result->fetch_assoc()) {
                    $subjects[] = $row['subject_id'];
                }
            }
        }
        $user['assigned_subjects'] = $subjects;
        
        header('Content-Type: application/json');
        echo json_encode($user);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Evaluator not found']);
    }
    exit;
}

// Handle evaluator creation
if(isset($_POST['create_evaluator'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        
        // Validate required fields
        if (empty($name)) {
            $error = "Name is required.";
        } elseif (empty($email)) {
            $error = "Email is required.";
        } elseif (empty($password)) {
            $error = "Password is required.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif (empty($_POST['moderator_id']) || !is_numeric($_POST['moderator_id'])) {
            $error = "Moderator assignment is required.";
        } else {
            $password = password_hash($password, PASSWORD_BCRYPT);
            $department = (!empty($_POST['department']) && trim($_POST['department']) !== '') ? trim($_POST['department']) : null;
            $phone = (!empty($_POST['phone']) && trim($_POST['phone']) !== '') ? trim($_POST['phone']) : null;
            $moderator_id = (!empty($_POST['moderator_id']) && $_POST['moderator_id'] !== '') ? (int)$_POST['moderator_id'] : null;
        
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if (!$check_stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $existing = $check_stmt->get_result();
            
            if($existing->num_rows > 0) {
                $error = "Email already exists. Please use a different email address.";
            } else {
                // Check which columns exist in the users table
                $phone_exists = false;
                $moderator_id_exists = false;
                $is_active_exists = false;
                
                $columns_result = $conn->query("SHOW COLUMNS FROM users");
                if ($columns_result) {
                    while ($column = $columns_result->fetch_assoc()) {
                        if ($column['Field'] == 'phone') $phone_exists = true;
                        if ($column['Field'] == 'moderator_id') $moderator_id_exists = true;
                        if ($column['Field'] == 'is_active') $is_active_exists = true;
                    }
                }
                
                // Build dynamic INSERT query based on available columns
                $fields = "name, email, password, role";
                $placeholders = "?, ?, ?, 'evaluator'";
                $types = "sss";
                $values = array($name, $email, $password);
                
                // Add department if provided
                if ($department !== null) {
                    $fields .= ", department";
                    $placeholders .= ", ?";
                    $types .= "s";
                    $values[] = $department;
                }
                
                if ($phone_exists && $phone !== null) {
                    $fields .= ", phone";
                    $placeholders .= ", ?";
                    $types .= "s";
                    $values[] = $phone;
                }
                
                if ($moderator_id_exists && $moderator_id !== null && $moderator_id > 0) {
                    $fields .= ", moderator_id";
                    $placeholders .= ", ?";
                    $types .= "i";
                    $values[] = $moderator_id;
                }
                
                if ($is_active_exists) {
                    $fields .= ", is_active";
                    $placeholders .= ", 1";
                }
                
                $fields .= ", created_at";
                $placeholders .= ", NOW()";
                
                $sql = "INSERT INTO users ($fields) VALUES ($placeholders)";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    $error = "Database prepare error: " . $conn->error;
                } else {
                    $stmt->bind_param($types, ...$values);
                    
                    if($stmt->execute()) {
                        $evaluator_id = $conn->insert_id;
                        
                        // Assign subjects to evaluator
                        if(isset($_POST['subjects']) && is_array($_POST['subjects'])) {
                            // Check if evaluator_subjects table exists
                            $table_check = $conn->query("SHOW TABLES LIKE 'evaluator_subjects'");
                            if ($table_check && $table_check->num_rows > 0) {
                                $subject_assignments = 0;
                                foreach($_POST['subjects'] as $subject_id) {
                                    if(!empty($subject_id) && is_numeric($subject_id)) {
                                        $assign_stmt = $conn->prepare("INSERT INTO evaluator_subjects (evaluator_id, subject_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE assigned_at = NOW()");
                                        if ($assign_stmt) {
                                            $assign_stmt->bind_param("ii", $evaluator_id, $subject_id);
                                            if($assign_stmt->execute()) {
                                                $subject_assignments++;
                                            }
                                        }
                                    }
                                }
                                $success = "Evaluator created successfully with {$subject_assignments} subject assignment(s).";
                            } else {
                                $success = "Evaluator created successfully. Subject assignment feature requires database update.";
                            }
                        } else {
                            $success = "Evaluator created successfully.";
                        }
                    } else {
                        $error = "Error creating evaluator: " . $stmt->error . " (SQL: " . $sql . ")";
                    }
                }
            }
        }
    }
    }
}

// Handle evaluator update
if(isset($_POST['update_evaluator'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $department = !empty($_POST['department']) ? trim($_POST['department']) : null;
        $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
        $moderator_id = !empty($_POST['moderator_id']) ? (int)$_POST['moderator_id'] : null;
        
        // Check if email already exists (excluding current user)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        if (!$check_stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $check_stmt->bind_param("si", $email, $user_id);
            $check_stmt->execute();
            $existing = $check_stmt->get_result();
            
            if($existing->num_rows > 0) {
                $error = "Email already exists. Please use a different email address.";
            } else {
                // Check which columns exist for dynamic UPDATE
                $phone_exists = false;
                $moderator_id_exists = false;
                
                $columns_result = $conn->query("SHOW COLUMNS FROM users");
                if ($columns_result) {
                    while ($column = $columns_result->fetch_assoc()) {
                        if ($column['Field'] == 'phone') $phone_exists = true;
                        if ($column['Field'] == 'moderator_id') $moderator_id_exists = true;
                    }
                }
                
                // Build dynamic UPDATE query
                $sql = "UPDATE users SET name=?, email=?, department=?";
                $types = "sss";
                $values = array($name, $email, $department);
                
                if ($phone_exists && $phone !== null) {
                    $sql .= ", phone=?";
                    $types .= "s";
                    $values[] = $phone;
                }
                
                if ($moderator_id_exists && $moderator_id !== null) {
                    $sql .= ", moderator_id=?";
                    $types .= "i";
                    $values[] = $moderator_id;
                }
                
                $sql .= " WHERE id=? AND role='evaluator'";
                $types .= "i";
                $values[] = $user_id;
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param($types, ...$values);
                    
                    if($stmt->execute()) {
                        // Update subject assignments - check if table exists first
                        $table_check = $conn->query("SHOW TABLES LIKE 'evaluator_subjects'");
                        if ($table_check && $table_check->num_rows > 0) {
                            // First, remove existing assignments
                            $delete_stmt = $conn->prepare("DELETE FROM evaluator_subjects WHERE evaluator_id = ?");
                            if ($delete_stmt) {
                                $delete_stmt->bind_param("i", $user_id);
                                $delete_stmt->execute();
                            }
                            
                            // Add new assignments
                            if(isset($_POST['subjects']) && is_array($_POST['subjects'])) {
                                $subject_assignments = 0;
                                foreach($_POST['subjects'] as $subject_id) {
                                    if(!empty($subject_id) && is_numeric($subject_id)) {
                                        $assign_stmt = $conn->prepare("INSERT INTO evaluator_subjects (evaluator_id, subject_id) VALUES (?, ?)");
                                        if ($assign_stmt) {
                                            $assign_stmt->bind_param("ii", $user_id, $subject_id);
                                            if($assign_stmt->execute()) {
                                                $subject_assignments++;
                                            }
                                        }
                                    }
                                }
                                $success = "Evaluator updated successfully with {$subject_assignments} subject assignment(s).";
                            } else {
                                $success = "Evaluator updated successfully.";
                            }
                        } else {
                            $success = "Evaluator updated successfully. Subject assignment feature requires database update.";
                        }
                    } else {
                        $error = "Error updating evaluator.";
                    }
                }
            }
        }
    }
}

// Handle password update
if(isset($_POST['update_password'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'evaluator'");
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("si", $new_password, $user_id);
            
            if($stmt->execute()) {
                $success = "Password updated successfully.";
            } else {
                $error = "Error updating password.";
            }
        }
    }
}

// Handle evaluator deletion
if(isset($_POST['delete_evaluator'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        
        // Check if evaluator has any dependencies
        $dep_check = $conn->prepare("SELECT 
            (SELECT COUNT(*) FROM submissions WHERE evaluator_id = ?) as evaluations,
            (SELECT COUNT(*) FROM evaluator_assignments WHERE evaluator_id = ?) as assignments");
        
        if (!$dep_check) {
            $error = "Database error: " . $conn->error;
        } else {
            $dep_check->bind_param("ii", $user_id, $user_id);
            $dep_check->execute();
            $dependencies = $dep_check->get_result()->fetch_assoc();
            
            if($dependencies && ($dependencies['evaluations'] > 0 || $dependencies['assignments'] > 0)) {
                $error = "Cannot delete evaluator: Has evaluation history or active assignments. Deactivate instead.";
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'evaluator'");
                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("i", $user_id);
                    
                    if($stmt->execute()) {
                        $success = "Evaluator deleted successfully.";
                    } else {
                        $error = "Error deleting evaluator.";
                    }
                }
            }
        }
    }
}

// Handle status updates
if(isset($_POST['toggle_status'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        $new_status = (int)$_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'evaluator'");
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ii", $new_status, $user_id);
            
            if($stmt->execute()) {
                $success = $new_status ? "Evaluator activated successfully." : "Evaluator deactivated successfully.";
            } else {
                $error = "Error updating evaluator status.";
            }
        }
    }
}

// Filter and search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$moderator_filter = isset($_GET['moderator']) ? $_GET['moderator'] : '';

// Build query for evaluators only
$query = "SELECT u.*, m.name as moderator_name,
    (SELECT COUNT(*) FROM submissions WHERE evaluator_id = u.id) as total_evaluations,
    (SELECT COUNT(*) FROM submissions WHERE evaluator_id = u.id AND status = 'evaluated') as completed_evaluations,
    (SELECT GROUP_CONCAT(CONCAT(s.code, ' - ', s.name) SEPARATOR ', ') 
     FROM evaluator_subjects es 
     LEFT JOIN subjects s ON es.subject_id = s.id 
     WHERE es.evaluator_id = u.id) as assigned_subjects
    FROM users u 
    LEFT JOIN users m ON u.moderator_id = m.id
    WHERE u.role = 'evaluator'";
$params = [];
$types = "";

if(!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.department LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if($status_filter !== '') {
    $query .= " AND u.is_active = ?";
    $params[] = (int)$status_filter;
    $types .= "i";
}

if(!empty($department_filter)) {
    $query .= " AND u.department = ?";
    $params[] = $department_filter;
    $types .= "s";
}

if(!empty($moderator_filter)) {
    $query .= " AND u.moderator_id = ?";
    $params[] = (int)$moderator_filter;
    $types .= "i";
}

$query .= " ORDER BY u.name ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    $error = "Database error: " . $conn->error;
    $evaluators = false;
} else {
    if(!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $evaluators = $stmt->get_result();
}

// Get evaluator statistics
$stats_query = $conn->query("SELECT 
    COUNT(*) as total_evaluators,
    SUM(is_active) as active_evaluators,
    COUNT(DISTINCT department) as total_departments,
    (SELECT COUNT(*) FROM submissions WHERE evaluator_id IS NOT NULL) as total_evaluations
    FROM users WHERE role = 'evaluator'");
$evaluatorStats = $stats_query ? $stats_query->fetch_assoc() : array(
    'total_evaluators' => 0,
    'active_evaluators' => 0, 
    'total_departments' => 0,
    'total_evaluations' => 0
);

// Get departments and moderators for filters
$departments = $conn->query("SELECT DISTINCT department FROM users WHERE role = 'evaluator' AND department IS NOT NULL ORDER BY department");
$moderators = $conn->query("SELECT id, name FROM users WHERE role = 'moderator' AND is_active = 1 ORDER BY name");
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<style>
.status-active { 
    color: var(--success-color);
    font-weight: 600; 
}

.status-inactive { 
    color: var(--danger-color);
    font-weight: 600; 
}

.fade-in {
    animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Ensure all badges have white text */
.badge {
    color: white !important;
}

.btn-group-actions {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.btn-group-actions .btn {
    border-radius: 6px;
    transition: all 0.2s ease;
    padding: 0.25rem 0.4rem;
}

.btn-group-actions .btn:hover {
    transform: scale(1.05);
}

.table th {
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    transform: scale(1.01);
}

.search-filters {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.subject-badge {
    font-size: 0.7em;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
}

.subjects-container {
    max-height: 60px;
    overflow-y: auto;
}
</style>

<div class="container-fluid" style="padding-left: 50px; padding-right: 50px;">
    <!-- Header -->
    <div class="row mb-4 mt-4 fade-in">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="fas fa-user-graduate text-primary"></i> Evaluator Management
                    </h1>
                    <p class="text-muted mb-0">Manage evaluator accounts, assignments, and evaluation performance</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="fas fa-user-plus"></i> Add New Evaluator
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

    <!-- Evaluator Statistics -->
    <div class="row g-4 mb-4 fade-in">
        <div class="col-md-3">
            <div class="stat-box">
                <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $evaluatorStats['active_evaluators'] ?></div>
                    <div class="stat-label">Active Evaluators</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <div class="stat-icon" style="background-color: rgba(37, 99, 235, 0.1); color: #2563eb;">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $evaluatorStats['total_departments'] ?></div>
                    <div class="stat-label">Departments</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $evaluatorStats['total_evaluations'] ?></div>
                    <div class="stat-label">Total Evaluations</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <div class="stat-icon" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $evaluatorStats['total_evaluators'] - $evaluatorStats['active_evaluators'] ?></div>
                    <div class="stat-label">Inactive</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="row mb-4 fade-in">
        <div class="col-12">
            <div class="search-filters">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search Evaluators</label>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Name, email, or department...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <?php while($dept = $departments->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($dept['department']) ?>" 
                                        <?= $department_filter === $dept['department'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Moderator</label>
                        <select name="moderator" class="form-select">
                            <option value="">All Moderators</option>
                            <?php while($mod = $moderators->fetch_assoc()): ?>
                                <option value="<?= $mod['id'] ?>" 
                                        <?= $moderator_filter == $mod['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mod['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="manage_evaluators.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Evaluators Table -->
    <div class="row fade-in">
        <div class="col-12">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Evaluators List
                        <small class="text-muted">(<?= $evaluators->num_rows ?> found)</small>
                    </h5>
                </div>
                
                <?php if($evaluators->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Evaluator Info</th>
                                    <th>Assign Subjects</th>
                                    <th>Moderator</th>
                                    <th>Evaluations</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($evaluator = $evaluators->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($evaluator['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($evaluator['email']) ?></small>
                                    </td>
                                    <td>
                                        <?php if($evaluator['assigned_subjects']): ?>
                                            <div class="subjects-container">
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php 
                                                    $subjects = explode(', ', $evaluator['assigned_subjects']);
                                                    foreach($subjects as $subject): 
                                                        if(trim($subject)): ?>
                                                            <span class="badge bg-primary subject-badge" title="<?= htmlspecialchars(trim($subject)) ?>"><?= htmlspecialchars(trim($subject)) ?></span>
                                                    <?php endif; endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editEvaluator(<?= $evaluator['id'] ?>)">
                                                <i class="fas fa-plus"></i> Assign Subjects
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($evaluator['moderator_name']): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($evaluator['moderator_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge bg-primary"><?= $evaluator['total_evaluations'] ?> Total</span>
                                            <?php if($evaluator['completed_evaluations'] > 0): ?>
                                                <span class="badge bg-success"><?= $evaluator['completed_evaluations'] ?> Done</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if(isset($evaluator['phone']) && $evaluator['phone']): ?>
                                            <div><small><?= htmlspecialchars($evaluator['phone']) ?></small></div>
                                        <?php endif; ?>
                                        <small class="text-muted">Joined: <?= date('M Y', strtotime($evaluator['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-<?= (isset($evaluator['is_active']) && $evaluator['is_active']) ? 'active' : 'inactive' ?>">
                                            <?= (isset($evaluator['is_active']) && $evaluator['is_active']) ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group-actions">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editEvaluator(<?= $evaluator['id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="editEvaluator(<?= $evaluator['id'] ?>)" title="Assign Subjects">
                                                <i class="fas fa-book"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="changePassword(<?= $evaluator['id'] ?>)" title="Change Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-<?= (isset($evaluator['is_active']) && $evaluator['is_active']) ? 'secondary' : 'success' ?>" 
                                                    onclick="toggleStatus(<?= $evaluator['id'] ?>, <?= (isset($evaluator['is_active']) && $evaluator['is_active']) ? '0' : '1' ?>)" 
                                                    title="<?= (isset($evaluator['is_active']) && $evaluator['is_active']) ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= (isset($evaluator['is_active']) && $evaluator['is_active']) ? 'pause' : 'play' ?>"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteEvaluator(<?= $evaluator['id'] ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Evaluators Found</h5>
                        <p class="text-muted">No evaluators match your current search criteria.</p>
                        <button class="btn btn-primary" onclick="showAddModal()">
                            <i class="fas fa-plus"></i> Add First Evaluator
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Evaluator Modal -->
<div class="modal fade" id="evaluatorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Evaluator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="evaluatorForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="user_id" id="user_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="name" id="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" id="department" 
                                   placeholder="e.g., Computer Science">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="phone" id="phone" 
                                   placeholder="e.g., +1234567890">
                        </div>
                        <div class="col-md-6">
                            <label for="moderator_id" class="form-label">Assign to Moderator *</label>
                            <select name="moderator_id" id="moderator_id" class="form-select" required>
                                <option value="">Select Moderator</option>
                                <?php 
                                $moderators->data_seek(0); // Reset result pointer
                                while($mod = $moderators->fetch_assoc()): 
                                ?>
                                    <option value="<?= $mod['id'] ?>"><?= htmlspecialchars($mod['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-text">Every evaluator must be assigned to a moderator</div>
                        </div>
                        <div class="col-12">
                            <label for="evaluator_subjects" class="form-label">Assign Subjects</label>
                            <select name="subjects[]" id="evaluator_subjects" class="form-select" multiple>
                                <option value="" disabled>Select subjects to assign to this evaluator</option>
                                <?php 
                                // Get all available subjects
                                $eval_subjects_result = $conn->query("SELECT id, name, code, department FROM subjects WHERE is_active = 1 ORDER BY department, name");
                                if($eval_subjects_result):
                                while($subject = $eval_subjects_result->fetch_assoc()): ?>
                                    <option value="<?= $subject['id'] ?>">
                                        <?= htmlspecialchars($subject['code']) ?> - <?= htmlspecialchars($subject['name']) ?>
                                        <?php if($subject['department']): ?>
                                            (<?= htmlspecialchars($subject['department']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <div class="form-text">Hold Ctrl/Cmd to select multiple subjects</div>
                        </div>
                        <div class="col-12" id="passwordSection">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" id="password" required>
                            <div class="form-text">Minimum 6 characters required</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Evaluator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">
                    <i class="fas fa-key"></i> Change Evaluator Password
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="passwordChangeForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="user_id" id="password_user_id">
                    <input type="hidden" name="update_password" value="1">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You are changing the password for this evaluator. The evaluator will need to use the new password to login.
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" required minlength="6">
                        <div class="form-text">Minimum 6 characters required</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirm_password" required minlength="6">
                        <div class="form-text">Re-enter the password to confirm</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let isEditing = false;

function showAddModal() {
    isEditing = false;
    document.getElementById('modalTitle').textContent = 'Add New Evaluator';
    document.getElementById('submitBtn').textContent = 'Add Evaluator';
    document.getElementById('evaluatorForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordSection').style.display = 'block';
    document.getElementById('evaluatorForm').action = '';
    
    // Add hidden field for create action
    let createInput = document.createElement('input');
    createInput.type = 'hidden';
    createInput.name = 'create_evaluator';
    createInput.value = '1';
    document.getElementById('evaluatorForm').appendChild(createInput);
    
    new bootstrap.Modal(document.getElementById('evaluatorModal')).show();
}

function editEvaluator(userId) {
    isEditing = true;
    document.getElementById('modalTitle').textContent = 'Edit Evaluator';
    document.getElementById('submitBtn').textContent = 'Update Evaluator';
    document.getElementById('password').required = false;
    document.getElementById('passwordSection').style.display = 'none';
    
    // Remove create_evaluator field if exists
    const createField = document.querySelector('input[name="create_evaluator"]');
    if(createField) createField.remove();
    
    // Remove update_evaluator field if exists to avoid duplicates
    const updateField = document.querySelector('input[name="update_evaluator"]');
    if(updateField) updateField.remove();
    
    // Add update field
    let updateInput = document.createElement('input');
    updateInput.type = 'hidden';
    updateInput.name = 'update_evaluator';
    updateInput.value = '1';
    document.getElementById('evaluatorForm').appendChild(updateInput);
    
    // Fetch evaluator data
    fetch(`?action=get_evaluator&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if(data.error) {
                alert('Error loading evaluator data: ' + data.error);
                return;
            }
            
            document.getElementById('user_id').value = data.id;
            document.getElementById('name').value = data.name || '';
            document.getElementById('email').value = data.email || '';
            document.getElementById('department').value = data.department || '';
            document.getElementById('phone').value = data.phone || '';
            document.getElementById('moderator_id').value = data.moderator_id || '';
            
            // Set assigned subjects
            const subjectSelect = document.getElementById('evaluator_subjects');
            if(subjectSelect && data.assigned_subjects) {
                // Clear all selections first
                for(let option of subjectSelect.options) {
                    option.selected = false;
                }
                // Select assigned subjects
                data.assigned_subjects.forEach(subjectId => {
                    for(let option of subjectSelect.options) {
                        if(option.value == subjectId) {
                            option.selected = true;
                        }
                    }
                });
            }
            
            new bootstrap.Modal(document.getElementById('evaluatorModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading evaluator data');
        });
}

function changePassword(userId) {
    document.getElementById('password_user_id').value = userId;
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    new bootstrap.Modal(document.getElementById('passwordModal')).show();
}

function toggleStatus(userId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    if(confirm(`Are you sure you want to ${action} this evaluator?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="toggle_status" value="1">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="new_status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteEvaluator(userId) {
    if(confirm('Are you sure you want to delete this evaluator? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="delete_evaluator" value="1">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
document.getElementById('evaluatorForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password');
    const moderatorId = document.getElementById('moderator_id').value;
    
    if (!name) {
        e.preventDefault();
        alert('Name is required');
        document.getElementById('name').focus();
        return;
    }
    
    if (!email) {
        e.preventDefault();
        alert('Email is required');
        document.getElementById('email').focus();
        return;
    }
    
    if (password.required && (!password.value || password.value.length < 6)) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        password.focus();
        return;
    }
    
    // Only check moderator requirement for new evaluators (when password is required)
    if (password.required && !moderatorId) {
        e.preventDefault();
        alert('Moderator assignment is required');
        document.getElementById('moderator_id').focus();
        return;
    }
});

document.getElementById('passwordModal').querySelector('form').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if(newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        return;
    }
    
    if(newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match. Please try again.');
        return;
    }
});
</script>

<?php include('../includes/footer.php'); ?>