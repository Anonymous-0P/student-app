<?php
include('../config/config.php');

if(isset($_POST['signup'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = 'student'; // Always student role
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
    $college_name = isset($_POST['college_name']) ? trim($_POST['college_name']) : null;
    $division = isset($_POST['division']) ? trim($_POST['division']) : null;

    // Validation
    if (empty($name)) {
        $error = "Full name is required.";
    } elseif (empty($email)) {
        $error = "Email is required.";
    } elseif (empty($_POST['password']) || strlen($_POST['password']) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (empty($phone)) {
        $error = "Phone number is required.";
    } elseif (empty($college_name)) {
        $error = "College name is required.";
    } elseif (empty($division)) {
        $error = "Division is required.";
    } else {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if (!$check_stmt) {
            $error = "Database error (email check): " . $conn->error;
        } else {
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if($check_result->num_rows > 0) {
                $error = "Email already exists. Please use a different email address.";
            } else {
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone, college_name, division) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $error = "Database error (insert): " . $conn->error;
                } else {
                    $stmt->bind_param("sssssss", $name, $email, $password, $role, $phone, $college_name, $division);
                    if($stmt->execute()){
                        $success = "Student account created successfully! You can now login.";
                        // Optional: Auto redirect after a delay
                        header("refresh:2;url=login.php");
                    } else {
                        $error = "Error creating account: " . $conn->error;
                    }
                }
            }
        }
    }
}
?>

<?php include('../includes/header.php'); ?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="page-card">
            <h2 class="mb-4">Create Student Account</h2>
            <p class="text-muted mb-4">Join our platform by filling in your details below. All fields marked with <span class="text-danger">*</span> are required.</p>
            <?php if(isset($error)) echo '<div class="alert alert-danger py-2">'.htmlspecialchars($error).'</div>'; ?>
            <?php if(isset($success)) echo '<div class="alert alert-success py-2">'.htmlspecialchars($success).'</div>'; ?>
            <form method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Jane Doe" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" class="form-control" placeholder="+1234567890" required value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">School/College Name <span class="text-danger">*</span></label>
                    <input type="text" name="college_name" class="form-control" placeholder="XYZ University" required value="<?= isset($_POST['college_name']) ? htmlspecialchars($_POST['college_name']) : '' ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Division <span class="text-danger">*</span></label>
                    <select name="division" class="form-select" required>
                        <option value="">Select Division</option>
                        <option value="10th" <?= (isset($_POST['division']) && $_POST['division'] == '10th') ? 'selected' : '' ?>>10th Standard(State Baord)</option>
                        <option value="11th" <?= (isset($_POST['division']) && $_POST['division'] == '11th') ? 'selected' : '' ?>>11th Standard</option>
                        <option value="12th" <?= (isset($_POST['division']) && $_POST['division'] == '12th') ? 'selected' : '' ?>>12th Standard(State Board)</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Enter a strong password" required minlength="6">
                    <small class="text-muted">Password must be at least 6 characters long</small>
                </div>
                
                <div class="col-12 d-grid">
                    <button type="submit" name="signup" class="btn btn-primary btn-lg">Create Student Account</button>
                </div>
                <p class="mt-2 mb-0 small text-center">Already have an account? <a href="login.php" class="text-decoration-none">Login here</a></p>
            </form>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
