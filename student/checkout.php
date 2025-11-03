<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];

// Get user info
$user_stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$user_stmt->bind_param("i", $student_id);
$user_stmt->execute();
$user_info = $user_stmt->get_result()->fetch_assoc();

// Get cart items
$cart_query = "SELECT c.*, s.code, s.name, s.description, s.department, s.year, s.semester, s.duration_days
               FROM cart c
               JOIN subjects s ON c.subject_id = s.id
               WHERE c.student_id = ?
               ORDER BY c.added_at";

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

// Redirect if cart is empty
if (count($items) === 0) {
    header("Location: cart.php");
    exit();
}

$pageTitle = "Checkout";
$isIndexPage = false;
require_once('../includes/header.php');
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<?php require_once('includes/sidebar.php'); ?>

<div class="dashboard-layout">
    <div class="main-content">

<style>
/* Additional styles for checkout */
.checkout-content {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #ffffff;
    color: var(--text-dark);
    line-height: 1.6;
}

.checkout-breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 1rem;
}

.checkout-breadcrumb .breadcrumb-item a {
    color: var(--primary-color);
    text-decoration: none;
}

.checkout-breadcrumb .breadcrumb-item.active {
    color: var(--text-muted);
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.75rem;
    margin-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.order-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.order-item-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}

.order-item-code {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.order-item-price {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-dark);
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
    margin-top: 1rem;
    border-top: 2px solid var(--border-color);
    font-size: 1.125rem;
    font-weight: 600;
}

.security-notice {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.security-notice i {
    color: var(--success-color);
    font-size: 1.25rem;
}

.sticky-sidebar {
    position: sticky;
    top: 20px;
}
</style>

<div class="checkout-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
           
            <h1><i class="fas fa-credit-card"></i> Checkout</h1>
            <p>Complete your purchase to get instant access to your selected exams</p>
        </div>
    </div>

    <div class="container">

        <!-- Error Alert -->
        <?php if (isset($_SESSION['checkout_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['checkout_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['checkout_error']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Payment Form -->
            <div class="col-lg-8">
                <form id="checkoutForm" action="process_payment.php" method="POST">
                    <!-- Billing Information -->
                    <div class="dashboard-card mb-4">
                        <h5 class="mb-3"><i class="fas fa-user"></i> Billing Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="billing_name" class="form-control" 
                                       value="<?= htmlspecialchars($user_info['name']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="billing_email" class="form-control" 
                                       value="<?= htmlspecialchars($user_info['email']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="billing_phone" class="form-control" 
                                       value="<?= htmlspecialchars($user_info['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Student ID</label>
                                <input type="text" class="form-control" 
                                       value="STU-<?= str_pad($student_id, 6, '0', STR_PAD_LEFT) ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="dashboard-card mb-4">
                        <h5 class="mb-3"><i class="fas fa-credit-card"></i> Payment Method</h5>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle"></i>
                            <strong>Demo Mode:</strong> This is a dummy payment gateway for demonstration purposes. 
                            No real payment will be processed.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Card Number *</label>
                                <input type="text" name="card_number" class="form-control" 
                                       placeholder="1234 5678 9012 3456" 
                                       value="4242 4242 4242 4242" required>
                                <small class="text-muted">Use test card: 4242 4242 4242 4242</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cardholder Name *</label>
                                <input type="text" name="card_name" class="form-control" 
                                       value="<?= htmlspecialchars($user_info['name']) ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Expiry Month *</label>
                                <select name="card_month" class="form-select" required>
                                    <option value="">MM</option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" 
                                                <?= $m === 12 ? 'selected' : '' ?>>
                                            <?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Expiry Year *</label>
                                <select name="card_year" class="form-select" required>
                                    <option value="">YYYY</option>
                                    <?php for ($y = date('Y'); $y <= date('Y') + 10; $y++): ?>
                                        <option value="<?= $y ?>" <?= $y === (date('Y') + 2) ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">CVV *</label>
                                <input type="text" name="card_cvv" class="form-control" 
                                       placeholder="123" value="123" maxlength="4" required>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="dashboard-card mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="terms_agreed" class="form-check-input" id="termsCheck" required>
                            <label class="form-check-label" for="termsCheck">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> 
                                and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="marketing_consent" class="form-check-input" id="marketingCheck">
                            <label class="form-check-label" for="marketingCheck">
                                I would like to receive updates about new exams and special offers (optional)
                            </label>
                        </div>
                    </div>

                    <?php csrf_input(); ?>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="dashboard-card sticky-sidebar">
                    <h5 class="mb-3"><i class="fas fa-receipt"></i> Order Summary</h5>
                    
                    <!-- Items List -->
                    <div class="mb-3">
                        <?php foreach ($items as $item): ?>
                            <div class="order-item">
                                <div>
                                    <div class="order-item-title"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="order-item-code"><?= htmlspecialchars($item['code']) ?></div>
                                </div>
                                <div class="order-item-price">₹<?= number_format($item['price'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Totals -->
                    <div class="summary-row">
                        <span>Subtotal (<?= count($items) ?> items)</span>
                        <span>₹<?= number_format($total, 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax</span>
                        <span>₹0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Processing Fee</span>
                        <span>₹0.00</span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Total</span>
                        <span style="color: var(--primary-color);">₹<?= number_format($total, 2) ?></span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" form="checkoutForm" class="btn btn-primary">
                            <i class="fas fa-lock"></i> Complete Payment
                        </button>
                        <a href="cart.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Cart
                        </a>
                    </div>

                    <!-- Security Notice -->
                    <div class="security-notice">
                        <i class="fas fa-shield-alt"></i>
                        <small>Your payment information is secure and encrypted</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Acceptance of Terms</h6>
                <p>By purchasing and using our exam platform, you agree to these terms and conditions.</p>
                
                <h6>2. Access and Usage</h6>
                <p>Upon successful payment, you will receive access to the purchased subjects for the specified duration. Access is personal and non-transferable.</p>
                
                <h6>3. Refund Policy</h6>
                <p>Refunds are available within 7 days of purchase if you haven't accessed more than 20% of the content.</p>
                
                <h6>4. Academic Integrity</h6>
                <p>The content is for educational purposes only. Sharing accounts or content is strictly prohibited.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Data Collection</h6>
                <p>We collect personal information necessary for account creation and payment processing.</p>
                
                <h6>Data Usage</h6>
                <p>Your data is used to provide educational services, process payments, and improve our platform.</p>
                
                <h6>Data Protection</h6>
                <p>We use industry-standard security measures to protect your personal information.</p>
                
                <h6>Third Parties</h6>
                <p>We do not sell your personal information to third parties.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Format card number input
document.querySelector('input[name="card_number"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});

// Validate form before submission
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const termsCheck = document.getElementById('termsCheck');
    if (!termsCheck.checked) {
        e.preventDefault();
        alert('Please accept the Terms and Conditions to continue.');
        termsCheck.focus();
        return false;
    }
});
</script>

    </div>
</div>

<?php require_once('../includes/footer.php'); ?>