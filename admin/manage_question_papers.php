<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_paper'])) {
    $subject_id = (int)$_POST['subject_id'];
    $title = trim($_POST['title']);
    // $description = trim($_POST['description']);
    $grade_level = $_POST['grade_level'];
    // $exam_type = $_POST['exam_type'];
    $marks = (int)$_POST['marks'];
    $duration = (int)$_POST['duration'];
    $instructions = trim($_POST['instructions']);
    
    $upload_dir = '../uploads/question_papers/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $success = false;
    $error = '';
    
    if (isset($_FILES['question_paper']) && $_FILES['question_paper']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['question_paper'];
        $original_filename = $file['name'];
        $file_size = $file['size'];
        $mime_type = $file['type'];
        
        // Validate file type
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($mime_type, $allowed_types)) {
            $error = 'Invalid file type. Only PDF, DOC, and DOCX files are allowed.';
        } else {
            // Generate unique filename
            $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $unique_filename;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO question_papers (subject_id, title, description, file_path, original_filename, file_size, mime_type, grade_level, exam_type, marks, duration_minutes, instructions, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssissiisi", $subject_id, $title, $description, $file_path, $original_filename, $file_size, $mime_type, $grade_level, $exam_type, $marks, $duration, $instructions, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $success = true;
                    $_SESSION['message'] = 'Question paper uploaded successfully!';
                } else {
                    $error = 'Database error occurred.';
                    unlink($file_path); // Remove uploaded file if DB insert fails
                }
            } else {
                $error = 'Failed to upload file.';
            }
        }
    } else {
        $error = 'No file selected or upload error occurred.';
    }
    
    if ($error) {
        $_SESSION['error'] = $error;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get file path before deleting from database
    $stmt = $conn->prepare("SELECT file_path FROM question_papers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $paper = $result->fetch_assoc();
    
    if ($paper) {
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM question_papers WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Delete physical file
            if (file_exists($paper['file_path'])) {
                unlink($paper['file_path']);
            }
            $_SESSION['message'] = 'Question paper deleted successfully!';
        } else {
            $_SESSION['error'] = 'Failed to delete question paper.';
        }
    }
    
    header('Location: manage_question_papers.php');
    exit;
}

// Handle status toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE question_papers SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Status updated successfully!';
    } else {
        $_SESSION['error'] = 'Failed to update status.';
    }
    
    header('Location: manage_question_papers.php');
    exit;
}

// Get subjects for dropdown
$subjects_result = $conn->query("SELECT id, code, name, grade_level FROM subjects WHERE is_active = 1 ORDER BY grade_level, code");
$subjects = [];
if ($subjects_result) {
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
    }
} else {
    echo "Error fetching subjects: " . $conn->error;
}

// Get question papers with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$subject_filter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

// Build query with filters
$where_conditions = ["qp.id IS NOT NULL"];
$params = [];

if ($search) {
    $where_conditions[] = "(qp.title LIKE ? OR qp.description LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($grade_filter) {
    $where_conditions[] = "qp.grade_level = ?";
    $params[] = $grade_filter;
}

if ($subject_filter) {
    $where_conditions[] = "qp.subject_id = ?";
    $params[] = $subject_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM question_papers qp 
                LEFT JOIN subjects s ON qp.subject_id = s.id 
                WHERE $where_clause";

if ($params) {
    $count_stmt = $conn->prepare($count_query);
    if ($count_stmt) {
        $types = str_repeat('s', count($params));
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_papers = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    } else {
        $total_papers = 0;
    }
} else {
    $count_result = $conn->query($count_query);
    $total_papers = $count_result ? $count_result->fetch_assoc()['total'] : 0;
}
$total_pages = ceil($total_papers / $per_page);

// Get papers for current page
$papers_query = "SELECT qp.*, s.code as subject_code, s.name as subject_name, u.name as uploaded_by_name,
                 (SELECT COUNT(*) FROM question_paper_downloads WHERE question_paper_id = qp.id) as download_count
                 FROM question_papers qp
                 LEFT JOIN subjects s ON qp.subject_id = s.id
                 LEFT JOIN users u ON qp.uploaded_by = u.id
                 WHERE $where_clause
                 ORDER BY qp.created_at DESC
                 LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

if (count($params) > 0) {
    $papers_stmt = $conn->prepare($papers_query);
    if ($papers_stmt) {
        $types = str_repeat('s', count($params) - 2) . 'ii'; // Last two params are integers (limit, offset)
        $papers_stmt->bind_param($types, ...$params);
        $papers_stmt->execute();
        $papers_result = $papers_stmt->get_result();
    } else {
        $papers_result = false;
    }
} else {
    $papers_result = $conn->query($papers_query);
}

$papers = [];
if ($papers_result) {
    while ($row = $papers_result->fetch_assoc()) {
        $papers[] = $row;
    }
}

include('../includes/header.php');
?>

<style>
.admin-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
}

