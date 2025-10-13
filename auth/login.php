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
        if(password_verify($password, $user['password'])){
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            
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
