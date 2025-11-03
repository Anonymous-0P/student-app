<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

// Check if payment success data exists
if (!isset($_SESSION['payment_success'])) {
    header("Location: browse_exams.php");
    exit();
}

$payment_data = $_SESSION['payment_success'];
unset($_SESSION['payment_success']); // Clear the session data

$pageTitle = "Payment Successful";
$isIndexPage = false;
require_once('../includes/header.php');
?>

<link rel="stylesheet" href="../moderator/css/moderator-style.css">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Success Header -->
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="fas fa-check-circle fa-5x text-success"></i>
                </div>
                <h1 class="text-success mb-2">Payment Successful!</h1>
                <p class="lead text-muted">Your exam subjects have been purchased successfully</p>
            </div>

            <!-- Order Details Card -->
            <div class="dashboard-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>
                        Order Confirmation
                    </h5>
                </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Payment ID</h6>
                            <p class="mb-0 font-monospace"><?= htmlspecialchars($payment_data['payment_id']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Purchase Date</h6>
                            <p class="mb-0"><?= date('F j, Y \a\t g:i A', strtotime($payment_data['purchase_date'])) ?></p>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Total Amount</h6>
                            <h4 class="text-success mb-0">$<?= number_format($payment_data['total'], 2) ?></h4>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Items Purchased</h6>
                            <p class="mb-0"><?= count($payment_data['items']) ?> Exam Subject<?= count($payment_data['items']) > 1 ? 's' : '' ?></p>
                        </div>
                    </div>

                    <!-- Purchased Items -->
                    <h6 class="text-muted mb-3">Purchased Subjects</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Access Duration</th>
                                    <th class="text-end">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_data['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?= htmlspecialchars($item['code']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($item['name']) ?></td>
                                        <td>
                                            <i class="fas fa-calendar-alt text-muted me-1"></i>
                                            <?= $item['duration_days'] ?> days
                                        </td>
                                        <td class="text-end">₹<?= number_format($item['price'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-success">
                                    <th colspan="3">Total</th>
                                    <th class="text-end">₹<?= number_format($payment_data['total'], 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
            </div>

            <!-- Email Confirmation Notice -->
            <?php if (isset($_SESSION['email_sent']) && $_SESSION['email_sent']): ?>
            <div class="alert alert-success mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-envelope-circle-check fa-2x me-3"></i>
                    <div>
                        <h6 class="alert-heading mb-1">Confirmation Email Sent!</h6>
                        <p class="mb-0 small">A purchase confirmation email with your invoice has been sent to your registered email address.</p>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['email_sent']); endif; ?>

            <!-- Action Buttons -->
            <div class="text-center mb-4">
                <div class="d-grid gap-2 d-md-block">
                    <a href="dashboard.php" class="btn btn-primary btn-lg me-2">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                    <a href="view_invoice.php?payment_id=<?= urlencode($payment_data['payment_id']) ?>" class="btn btn-success btn-lg me-2" target="_blank">
                        <i class="fas fa-file-invoice"></i> View Invoice
                    </a>
                    <a href="browse_exams.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-books"></i> Browse More Exams
                    </a>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="dashboard-card" style="background: #f8f9fa;">
                <h6 class="mb-3">
                    <i class="fas fa-info-circle me-2" style="color: #0ea5e9;"></i>
                    Important Information
                </h6>
                <ul class="mb-0">
                    <li><strong>Email Confirmation:</strong> Check your email for the purchase confirmation and detailed invoice.</li>
                    <li><strong>Access Duration:</strong> Your access to each subject will expire after the specified duration period.</li>
                    <li><strong>Receipt:</strong> Keep your payment ID for future reference and support queries.</li>
                    <li><strong>Support:</strong> If you have any questions or issues, please contact our support team.</li>
                    <li><strong>Refund Policy:</strong> Refunds are available within 7 days of purchase under certain conditions.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.fa-check-circle {
    animation: bounce 1s ease-in-out;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-10px);
    }
    60% {
        transform: translateY(-5px);
    }
}

.card {
    border: none;
    border-radius: 10px;
}

.table th {
    border-top: none;
}

.font-monospace {
    background-color: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.9em;
}
</style>

<?php require_once('../includes/footer.php'); ?>