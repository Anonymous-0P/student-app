<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Handle AJAX request for user data
if(isset($_GET['action']) && $_GET['action'] == 'get_user' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($user);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Student not found']);
    }
    exit;
}

// Handle student creation
if(isset($_POST['create_user'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
        $college_name = !empty($_POST['college_name']) ? trim($_POST['college_name']) : null;
        $division = !empty($_POST['division']) ? trim($_POST['division']) : null;
        
        // Validation
        if (empty($name)) {
            $error = "Name is required.";
        } elseif (empty($email)) {
            $error = "Email is required.";
        } elseif (empty($_POST['password'])) {
            $error = "Password is required.";
        } elseif (empty($division)) {
            $error = "Division is required.";
        } else {
            // Check if email already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $existing = $check_stmt->get_result();
            
            if($existing->num_rows > 0) {
                $error = "Email already exists. Please use a different email address.";
            } else {
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone, college_name, division, is_active) VALUES (?, ?, ?, 'student', ?, ?, ?, 1)");
                $stmt->bind_param("ssssss", $name, $email, $password, $phone, $college_name, $division);
                
                if($stmt->execute()) {
                    $success = "Student created successfully.";
                } else {
                    $error = "Error creating student: " . $conn->error;
                }
            }
        }
    }
}

// Handle student update
if(isset($_POST['update_user'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
        $college_name = !empty($_POST['college_name']) ? trim($_POST['college_name']) : null;
        $division = !empty($_POST['division']) ? trim($_POST['division']) : null;
        
        // Validation
        if (empty($name)) {
            $error = "Name is required.";
        } elseif (empty($email)) {
            $error = "Email is required.";
        } elseif (empty($division)) {
            $error = "Division is required.";
        } else {
            // Check if email already exists (excluding current user)
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->bind_param("si", $email, $user_id);
            $check_stmt->execute();
            $existing = $check_stmt->get_result();
            
            if($existing->num_rows > 0) {
                $error = "Email already exists. Please use a different email address.";
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, college_name=?, division=? WHERE id=? AND role='student'");
                $stmt->bind_param("sssssi", $name, $email, $phone, $college_name, $division, $user_id);
                
                if($stmt->execute()) {
                    $success = "Student updated successfully.";
                } else {
                    $error = "Error updating student.";
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
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'student'");
        $stmt->bind_param("si", $new_password, $user_id);
        
        if($stmt->execute()) {
            $success = "Password updated successfully.";
        } else {
            $error = "Error updating password.";
        }
    }
}

// Handle student deletion
if(isset($_POST['delete_user'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        
        // Check if student has any dependencies (submissions)
        $dep_check = $conn->prepare("SELECT COUNT(*) as submissions FROM submissions WHERE student_id = ?");
        $dep_check->bind_param("i", $user_id);
        $dep_check->execute();
        $dependencies = $dep_check->get_result()->fetch_assoc();
        
        if($dependencies['submissions'] > 0) {
            $error = "Cannot delete student: Student has existing submissions. Deactivate instead.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $stmt->bind_param("i", $user_id);
            
            if($stmt->execute()) {
                $success = "Student deleted successfully.";
            } else {
                $error = "Error deleting student.";
            }
        }
    }
}

// Handle student status updates
if(isset($_POST['toggle_status'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['user_id'];
        $new_status = (int)$_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'student'");
        $stmt->bind_param("ii", $new_status, $user_id);
        
        if($stmt->execute()) {
            $success = $new_status ? "Student activated successfully." : "Student deactivated successfully.";
        } else {
            $error = "Error updating student status.";
        }
    }
}

// Filter and search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query for students only
$query = "SELECT * FROM users WHERE role = 'student'";
$params = [];
$types = "";

if(!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR college_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if($status_filter !== '') {
    $query .= " AND is_active = ?";
    $params[] = (int)$status_filter;
    $types .= "i";
}

$query .= " ORDER BY name ASC";

$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

// Get student statistics
$studentStats = $conn->query("SELECT 
    COUNT(*) as total_students,
    SUM(is_active) as active_students,
    COUNT(DISTINCT college_name) as total_colleges
    FROM users WHERE role = 'student'")->fetch_assoc();
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
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
}

.btn-group-actions .btn {
    border-radius: 6px;
    transition: all 0.2s ease;
    min-width: 32px;
    height: 32px;
    padding: 0.25rem 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    line-height: 1;
    border-width: 1px;
}

.btn-group-actions .btn:hover {
    transform: scale(1.05);
}

.btn-group-actions .btn i {
    font-size: 0.875rem;
}

/* Ensure buttons are visible with proper colors */
.btn-outline-primary {
    color: #0d6efd;
    border-color: #0d6efd;
}

.btn-outline-primary:hover {
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-outline-warning {
    color: #f57c00;
    border-color: #f57c00;
}

.btn-outline-warning:hover {
    color: #fff;
    background-color: #f57c00;
    border-color: #f57c00;
}

.btn-outline-success {
    color: #198754;
    border-color: #198754;
}

.btn-outline-success:hover {
    color: #fff;
    background-color: #198754;
    border-color: #198754;
}

.btn-outline-secondary {
    color: #6c757d;
    border-color: #6c757d;
}

.btn-outline-secondary:hover {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}

.btn-outline-danger {
    color: #dc3545;
    border-color: #dc3545;
}

.btn-outline-danger:hover {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
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
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}
</style>

<div class="container-fluid" style="padding-left: 50px; padding-right: 50px;">
    <!-- Header -->
    <div class="row mb-4 mt-4 fade-in">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="fas fa-graduation-cap text-primary"></i> Student Management
                    </h1>
                    <p class="text-muted mb-0">Manage student accounts, profiles, and academic information</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="fas fa-user-plus"></i> Add New Student
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

    <!-- Student Statistics -->
    <!-- <div class="row g-3 mb-4 fade-in ">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="h3 mb-1"><?= $studentStats['active_students'] ?></div>
                <div class="small">Active Students</div>
            </div>
        </div>
       
        <div class="col-md-3">
            <div class="stats-card">
                <div class="h3 mb-1"><?= $studentStats['total_colleges'] ?></div>
                <div class="small">Colleges</div>
            </div>
        </div>
    </div> -->

    <!-- Search and Filters -->
    <div class="row mb-4 fade-in">
        <div class="col-12">
            <div class="search-filters">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search Students</label>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Name, email, or college...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="manage_users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="row fade-in">
        <div class="col-12">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Students List
                        <small class="text-muted">(<?= $students->num_rows ?> found)</small>
                    </h5>
                </div>
                
                <?php if($students->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Student Info</th>
                                    <th>College Details</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($student = $students->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($student['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($student['email']) ?></small>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($student['college_name']) ?></div>
                                        <?php if($student['division']): ?>
                                            <small class="badge bg-primary"><?= htmlspecialchars($student['division']) ?> Standard</small>
                                        <?php endif; ?>
                                        <?php if($student['phone']): ?>
                                            <small class="text-muted d-block">Phone: <?= htmlspecialchars($student['phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($student['email']) ?></div>
                                        <small class="text-muted">Joined: <?= date('M Y', strtotime($student['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-<?= $student['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $student['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group-actions">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editUser(<?= $student['id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="changePassword(<?= $student['id'] ?>)" title="Change Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-<?= $student['is_active'] ? 'secondary' : 'success' ?>" 
                                                    onclick="toggleStatus(<?= $student['id'] ?>, <?= $student['is_active'] ? '0' : '1' ?>)" 
                                                    title="<?= $student['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $student['is_active'] ? 'pause' : 'play' ?>"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteUser(<?= $student['id'] ?>)" title="Delete">
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
                        <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Students Found</h5>
                        <p class="text-muted">No students match your current search criteria.</p>
                        <button class="btn btn-success" onclick="showAddModal()">
                            <i class="fas fa-plus"></i> Add First Student
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Student Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm" method="POST">
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
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" id="phone" placeholder="+1234567890">
                        </div>
                        <div class="col-md-6">
                            <label for="college_name" class="form-label">College Name</label>
                            <input type="text" class="form-control" name="college_name" id="college_name" placeholder="XYZ University">
                        </div>
                        <div class="col-md-6">
                            <label for="division" class="form-label">Division <span class="text-danger">*</span></label>
                            <select class="form-select" name="division" id="division" required>
                                <option value="">Select Division</option>
                                <option value="10th">10th Standard</option>
                                <option value="12th">12th Standard</option>
                            </select>
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
                    <button type="submit" class="btn btn-success" id="submitBtn">Add Student</button>
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

<script>
let isEditing = false;

function showAddModal() {
    isEditing = false;
    document.getElementById('modalTitle').textContent = 'Add New Student';
    document.getElementById('submitBtn').textContent = 'Add Student';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordSection').style.display = 'block';
    document.getElementById('userForm').action = '';
    
    // Add hidden field for create action
    let createInput = document.createElement('input');
    createInput.type = 'hidden';
    createInput.name = 'create_user';
    createInput.value = '1';
    document.getElementById('userForm').appendChild(createInput);
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function editUser(userId) {
    isEditing = true;
    document.getElementById('modalTitle').textContent = 'Edit Student';
    document.getElementById('submitBtn').textContent = 'Update Student';
    document.getElementById('password').required = false;
    document.getElementById('passwordSection').style.display = 'none';
    
    // Remove create_user field if exists
    const createField = document.querySelector('input[name="create_user"]');
    if(createField) createField.remove();
    
    // Add update field
    let updateInput = document.createElement('input');
    updateInput.type = 'hidden';
    updateInput.name = 'update_user';
    updateInput.value = '1';
    document.getElementById('userForm').appendChild(updateInput);
    
    // Fetch user data
    fetch(`?action=get_user&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if(data.error) {
                alert('Error loading student data');
                return;
            }
            
            document.getElementById('user_id').value = data.id;
            document.getElementById('name').value = data.name || '';
            document.getElementById('email').value = data.email || '';
            document.getElementById('phone').value = data.phone || '';
            document.getElementById('college_name').value = data.college_name || '';
            document.getElementById('division').value = data.division || '';
            
            new bootstrap.Modal(document.getElementById('userModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading student data');
        });
}

function changePassword(userId) {
    document.getElementById('password_user_id').value = userId;
    document.getElementById('new_password').value = '';
    new bootstrap.Modal(document.getElementById('passwordModal')).show();
}

function toggleStatus(userId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    if(confirm(`Are you sure you want to ${action} this student?`)) {
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

function deleteUser(userId) {
    if(confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="delete_user" value="1">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
document.getElementById('userForm').addEventListener('submit', function(e) {
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
</script>

<?php include('../includes/footer.php'); ?>