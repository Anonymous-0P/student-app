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
                ORDER BY has_access DESC, qp.created_at DESC";

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

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<style>
/* Additional custom styles for question papers page */
.question-papers-content {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #ffffff;
    color: var(--text-dark);
    line-height: 1.6;
}

.paper-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
    height: 100%;
}

.paper-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.paper-card.downloaded {
    border-left: 3px solid var(--success-color);
}

.paper-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.paper-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.paper-subject {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}

.paper-meta {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

/* Override badge colors to have white text */
.badge.bg-primary,
.badge.bg-success,
.badge.bg-warning,
.badge.bg-danger,
.badge.bg-info,
.badge.bg-secondary {
    color: white !important;
}

.badge.bg-primary { 
    background: var(--primary-color) !important;
}

.badge.bg-success { 
    background: var(--success-color) !important;
}

.badge.bg-warning { 
    background: var(--warning-color) !important;
}

.badge.bg-danger { 
    background: var(--danger-color) !important;
}

.badge.bg-info { 
    background: #0ea5e9 !important;
}

.badge.bg-secondary { 
    background: #6b7280 !important;
}

.paper-description {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    line-height: 1.5;
}

.paper-instructions {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0.75rem;
    font-size: 0.8125rem;
    margin-bottom: 1rem;
}

.paper-instructions strong {
    color: var(--text-dark);
}

.paper-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-icon-wrapper.primary {
    background: #dbeafe;
    color: var(--primary-color);
}

.stat-icon-wrapper.success {
    background: #dcfce7;
    color: var(--success-color);
}

.stat-icon-wrapper.warning {
    background: #fef3c7;
    color: var(--warning-color);
}

.stat-icon-wrapper.info {
    background: #dbeafe;
    color: #0ea5e9;
}

.stat-details h4 {
    font-size: 1.75rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.stat-details small {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.papers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.empty-papers {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-papers i {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.empty-papers h5 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.empty-papers p {
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .papers-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-cards {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="question-papers-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-file-alt"></i> Question Papers</h1>
                    <p>View question papers for practice and preparation</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
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
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fas fa-file-alt"></i> Available Question Papers</h5>
            </div>
            
            <?php if ($papers->num_rows === 0): ?>
                <div class="empty-papers">
                    <i class="fas fa-file-alt"></i>
                    <h5>No Question Papers Available</h5>
                    <p>Check back later for new papers or adjust your filters.</p>
                </div>
            <?php else: ?>
                <div class="papers-grid">
                    <?php while ($paper = $papers->fetch_assoc()): ?>
                        <div class="paper-card">
                            <div class="paper-header">
                                <span class="badge bg-primary"><?= htmlspecialchars($paper['grade_level']) ?></span>
                            </div>

                            <h6 class="paper-title"><?= htmlspecialchars($paper['title']) ?></h6>
                            
                            <div class="paper-subject">
                                <i class="fas fa-book"></i> <?= htmlspecialchars($paper['subject_code']) ?> - <?= htmlspecialchars($paper['subject_name']) ?>
                            </div>

                            <div class="paper-meta">
                                <span class="badge bg-secondary"><?= $paper['marks'] ?> marks</span>
                                <span class="badge bg-secondary"><?= $paper['duration_minutes'] ?>m</span>
                            </div>

                            <?php if ($paper['description']): ?>
                                <p class="paper-description"><?= htmlspecialchars(substr($paper['description'], 0, 100)) ?><?= strlen($paper['description']) > 100 ? '...' : '' ?></p>
                            <?php endif; ?>

                            <?php if ($paper['instructions']): ?>
                                <div class="paper-instructions">
                                    <strong>Instructions:</strong> <?= htmlspecialchars(substr($paper['instructions'], 0, 80)) ?><?= strlen($paper['instructions']) > 80 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>

                            <div class="paper-actions">
                                <?php if ($paper['has_access'] > 0): ?>
                                    <a href="pdf_viewer.php?paper_id=<?= $paper['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Paper
                                    </a>
                                    <small class="text-success text-center">
                                        <i class="fas fa-check-circle"></i> Access granted
                                    </small>
                                <?php else: ?>
                                    <button class="btn btn-outline-danger" onclick="showPurchaseModal('<?= htmlspecialchars($paper['subject_code']) ?>', '<?= htmlspecialchars($paper['subject_name']) ?>', <?= $paper['subject_id'] ?>, <?= $paper['price'] ?? 0 ?>)">
                                        <i class="fas fa-lock"></i> Purchase Required
                                    </button>
                                    <small class="text-muted text-center">
                                        <i class="fas fa-shopping-cart"></i> Price: ₹<?= number_format($paper['price'] ?? 0, 2) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: 1px solid var(--border-color); border-radius: 8px;">
            <div class="modal-header" style="background: white; border-bottom: 1px solid var(--border-color);">
                <h5 class="modal-title" id="purchaseModalLabel" style="font-weight: 600; color: var(--text-dark);">
                    <i class="fas fa-shopping-cart" style="color: var(--primary-color);"></i> Purchase Required
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div class="text-center mb-4">
                    <div style="width: 64px; height: 64px; margin: 0 auto 1rem; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-lock" style="font-size: 2rem; color: var(--warning-color);"></i>
                    </div>
                    <h6 style="font-weight: 600; color: var(--text-dark); margin-bottom: 0.5rem;">Access Restricted</h6>
                    <p class="text-muted" style="font-size: 0.875rem;">You need to purchase this subject to access question papers and attend exams.</p>
                </div>
                
                <div style="background: var(--bg-light); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1" id="subjectDetails" style="font-weight: 600; color: var(--text-dark);">Subject Name</h6>
                            <small class="text-muted" id="subjectCode">Subject Code</small>
                        </div>
                        <div class="text-end">
                            <div class="h5 mb-0" id="subjectPrice" style="color: var(--success-color); font-weight: 700;">₹0.00</div>
                            <small class="text-muted">Price</small>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3" style="font-size: 0.875rem;">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>What you get:</strong> Access to all question papers, practice tests, and ability to attend exams for this subject.
                </div>
            </div>
            <div class="modal-footer" style="background: var(--bg-light); border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="browseExamsBtn" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> Go to Purchase
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
    document.getElementById('subjectPrice').textContent = '₹' + parseFloat(price).toFixed(2);
    document.getElementById('browseExamsBtn').href = 'browse_exams.php?subject_id=' + subjectId;
    
    const modal = new bootstrap.Modal(document.getElementById('purchaseModal'));
    modal.show();
}
</script>

<?php require_once('../includes/footer.php'); ?>