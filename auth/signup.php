<?php
include('../config/config.php');

if(isset($_POST['signup'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password, $role);

    if($stmt->execute()){
        header("Location: login.php");
    } else {
        $error = "Error: " . $conn->error;
    }
}
?>

<?php include('../includes/header.php'); ?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="page-card">
            <h2 class="mb-4">Create an Account</h2>
            <?php if(isset($error)) echo '<div class="alert alert-danger py-2">'.htmlspecialchars($error).'</div>'; ?>
            <form method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Jane Doe" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="">Select Role</option>
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                    </select>
                </div>
                <div class="col-12 d-grid">
                    <button type="submit" name="signup" class="btn btn-primary btn-lg">Sign Up</button>
                </div>
                <p class="mt-2 mb-0 small text-center">Already have an account? <a href="login.php">Login</a></p>
            </form>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
