<?php
include('../config/config.php');

if(isset($_POST['login'])){
    $identifier = $_POST['identifier']; // email or roll
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR roll_no=? LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows == 1){
        $user = $result->fetch_assoc();
        
        // Check if user account is active
        if(isset($user['is_active']) && $user['is_active'] == 0){
            $error = "Your account has been deactivated. Please contact the administrator.";
        } elseif(password_verify($password, $user['password'])){
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            
            // For students, restore guest cart if exists
            if($user['role'] === 'student' && !empty($_SESSION['guest_cart'])) {
                $student_id = $user['id'];
                
                // Move session cart items to database
                foreach($_SESSION['guest_cart'] as $cart_item) {
                    $subject_id = $cart_item['subject_id'];
                    $price = $cart_item['price'];
                    
                    // Check if not already purchased
                    $purchased_check = $conn->prepare("SELECT id FROM purchased_subjects WHERE student_id = ? AND subject_id = ? AND status = 'active'");
                    $purchased_check->bind_param("ii", $student_id, $subject_id);
                    $purchased_check->execute();
                    
                    if ($purchased_check->get_result()->num_rows == 0) {
                        // Add to database cart
                        $cart_stmt = $conn->prepare("INSERT INTO cart (student_id, subject_id, price) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), added_at = CURRENT_TIMESTAMP");
                        $cart_stmt->bind_param("iid", $student_id, $subject_id, $price);
                        $cart_stmt->execute();
                    }
                }
                
                // Clear guest cart
                unset($_SESSION['guest_cart']);
                
                // Redirect to cart if items were restored
                header("Location: ../student/cart.php?restored=1");
                exit();
            }
            
            // Check if there's a redirect parameter (from checkout/cart)
            if (isset($_GET['redirect']) && $_GET['redirect'] === 'checkout') {
                header("Location: ../student/checkout.php");
                exit();
            }
            
            // Redirect based on role (keep existing roles for existing users)
            switch($user['role']) {
                case 'student':
                    header("Location: ../student/dashboard.php");
                    break;
                case 'evaluator':
                    header("Location: ../evaluator/dashboard.php");
                    break;
                case 'moderator':
                    header("Location: ../moderator/dashboard.php");
                    break;
                case 'admin':
                    header("Location: ../admin/dashboard.php");
                    break;
                default:
                    // Default to student dashboard for any unrecognized roles
                    header("Location: ../student/dashboard.php");
            }
        } else {
            $error = "Incorrect password";
        }
    } else {
        $error = "User not found";
    }
}
?>

<?php include('../includes/header.php'); ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="page-card">
            <h2 class="mb-4">Student Login</h2>
            <?php if(isset($error)) echo '<div class="alert alert-danger py-2">'.htmlspecialchars($error).'</div>'; ?>
            <form method="POST" class="vstack gap-3">
                <div>
                    <label class="form-label">Email or Roll Number</label>
                    <input type="text" name="identifier" class="form-control" placeholder="you@example.com or CS21-001" required value="<?= isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : '' ?>">
                    <small class="text-muted">Enter your email address or roll number</small>
                </div>
                <div>
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <div class="text-end mt-1">
                        <small><a href="forgot_password.php" class="text-decoration-none">Forgot Password?</a></small>
                    </div>
                </div>
                <div class="d-grid">
                    <button type="submit" name="login" class="btn btn-primary btn-lg">Login to Dashboard</button>
                </div>
                <p class="mt-3 mb-0 small text-center">
                    Don't have a student account? <a href="signup.php" class="text-decoration-none">Sign up here</a>
                </p>
            </form>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
