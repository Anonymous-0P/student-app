<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Handle AJAX request for user data
if(isset($_GET['action']) && $_GET['action'] == 'get_user' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($user);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User not found']);
    }
    exit;
}

// Handle user creation
if(isset($_POST['create_user'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $roll_no = !empty($_POST['roll_no']) ? trim($_POST['roll_no']) : null;
        $course = !empty($_POST['course']) ? trim($_POST['course']) : null;
        $year = !empty($_POST['year']) ? (int)$_POST['year'] : null;
        $department = !empty($_POST['department']) ? trim($_POST['department']) : null;
        $moderator_id = !empty($_POST['moderator_id']) ? (int)$_POST['moderator_id'] : null;
        
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $existing = $check_stmt->get_result();
        
        if($existing->num_rows > 0) {
            $error = "Email already exists. Please use a different email address.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, roll_no, course, year, department, moderator_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssssssiis", $name, $email, $password, $role, $roll_no, $course, $year, $department, $moderator_id);
            
            if($stmt->execute()) {
                $success = ucfirst($role) . " created successfully.";
            } else {
                $error = "Error creating user: " . $conn->error;
            }
        }
    }
}

// Handle user updates
if(isset($_POST['update_user'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $roll_no = !empty($_POST['roll_no']) ? trim($_POST['roll_no']) : null;
        $course = !empty($_POST['course']) ? trim($_POST['course']) : null;
        $year = !empty($_POST['year']) ? (int)$_POST['year'] : null;
        $department = !empty($_POST['department']) ? trim($_POST['department']) : null;
        $moderator_id = !empty($_POST['moderator_id']) ? (int)$_POST['moderator_id'] : null;
        
        // Check if email already exists for another user
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result();
        
        if($existing->num_rows > 0) {
            $error = "Email already exists. Please use a different email address.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, roll_no=?, course=?, year=?, department=?, moderator_id=? WHERE id=?");
            $stmt->bind_param("ssssssiis", $name, $email, $role, $roll_no, $course, $year, $department, $moderator_id, $user_id);
            
            if($stmt->execute()) {
                $success = "User updated successfully.";
            } else {
                $error = "Error updating user: " . $conn->error;
            }
        }
    }
}

// Handle password updates
if(isset($_POST['update_password'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_password, $user_id);
        
        if($stmt->execute()) {
            $success = "Password updated successfully.";
        } else {
            $error = "Error updating password.";
        }
    }
}

// Handle user deletion
if(isset($_POST['delete_user'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        
        // Check if user has any dependencies (submissions, evaluations, etc.)
        $dep_check = $conn->prepare("SELECT 
            (SELECT COUNT(*) FROM submissions WHERE student_id = ?) as submissions,
            (SELECT COUNT(*) FROM submissions WHERE evaluator_id = ?) as evaluations,
            (SELECT COUNT(*) FROM users WHERE moderator_id = ?) as assigned_users");
        $dep_check->bind_param("iii", $user_id, $user_id, $user_id);
        $dep_check->execute();
        $dependencies = $dep_check->get_result()->fetch_assoc();
        
        if($dependencies['submissions'] > 0 || $dependencies['evaluations'] > 0 || $dependencies['assigned_users'] > 0) {
            $error = "Cannot delete user: User has existing submissions, evaluations, or assigned users. Deactivate instead.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if($stmt->execute()) {
                $success = "User deleted successfully.";
            } else {
                $error = "Error deleting user.";
            }
        }
    }
}

// Handle user status updates
if(isset($_POST['toggle_status'])) {
    $user_id = (int)$_POST['user_id'];
    $new_status = (int)$_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $user_id);
    
    if($stmt->execute()) {
        $success = "User status updated successfully.";
    } else {
        $error = "Error updating user status.";
    }
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build user query with filters
$query = "SELECT u.*, m.name as moderator_name FROM users u LEFT JOIN users m ON u.moderator_id = m.id WHERE 1=1";
$params = [];
$types = '';

if($role_filter) {
    $query .= " AND u.role = ?";
    $types .= 's';
    $params[] = $role_filter;
}

if($status_filter !== '') {
    $query .= " AND u.is_active = ?";
    $types .= 'i';
    $params[] = (int)$status_filter;
}

if($search) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.roll_no LIKE ?)";
    $types .= 'sss';
    $searchPattern = "%$search%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Get moderators for assignment dropdown
