<?php
include('../config/config.php');

$success = '';
$error = '';

if(isset($_POST['submit'])){
    $email = trim($_POST['email']);
    
    // Check if reset_token column exists (migration check)
    $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
    if ($columnCheck->num_rows == 0) {
        $error = "Password reset feature is not configured. Please contact the administrator or run the database migration at: <a href='../migrate_password_reset.php'>migrate_password_reset.php</a>";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
    
            if($result->num_rows == 1){
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $reset_token = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                if (!$update_stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $update_stmt->bind_param("ssi", $reset_token, $token_expiry, $user['id']);
                    
                    if($update_stmt->execute()){
                        // Send password reset email
                        require_once('../includes/mail_helper.php');
                        $emailResult = sendPasswordResetEmail($user['email'], $user['name'], $reset_token);
                        
                        if ($emailResult['success']) {
                            $success = "Password reset instructions have been sent to your email address. Please check your inbox.";
                        } else {
                            $error = "An error occurred while sending the reset email. Please try again or contact support.";
                        }
                    } else {
                        $error = "An error occurred. Please try again.";
                    }
                }
            } else {
                // Don't reveal if email exists or not for security
                $success = "If an account exists with this email, password reset instructions have been sent.";
            }
        }
    }
}
?>

<?php include('../includes/header.php'); ?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="page-card">
            <div class="text-center mb-4">
                <i class="fas fa-key text-primary" style="font-size: 3rem;"></i>
                <h2 class="mt-3">Forgot Password?</h2>
                <p class="text-muted">Enter your email address and we'll send you instructions to reset your password.</p>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            <?php else: ?>
                <?php if($error): ?>
                    <div class="alert alert-danger py-2">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="vstack gap-3">
                    <div>
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        <small class="text-muted">Enter the email address associated with your account</small>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <p class="text-muted small mb-2">Remember your password?</p>
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.page-card {
    background: #fff;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

@media (max-width: 768px) {
    .page-card {
        margin: 1rem;
        padding: 1.5rem;
    }
}
</style>

<?php include('../includes/footer.php'); ?>
