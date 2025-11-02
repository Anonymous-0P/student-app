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
require_once('../includes/header.php');
?>

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
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>
                        Order Confirmation
                    </h5>
                </div>
                <div class="card-body">
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
            </div>

            <!-- What's Next -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-arrow-right text-primary me-2"></i>
                        What's Next?
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-tachometer-alt fa-2x text-primary me-3"></i>
                                </div>
                                <div>
                                    <h6>Access Your Dashboard</h6>
                                    <p class="text-muted mb-0">Your purchased subjects are now available on your student dashboard with "Active" status.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-file-alt fa-2x text-primary me-3"></i>
                                </div>
                                <div>
                                    <h6>Start Taking Exams</h6>
                                    <p class="text-muted mb-0">Begin practicing with our comprehensive question banks and mock tests.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-chart-line fa-2x text-primary me-3"></i>
                                </div>
                                <div>
                                    <h6>Track Progress</h6>
                                    <p class="text-muted mb-0">Monitor your performance with detailed analytics and progress reports.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-envelope fa-2x text-primary me-3"></i>
                                </div>
                                <div>
                                    <h6>Email Confirmation</h6>
                                    <p class="text-muted mb-0">A confirmation email with your receipt has been sent to your registered email address.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center mb-4">
                <div class="d-grid gap-2 d-md-block">
                    <a href="dashboard.php" class="btn btn-primary btn-lg me-2">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                    <a href="browse_exams.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-books"></i> Browse More Exams
                    </a>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="card bg-light border-0">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="fas fa-info-circle text-info me-2"></i>
                        Important Information
                    </h6>
                    <ul class="mb-0">
                        <li><strong>Access Duration:</strong> Your access to each subject will expire after the specified duration period.</li>
                        <li><strong>Receipt:</strong> Keep your payment ID for future reference and support queries.</li>
                        <li><strong>Support:</strong> If you have any questions or issues, please contact our support team.</li>
                        <li><strong>Refund Policy:</strong> Refunds are available within 7 days of purchase under certain conditions.</li>
                    </ul>
                </div>
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