<?php
include('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Handle AJAX request for moderator subjects (before any output)
if(isset($_GET['action']) && $_GET['action'] == 'get_moderator_subjects' && isset($_GET['id'])) {
    $moderator_id = (int)$_GET['id'];
    
    // Get moderator info
    $mod_stmt = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'moderator'");
    if($mod_stmt) {
        $mod_stmt->bind_param("i", $moderator_id);
        $mod_stmt->execute();
        $mod_result = $mod_stmt->get_result();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    if($mod_result->num_rows > 0) {
        $moderator = $mod_result->fetch_assoc();
        
        // Get assigned subjects
        $subjects_stmt = $conn->prepare("
            SELECT s.id, s.code, s.name, s.department 
            FROM subjects s 
            JOIN moderator_subjects ms ON s.id = ms.subject_id 
            WHERE ms.moderator_id = ? 
            ORDER BY s.department, s.name
        ");
        
        $subjects = [];
        if($subjects_stmt) {
            $subjects_stmt->bind_param("i", $moderator_id);
            $subjects_stmt->execute();
            $subjects_result = $subjects_stmt->get_result();
            
            while($subject = $subjects_result->fetch_assoc()) {
                $subjects[] = $subject;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'moderator_name' => $moderator['name'],
            'subjects' => $subjects
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Moderator not found']);
    }
    exit;
}

// Handle AJAX request for moderator data
if(isset($_GET['action']) && $_GET['action'] == 'get_moderator' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'moderator'");
    if($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Get assigned subjects for this moderator
        $subjects_stmt = $conn->prepare("SELECT subject_id FROM moderator_subjects WHERE moderator_id = ?");
        $assigned_subjects = [];
        
        if($subjects_stmt) {
            $subjects_stmt->bind_param("i", $user_id);
            $subjects_stmt->execute();
            $subjects_result = $subjects_stmt->get_result();
            
            while($subject = $subjects_result->fetch_assoc()) {
                $assigned_subjects[] = $subject['subject_id'];
            }
        }
        $user['assigned_subjects'] = $assigned_subjects;
        
        header('Content-Type: application/json');
        echo json_encode($user);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Moderator not found']);
    }
    exit;
}

include('../includes/header.php');

// Handle moderator creation
if(isset($_POST['create_moderator'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
        
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $existing = $check_stmt->get_result();
        
        if($existing->num_rows > 0) {
            $error = "Email already exists. Please use a different email address.";
        } else {
            // Check if required columns exist and prepare appropriate query
            $columns_check = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
            $has_phone = $columns_check && $columns_check->num_rows > 0;
            
            $is_active_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
            $has_is_active = $is_active_check && $is_active_check->num_rows > 0;
            
            if($has_phone && $has_is_active) {
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone, is_active, created_at) VALUES (?, ?, ?, 'moderator', ?, 1, NOW())");
                if($stmt) {
                    $stmt->bind_param("ssss", $name, $email, $password, $phone);
                } else {
                    $error = "Database error: " . $conn->error;
                }
            } else if($has_phone) {
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone, created_at) VALUES (?, ?, ?, 'moderator', ?, NOW())");
                if($stmt) {
                    $stmt->bind_param("ssss", $name, $email, $password, $phone);
                } else {
                    $error = "Database error: " . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'moderator', NOW())");
                if($stmt) {
                    $stmt->bind_param("sss", $name, $email, $password);
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
            
            if(isset($stmt) && $stmt && $stmt->execute()) {
                $moderator_id = $conn->insert_id;
                
                // Assign subjects to moderator
                if(isset($_POST['subjects']) && is_array($_POST['subjects'])) {
                    $subject_assignments = 0;
                    foreach($_POST['subjects'] as $subject_id) {
                        if(!empty($subject_id) && is_numeric($subject_id)) {
                            $assign_stmt = $conn->prepare("INSERT INTO moderator_subjects (moderator_id, subject_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE assigned_at = NOW()");
                            if($assign_stmt) {
                                $assign_stmt->bind_param("ii", $moderator_id, $subject_id);
                                if($assign_stmt->execute()) {
                                    $subject_assignments++;
                                }
                            } else {
                                // Table doesn't exist, skip subject assignment for now
                                error_log("moderator_subjects table doesn't exist: " . $conn->error);
                            }
                        }
                    }
                    if($subject_assignments > 0) {
                        $success = "Moderator created successfully with {$subject_assignments} subject assignment(s).";
                    } else {
                        $success = "Moderator created successfully. Note: Subject assignments require database update.";
                    }
                } else {
                    $success = "Moderator created successfully.";
                }
            } else if(isset($stmt) && $stmt) {
                $error = "Error creating moderator: " . $conn->error;
            } else if(!isset($error)) {
                $error = "Database error: Could not prepare statement. " . $conn->error;
            }
        }
    }
}

// Handle moderator update
if(isset($_POST['update_moderator'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
        
        // Check if email already exists (excluding current user)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result();
        
        if($existing->num_rows > 0) {
            $error = "Email already exists. Please use a different email address.";
        } else {
            // Check if required columns exist and prepare appropriate query
            $columns_check = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
            $has_phone = $columns_check && $columns_check->num_rows > 0;
            
            if($has_phone) {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=? AND role='moderator'");
                if($stmt) {
                    $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
                } else {
                    $error = "Database error: " . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=? AND role='moderator'");
                if($stmt) {
                    $stmt->bind_param("ssi", $name, $email, $user_id);
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
            
            if(isset($stmt) && $stmt && $stmt->execute()) {
                // Update subject assignments
                // First, remove existing assignments
                $delete_stmt = $conn->prepare("DELETE FROM moderator_subjects WHERE moderator_id = ?");
                if($delete_stmt) {
                    $delete_stmt->bind_param("i", $user_id);
                    $delete_stmt->execute();
                }
                
                // Add new assignments
                if(isset($_POST['subjects']) && is_array($_POST['subjects'])) {
                    $subject_assignments = 0;
                    foreach($_POST['subjects'] as $subject_id) {
                        if(!empty($subject_id) && is_numeric($subject_id)) {
                            $assign_stmt = $conn->prepare("INSERT INTO moderator_subjects (moderator_id, subject_id) VALUES (?, ?)");
                            if($assign_stmt) {
                                $assign_stmt->bind_param("ii", $user_id, $subject_id);
                                if($assign_stmt->execute()) {
                                    $subject_assignments++;
                                }
                            } else {
                                // Table doesn't exist, skip subject assignment for now
                                error_log("moderator_subjects table doesn't exist: " . $conn->error);
                            }
                        }
                    }
                    if($subject_assignments > 0) {
                        $success = "Moderator updated successfully with {$subject_assignments} subject assignment(s).";
                    } else {
                        $success = "Moderator updated successfully. Note: Subject assignments require database update.";
                    }
                } else {
                    $success = "Moderator updated successfully.";
                }
            } else if(isset($stmt) && $stmt) {
                $error = "Error updating moderator: " . $conn->error;
            } else if(!isset($error)) {
                $error = "Database error: Could not prepare statement. " . $conn->error;
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
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'moderator'");
        if($stmt) {
            $stmt->bind_param("si", $new_password, $user_id);
            
            if($stmt->execute()) {
                $success = "Password updated successfully.";
            } else {
                $error = "Error updating password.";
            }
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

// Handle moderator deletion
if(isset($_POST['delete_moderator'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        
        // Check if moderator_subjects table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'moderator_subjects'");
        $moderator_subjects_exists = $table_check && $table_check->num_rows > 0;
        
        // Check if moderator has any dependencies
        if($moderator_subjects_exists) {
            $dep_check = $conn->prepare("SELECT 
                (SELECT COUNT(*) FROM users WHERE moderator_id = ?) as assigned_users,
                (SELECT COUNT(*) FROM moderator_subjects WHERE moderator_id = ?) as assigned_subjects");
            if($dep_check) {
                $dep_check->bind_param("ii", $user_id, $user_id);
                $dep_check->execute();
                $dependencies = $dep_check->get_result()->fetch_assoc();
            } else {
                $error = "Database error: " . $conn->error;
            }
        } else {
            $dep_check = $conn->prepare("SELECT 
                (SELECT COUNT(*) FROM users WHERE moderator_id = ?) as assigned_users,
                0 as assigned_subjects");
            if($dep_check) {
                $dep_check->bind_param("i", $user_id);
                $dep_check->execute();
                $dependencies = $dep_check->get_result()->fetch_assoc();
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
        
        if(isset($dependencies) && ($dependencies['assigned_users'] > 0 || $dependencies['assigned_subjects'] > 0)) {
            $error = "Cannot delete moderator: Has assigned users or subjects. Deactivate instead.";
        } else if(isset($dependencies)) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'moderator'");
            if($stmt) {
                $stmt->bind_param("i", $user_id);
                
                if($stmt->execute()) {
                    $success = "Moderator deleted successfully.";
                } else {
                    $error = "Error deleting moderator.";
                }
            } else {
                $error = "Database error: " . $conn->error;
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
        
        // Check if is_active column exists
        $is_active_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
        if($is_active_check && $is_active_check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'moderator'");
            if($stmt) {
                $stmt->bind_param("ii", $new_status, $user_id);
                
                if($stmt->execute()) {
                    $success = $new_status ? "Moderator activated successfully." : "Moderator deactivated successfully.";
                } else {
                    $error = "Error updating moderator status.";
                }
            } else {
                $error = "Database error: " . $conn->error;
            }
        } else {
            $error = "Status toggle feature requires database update.";
        }
    }
}

// Filter and search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query for moderators only
// Check if moderator_subjects table exists
$table_check = $conn->query("SHOW TABLES LIKE 'moderator_subjects'");
$moderator_subjects_exists = $table_check && $table_check->num_rows > 0;

if($moderator_subjects_exists) {
    $query = "SELECT u.*, 
        (SELECT COUNT(*) FROM users WHERE moderator_id = u.id AND role IN ('student', 'evaluator')) as assigned_users,
        (SELECT COUNT(*) FROM moderator_subjects ms WHERE ms.moderator_id = u.id) as assigned_subjects
        FROM users u 
        WHERE u.role = 'moderator'";
} else {
    $query = "SELECT u.*, 
        (SELECT COUNT(*) FROM users WHERE moderator_id = u.id AND role IN ('student', 'evaluator')) as assigned_users,
        0 as assigned_subjects
        FROM users u 
        WHERE u.role = 'moderator'";
}
$params = [];
$types = "";

if(!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if($status_filter !== '') {
    $query .= " AND u.is_active = ?";
    $params[] = (int)$status_filter;
    $types .= "i";
}

$query .= " ORDER BY u.name ASC";

$stmt = $conn->prepare($query);
if($stmt) {
    if(!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $moderators = $stmt->get_result();
} else {
    // If prepare fails, create an empty result using a simple query
    $moderators = $conn->query("SELECT * FROM users WHERE 1=0 LIMIT 0");
    if(!$moderators) {
        $moderators = false;
    }
    if(!isset($error)) {
        $error = "Database error: " . $conn->error;
    }
}

// Get moderator statistics
$is_active_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if($is_active_check && $is_active_check->num_rows > 0) {
    $moderatorStats = $conn->query("SELECT 
        COUNT(*) as total_moderators,
        SUM(is_active) as active_moderators,
        (SELECT COUNT(*) FROM users WHERE role IN ('student', 'evaluator') AND moderator_id IS NOT NULL) as total_assigned_users
        FROM users WHERE role = 'moderator'")->fetch_assoc();
} else {
    $moderatorStats = $conn->query("SELECT 
        COUNT(*) as total_moderators,
        COUNT(*) as active_moderators,
        (SELECT COUNT(*) FROM users WHERE role IN ('student', 'evaluator') AND moderator_id IS NOT NULL) as total_assigned_users
        FROM users WHERE role = 'moderator'")->fetch_assoc();
}
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<style>
/* Navbar Styling Override - Match evaluation_ratings.php */
.navbar-enhanced {
    background: white !important;
    border-bottom: 1px solid #e5e7eb !important;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
    padding: 1rem 0 !important;
}

.navbar-enhanced::before,
.navbar-enhanced::after {
    display: none !important;
}

.navbar-brand-enhanced {
    font-weight: 600 !important;
    color: #111827 !important;
    font-size: 1.25rem !important;
}

.navbar-brand-enhanced:hover {
    color: #111827 !important;
}

.nav-link-enhanced {
    color: #6b7280 !important;
    font-weight: 500 !important;
    transition: color 0.2s ease !important;
}

.nav-link-enhanced:hover {
    color: #007bff !important;
}

.nav-link-enhanced::before {
    display: none !important;
}

.nav-link-enhanced i {
    color: inherit !important;
}

/* Hide all navbar items except back to dashboard */
.navbar-nav .nav-item {
    display: none !important;
}

/* Only show the dashboard link */
.navbar-nav .nav-item:has(a[href*="dashboard.php"]) {
    display: block !important;
}

/* Hide hamburger menu button */
.hamburger-btn {
    display: none !important;
}

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
}

.btn-group-actions .btn {
    border-radius: 6px;
    transition: all 0.2s ease;
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

.stat-sublabel {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}
</style>

<div class="container-fluid" style="padding-left: 50px; padding-right: 50px;">
    <!-- Header -->
    <div class="row mb-4 mt-4 fade-in">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="fas fa-user-tie text-success"></i> Moderator Management
                    </h1>
                    <p class="text-muted mb-0">Manage moderator accounts, assignments, and permissions</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="fas fa-user-plus"></i> Add New Moderator
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

    <!-- Moderator Statistics -->
    <div class="row g-4 mb-4 fade-in">
        <div class="col-md-4">
            <div class="stat-box">
                <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $moderatorStats['active_moderators'] ?></div>
                    <div class="stat-label">Active Moderators</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-box">
                <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $moderatorStats['total_assigned_users'] ?></div>
                    <div class="stat-label">Assigned Users</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-box">
                <div class="stat-icon" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $moderatorStats['total_moderators'] - $moderatorStats['active_moderators'] ?></div>
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
                    <div class="col-md-6">
                        <label class="form-label">Search Moderators</label>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Name or email...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="manage_moderators.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Moderators Table -->
    <div class="row fade-in">
        <div class="col-12">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Moderators List
                        <small class="text-muted">(<?= $moderators->num_rows ?> found)</small>
                    </h5>
                </div>
                
                <?php if($moderators->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th>Moderator Info</th>
                                    <th>Assignments</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($moderator = $moderators->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($moderator['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($moderator['email']) ?></small>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge bg-primary"><?= $moderator['assigned_users'] ?> Users</span>
                                            <span class="badge bg-secondary" 
                                                  title="Click to view assigned subjects" 
                                                  style="cursor: pointer;" 
                                                  onclick="showAssignedSubjects(<?= $moderator['id'] ?>)">
                                                <?= $moderator['assigned_subjects'] ?> Subjects
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if(isset($moderator['phone']) && $moderator['phone']): ?>
                                            <div><small><?= htmlspecialchars($moderator['phone']) ?></small></div>
                                        <?php endif; ?>
                                        <small class="text-muted">Joined: <?= date('M Y', strtotime($moderator['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-<?= (isset($moderator['is_active']) && $moderator['is_active']) ? 'active' : 'inactive' ?>">
                                            <?= (isset($moderator['is_active']) && $moderator['is_active']) ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group-actions">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editModerator(<?= $moderator['id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="changePassword(<?= $moderator['id'] ?>)" title="Change Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-<?= (isset($moderator['is_active']) && $moderator['is_active']) ? 'secondary' : 'success' ?>" 
                                                    onclick="toggleStatus(<?= $moderator['id'] ?>, <?= (isset($moderator['is_active']) && $moderator['is_active']) ? '0' : '1' ?>)" 
                                                    title="<?= (isset($moderator['is_active']) && $moderator['is_active']) ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= (isset($moderator['is_active']) && $moderator['is_active']) ? 'pause' : 'play' ?>"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteModerator(<?= $moderator['id'] ?>)" title="Delete">
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
                        <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Moderators Found</h5>
                        <p class="text-muted">No moderators match your current search criteria.</p>
                        <button class="btn btn-success" onclick="showAddModal()">
                            <i class="fas fa-plus"></i> Add First Moderator
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Moderator Modal -->
<div class="modal fade" id="moderatorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Moderator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="moderatorForm" method="POST">
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
                        <div class="col-md-12">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="phone" id="phone" 
                                   placeholder="e.g., +1234567890">
                        </div>
                        <div class="col-12">
                            <label for="subjects" class="form-label">Assign Subjects</label>
                            <select name="subjects[]" id="subjects" class="form-select" multiple>
                                <option value="" disabled>Select subjects to assign to this moderator</option>
                                <?php 
                                // Get all available subjects
                                $subjects_result = $conn->query("SELECT id, name, code, department FROM subjects WHERE is_active = 1 ORDER BY department, name");
                                if($subjects_result):
                                while($subject = $subjects_result->fetch_assoc()): ?>
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
                    <button type="submit" class="btn btn-success" id="submitBtn">Add Moderator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="user_id" id="password_user_id">
                    <input type="hidden" name="update_password" value="1">
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" required>
                        <div class="form-text">Minimum 6 characters required</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assigned Subjects Modal -->
<div class="modal fade" id="subjectsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-book"></i> Assigned Subjects - <span id="moderatorNameDisplay"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="subjectsList">
                    <!-- Subjects will be loaded here via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let isEditing = false;

function showAddModal() {
    isEditing = false;
    document.getElementById('modalTitle').textContent = 'Add New Moderator';
    document.getElementById('submitBtn').textContent = 'Add Moderator';
    document.getElementById('moderatorForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordSection').style.display = 'block';
    document.getElementById('moderatorForm').action = '';
    
    // Clear subjects selection
    const subjectsSelect = document.getElementById('subjects');
    for(let option of subjectsSelect.options) {
        option.selected = false;
    }
    
    // Add hidden field for create action
    let createInput = document.createElement('input');
    createInput.type = 'hidden';
    createInput.name = 'create_moderator';
    createInput.value = '1';
    document.getElementById('moderatorForm').appendChild(createInput);
    
    new bootstrap.Modal(document.getElementById('moderatorModal')).show();
}

function editModerator(userId) {
    isEditing = true;
    document.getElementById('modalTitle').textContent = 'Edit Moderator';
    document.getElementById('submitBtn').textContent = 'Update Moderator';
    document.getElementById('password').required = false;
    document.getElementById('passwordSection').style.display = 'none';
    
    // Remove create_moderator field if exists
    const createField = document.querySelector('input[name="create_moderator"]');
    if(createField) createField.remove();
    
    // Add update field
    let updateInput = document.createElement('input');
    updateInput.type = 'hidden';
    updateInput.name = 'update_moderator';
    updateInput.value = '1';
    document.getElementById('moderatorForm').appendChild(updateInput);
    
    // Fetch moderator data
    fetch(`?action=get_moderator&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if(data.error) {
                alert('Error loading moderator data');
                return;
            }
            
            document.getElementById('user_id').value = data.id;
            document.getElementById('name').value = data.name || '';
            document.getElementById('email').value = data.email || '';
            document.getElementById('phone').value = data.phone || '';
            
            // Set assigned subjects
            const subjectsSelect = document.getElementById('subjects');
            if(data.assigned_subjects && Array.isArray(data.assigned_subjects)) {
                // Clear all selections first
                for(let option of subjectsSelect.options) {
                    option.selected = false;
                }
                // Select assigned subjects
                for(let subjectId of data.assigned_subjects) {
                    for(let option of subjectsSelect.options) {
                        if(option.value == subjectId) {
                            option.selected = true;
                            break;
                        }
                    }
                }
            }
            
            new bootstrap.Modal(document.getElementById('moderatorModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading moderator data');
        });
}

function changePassword(userId) {
    document.getElementById('password_user_id').value = userId;
    document.getElementById('new_password').value = '';
    new bootstrap.Modal(document.getElementById('passwordModal')).show();
}

function toggleStatus(userId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    if(confirm(`Are you sure you want to ${action} this moderator?`)) {
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

function deleteModerator(userId) {
    if(confirm('Are you sure you want to delete this moderator? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="delete_moderator" value="1">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
document.getElementById('moderatorForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password');
    if(password.required && password.value.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        return;
    }
});

document.getElementById('passwordModal').querySelector('form').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    if(newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        return;
    }
});

function showAssignedSubjects(moderatorId) {
    fetch(`?action=get_moderator_subjects&id=${moderatorId}`)
        .then(response => response.json())
        .then(data => {
            if(data.error) {
                alert('Error loading subject assignments');
                return;
            }
            
            let subjectsList = '';
            if(data.subjects && data.subjects.length > 0) {
                subjectsList = data.subjects.map(subject => 
                    `<div class="d-flex justify-content-between align-items-center mb-2">
                        <span><strong>${subject.code}</strong> - ${subject.name}</span>
                        <span class="badge bg-info">${subject.department || 'N/A'}</span>
                    </div>`
                ).join('');
            } else {
                subjectsList = '<p class="text-muted">No subjects assigned yet.</p>';
            }
            
            document.getElementById('subjectsList').innerHTML = subjectsList;
            document.getElementById('moderatorNameDisplay').textContent = data.moderator_name || 'Moderator';
            new bootstrap.Modal(document.getElementById('subjectsModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading subject assignments');
        });
}
</script>

<?php include('../includes/footer.php'); ?>