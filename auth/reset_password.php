<?php
include('../config/config.php');

$success = '';
$error = '';
$valid_token = false;
$user_id = null;

// Check if token is provided
if(isset($_GET['token'])){
    $token = trim($_GET['token']);
    
    // Debug: Check if token exists at all (for testing)
    $debug_stmt = $conn->prepare("SELECT id, name, email, reset_token_expiry FROM users WHERE reset_token = ?");
    $debug_stmt->bind_param("s", $token);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    
    if($debug_result->num_rows == 1){
        $debug_user = $debug_result->fetch_assoc();
        $current_time = date('Y-m-d H:i:s');
        // Check if token is expired
        if($debug_user['reset_token_expiry'] > $current_time){
            $valid_token = true;
            $user_id = $debug_user['id'];
            $user = $debug_user;
        } else {
            $error = "This reset link has expired. Please request a new password reset. (Token expired at: " . $debug_user['reset_token_expiry'] . ", current time: " . $current_time . ")";
        }
    } else {
        // Check if any user has a reset token (for debugging)
        $check_stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE reset_token IS NOT NULL AND reset_token != ''");
        $check_result = $check_stmt->fetch_assoc();
        
        if($check_result['count'] > 0){
            $error = "Invalid reset token. The token in the URL doesn't match any pending reset requests. Please request a new password reset.";
        } else {
            $error = "Invalid or expired reset link. Please request a new password reset.";
        }
    }
} else {
    $error = "No reset token provided.";
}

// Handle password reset
if(isset($_POST['reset']) && $valid_token){
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if(strlen($new_password) < 8){
        $error = "Password must be at least 8 characters long.";
    } elseif($new_password !== $confirm_password){
        $error = "Passwords do not match.";
    } else {
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if($update_stmt->execute()){
            $success = "Your password has been reset successfully! You can now login with your new password.";
            $valid_token = false; // Prevent form from showing again
        } else {
            $error = "An error occurred while resetting your password. Please try again.";
        }
    }
}
?>

<?php include('../includes/header.php'); ?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="page-card">
            <div class="text-center mb-4">
                <i class="fas fa-lock text-primary" style="font-size: 3rem;"></i>
                <h2 class="mt-3">Reset Password</h2>
                <?php if($valid_token): ?>
                    <p class="text-muted">Enter your new password below.</p>
                <?php endif; ?>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Login Now
                    </a>
                </div>
            <?php elseif($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <div class="text-center mt-3">
                    <a href="forgot_password.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Request New Reset Link
                    </a>
                    <div class="mt-3">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                </div>
            <?php elseif($valid_token): ?>
                <form method="POST" class="vstack gap-3" id="resetForm">
                    <div>
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="••••••••" required minlength="8">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye" id="new_password_icon"></i>
                            </button>
                        </div>
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                    
                    <div>
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required minlength="8">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password_icon"></i>
                            </button>
                        </div>
                        <small class="text-muted">Re-enter your new password</small>
                    </div>
                    
                    <div id="password-match-message" class="small"></div>
                    
                    <div class="d-grid">
                        <button type="submit" name="reset" class="btn btn-primary btn-lg" id="resetBtn">
                            <i class="fas fa-check me-2"></i>Reset Password
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
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

.input-group .btn-outline-secondary {
    border-color: #ced4da;
}

.input-group .btn-outline-secondary:hover {
    background-color: #f8f9fa;
    border-color: #ced4da;
}

@media (max-width: 768px) {
    .page-card {
        margin: 1rem;
        padding: 1.5rem;
    }
}
</style>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Check password match in real-time
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const messageDiv = document.getElementById('password-match-message');
    const resetBtn = document.getElementById('resetBtn');
    
    if (newPassword && confirmPassword) {
        function checkPasswordMatch() {
            if (confirmPassword.value === '') {
                messageDiv.textContent = '';
                messageDiv.className = 'small';
                return;
            }
            
            if (newPassword.value === confirmPassword.value) {
                messageDiv.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i><span class="text-success">Passwords match!</span>';
                resetBtn.disabled = false;
            } else {
                messageDiv.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i><span class="text-danger">Passwords do not match</span>';
                resetBtn.disabled = false; // Let server-side validation handle this
            }
        }
        
        newPassword.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);
    }
    
    // Password strength indicator
    if (newPassword) {
        newPassword.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            // You can add a strength indicator UI here if desired
        });
    }
});

function calculatePasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    return strength;
}
</script>

<?php include('../includes/footer.php'); ?>
