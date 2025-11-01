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

<div class="container">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-shopping-cart text-primary"></i> Shopping Cart</h2>
            <p class="text-muted">Review your selected exams before checkout</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="browse_exams.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Continue Shopping
            </a>
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

    <?php if (count($items) > 0): ?>
        <div class="row">
            <!-- Cart Items -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Cart Items (<?= count($items) ?>)</h5>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to clear all items from your cart?')">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash"></i> Clear Cart
                            </button>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($items as $item): ?>
                            <div class="border-bottom p-3">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6 class="mb-1 text-primary"><?= htmlspecialchars($item['code']) ?></h6>
                                        <h5 class="mb-2"><?= htmlspecialchars($item['name']) ?></h5>
                                        <p class="text-muted small mb-2">
                                            <?= htmlspecialchars($item['description'] ?: 'No description available.') ?>
                                        </p>
                                        <div class="small text-muted">
                                            <span class="badge bg-secondary me-2"><?= htmlspecialchars($item['department']) ?></span>
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
                                        <div class="h5 text-success mb-0">$<?= number_format($item['price'], 2) ?></div>
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
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card sticky-top">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-receipt"></i> Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal (<?= count($items) ?> items):</span>
                            <span>$<?= number_format($total, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax:</span>
                            <span>$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Discount:</span>
                            <span class="text-success">$0.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <h5>Total:</h5>
                            <h5 class="text-primary">$<?= number_format($total, 2) ?></h5>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="checkout.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-credit-card"></i> Proceed to Checkout
                            </a>
                            <a href="browse_exams.php" class="btn btn-outline-secondary">
                                <i class="fas fa-plus"></i> Add More Items
                            </a>
                        </div>

                        <div class="mt-3 p-3 bg-light rounded">
                            <h6 class="small text-muted mb-2">
                                <i class="fas fa-info-circle"></i> What's Included:
                            </h6>
                            <ul class="small mb-0 ps-3">
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
        </div>
    <?php else: ?>
        <!-- Empty Cart -->
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                    <h3 class="text-muted mb-3">Your cart is empty</h3>
                    <p class="text-muted mb-4">Browse our collection of exam subjects and add them to your cart.</p>
                    <a href="browse_exams.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-books"></i> Browse Exams
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.sticky-top {
    top: 20px;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s ease-in-out;
}
</style>

<?php require_once('../includes/footer.php'); ?>