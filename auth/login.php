<?php
include('../config/config.php');

if(isset($_POST['login'])){
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows == 1){
        $user = $result->fetch_assoc();
        if(password_verify($password, $user['password'])){
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            if($user['role'] == 'student'){
                header("Location: ../student/dashboard.php");
            } else {
                header("Location: ../faculty/dashboard.php");
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
            <h2 class="mb-4">Login</h2>
            <?php if(isset($error)) echo '<div class="alert alert-danger py-2">'.htmlspecialchars($error).'</div>'; ?>
            <form method="POST" class="vstack gap-3">
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
                <div>
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="d-grid">
                    <button type="submit" name="login" class="btn btn-primary btn-lg">Login</button>
                </div>
                <p class="mt-3 mb-0 small text-center">No account? <a href="signup.php">Sign up</a></p>
            </form>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