$moderators = $conn->query("SELECT id, name FROM users WHERE role = 'moderator' AND is_active = 1 ORDER BY name");

// Get user statistics
$userStats = $conn->query("SELECT 
    role,
    COUNT(*) as total,
    SUM(is_active) as active
    FROM users 
    GROUP BY role
    ORDER BY FIELD(role, 'admin', 'moderator', 'evaluator', 'student')")->fetch_all(MYSQLI_ASSOC);
?>

<style>
.user-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: none;
    margin-bottom: 1rem;
}

.user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.role-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-admin { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
.role-moderator { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; }
.role-evaluator { background: linear-gradient(135deg, #007bff, #0056b3); color: white; }
.role-student { background: linear-gradient(135deg, #6c757d, #545b62); color: white; }

.status-active { color: #28a745; font-weight: 600; }
.status-inactive { color: #dc3545; font-weight: 600; }

.modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

.modal-header {
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.modal-footer {
    border-top: 1px solid rgba(0,0,0,0.1);
}

.fade-in {
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* New styles for section separation */
.stats-row {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 0.5rem;
    color: white;
    margin-bottom: 2rem;
    padding: 1rem;
}

.stats-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.375rem;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.15);
}

.section-divider {
    height: 3px;
    background: linear-gradient(90deg, #dc3545, #fd7e14, #198754, #0d6efd);
    border-radius: 1.5px;
    margin: 2rem 0;
}

#staffSection {
    border-left: 4px solid #fd7e14;
    padding-left: 1rem;
    margin-bottom: 3rem;
}

#studentSection {
    border-left: 4px solid #198754;
    padding-left: 1rem;
}

.section-header {
    background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.table th {
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
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

.user-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
    border-radius: 12px;
    padding: 1.5rem;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.dropdown-menu {
    border: none;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    border-radius: 10px;
}

.dropdown-item {
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    transform: translateX(5px);
}

.alert {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.table-responsive {
    border-radius: 10px;
    overflow: hidden;
}

.table {
    margin-bottom: 0;
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    transform: scale(1.01);
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4 fade-in">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="fas fa-users text-primary"></i> User Management
                    </h1>
                    <p class="text-muted mb-0">Manage staff (moderators & evaluators) and students separately</p>
                </div>
                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-tie"></i> Add Staff
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="quickAdd('moderator')">
                                <i class="fas fa-user-tie text-success"></i> Add Moderator
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="quickAdd('evaluator')">
                                <i class="fas fa-user-graduate text-primary"></i> Add Evaluator
                            </a></li>
                        </ul>
                    </div>
                    <button class="btn btn-success" onclick="quickAdd('student')">
                        <i class="fas fa-user-plus"></i> Add Student
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

    <!-- Staff Statistics -->
    <div class="row g-3 mb-4 fade-in">
        <div class="col-12">
            <div class="user-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-user-tie text-primary"></i> Staff Management
                    </h5>
                    <button class="btn btn-outline-primary btn-sm" onclick="toggleSection('staff')">
                        <i class="fas fa-eye" id="staffToggleIcon"></i> <span id="staffToggleText">Hide</span>
                    </button>
                </div>
                <div class="row g-3" id="staffStats">
                    <?php 
                    $staffStats = $conn->query("SELECT 
                        role,
                        COUNT(*) as total,
                        SUM(is_active) as active
                        FROM users 
                        WHERE role IN ('admin', 'moderator', 'evaluator')
                        GROUP BY role
                        ORDER BY FIELD(role, 'admin', 'moderator', 'evaluator')")->fetch_all(MYSQLI_ASSOC);
                    
                    foreach($staffStats as $stat): ?>
                    <div class="col-md-4">
                        <div class="stat-card-small role-<?= $stat['role'] ?>">
                            <div class="h4 mb-1"><?= $stat['active'] ?></div>
                            <div class="small">Active <?= ucfirst($stat['role']) ?>s</div>
                            <div class="small opacity-75"><?= $stat['total'] ?> total</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Statistics -->
    <div class="row g-3 mb-4 fade-in">
        <div class="col-12">
            <div class="user-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-graduation-cap text-success"></i> Student Management
                    </h5>
                    <button class="btn btn-outline-success btn-sm" onclick="toggleSection('student')">
                        <i class="fas fa-eye" id="studentToggleIcon"></i> <span id="studentToggleText">Hide</span>
                    </button>
                </div>
                <div class="row g-3" id="studentStats">
                    <?php 
                    $studentStat = $conn->query("SELECT 
                        COUNT(*) as total,
                        SUM(is_active) as active,
                        COUNT(DISTINCT course) as courses,
                        COUNT(DISTINCT department) as departments
                        FROM users 
                        WHERE role = 'student'")->fetch_assoc();
                    ?>
                    <div class="col-md-3">
                        <div class="stat-card-small stat-card-student">
                            <div class="h4 mb-1"><?= $studentStat['active'] ?></div>
                            <div class="small">Active Students</div>
                            <div class="small opacity-75"><?= $studentStat['total'] ?> total</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-small stat-card-info">
                            <div class="h4 mb-1"><?= $studentStat['courses'] ?></div>
                            <div class="small">Courses</div>
                            <div class="small opacity-75">Different programs</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-small stat-card-warning">
                            <div class="h4 mb-1"><?= $studentStat['departments'] ?></div>
                            <div class="small">Departments</div>
                            <div class="small opacity-75">Academic units</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-small stat-card-secondary">
                            <div class="h4 mb-1"><?= $studentStat['total'] - $studentStat['active'] ?></div>
                            <div class="small">Inactive</div>
                            <div class="small opacity-75">Disabled accounts</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Section -->
    <div class="row mb-4 fade-in" id="staffSection">
        <div class="col-12">
            <div class="user-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-users-cog text-primary"></i> Staff (Moderators & Evaluators)
                    </h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="quickAdd('moderator')">
                            <i class="fas fa-plus"></i> Add Moderator
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="quickAdd('evaluator')">
                            <i class="fas fa-plus"></i> Add Evaluator
                        </button>
                    </div>
                </div>
                
                <!-- Staff Filters -->
                <form method="GET" class="row g-3 mb-3">
                    <input type="hidden" name="section" value="staff">
                    <div class="col-md-3">
                        <label class="form-label">Staff Role</label>
                        <select name="role" class="form-select">
                            <option value="">All Staff</option>
                            <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Administrator</option>
                            <option value="moderator" <?= $role_filter == 'moderator' ? 'selected' : '' ?>>Moderator</option>
                            <option value="evaluator" <?= $role_filter == 'evaluator' ? 'selected' : '' ?>>Evaluator</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search Staff</label>
                        <input type="text" name="search" class="form-control" placeholder="Name or email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="?section=staff" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>

                <!-- Staff Table -->
                <?php 
                // Get staff users
                $staff_query = "SELECT u.*, m.name as moderator_name FROM users u LEFT JOIN users m ON u.moderator_id = m.id WHERE u.role IN ('admin', 'moderator', 'evaluator')";
                $staff_params = [];
                $staff_types = '';

                if($role_filter && in_array($role_filter, ['admin', 'moderator', 'evaluator'])) {
                    $staff_query .= " AND u.role = ?";
                    $staff_types .= 's';
                    $staff_params[] = $role_filter;
                }

                if($status_filter !== '') {
                    $staff_query .= " AND u.is_active = ?";
                    $staff_types .= 'i';
                    $staff_params[] = (int)$status_filter;
                }

                if($search) {
                    $staff_query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
                    $staff_types .= 'ss';
                    $searchPattern = "%$search%";
                    $staff_params[] = $searchPattern;
                    $staff_params[] = $searchPattern;
                }

                $staff_query .= " ORDER BY FIELD(u.role, 'admin', 'moderator', 'evaluator'), u.name";

                $staff_stmt = $conn->prepare($staff_query);
                if(!empty($staff_params)) {
                    $staff_stmt->bind_param($staff_types, ...$staff_params);
                }
                $staff_stmt->execute();
                $staff_users = $staff_stmt->get_result();
                ?>

                <?php if($staff_users->num_rows == 0): ?>
                    <div class="text-center py-5">
                        <div class="text-muted mb-3" style="font-size: 3rem;">👨‍💼</div>
                        <h5 class="text-muted">No staff members found</h5>
                        <p class="text-muted">No staff match your current filters</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($user = $staff_users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($user['email']) ?></div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?= $user['role'] ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($user['department']): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($user['department']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($user['moderator_name']): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($user['moderator_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-<?= $user['is_active'] ? 'active' : 'inactive' ?>">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                                        <div class="small text-muted"><?= date('g:i A', strtotime($user['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $user['id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="changePassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" title="Change Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $user['is_active'] ? 0 : 1 ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-<?= $user['is_active'] ? 'danger' : 'success' ?>" 
                                                        title="<?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>" 
                                                        onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?>"></i>
                                                </button>
                                            </form>
                                            <?php if($user['role'] != 'admin'): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Student Section -->
    <div class="row mb-4 fade-in" id="studentSection">
        <div class="col-12">
            <div class="user-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-graduation-cap text-success"></i> Students
                    </h5>
                    <button class="btn btn-outline-success btn-sm" onclick="quickAdd('student')">
                        <i class="fas fa-plus"></i> Add Student
                    </button>
                </div>
                
                <!-- Student Filters -->
                <form method="GET" class="row g-3 mb-3">
                    <input type="hidden" name="section" value="student">
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php 
                            $courses = $conn->query("SELECT DISTINCT course FROM users WHERE role = 'student' AND course IS NOT NULL ORDER BY course");
                            while($course = $courses->fetch_assoc()): 
                            ?>
                                <option value="<?= htmlspecialchars($course['course']) ?>" <?= (isset($_GET['course']) && $_GET['course'] == $course['course']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select">
                            <option value="">All Years</option>
                            <option value="1" <?= (isset($_GET['year']) && $_GET['year'] == '1') ? 'selected' : '' ?>>1st Year</option>
                            <option value="2" <?= (isset($_GET['year']) && $_GET['year'] == '2') ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3" <?= (isset($_GET['year']) && $_GET['year'] == '3') ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4" <?= (isset($_GET['year']) && $_GET['year'] == '4') ? 'selected' : '' ?>>4th Year</option>
                            <option value="5" <?= (isset($_GET['year']) && $_GET['year'] == '5') ? 'selected' : '' ?>>5th Year</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search Students</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, email, or roll number..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success me-2">Filter</button>
                        <a href="?section=student" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>

                <!-- Student Table -->
                <?php 
                // Get student users
                $student_query = "SELECT * FROM users WHERE role = 'student'";
                $student_params = [];
                $student_types = '';

                if(isset($_GET['course']) && $_GET['course']) {
                    $student_query .= " AND course = ?";
                    $student_types .= 's';
                    $student_params[] = $_GET['course'];
                }

                if(isset($_GET['year']) && $_GET['year']) {
                    $student_query .= " AND year = ?";
                    $student_types .= 'i';
                    $student_params[] = (int)$_GET['year'];
                }

                if($status_filter !== '') {
                    $student_query .= " AND is_active = ?";
                    $student_types .= 'i';
                    $student_params[] = (int)$status_filter;
                }

                if($search) {
                    $student_query .= " AND (name LIKE ? OR email LIKE ? OR roll_no LIKE ?)";
                    $student_types .= 'sss';
                    $searchPattern = "%$search%";
                    $student_params[] = $searchPattern;
                    $student_params[] = $searchPattern;
                    $student_params[] = $searchPattern;
                }

                $student_query .= " ORDER BY name";

                $student_stmt = $conn->prepare($student_query);
                if(!empty($student_params)) {
                    $student_stmt->bind_param($student_types, ...$student_params);
                }
                $student_stmt->execute();
                $student_users = $student_stmt->get_result();
                ?>

                <?php if($student_users->num_rows == 0): ?>
                    <div class="text-center py-5">
                        <div class="text-muted mb-3" style="font-size: 3rem;">🎓</div>
                        <h5 class="text-muted">No students found</h5>
                        <p class="text-muted">No students match your current filters</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Roll Number</th>
                                    <th>Course</th>
                                    <th>Year/Dept</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($student = $student_users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($student['name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($student['email']) ?></div>
                                    </td>
                                    <td>
                                        <?php if($student['roll_no']): ?>
                                            <span class="badge bg-primary"><?= htmlspecialchars($student['roll_no']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($student['course']): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($student['course']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?php if($student['year']): ?>
                                                <div><strong>Year:</strong> <?= $student['year'] ?></div>
                                            <?php endif; ?>
                                            <?php if($student['department']): ?>
                                                <div><strong>Dept:</strong> <?= htmlspecialchars($student['department']) ?></div>
                                            <?php endif; ?>
                                            <?php if(!$student['year'] && !$student['department']): ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-<?= $student['is_active'] ? 'active' : 'inactive' ?>">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                            <?= $student['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?= date('M j, Y', strtotime($student['created_at'])) ?></div>
                                        <div class="small text-muted"><?= date('g:i A', strtotime($student['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $student['id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="changePassword(<?= $student['id'] ?>, '<?= htmlspecialchars($student['name']) ?>')" title="Change Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?= $student['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $student['is_active'] ? 0 : 1 ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-<?= $student['is_active'] ? 'danger' : 'success' ?>" 
                                                        title="<?= $student['is_active'] ? 'Deactivate' : 'Activate' ?>" 
                                                        onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-<?= $student['is_active'] ? 'ban' : 'check' ?>"></i>
                                                </button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $student['id'] ?>, '<?= htmlspecialchars($student['name']) ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required id="userRole">
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="moderator">Moderator</option>
                                <option value="evaluator">Evaluator</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                        
                        <!-- Student/Evaluator specific fields -->
                        <div class="col-md-6" id="rollNoField" style="display: none;">
                            <label class="form-label">Roll Number</label>
                            <input type="text" name="roll_no" class="form-control">
                        </div>
                        <div class="col-md-6" id="courseField" style="display: none;">
                            <label class="form-label">Course</label>
                            <input type="text" name="course" class="form-control">
                        </div>
                        <div class="col-md-6" id="yearField" style="display: none;">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-select">
                                <option value="">Select Year</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="departmentField" style="display: none;">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control" placeholder="Computer Science">
                        </div>
                        <div class="col-md-6" id="moderatorField" style="display: none;">
                            <label class="form-label">Assign to Moderator</label>
                            <select name="moderator_id" class="form-select">
                                <option value="">Select Moderator</option>
                                <?php 
                                $moderators->data_seek(0); // Reset result pointer
                                while($mod = $moderators->fetch_assoc()): 
                                ?>
                                    <option value="<?= $mod['id'] ?>"><?= htmlspecialchars($mod['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                <?php csrf_input(); ?>
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required id="editUserName">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required id="editUserEmail">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required id="editUserRole">
                                <option value="admin">Administrator</option>
                                <option value="moderator">Moderator</option>
                                <option value="evaluator">Evaluator</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="editRollNoField" style="display: none;">
                            <label class="form-label">Roll Number</label>
                            <input type="text" name="roll_no" class="form-control" id="editUserRollNo">
                        </div>
                        <div class="col-md-6" id="editCourseField" style="display: none;">
                            <label class="form-label">Course</label>
                            <input type="text" name="course" class="form-control" id="editUserCourse">
                        </div>
                        <div class="col-md-6" id="editYearField" style="display: none;">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-select" id="editUserYear">
                                <option value="">Select Year</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="editDepartmentField" style="display: none;">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control" id="editUserDepartment">
                        </div>
                        <div class="col-md-6" id="editModeratorField" style="display: none;">
                            <label class="form-label">Assign to Moderator</label>
                            <select name="moderator_id" class="form-select" id="editUserModerator">
                                <option value="">Select Moderator</option>
                                <?php 
                                $moderators->data_seek(0); // Reset result pointer
                                while($mod = $moderators->fetch_assoc()): 
                                ?>
                                    <option value="<?= $mod['id'] ?>"><?= htmlspecialchars($mod['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_input(); ?>
                <input type="hidden" name="user_id" id="passwordUserId">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Change password for: <strong id="passwordUserName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" id="confirmPassword" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_password" class="btn btn-warning">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_input(); ?>
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">Are you sure?</h5>
                        <p>You are about to delete: <strong id="deleteUserName"></strong></p>
                        <p class="text-muted">This action cannot be undone. The user will be permanently removed from the system.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('userRole');
    const editRoleSelect = document.getElementById('editUserRole');
    
    // Function to toggle fields based on role
    function toggleFields(role, prefix = '') {
        const fields = ['rollNo', 'course', 'year', 'department', 'moderator'];
        
        // Hide all fields first
        fields.forEach(field => {
            const element = document.getElementById(prefix + field + 'Field');
            if (element) element.style.display = 'none';
        });
        
        // Show fields based on role
        if (role === 'student') {
            ['rollNo', 'course', 'year', 'department'].forEach(field => {
                const element = document.getElementById(prefix + field + 'Field');
                if (element) element.style.display = 'block';
            });
        } else if (role === 'evaluator') {
            ['department', 'moderator'].forEach(field => {
                const element = document.getElementById(prefix + field + 'Field');
                if (element) element.style.display = 'block';
            });
        }
    }
    
    // Create user role change
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            toggleFields(this.value);
        });
    }
    
    // Edit user role change
    if (editRoleSelect) {
        editRoleSelect.addEventListener('change', function() {
            toggleFields(this.value, 'edit');
        });
    }
    
    // Password confirmation validation
    const passwordForm = document.querySelector('#changePasswordModal form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = this.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please try again.');
                return false;
            }
        });
    }
});

// Function to edit user
function editUser(userId) {
    // Fetch user data via AJAX
    fetch(`manage_users.php?action=get_user&id=${userId}`)
        .then(response => response.json())
        .then(user => {
            if (user.error) {
                alert('Error loading user data');
                return;
            }
            
            // Populate form fields
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUserName').value = user.name;
            document.getElementById('editUserEmail').value = user.email;
            document.getElementById('editUserRole').value = user.role;
            document.getElementById('editUserRollNo').value = user.roll_no || '';
            document.getElementById('editUserCourse').value = user.course || '';
            document.getElementById('editUserYear').value = user.year || '';
            document.getElementById('editUserDepartment').value = user.department || '';
            document.getElementById('editUserModerator').value = user.moderator_id || '';
            
            // Toggle fields based on role
            toggleFields(user.role, 'edit');
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading user data');
        });
}

// Function to change password
function changePassword(userId, userName) {
    document.getElementById('passwordUserId').value = userId;
    document.getElementById('passwordUserName').textContent = userName;
    
    // Clear password fields
    document.querySelector('#changePasswordModal input[name="new_password"]').value = '';
    document.getElementById('confirmPassword').value = '';
    
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
}

// Function to delete user
function deleteUser(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}

// Function to quick add specific roles
function quickAdd(role) {
    // Reset the create form
    document.querySelector('#createUserModal form').reset();
    
    // Set the role
    document.getElementById('userRole').value = role;
    
    // Update modal title
    const modalTitle = document.querySelector('#createUserModal .modal-title');
    modalTitle.textContent = `Add New ${role.charAt(0).toUpperCase() + role.slice(1)}`;
    
    // Toggle fields based on role
    toggleFields(role);
    
    // Show modal
    new bootstrap.Modal(document.getElementById('createUserModal')).show();
}

// Function to toggle fields (used by both create and edit forms)
function toggleFields(role, prefix = '') {
    const fields = ['rollNo', 'course', 'year', 'department', 'moderator'];
    
    // Hide all fields first
    fields.forEach(field => {
        const element = document.getElementById(prefix + field + 'Field');
        if (element) element.style.display = 'none';
    });
    
    // Show fields based on role
    if (role === 'student') {
        ['rollNo', 'course', 'year', 'department'].forEach(field => {
            const element = document.getElementById(prefix + field + 'Field');
            if (element) element.style.display = 'block';
        });
    } else if (role === 'evaluator') {
        ['department', 'moderator'].forEach(field => {
            const element = document.getElementById(prefix + field + 'Field');
            if (element) element.style.display = 'block';
        });
    }
}
</script>

<?php include('../includes/footer.php'); ?>