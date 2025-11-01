<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];

// Get student info for grade filtering
$userStmt = $conn->prepare("SELECT name, email, year, course FROM users WHERE id = ?");
$userStmt->bind_param("i", $student_id);
$userStmt->execute();
$user_info = $userStmt->get_result()->fetch_assoc();

// Map student year to grade level
$grade_level = null;
if ($user_info['year']) {
    switch ($user_info['year']) {
        case 1:
            $grade_level = '10th';
            break;
        case 2:
            $grade_level = '11th';
            break;
        case 3:
            $grade_level = '12th';
            break;
    }
}

// Get filters
$subject_filter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$exam_type_filter = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$where_conditions = ["qp.is_active = 1"];
$params = [];
$types = '';

// Filter by student's grade level if available
if ($grade_level) {
    $where_conditions[] = "qp.grade_level = ?";
    $params[] = $grade_level;
    $types .= 's';
}

if ($subject_filter) {
    $where_conditions[] = "qp.subject_id = ?";
    $params[] = $subject_filter;
    $types .= 'i';
}

if ($exam_type_filter) {
    $where_conditions[] = "qp.exam_type = ?";
    $params[] = $exam_type_filter;
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(qp.title LIKE ? OR qp.description LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

$where_clause = implode(" AND ", $where_conditions);

// Get question papers with purchase access check
$papersQuery = "SELECT qp.*, s.code as subject_code, s.name as subject_name, s.price,
                (SELECT COUNT(*) FROM question_paper_downloads WHERE question_paper_id = qp.id AND student_id = ?) as downloaded_by_student,
                (SELECT COUNT(*) FROM question_paper_downloads WHERE question_paper_id = qp.id) as total_downloads,
                (SELECT COUNT(*) FROM purchased_subjects ps WHERE ps.subject_id = qp.subject_id AND ps.student_id = ? AND ps.status = 'active' AND ps.expiry_date > CURDATE()) as has_access
                FROM question_papers qp
                LEFT JOIN subjects s ON qp.subject_id = s.id
                WHERE $where_clause
                ORDER BY qp.created_at DESC";

$papersStmt = $conn->prepare($papersQuery);
$all_params = array_merge([$student_id, $student_id], $params);
$all_types = 'ii' . $types;
$papersStmt->bind_param($all_types, ...$all_params);
$papersStmt->execute();
$papers = $papersStmt->get_result();

// Get subjects for filter dropdown
$subjectsQuery = "SELECT DISTINCT s.id, s.code, s.name FROM subjects s 
                  INNER JOIN question_papers qp ON s.id = qp.subject_id 
                  WHERE qp.is_active = 1";
if ($grade_level) {
    $subjectsQuery .= " AND qp.grade_level = '$grade_level'";
}
$subjectsQuery .= " ORDER BY s.code";
$subjects = $conn->query($subjectsQuery);

$pageTitle = "Question Papers";
require_once('../includes/header.php');
?>

<style>
.paper-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid #667eea;
    height: 100%;
}

.paper-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.paper-card.downloaded {
    border-left-color: #28a745;
    background: linear-gradient(135deg, #f8fff9 0%, #ffffff 100%);
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

.btn-download {
    background: linear-gradient(135deg, #007bff, #0056b3);
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
    color: white;
}

.btn-downloaded {
    background: #6c757d;
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
}

.filter-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
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
                    <h1 class="h3 mb-2"><i class="fas fa-file-alt text-primary"></i> Question Papers</h1>
                    <p class="text-muted mb-0">View question papers for practice and preparation</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <?php
        $statsQuery = "SELECT 
            COUNT(*) as total_papers,
            COUNT(CASE WHEN exam_type = 'practice' THEN 1 END) as practice_papers,
            COUNT(CASE WHEN exam_type IN ('unit_test', 'mid_term', 'final') THEN 1 END) as exam_papers,
            (SELECT COUNT(*) FROM question_paper_downloads WHERE student_id = ?) as my_downloads
            FROM question_papers qp WHERE qp.is_active = 1";
        
        if ($grade_level) {
            $statsQuery .= " AND qp.grade_level = '$grade_level'";
        }
        
        $statsStmt = $conn->prepare($statsQuery);
        $statsStmt->bind_param("i", $student_id);
        $statsStmt->execute();
        $stats = $statsStmt->get_result()->fetch_assoc();
        ?>
        
        

    <!-- Filters -->
    <!-- <div class="row mb-4">
        <div class="col-12">
            <div class="filter-card fade-in" style="animation-delay: 0.5s;">
                <h6 class="mb-3"><i class="fas fa-filter text-primary"></i> Filter Papers</h6>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by title or description">
                    </div>
                    <div class="col-md-3">
                        <label for="subject" class="form-label">Subject</label>
                        <select class="form-select" id="subject" name="subject">
                            <option value="">All Subjects</option>
                            <?php while ($subject = $subjects->fetch_assoc()): ?>
                                <option value="<?= $subject['id'] ?>" <?= $subject_filter == $subject['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['code']) ?> - <?= htmlspecialchars($subject['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                   
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div> -->

    <!-- Question Papers List -->
    <div class="row mt-4">
        <?php if ($papers->num_rows === 0): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Question Papers Available</h5>
                    <p class="text-muted">Check back later for new papers or adjust your filters.</p>
                </div>
            </div>
        <?php else: ?>
            <?php $delay = 0.6; while ($paper = $papers->fetch_assoc()): $delay += 0.1; ?>
                <div class="col-md-6 col-lg-4 mb-4 fade-in" style="animation-delay: <?= $delay ?>s;">
                    <div class="paper-card <?= $paper['downloaded_by_student'] > 0 ? 'downloaded' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="grade-badge"><?= $paper['grade_level'] ?></span>
                           
                        </div>

                        <h5 class="mb-2"><?= htmlspecialchars($paper['title']) ?></h5>
                        
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-book"></i> <?= htmlspecialchars($paper['subject_code']) ?> - <?= htmlspecialchars($paper['subject_name']) ?>
                            </small>
                        </div>

                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <span class="exam-type-badge"><?= $paper['marks'] ?> marks</span>
                            <span class="exam-type-badge"><?= $paper['duration_minutes'] ?>m</span>
                        </div>

                        <?php if ($paper['description']): ?>
                            <p class="text-muted small mb-3"><?= htmlspecialchars(substr($paper['description'], 0, 100)) ?><?= strlen($paper['description']) > 100 ? '...' : '' ?></p>
                        <?php endif; ?>

                        <?php if ($paper['instructions']): ?>
                            <div class="alert alert-light py-2 px-3 mb-3">
                                <small><strong>Instructions:</strong> <?= htmlspecialchars(substr($paper['instructions'], 0, 80)) ?><?= strlen($paper['instructions']) > 80 ? '...' : '' ?></small>
                            </div>
                        <?php endif; ?>

                       

                        <div class="d-grid">
                            <?php if ($paper['has_access'] > 0): ?>
                                <a href="pdf_viewer.php?paper_id=<?= $paper['id'] ?>" class="btn-download text-decoration-none">
                                    <i class="fas fa-eye"></i> View Paper
                                </a>
                                <div class="mt-2">
                                    <small class="text-success">
                                        <i class="fas fa-check-circle"></i> Access granted
                                    </small>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-outline-danger w-100" onclick="showPurchaseModal('<?= htmlspecialchars($paper['subject_code']) ?>', '<?= htmlspecialchars($paper['subject_name']) ?>', <?= $paper['subject_id'] ?>, <?= $paper['price'] ?? 0 ?>)">
                                    <i class="fas fa-lock"></i> Purchase Required
                                </button>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-shopping-cart"></i> Price: $<?= number_format($paper['price'] ?? 0, 2) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <!-- Help Text -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> How to use question papers:</h6>
                <ul class="mb-0">
                    <li><strong>Purchase Access:</strong> You need to purchase a subject to access its question papers and attend exams</li>
                    <li><strong>View Papers:</strong> Once purchased, click "View Paper" to access question papers</li>
                    <li><strong>Practice:</strong> Solve questions within the time limit and upload your answer sheets</li>
                    <li><strong>Get Evaluated:</strong> Wait for evaluation and feedback from your evaluator</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="purchaseModalLabel">
                    <i class="fas fa-shopping-cart text-primary"></i> Purchase Required
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-lock fa-3x text-warning mb-3"></i>
                    <h6>Access Restricted</h6>
                    <p class="text-muted">You need to purchase this subject to access question papers and attend exams.</p>
                </div>
                
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h6 class="mb-1" id="subjectDetails">Subject Name</h6>
                                <small class="text-muted" id="subjectCode">Subject Code</small>
                            </div>
                            <div class="col-4 text-end">
                                <div class="h5 text-success mb-0" id="subjectPrice">$0.00</div>
                                <small class="text-muted">Price</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>What you get:</strong> Access to all question papers, practice tests, and ability to attend exams for this subject.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="browseExamsBtn" class="btn btn-primary">
                    <i class="fas fa-shopping-cart me-2"></i>Go to Purchase
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (!alert.classList.contains('alert-info')) {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.classList.remove('show');
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 150);
                }
            }, 5000);
        }
    });
});

// Show purchase modal
function showPurchaseModal(subjectCode, subjectName, subjectId, price) {
    document.getElementById('subjectCode').textContent = subjectCode;
    document.getElementById('subjectDetails').textContent = subjectName;
    document.getElementById('subjectPrice').textContent = '$' + parseFloat(price).toFixed(2);
    document.getElementById('browseExamsBtn').href = 'browse_exams.php?subject_id=' + subjectId;
    
    const modal = new bootstrap.Modal(document.getElementById('purchaseModal'));
    modal.show();
}
</script>

<?php require_once('../includes/footer.php'); ?>