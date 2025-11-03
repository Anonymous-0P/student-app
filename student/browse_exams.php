<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in (but allow browsing without login)
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'student';
$student_id = $is_logged_in ? $_SESSION['user_id'] : null;

// Initialize session cart if not exists
if (!isset($_SESSION['guest_cart'])) {
    $_SESSION['guest_cart'] = [];
}

// Check for access denied message
$access_denied = isset($_GET['access_denied']) && $_GET['access_denied'] == '1';
$subject_id_for_access = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $subject_id = (int)$_POST['subject_id'];
    
    // Check if subject exists and get its price
    $subject_check = $conn->prepare("SELECT id, code, name, price FROM subjects WHERE id = ? AND is_active = 1");
    $subject_check->bind_param("i", $subject_id);
    $subject_check->execute();
    $subject_result = $subject_check->get_result();
    
    if ($subject_result->num_rows > 0) {
        $subject = $subject_result->fetch_assoc();
        
        if ($is_logged_in) {
            // Check if student already purchased this subject
            $purchased_check = $conn->prepare("SELECT id FROM purchased_subjects WHERE student_id = ? AND subject_id = ? AND status = 'active'");
            $purchased_check->bind_param("ii", $student_id, $subject_id);
            $purchased_check->execute();
            
            if ($purchased_check->get_result()->num_rows > 0) {
                $error = "You have already purchased this subject!";
            } else {
                // Add to database cart for logged in users
                $cart_stmt = $conn->prepare("INSERT INTO cart (student_id, subject_id, price) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), added_at = CURRENT_TIMESTAMP");
                $cart_stmt->bind_param("iid", $student_id, $subject_id, $subject['price']);
                
                if ($cart_stmt->execute()) {
                    $success = "Subject added to cart successfully!";
                } else {
                    $error = "Error adding subject to cart.";
                }
            }
        } else {
            // Add to session cart for guest users
            if (!isset($_SESSION['guest_cart'][$subject_id])) {
                $_SESSION['guest_cart'][$subject_id] = [
                    'subject_id' => $subject['id'],
                    'code' => $subject['code'],
                    'name' => $subject['name'],
                    'price' => $subject['price'],
                    'added_at' => date('Y-m-d H:i:s')
                ];
                $success = "Subject added to cart successfully!";
            } else {
                $error = "This subject is already in your cart!";
            }
        }
    } else {
        $error = "Subject not found.";
    }
}

// Get filter parameters
$department_filter = $_GET['department'] ?? '';
$grade_filter = $_GET['grade'] ?? '';
$price_min = $_GET['price_min'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for subjects
$query = "SELECT s.*, 
          CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as is_purchased,
          CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END as in_cart
          FROM subjects s 
          LEFT JOIN purchased_subjects ps ON s.id = ps.subject_id AND ps.student_id = ? AND ps.status = 'active'
          LEFT JOIN cart c ON s.id = c.subject_id AND c.student_id = ?
          WHERE s.is_active = 1";

$params = [$student_id, $student_id];
$types = 'ii';

if (!empty($department_filter)) {
    $query .= " AND s.department = ?";
    $params[] = $department_filter;
    $types .= 's';
}

if (!empty($grade_filter)) {
    $query .= " AND s.grade_level = ?";
    $params[] = $grade_filter;
    $types .= 's';
}

if (!empty($price_min)) {
    $query .= " AND s.price >= ?";
    $params[] = (float)$price_min;
    $types .= 'd';
}

if (!empty($price_max)) {
    $query .= " AND s.price <= ?";
    $params[] = (float)$price_max;
    $types .= 'd';
}

if (!empty($search)) {
    $query .= " AND (s.name LIKE ? OR s.code LIKE ? OR s.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$query .= " ORDER BY s.department, s.grade_level, s.code";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$subjects = $stmt->get_result();

// Get departments for filter
$departments = $conn->query("SELECT DISTINCT department FROM subjects WHERE is_active = 1 AND department IS NOT NULL ORDER BY department");

// Get cart count
$cart_count = 0;
if ($is_logged_in) {
    $cart_stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE student_id = ?");
    $cart_stmt->bind_param("i", $student_id);
    $cart_stmt->execute();
    $cart_count = $cart_stmt->get_result()->fetch_assoc()['count'];
} else {
    $cart_count = count($_SESSION['guest_cart']);
}

$pageTitle = "Browse Exams";
$isIndexPage = false;
require_once('../includes/header.php');
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<?php require_once('includes/sidebar.php'); ?>

<div class="dashboard-layout">
    <div class="main-content">

<style>
/* Additional styles for browse exams */
.browse-exams-content {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #ffffff;
    color: var(--text-dark);
    line-height: 1.6;
}

.subject-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    height: 100%;
    transition: all 0.2s;
}

.subject-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.subject-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.subject-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.75rem;
}

.subject-description {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
    line-height: 1.5;
    flex-grow: 1;
}

.subject-footer {
    margin-top: auto;
}

.subject-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.subject-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
}

.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.empty-subjects {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-subjects i {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.highlight-subject {
    border: 2px solid var(--warning-color) !important;
    animation: highlight-pulse 2s ease-in-out 3;
}

@keyframes highlight-pulse {
    0%, 100% { box-shadow: 0 0 10px rgba(245, 158, 11, 0.5); }
    50% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.8); }
}

