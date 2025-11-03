<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];

// Get invoice file path from query parameter
if (!isset($_GET['payment_id'])) {
    header("Location: dashboard.php");
    exit();
}

$payment_id = $_GET['payment_id'];

// Verify this payment belongs to the logged-in student
$verifyStmt = $conn->prepare("SELECT * FROM payment_transactions WHERE payment_id = ? AND student_id = ?");
$verifyStmt->bind_param("si", $payment_id, $student_id);
$verifyStmt->execute();
$payment = $verifyStmt->get_result()->fetch_assoc();

if (!$payment) {
    header("Location: dashboard.php");
    exit();
}

// Look for invoice file
$invoiceDir = '../uploads/invoices/';
$invoiceFiles = glob($invoiceDir . 'invoice_' . $payment_id . '*');

if (empty($invoiceFiles)) {
    // Generate invoice on-the-fly if not found
    require_once('../includes/mail_helper.php');
    
    // Get user info
    $userStmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $userStmt->bind_param("i", $student_id);
    $userStmt->execute();
    $userInfo = $userStmt->get_result()->fetch_assoc();
    
    // Get purchased items for this payment
    $itemsStmt = $conn->prepare("
        SELECT ps.*, s.code, s.name, s.duration_days 
        FROM purchased_subjects ps 
        JOIN subjects s ON ps.subject_id = s.id 
        WHERE ps.payment_id = ?
    ");
    $itemsStmt->bind_param("s", $payment_id);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = [
            'name' => $item['name'],
            'code' => $item['code'],
            'duration_days' => $item['duration_days'],
            'price' => $item['price_paid']
        ];
    }
    
    $invoicePath = generateInvoicePDF($payment_id, $userInfo, $items, $payment['total_amount'], []);
    
    if ($invoicePath && file_exists($invoicePath)) {
        $invoiceFiles = [$invoicePath];
    }
}

if (empty($invoiceFiles)) {
    echo "<div style='text-align: center; padding: 50px;'>";
    echo "<h3>Invoice not found</h3>";
    echo "<p>Unable to generate invoice. Please contact support.</p>";
    echo "<a href='dashboard.php' class='btn btn-primary'>Back to Dashboard</a>";
    echo "</div>";
    exit();
}

$invoiceFile = $invoiceFiles[0];
$fileExtension = pathinfo($invoiceFile, PATHINFO_EXTENSION);

// If HTML invoice, display it
if ($fileExtension === 'html') {
    $invoiceContent = file_get_contents($invoiceFile);
    
    // Add print button
    echo '<div style="position: fixed; top: 20px; right: 20px; z-index: 1000;">';
    echo '<button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Invoice</button>';
    echo '<a href="dashboard.php" class="btn btn-secondary ms-2"><i class="fas fa-arrow-left"></i> Back</a>';
    echo '</div>';
    
    echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<style>@media print { .btn { display: none !important; } }</style>';
    
    echo $invoiceContent;
} else if ($fileExtension === 'pdf') {
    // Display PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="invoice_' . $payment_id . '.pdf"');
    readfile($invoiceFile);
}
?>
