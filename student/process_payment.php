<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];
$error = null;
$success = false;

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid security token. Please try again.';
} else {
    // Validate required fields
    $required_fields = ['billing_name', 'billing_email', 'card_number', 'card_name', 'card_month', 'card_year', 'card_cvv'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $error = 'Please fill in all required fields.';
            break;
        }
    }

    // Check terms agreement
    if (!isset($_POST['terms_agreed'])) {
        $error = 'You must agree to the Terms and Conditions.';
    }

    if (!$error) {
        // Get cart items
        $cart_query = "SELECT c.*, s.code, s.name, s.duration_days
                       FROM cart c
                       JOIN subjects s ON c.subject_id = s.id
                       WHERE c.student_id = ?";
        
        $cart_stmt = $conn->prepare($cart_query);
        $cart_stmt->bind_param("i", $student_id);
        $cart_stmt->execute();
        $cart_items = $cart_stmt->get_result();
        
        if ($cart_items->num_rows === 0) {
            $error = 'Your cart is empty. Please add items before checkout.';
        } else {
            // Calculate total and prepare items array
            $total = 0;
            $items = [];
            while ($item = $cart_items->fetch_assoc()) {
                $items[] = $item;
                $total += $item['price'];
            }

            // Generate unique payment ID
            $payment_id = 'PAY_' . uniqid() . '_' . time();
            
            // Start transaction
            $conn->autocommit(false);
            
            try {
                // Simulate payment processing
                $payment_status = simulatePayment($_POST, $total);
                
                if ($payment_status['success']) {
                    // Create payment transaction record
                    $transaction_data = json_encode([
                        'card_last_four' => substr(str_replace(' ', '', $_POST['card_number']), -4),
                        'card_type' => detectCardType($_POST['card_number']),
                        'billing_name' => $_POST['billing_name'],
                        'billing_email' => $_POST['billing_email'],
                        'processing_time' => date('Y-m-d H:i:s'),
                        'gateway_response' => $payment_status['details']
                    ]);

                    $payment_stmt = $conn->prepare("INSERT INTO payment_transactions (student_id, payment_id, total_amount, payment_method, payment_status, transaction_data) VALUES (?, ?, ?, 'dummy_gateway', 'completed', ?)");
                    $payment_stmt->bind_param("isds", $student_id, $payment_id, $total, $transaction_data);
                    
                    if (!$payment_stmt->execute()) {
                        throw new Exception("Failed to create payment transaction record.");
                    }

                    // Add purchased subjects
                    foreach ($items as $item) {
                        $expiry_date = date('Y-m-d', strtotime('+' . $item['duration_days'] . ' days'));
                        
                        $purchase_stmt = $conn->prepare("INSERT INTO purchased_subjects (student_id, subject_id, price_paid, payment_id, expiry_date) VALUES (?, ?, ?, ?, ?)");
                        $purchase_stmt->bind_param("iidss", $student_id, $item['subject_id'], $item['price'], $payment_id, $expiry_date);
                        
                        if (!$purchase_stmt->execute()) {
                            throw new Exception("Failed to record subject purchase for subject ID: " . $item['subject_id']);
                        }
                    }

                    // Clear cart
                    $clear_cart_stmt = $conn->prepare("DELETE FROM cart WHERE student_id = ?");
                    $clear_cart_stmt->bind_param("i", $student_id);
                    
                    if (!$clear_cart_stmt->execute()) {
                        throw new Exception("Failed to clear cart.");
                    }

                    // Commit transaction
                    $conn->commit();
                    $success = true;
                    
                    // Store success data for display
                    $_SESSION['payment_success'] = [
                        'payment_id' => $payment_id,
                        'total' => $total,
                        'items' => $items,
                        'purchase_date' => date('Y-m-d H:i:s')
                    ];
                    
                } else {
                    throw new Exception($payment_status['error']);
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
            
            $conn->autocommit(true);
        }
    }
}

// Function to simulate payment processing
function simulatePayment($payment_data, $amount) {
    // Simulate processing delay
    sleep(1);
    
    // Basic validation for demo
    $card_number = str_replace(' ', '', $payment_data['card_number']);
    
    // Test card numbers that should succeed
    $valid_test_cards = ['4242424242424242', '4000056655665556', '5555555555554444', '2223003122003222'];
    
    if (!in_array($card_number, $valid_test_cards)) {
        return [
            'success' => false,
            'error' => 'Invalid test card number. Use 4242 4242 4242 4242 for testing.'
        ];
    }
    
    // Simulate random failure (5% chance)
    if (rand(1, 100) <= 5) {
        return [
            'success' => false,
            'error' => 'Payment declined by your bank. Please try a different card.'
        ];
    }
    
    return [
        'success' => true,
        'details' => [
            'transaction_id' => 'TXN_' . uniqid(),
            'authorization_code' => strtoupper(bin2hex(random_bytes(4))),
            'gateway' => 'Dummy Payment Gateway',
            'processed_at' => date('Y-m-d H:i:s')
        ]
    ];
}

// Function to detect card type from number
function detectCardType($number) {
    $number = str_replace(' ', '', $number);
    
    if (preg_match('/^4/', $number)) return 'Visa';
    if (preg_match('/^5[1-5]/', $number)) return 'MasterCard';
    if (preg_match('/^3[47]/', $number)) return 'American Express';
    if (preg_match('/^6(?:011|5)/', $number)) return 'Discover';
    
    return 'Unknown';
}

// Redirect based on result
if ($success) {
    header("Location: payment_success.php");
    exit();
} else {
    // Redirect back to checkout with error
    $_SESSION['checkout_error'] = $error;
    header("Location: checkout.php");
    exit();
}
?>