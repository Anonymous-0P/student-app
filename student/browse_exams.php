<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in (but allow browsing without login)
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'student';
$student_id = $is_logged_in ? $_SESSION['user_id'] : null;

// Check for access denied message
$access_denied = isset($_GET['access_denied']) && $_GET['access_denied'] == '1';
$subject_id_for_access = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;

// Handle add to cart
if (isset($_POST['add_to_cart']) && $is_logged_in) {
    $subject_id = (int)$_POST['subject_id'];
    
    // Check if subject exists and get its price
    $subject_check = $conn->prepare("SELECT id, price FROM subjects WHERE id = ? AND is_active = 1");
    $subject_check->bind_param("i", $subject_id);
    $subject_check->execute();
    $subject_result = $subject_check->get_result();
    
    if ($subject_result->num_rows > 0) {
        $subject = $subject_result->fetch_assoc();
        
        // Check if student already purchased this subject
        $purchased_check = $conn->prepare("SELECT id FROM purchased_subjects WHERE student_id = ? AND subject_id = ? AND status = 'active'");
        $purchased_check->bind_param("ii", $student_id, $subject_id);
        $purchased_check->execute();
        
        if ($purchased_check->get_result()->num_rows > 0) {
            $error = "You have already purchased this subject!";
        } else {
            // Add to cart (or update if already in cart)
            $cart_stmt = $conn->prepare("INSERT INTO cart (student_id, subject_id, price) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), added_at = CURRENT_TIMESTAMP");
            $cart_stmt->bind_param("iid", $student_id, $subject_id, $subject['price']);
            
            if ($cart_stmt->execute()) {
                $success = "Subject added to cart successfully!";
            } else {
                $error = "Error adding subject to cart.";
            }
        }
    } else {
        $error = "Subject not found.";
    }
}

// Get filter parameters
$department_filter = $_GET['department'] ?? '';
$year_filter = $_GET['year'] ?? '';
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

if (!empty($year_filter)) {
    $query .= " AND s.year = ?";
    $params[] = (int)$year_filter;
    $types .= 'i';
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

$query .= " ORDER BY s.department, s.year, s.code";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$subjects = $stmt->get_result();

// Get departments for filter
$departments = $conn->query("SELECT DISTINCT department FROM subjects WHERE is_active = 1 AND department IS NOT NULL ORDER BY department");

// Get cart count for logged in user
$cart_count = 0;
if ($is_logged_in) {
    $cart_stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE student_id = ?");
    $cart_stmt->bind_param("i", $student_id);
    $cart_stmt->execute();
    $cart_count = $cart_stmt->get_result()->fetch_assoc()['count'];
}

$pageTitle = "Browse Exams";
require_once('../includes/header.php');
?>

<div class="container">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-books text-primary"></i> Browse Available Exams</h2>
            <p class="text-muted">Explore our comprehensive collection of exam subjects and add them to your cart</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($is_logged_in): ?>
                <a href="cart.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> Cart 
                    <?php if ($cart_count > 0): ?>
                        <span class="badge bg-light text-dark"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <a href="../auth/login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login to Purchase
                </a>
            <?php endif; ?>
        </div>
    </div>

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
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-filter"></i> Filter Subjects</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php while ($dept = $departments->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($dept['department']) ?>" 
                                    <?= $department_filter === $dept['department'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <option value="">All Years</option>
                        <option value="1" <?= $year_filter === '1' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2" <?= $year_filter === '2' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3" <?= $year_filter === '3' ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4" <?= $year_filter === '4' ? 'selected' : '' ?>>4th Year</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Min Price</label>
                    <input type="number" name="price_min" class="form-control" step="0.01" 
                           value="<?= htmlspecialchars($price_min) ?>" placeholder="0.00">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Max Price</label>
                    <input type="number" name="price_max" class="form-control" step="0.01" 
                           value="<?= htmlspecialchars($price_max) ?>" placeholder="200.00">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" 
                               value="<?= htmlspecialchars($search) ?>" placeholder="Search subjects...">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Subjects Grid -->
    <div class="row">
        <?php if ($subjects->num_rows > 0): ?>
            <?php while ($subject = $subjects->fetch_assoc()): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 shadow-sm" data-subject-id="<?= $subject['id'] ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-primary"><?= htmlspecialchars($subject['code']) ?></h6>
                            <span class="badge bg-secondary"><?= htmlspecialchars($subject['department']) ?></span>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($subject['name']) ?></h5>
                            <p class="card-text text-muted flex-grow-1">
                                <?= htmlspecialchars($subject['description'] ?: 'No description available.') ?>
                            </p>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <?php if ($subject['year']): ?>
                                            <small class="text-muted">Year <?= $subject['year'] ?></small><br>
                                        <?php endif; ?>
                                        <?php if ($subject['semester']): ?>
                                            <small class="text-muted">Semester <?= $subject['semester'] ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="h5 text-primary mb-0">$<?= number_format($subject['price'], 2) ?></div>
                                        <small class="text-muted"><?= $subject['duration_days'] ?> days access</small>
                                    </div>
                                </div>

                                <?php if (!$is_logged_in): ?>
                                    <a href="../auth/login.php" class="btn btn-primary w-100">
                                        <i class="fas fa-sign-in-alt"></i> Login to Purchase
                                    </a>
                                <?php elseif ($subject['is_purchased']): ?>
                                    <button class="btn btn-success w-100" disabled>
                                        <i class="fas fa-check"></i> Already Purchased
                                    </button>
                                <?php elseif ($subject['in_cart']): ?>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-secondary flex-grow-1" disabled>
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
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-books fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No subjects found</h4>
                    <p class="text-muted">Try adjusting your filters or search criteria.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.card:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease-in-out;
}

.badge {
    font-size: 0.75em;
}

.highlight-subject {
    border: 2px solid #ffc107 !important;
    animation: highlight-pulse 2s ease-in-out 3;
}

@keyframes highlight-pulse {
    0%, 100% { box-shadow: 0 0 10px rgba(255, 193, 7, 0.5); }
    50% { box-shadow: 0 0 20px rgba(255, 193, 7, 0.8); }
}
</style>

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

<?php require_once('../includes/footer.php'); ?>