@media (max-width: 768px) {
    .subjects-grid {
        grid-template-columns: 1fr;
    }
    
    /* Mobile filter buttons - 2 buttons per row */
    .dashboard-card .d-flex.gap-2 {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
    }
    
    /* Completely override btn-group behavior on mobile */
    .dashboard-card .btn-group {
        display: contents !important;
    }
    
    .dashboard-card .btn-group .btn {
        font-size: 0.75rem;
        padding: 0.625rem 0.75rem;
        white-space: nowrap;
        width: calc(50% - 0.25rem) !important;
        flex: 0 0 calc(50% - 0.25rem) !important;
        border-radius: 0.375rem !important;
        border: 1px solid !important;
        margin: 0 !important;
    }
    
    .dashboard-card > .d-flex > a.btn-primary {
        font-size: 0.75rem;
        padding: 0.625rem 0.75rem;
        white-space: nowrap;
        width: calc(50% - 0.25rem) !important;
        flex: 0 0 calc(50% - 0.25rem) !important;
        border-radius: 0.375rem !important;
    }
}
</style>

<div class="browse-exams-content">
    <!-- Page Header -->

    <div class="container">

        <!-- Alerts -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($access_denied): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-lock"></i> <strong>Access Denied!</strong> You need to purchase this subject to access question papers and attend exams.
                <?php if ($subject_id_for_access): ?>
                    <br><small>Please purchase the subject below to gain access.</small>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="dashboard-card mt-2">
            <div class="d-flex gap-2 w-100">
                <div class="btn-group flex-grow-1" role="group">
                    <a href="browse_exams.php" class="btn <?= $grade_filter === '' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-list"></i> All Grades
                    </a>
                    <a href="browse_exams.php?grade=10th" class="btn <?= $grade_filter === '10th' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-graduation-cap"></i> 10th Standard
                    </a>
                    <a href="browse_exams.php?grade=12th" class="btn <?= $grade_filter === '12th' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-user-graduate"></i> 12th Standard
                    </a>
                </div>
                <a href="cart.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> Cart 
                    <?php if ($cart_count > 0): ?>
                        <span class="badge bg-light text-primary"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Subjects Grid -->
        <div class="dashboard-card">
            <?php if ($subjects->num_rows > 0): ?>
                <div class="subjects-grid">
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <div class="subject-card" data-subject-id="<?= $subject['id'] ?>">
                            <div class="d-flex flex-column h-100">
                                <h6 class="subject-title"><?= htmlspecialchars($subject['name']) ?></h6>
                                
                                <?php if ($subject['description']): ?>
                                    <p class="subject-description">
                                        <?= htmlspecialchars($subject['description']) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="subject-footer">
                                    <div class="subject-info">
                                        <div>
                                            <?php if ($subject['grade_level']): ?>
                                                <span class="badge bg-primary" style="background: var(--primary-color) !important; color: white !important;">
                                                    <?= htmlspecialchars($subject['grade_level']) ?> Standard
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($subject['semester']): ?>
                                                <small class="text-muted d-block mt-1">Semester <?= $subject['semester'] ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="subject-price">₹<?= number_format($subject['price'], 2) ?></div>
                                    </div>

                                    <?php if ($is_logged_in && $subject['is_purchased']): ?>
                                        <button class="btn btn-outline-success w-100" disabled>
                                            <i class="fas fa-check"></i> Already Purchased
                                        </button>
                                    <?php elseif ($is_logged_in && $subject['in_cart']): ?>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-outline-secondary flex-grow-1" disabled>
                                                <i class="fas fa-shopping-cart"></i> In Cart
                                            </button>
                                            <a href="cart.php" class="btn btn-primary">
                                                <i class="fas fa-arrow-right"></i>
                                            </a>
                                        </div>
                                    <?php elseif (!$is_logged_in && isset($_SESSION['guest_cart'][$subject['id']])): ?>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-outline-secondary flex-grow-1" disabled>
                                                <i class="fas fa-shopping-cart"></i> In Cart
                                            </button>
                                            <a href="cart.php" class="btn btn-primary">
                                                <i class="fas fa-arrow-right"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline w-100">
                                            <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                            <button type="submit" name="add_to_cart" class="btn btn-primary w-100">
                                                <i class="fas fa-cart-plus"></i> Add to Cart
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-subjects">
                    <i class="fas fa-book-open"></i>
                    <h5>No subjects found</h5>
                    <p class="text-muted">Try adjusting your filters or search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Highlight specific subject if access was denied
    <?php if ($access_denied && $subject_id_for_access): ?>
    const subjectCard = document.querySelector('[data-subject-id="<?= $subject_id_for_access ?>"]');
    if (subjectCard) {
        subjectCard.classList.add('highlight-subject');
        subjectCard.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
        
        // Remove highlight after animation
        setTimeout(() => {
            subjectCard.classList.remove('highlight-subject');
        }, 6000);
    }
    <?php endif; ?>
});
</script>

    </div>
</div>

<?php require_once('../includes/footer.php'); ?>