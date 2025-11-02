<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];

// Handle cart updates
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'remove' && isset($_POST['subject_id'])) {
        $subject_id = (int)$_POST['subject_id'];
        $stmt = $conn->prepare("DELETE FROM cart WHERE student_id = ? AND subject_id = ?");
        $stmt->bind_param("ii", $student_id, $subject_id);
        if ($stmt->execute()) {
            $success = "Item removed from cart successfully!";
        } else {
            $error = "Error removing item from cart.";
        }
    } elseif ($_POST['action'] === 'clear') {
        $stmt = $conn->prepare("DELETE FROM cart WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            $success = "Cart cleared successfully!";
        } else {
            $error = "Error clearing cart.";
        }
    }
}

// Get cart items
$cart_query = "SELECT c.*, s.code, s.name, s.description, s.department, s.year, s.semester, s.duration_days
               FROM cart c
               JOIN subjects s ON c.subject_id = s.id
               WHERE c.student_id = ?
               ORDER BY c.added_at DESC";

$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $student_id);
$cart_stmt->execute();
$cart_items = $cart_stmt->get_result();

// Calculate total
$total = 0;
$items = [];
while ($item = $cart_items->fetch_assoc()) {
    $items[] = $item;
    $total += $item['price'];
}

$pageTitle = "Shopping Cart";
require_once('../includes/header.php');
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<style>
/* Additional styles for cart */
.cart-content {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #ffffff;
    color: var(--text-dark);
    line-height: 1.6;
}

.cart-item {
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem 0;
}

.cart-item:last-child {
    border-bottom: none;
}

.cart-item-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}

.cart-item-code {
    font-size: 0.875rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.cart-item-description {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.cart-item-meta {
    font-size: 0.8125rem;
    color: var(--text-muted);
}

.cart-price {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--success-color);
}

.empty-cart {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-cart i {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    padding-top: 1rem;
    border-top: 2px solid var(--border-color);
    font-size: 1.125rem;
    font-weight: 600;
}

.info-box {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.info-box h6 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.info-box ul {
    font-size: 0.8125rem;
    color: var(--text-muted);
    margin-bottom: 0;
    padding-left: 1.25rem;
}

.sticky-sidebar {
    position: sticky;
    top: 20px;
}
</style>

<div class="cart-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>
                    <p>Review your selected exams before checkout</p>
                </div>
                <div>
                    <a href="browse_exams.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </div>

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

        <?php if (count($items) > 0): ?>
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Cart Items (<?= count($items) ?>)</h5>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to clear all items from your cart?')">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i> Clear Cart
                                </button>
                            </form>
                        </div>
                        
                        <?php foreach ($items as $item): ?>
                            <div class="cart-item">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="cart-item-code"><?= htmlspecialchars($item['code']) ?></div>
                                        <h6 class="cart-item-title"><?= htmlspecialchars($item['name']) ?></h6>
                                        <p class="cart-item-description">
                                            <?= htmlspecialchars($item['description'] ?: 'No description available.') ?>
                                        </p>
                                        <div class="cart-item-meta">
                                            <span class="badge bg-secondary" style="background: #6b7280 !important; color: white !important;"><?= htmlspecialchars($item['department']) ?></span>
                                            <?php if ($item['year']): ?>
                                                <span class="me-2">Year <?= $item['year'] ?></span>
                                            <?php endif; ?>
                                            <?php if ($item['semester']): ?>
                                                <span class="me-2">Sem <?= $item['semester'] ?></span>
                                            <?php endif; ?>
                                            <span><?= $item['duration_days'] ?> days access</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="cart-price">₹<?= number_format($item['price'], 2) ?></div>
                                        <small class="text-muted">Added <?= date('M j, Y', strtotime($item['added_at'])) ?></small>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this item from cart?')">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="subject_id" value="<?= $item['subject_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="dashboard-card sticky-sidebar">
                        <h5 class="mb-3"><i class="fas fa-receipt"></i> Order Summary</h5>
                        
                        <div class="summary-row">
                            <span>Subtotal (<?= count($items) ?> items):</span>
                            <span>₹<?= number_format($total, 2) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Tax:</span>
                            <span>₹0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Discount:</span>
                            <span class="text-success">₹0.00</span>
                        </div>
                        
                        <div class="summary-total">
                            <span>Total:</span>
                            <span style="color: var(--primary-color);">₹<?= number_format($total, 2) ?></span>
                        </div>
                        
                        <div class="d-grid gap-2 mt-3">
                            <a href="checkout.php" class="btn btn-primary">
                                <i class="fas fa-credit-card"></i> Proceed to Checkout
                            </a>
                            <a href="browse_exams.php" class="btn btn-outline-secondary">
                                <i class="fas fa-plus"></i> Add More Items
                            </a>
                        </div>

                        <div class="info-box">
                            <h6><i class="fas fa-info-circle"></i> What's Included:</h6>
                            <ul>
                                <li>Full access to exam questions</li>
                                <li>Practice tests and mock exams</li>
                                <li>Detailed solutions and explanations</li>
                                <li>Progress tracking and analytics</li>
                                <li><?= $items[0]['duration_days'] ?? 365 ?> days of unlimited access</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty Cart -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p class="text-muted mb-4">Browse our collection of exam subjects and add them to your cart.</p>
                        <a href="browse_exams.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-book-open"></i> Browse Exams
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>