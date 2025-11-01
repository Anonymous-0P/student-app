<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Handle subject creation
if(isset($_POST['create_subject'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name']);
        $grade = trim($_POST['grade']);
        $description = trim($_POST['description']) ?: null;
        $price = floatval($_POST['price']) ?: 100.00;
        $duration_days = intval($_POST['duration_days']) ?: 365;
        
        // Generate subject code from name and grade
        $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)) . '_' . strtoupper($grade);
        
        // Check if subject with same name and grade already exists
        $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE name = ? AND department = ?");
        if (!$check_stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $check_stmt->bind_param("ss", $name, $grade);
            $check_stmt->execute();
            $existing = $check_stmt->get_result();
            
            if($existing->num_rows > 0) {
                $error = "Subject with same name and grade already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO subjects (code, name, department, description, price, duration_days, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssdi", $code, $name, $grade, $description, $price, $duration_days);
                    
                    if($stmt->execute()) {
                        $success = "Subject created successfully.";
                    } else {
                        $error = "Error creating subject: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Handle subject update
if(isset($_POST['update_subject'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $subject_id = (int)$_POST['subject_id'];
        $name = trim($_POST['name']);
        $grade = trim($_POST['grade']);
        $description = trim($_POST['description']) ?: null;
        $price = floatval($_POST['price']) ?: 100.00;
        $duration_days = intval($_POST['duration_days']) ?: 365;
        
        // Generate subject code from name and grade
        $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)) . '_' . strtoupper($grade);
        
        // Check if subject with same name and grade already exists (excluding current subject)
        $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE name = ? AND department = ? AND id != ?");
        if (!$check_stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $check_stmt->bind_param("ssi", $name, $grade, $subject_id);
            $check_stmt->execute();
            $existing = $check_stmt->get_result();
            
            if($existing->num_rows > 0) {
                $error = "Subject with same name and grade already exists.";
            } else {
                $stmt = $conn->prepare("UPDATE subjects SET code=?, name=?, department=?, description=?, price=?, duration_days=? WHERE id=?");
                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssdii", $code, $name, $grade, $description, $price, $duration_days, $subject_id);
                    
                    if($stmt->execute()) {
                        $success = "Subject updated successfully.";
                    } else {
                        $error = "Error updating subject: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Handle subject deletion
if(isset($_POST['delete_subject'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $subject_id = (int)$_POST['subject_id'];
        
        // Check if subject has dependencies
        $dep_check = $conn->prepare("SELECT 
            (SELECT COUNT(*) FROM moderator_subjects WHERE subject_id = ?) as moderator_assignments,
            (SELECT COUNT(*) FROM evaluator_subjects WHERE subject_id = ?) as evaluator_assignments,
            (SELECT COUNT(*) FROM questions WHERE subject_id = ?) as questions_count");
        
        if (!$dep_check) {
            $error = "Database error: " . $conn->error;
        } else {
            $dep_check->bind_param("iii", $subject_id, $subject_id, $subject_id);
            $dep_check->execute();
            $dependencies = $dep_check->get_result()->fetch_assoc();
            
            if($dependencies && ($dependencies['moderator_assignments'] > 0 || $dependencies['evaluator_assignments'] > 0 || $dependencies['questions_count'] > 0)) {
                $error = "Cannot delete subject: It has moderator/evaluator assignments or questions. Deactivate instead.";
            } else {
                $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("i", $subject_id);
                    
                    if($stmt->execute()) {
                        $success = "Subject deleted successfully.";
                    } else {
                        $error = "Error deleting subject.";
                    }
                }
            }
        }
    }
}

// Handle status toggle
if(isset($_POST['toggle_status'])) {
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $subject_id = (int)$_POST['subject_id'];
        $new_status = (int)$_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE subjects SET is_active = ? WHERE id = ?");
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ii", $new_status, $subject_id);
            
            if($stmt->execute()) {
                $success = $new_status ? "Subject activated successfully." : "Subject deactivated successfully.";
            } else {
                $error = "Error updating subject status.";
            }
        }
    }
}

// Handle AJAX request for subject data
if(isset($_GET['action']) && $_GET['action'] == 'get_subject' && isset($_GET['id'])) {
    $subject_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
    if (!$stmt) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $subject = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($subject);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Subject not found']);
    }
    exit;
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$grade_filter = isset($_GET['grade']) ? trim($_GET['grade']) : '';

// Build query with filters
$query = "SELECT s.*, 
    (SELECT COUNT(*) FROM moderator_subjects ms WHERE ms.subject_id = s.id) as moderator_count,
    (SELECT COUNT(*) FROM evaluator_subjects es WHERE es.subject_id = s.id) as evaluator_count,
    (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.id) as questions_count
    FROM subjects s WHERE 1=1";
$params = [];
$types = "";

if(!empty($search)) {
    $query .= " AND (s.code LIKE ? OR s.name LIKE ? OR s.department LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if($status_filter !== '') {
    $query .= " AND s.is_active = ?";
    $params[] = (int)$status_filter;
    $types .= "i";
}

if(!empty($grade_filter)) {
    $query .= " AND s.department = ?";
    $params[] = $grade_filter;
    $types .= "s";
}

$query .= " ORDER BY s.code ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    $error = "Database error: " . $conn->error;
    $subjects = false;
} else {
    if(!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $subjects = $stmt->get_result();
}

// Get subject statistics
$stats_query = $conn->query("SELECT 
    COUNT(*) as total_subjects,
    SUM(is_active) as active_subjects,
    COUNT(DISTINCT department) as total_grades,
    (SELECT COUNT(*) FROM questions) as total_questions
    FROM subjects");
$subjectStats = $stats_query ? $stats_query->fetch_assoc() : array(
    'total_subjects' => 0,
    'active_subjects' => 0, 
    'total_grades' => 0,
    'total_questions' => 0
);

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1"><i class="fas fa-book text-primary"></i> Subject Management</h1>
                    <p class="text-muted mb-0">Manage academic subjects and courses</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="resetForm()">
                    <i class="fas fa-plus"></i> Add Subject
                </button>
            </div>

            <!-- Statistics Cards -->
            <!-- <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary bg-gradient p-3 rounded-circle">
                                        <i class="fas fa-book text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="text-muted mb-1">Total Subjects</h6>
                                    <h4 class="mb-0"><?= $subjectStats['total_subjects'] ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="bg-success bg-gradient p-3 rounded-circle">
                                        <i class="fas fa-check-circle text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="text-muted mb-1">Active Subjects</h6>
                                    <h4 class="mb-0"><?= $subjectStats['active_subjects'] ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="bg-info bg-gradient p-3 rounded-circle">
                                        <i class="fas fa-building text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="text-muted mb-1">Grade Levels</h6>
                                    <h4 class="mb-0"><?= $subjectStats['total_grades'] ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="bg-warning bg-gradient p-3 rounded-circle">
                                        <i class="fas fa-question-circle text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="text-muted mb-1">Total Questions</h6>
                                    <h4 class="mb-0"><?= $subjectStats['total_questions'] ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->

            <!-- Alert Messages -->
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search and Filter Section -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Search by subject name">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="grade" class="form-label">Grade Level</label>
                                <select class="form-select" id="grade_filter" name="grade">
                                    <option value="">All Grades</option>
                                    <option value="10th" <?= $grade_filter === '10th' ? 'selected' : '' ?>>10th Grade</option>
                                    <option value="11th" <?= $grade_filter === '11th' ? 'selected' : '' ?>>11th Grade</option>
                                    <option value="12th" <?= $grade_filter === '12th' ? 'selected' : '' ?>>12th Grade</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Subjects Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if($subjects && $subjects->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Grade Level</th>
                                        <th>Price</th>
                                        <th>Duration</th>
                                        <th>Assignments</th>
                                        <th>Questions</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($subject = $subjects->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($subject['code']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($subject['name']) ?></div>
                                        </td>
                                        <td>
                                            <?php if($subject['department']): ?>
                                                <span class="badge bg-primary"><?= htmlspecialchars($subject['department']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-success">
                                                $<?= number_format($subject['price'] ?? 0, 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?= $subject['duration_days'] ?? 365 ?> days</span>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge bg-primary"><?= $subject['moderator_count'] ?> Moderators</span>
                                                <span class="badge bg-success"><?= $subject['evaluator_count'] ?> Evaluators</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?= $subject['questions_count'] ?> Questions</span>
                                        </td>
                                        <td>
                                            <?php if($subject['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editSubject(<?= $subject['id'] ?>)" 
                                                        title="Edit Subject">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to <?= $subject['is_active'] ? 'deactivate' : 'activate' ?> this subject?')">
                                                    <?php csrf_input(); ?>
                                                    <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                                    <input type="hidden" name="new_status" value="<?= $subject['is_active'] ? 0 : 1 ?>">
                                                    <button type="submit" name="toggle_status" 
                                                            class="btn btn-sm btn-outline-<?= $subject['is_active'] ? 'warning' : 'success' ?>" 
                                                            title="<?= $subject['is_active'] ? 'Deactivate' : 'Activate' ?> Subject">
                                                        <i class="fas fa-<?= $subject['is_active'] ? 'pause' : 'play' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this subject? This action cannot be undone.')">
                                                    <?php csrf_input(); ?>
                                                    <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                                    <button type="submit" name="delete_subject" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            title="Delete Subject">
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
                            <div class="mb-3">
                                <i class="fas fa-book fa-3x text-muted"></i>
                            </div>
                            <h5 class="text-muted">No subjects found</h5>
                            <p class="text-muted">Start by creating your first subject.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="resetForm()">
                                <i class="fas fa-plus"></i> Add Subject
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subject Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1" aria-labelledby="subjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subjectModalLabel">Add Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" id="subjectForm">
                <div class="modal-body">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="subject_id" id="subject_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Subject Name *</label>
                            <input type="text" class="form-control" name="name" id="name" required
                                   placeholder="e.g., Mathematics, Physics, Chemistry">
                        </div>
                        <div class="col-md-6">
                            <label for="grade" class="form-label">Grade Level *</label>
                            <select class="form-select" name="grade" id="grade" required>
                                <option value="">Select Grade Level</option>
                                <option value="10th">10th Grade</option>
                                <option value="11th">11th Grade</option>
                                <option value="12th">12th Grade</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="3"
                                      placeholder="Brief description of the subject content and objectives"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label">Price ($) *</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="price" id="price" 
                                   value="100.00" required placeholder="99.99">
                            <div class="form-text">Price for students to purchase this subject</div>
                        </div>
                        <div class="col-md-6">
                            <label for="duration_days" class="form-label">Access Duration (Days) *</label>
                            <input type="number" min="1" class="form-control" name="duration_days" id="duration_days" 
                                   value="365" required placeholder="365">
                            <div class="form-text">How many days students have access after purchase</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_subject" id="submitBtn" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('subjectForm').reset();
    document.getElementById('subject_id').value = '';
    document.getElementById('price').value = '100.00';
    document.getElementById('duration_days').value = '365';
    document.getElementById('subjectModalLabel').textContent = 'Add Subject';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Create Subject';
    document.getElementById('submitBtn').name = 'create_subject';
}

function editSubject(subjectId) {
    fetch(`?action=get_subject&id=${subjectId}`)
        .then(response => response.json())
        .then(data => {
            if(data.error) {
                alert('Error: ' + data.error);
                return;
            }
            
            document.getElementById('subject_id').value = data.id;
            document.getElementById('name').value = data.name || '';
            document.getElementById('grade').value = data.department || '';
            document.getElementById('description').value = data.description || '';
            document.getElementById('price').value = data.price || '100.00';
            document.getElementById('duration_days').value = data.duration_days || '365';
            
            document.getElementById('subjectModalLabel').textContent = 'Edit Subject';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update Subject';
            document.getElementById('submitBtn').name = 'update_subject';
            
            new bootstrap.Modal(document.getElementById('subjectModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading subject data');
        });
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if(alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => {
                    if(alert.parentNode) {
                        alert.remove();
                    }
                }, 150);
            }
        }, 5000);
    });
});
</script>

<?php include('../includes/footer.php'); ?>