.admin-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.upload-area {
    border: 2px dashed #667eea;
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    background: #f8f9ff;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-area:hover {
    border-color: #764ba2;
    background: #f0f2ff;
}

.upload-area.dragover {
    border-color: #764ba2;
    background: #e8ebff;
    transform: scale(1.02);
}

.file-icon {
    font-size: 3rem;
    color: #667eea;
    margin-bottom: 1rem;
}

.btn-gradient {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    color: white;
}

.paper-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.paper-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.paper-card.active {
    border-left-color: #28a745;
}

.paper-card.inactive {
    border-left-color: #dc3545;
    opacity: 0.7;
}

.grade-badge {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.exam-type-badge {
    background: #f8f9fa;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 15px;
    font-size: 0.75rem;
    border: 1px solid #e9ecef;
}

.stats-widget {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 1.5rem;
    color: white;
    text-align: center;
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
                    <h1 class="h3 mb-2"><i class="fas fa-file-upload text-primary"></i> Question Paper Management</h1>
                    <p class="text-muted mb-0">Upload, manage, and assign question papers to students</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="row g-4 mb-4">
        <?php
        // Get stats with error handling
        $stats_result = $conn->query("SELECT 
            COUNT(*) as total_papers,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_papers,
            COUNT(CASE WHEN grade_level = '10th' THEN 1 END) as grade_10_papers,
            COUNT(CASE WHEN grade_level = '11th' THEN 1 END) as grade_11_papers,
            COUNT(CASE WHEN grade_level = '12th' THEN 1 END) as grade_12_papers
            FROM question_papers");
        
        if ($stats_result) {
            $stats = $stats_result->fetch_assoc();
            // Get downloads count separately
            $downloads_result = $conn->query("SELECT COUNT(*) as total_downloads FROM question_paper_downloads");
            $stats['total_downloads'] = $downloads_result ? $downloads_result->fetch_assoc()['total_downloads'] : 0;
        } else {
            // Default values if query fails
            $stats = [
                'total_papers' => 0,
                'active_papers' => 0,
                'grade_10_papers' => 0,
                'grade_11_papers' => 0,
                'grade_12_papers' => 0,
                'total_downloads' => 0
            ];
        }
        ?>
        <div class="col-md-2 fade-in" style="animation-delay: 0.1s;">
            <div class="stats-widget">
                <div class="h4 mb-1"><?= $stats['total_papers'] ?></div>
                <div class="small opacity-75">Total Papers</div>
            </div>
        </div>
        <div class="col-md-2 fade-in" style="animation-delay: 0.2s;">
            <div class="stats-widget">
                <div class="h4 mb-1"><?= $stats['active_papers'] ?></div>
                <div class="small opacity-75">Active Papers</div>
            </div>
        </div>
        <div class="col-md-2 fade-in" style="animation-delay: 0.3s;">
            <div class="stats-widget">
                <div class="h4 mb-1"><?= $stats['grade_10_papers'] ?></div>
                <div class="small opacity-75">Grade 10th</div>
            </div>
        </div>
        
        <div class="col-md-2 fade-in" style="animation-delay: 0.5s;">
            <div class="stats-widget">
                <div class="h4 mb-1"><?= $stats['grade_12_papers'] ?></div>
                <div class="small opacity-75">Grade 12th</div>
            </div>
        </div>
        
    </div>

    <!-- Upload Form -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="admin-card fade-in" style="animation-delay: 0.7s;">
                <h5 class="mb-3"><i class="fas fa-upload text-primary"></i> Upload New Question Paper</h5>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="row g-3">
                        <!-- File Upload Area -->
                        <div class="col-12">
                            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                                <div class="file-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h6>Click to upload or drag and drop</h6>
                                <p class="text-muted mb-0">PDF, DOC, or DOCX files only (Max 10MB)</p>
                                <input type="file" id="fileInput" name="question_paper" accept=".pdf,.doc,.docx" style="display: none;" required>
                            </div>
                            <div id="fileInfo" class="mt-2" style="display: none;"></div>
                        </div>

                        <!-- Form Fields -->
                        <div class="col-md-6">
                            <label for="subject_id" class="form-label">Subject *</label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>" data-grade="<?= $subject['grade_level'] ?>">
                                        <?= htmlspecialchars($subject['code']) ?> - <?= htmlspecialchars($subject['name']) ?> (<?= $subject['grade_level'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="grade_level" class="form-label">Grade Level *</label>
                            <select class="form-select" id="grade_level" name="grade_level" required>
                                <option value="">Select Grade</option>
                                <option value="10th">10th Grade</option>
                                <option value="11th">11th Grade</option>
                                <option value="12th">12th Grade</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="title" class="form-label">Paper Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>

                       
                        <div class="col-md-4">
                            <label for="marks" class="form-label">Total Marks</label>
                            <input type="number" class="form-control" id="marks" name="marks" value="100" min="1" max="1000">
                        </div>

                        <div class="col-md-4">
                            <label for="duration" class="form-label">Duration (Minutes)</label>
                            <input type="number" class="form-control" id="duration" name="duration" value="180" min="30" max="600">
                        </div>

                      
                        <div class="col-12">
                            <label for="instructions" class="form-label">Instructions for Students</label>
                            <textarea class="form-control" id="instructions" name="instructions" rows="4" placeholder="Enter instructions for students (e.g., Answer all questions, Use black/blue pen only, etc.)"></textarea>
                        </div>

                        <div class="col-12">
                            <button type="submit" name="upload_paper" class="btn-gradient">
                                <i class="fas fa-upload"></i> Upload Question Paper
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    

    <!-- Question Papers List -->
    <div class="row">
        <div class="col-12">
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-list text-info"></i> Question Papers (<?= $total_papers ?> total)</h5>
                </div>

                <?php if (empty($papers)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Question Papers Found</h5>
                        <p class="text-muted">Upload your first question paper using the form above.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($papers as $paper): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="paper-card <?= $paper['is_active'] ? 'active' : 'inactive' ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="grade-badge"><?= $paper['grade_level'] ?></span>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="../uploads/question_papers/<?= basename($paper['file_path']) ?>" target="_blank">
                                                    <i class="fas fa-eye"></i> View
                                                </a></li>
                                                <li><a class="dropdown-item" href="../uploads/question_papers/<?= basename($paper['file_path']) ?>" download>
                                                    <i class="fas fa-download"></i> Download
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="?toggle=<?= $paper['id'] ?>">
                                                    <i class="fas fa-<?= $paper['is_active'] ? 'pause' : 'play' ?>"></i> 
                                                    <?= $paper['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                </a></li>
                                                <li><a class="dropdown-item text-danger" href="?delete=<?= $paper['id'] ?>" onclick="return confirm('Are you sure you want to delete this question paper?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>

                                    <h6 class="mb-2"><?= htmlspecialchars($paper['title']) ?></h6>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-book"></i> <?= htmlspecialchars($paper['subject_code']) ?>
                                        </small>
                                    </div>

                                    <div class="d-flex gap-2 mb-2">
                                        
                                        <span class="exam-type-badge"><?= $paper['marks'] ?> marks</span>
                                        <span class="exam-type-badge"><?= $paper['duration_minutes'] ?>m</span>
                                    </div>

                                    <?php if ($paper['description']): ?>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars(substr($paper['description'], 0, 100)) ?><?= strlen($paper['description']) > 100 ? '...' : '' ?></p>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center">
                                        
                                        <small class="text-muted">
                                            <?= date('M j, Y', strtotime($paper['created_at'])) ?>
                                        </small>
                                    </div>

                                    <div class="mt-2">
                                        <small class="text-muted">By: <?= htmlspecialchars($paper['uploaded_by_name']) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4" aria-label="Question papers pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&grade=<?= urlencode($grade_filter) ?>&subject=<?= $subject_filter ?>">Previous</a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&grade=<?= urlencode($grade_filter) ?>&subject=<?= $subject_filter ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&grade=<?= urlencode($grade_filter) ?>&subject=<?= $subject_filter ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('fileInput');
    const uploadArea = document.querySelector('.upload-area');
    const fileInfo = document.getElementById('fileInfo');
    const subjectSelect = document.getElementById('subject_id');
    const gradeSelect = document.getElementById('grade_level');

    // File input change handler
    fileInput.addEventListener('change', function(e) {
        displayFileInfo(e.target.files[0]);
    });

    // Drag and drop handlers
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            displayFileInfo(files[0]);
        }
    });

    // Subject change handler - auto select grade
    subjectSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.dataset.grade) {
            gradeSelect.value = selectedOption.dataset.grade;
        }
    });

    function displayFileInfo(file) {
        if (file) {
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            fileInfo.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-file"></i> 
                    <strong>${file.name}</strong> (${fileSize} MB)
                </div>
            `;
            fileInfo.style.display = 'block';
        }
    }

    // Form validation
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        if (!fileInput.files.length) {
            e.preventDefault();
            alert('Please select a file to upload.');
            return;
        }

        const file = fileInput.files[0];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        if (file.size > maxSize) {
            e.preventDefault();
            alert('File size must be less than 10MB.');
            return;
        }

        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowedTypes.includes(file.type)) {
            e.preventDefault();
            alert('Only PDF, DOC, and DOCX files are allowed.');
            return;
        }
    });
});

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include('../includes/footer.php'); ?>